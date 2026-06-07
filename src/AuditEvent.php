<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

/**
 * An immutable audit event (ADR 0019). `recorded_at` is server-authoritative;
 * emitters MUST NOT supply it — it is set by the store on `write`.
 */
final readonly class AuditEvent
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $occurredAt,
        public \DateTimeImmutable $recordedAt,
        public ?string $actorUsrId,
        public ?AuthContext $auth,
        public ?OnBehalf $onBehalf,
        public string $action,
        public Target $target,
        public ?Scope $scope,
        public Outcome $outcome,
        public array $metadata,
        public ?EventContext $context,
    ) {}

    private static function fmtTs(\DateTimeImmutable $dt): string
    {
        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }

    public function toArray(): array
    {
        $out = [
            'id'           => $this->id,
            'occurred_at'  => self::fmtTs($this->occurredAt),
            'recorded_at'  => self::fmtTs($this->recordedAt),
            'actor_usr_id' => $this->actorUsrId,
            'action'       => $this->action,
            'target'       => $this->target->toArray(),
            'outcome'      => $this->outcome->value,
            'metadata'     => $this->metadata,
        ];
        if ($this->auth !== null) {
            $out['auth'] = $this->auth->toArray();
        }
        if ($this->onBehalf !== null) {
            $out['on_behalf'] = $this->onBehalf->toArray();
        }
        if ($this->scope !== null) {
            $out['scope'] = $this->scope->toArray();
        }
        if ($this->context !== null) {
            $out['context'] = $this->context->toArray();
        }
        return $out;
    }
}
