<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

use Flametrench\Audit\Exceptions\InvalidFormatException;
use Flametrench\Audit\Exceptions\NotFoundException;
use Flametrench\Ids\Id;

/**
 * Reference in-memory AuditStore. Spec-conformant for write/get round-trips.
 * Not thread-safe; intended for tests, documentation, and small applications.
 * Production workloads should use a durable backend (Postgres, etc.).
 */
final class InMemoryAuditStore implements AuditStore
{
    /** @var array<string, AuditEvent> */
    private array $events = [];

    /** @var callable(): \DateTimeImmutable */
    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): \DateTimeImmutable => new \DateTimeImmutable();
    }

    private static function validateWrite(
        ?string $actorUsrId,
        string $action,
        Target $target,
        array $metadata,
        ?AuthContext $auth,
    ): void {
        // actor_usr_id: if present, must be usr_<32hex> (ADR 0019 §Errors)
        if ($actorUsrId !== null && preg_match('/^usr_[0-9a-f]{32}$/', $actorUsrId) !== 1) {
            throw new InvalidFormatException('actor_usr_id', "actor_usr_id must be usr_<32hex>");
        }

        // target.kind: Flametrench entity type OR adopter object_type ^[a-z]{2,6}$
        if (preg_match('/^[a-z]{2,6}$/', $target->kind) !== 1) {
            throw new InvalidFormatException('target.kind', "target.kind must match ^[a-z]{2,6}\$");
        }

        // auth field consistency: exactly one kind-specific id field, matching kind (ADR 0019 §Errors)
        if ($auth !== null) {
            $kindToField = [
                'session' => 'sessionId',
                'pat'     => 'patId',
                'share'   => 'shareId',
                'system'  => 'systemId',
            ];
            if (!array_key_exists($auth->kind, $kindToField)) {
                throw new InvalidFormatException('auth', "auth.kind must be session|pat|share|system");
            }
            $expectedProp = $kindToField[$auth->kind];
            if ($auth->$expectedProp === null) {
                throw new InvalidFormatException('auth', "auth.{$expectedProp} is required for kind={$auth->kind}");
            }
            foreach ($kindToField as $k => $prop) {
                if ($k !== $auth->kind && $auth->$prop !== null) {
                    throw new InvalidFormatException('auth', "auth.{$prop} must not be set for kind={$auth->kind}");
                }
            }
        }

        // size: whole event ≤ 64 KB (ADR 0019 §Constraints)
        $serialized = json_encode([
            'actor_usr_id' => $actorUsrId,
            'action'       => $action,
            'target'       => $target->toArray(),
            'metadata'     => $metadata,
            'auth'         => $auth?->toArray(),
        ]);
        if ($serialized !== false && strlen($serialized) > 65536) {
            throw new InvalidFormatException('size', 'Event exceeds the 64 KB limit');
        }
    }

    public function write(
        \DateTimeImmutable $occurredAt,
        ?string $actorUsrId,
        string $action,
        Target $target,
        Outcome $outcome,
        array $metadata,
        ?AuthContext $auth = null,
        ?OnBehalf $onBehalf = null,
        ?Scope $scope = null,
        ?EventContext $context = null,
    ): AuditEvent {
        self::validateWrite($actorUsrId, $action, $target, $metadata, $auth);
        $event = new AuditEvent(
            id: Id::generate('aud'),
            occurredAt: $occurredAt,
            recordedAt: ($this->clock)(),
            actorUsrId: $actorUsrId,
            auth: $auth,
            onBehalf: $onBehalf,
            action: $action,
            target: $target,
            scope: $scope,
            outcome: $outcome,
            metadata: $metadata,
            context: $context,
        );
        $this->events[$event->id] = $event;
        return $event;
    }

    public function get(string $id): AuditEvent
    {
        return $this->events[$id]
            ?? throw new NotFoundException("Audit event {$id} not found");
    }
}
