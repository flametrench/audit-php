<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

use Flametrench\Audit\AuthContext;
use Flametrench\Audit\AuditEvent;
use Flametrench\Audit\EventContext;
use Flametrench\Audit\Exceptions\InvalidFormatException;
use Flametrench\Audit\Exceptions\NotFoundException;
use Flametrench\Audit\InMemoryAuditStore;
use Flametrench\Audit\OnBehalf;
use Flametrench\Audit\Outcome;
use Flametrench\Audit\Scope;
use Flametrench\Audit\Target;

describe('InMemoryAuditStore', function () {
    beforeEach(function () {
        $this->store = new InMemoryAuditStore();
    });

    it('write returns an AuditEvent with aud_ prefixed id', function () {
        $event = $this->store->write(
            occurredAt: new DateTimeImmutable('2026-06-05T10:00:00Z'),
            actorUsrId: 'usr_0190f2a81b3c7abc8123000000000001',
            action: 'data.create.record',
            target: new Target('doc', 'doc_abc'),
            outcome: Outcome::Success,
            metadata: [],
        );
        expect($event)->toBeInstanceOf(AuditEvent::class);
        expect($event->id)->toMatch('/^aud_[0-9a-f]{32}$/');
    });

    it('recorded_at is set server-side and >= occurred_at', function () {
        $occurred = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $event = $this->store->write(
            occurredAt: $occurred,
            actorUsrId: null,
            action: 'identity.login',
            target: new Target('usr', 'usr_0190f2a81b3c7abc8123000000000001'),
            outcome: Outcome::Failure,
            metadata: [],
        );
        expect($event->recordedAt >= $event->occurredAt)->toBeTrue();
    });

    it('get retrieves the written event verbatim', function () {
        $auth = new AuthContext(kind: 'pat', patId: 'pat_0190f2a81b3c7abc8123000000000003');
        $onBehalf = new OnBehalf('assistant-prod-7');
        $target = new Target('doc', 'doc_2f9c1a00000000000000000000000001');
        $scope = new Scope('org', 'org_0190f2a81b3c7abc8123000000000004');
        $ctx = new EventContext(requestId: 'req-abc123', ip: '192.0.2.1');

        $written = $this->store->write(
            occurredAt: new DateTimeImmutable('2026-06-05T10:00:00Z'),
            actorUsrId: 'usr_0190f2a81b3c7abc8123000000000002',
            action: 'data.create.record',
            target: $target,
            outcome: Outcome::Success,
            metadata: ['mcp' => true],
            auth: $auth,
            onBehalf: $onBehalf,
            scope: $scope,
            context: $ctx,
        );

        $fetched = $this->store->get($written->id);
        expect($fetched->id)->toBe($written->id);
        expect($fetched->actorUsrId)->toBe('usr_0190f2a81b3c7abc8123000000000002');
        expect($fetched->auth?->kind)->toBe('pat');
        expect($fetched->auth?->patId)->toBe('pat_0190f2a81b3c7abc8123000000000003');
        expect($fetched->onBehalf?->agentId)->toBe('assistant-prod-7');
        expect($fetched->action)->toBe('data.create.record');
        expect($fetched->target->kind)->toBe('doc');
        expect($fetched->target->id)->toBe('doc_2f9c1a00000000000000000000000001');
        expect($fetched->scope?->kind)->toBe('org');
        expect($fetched->outcome)->toBe(Outcome::Success);
        expect($fetched->metadata)->toBe(['mcp' => true]);
        expect($fetched->context?->requestId)->toBe('req-abc123');
        expect($fetched->context?->ip)->toBe('192.0.2.1');
    });

    it('write preserves null actor_usr_id for pre-auth events', function () {
        $event = $this->store->write(
            occurredAt: new DateTimeImmutable('2026-06-05T10:01:00Z'),
            actorUsrId: null,
            action: 'identity.login',
            target: new Target('usr', 'usr_0190f2a81b3c7abc8123000000000002'),
            outcome: Outcome::Failure,
            metadata: ['reason' => 'bad_credential'],
        );
        expect($event->actorUsrId)->toBeNull();
        expect($event->auth)->toBeNull();
        expect($event->scope)->toBeNull();
    });

    it('write preserves system auth kind with system_id', function () {
        $auth = new AuthContext(kind: 'system', systemId: 'billing-cron');
        $event = $this->store->write(
            occurredAt: new DateTimeImmutable('2026-06-05T02:00:00Z'),
            actorUsrId: null,
            action: 'tenancy.subscription_renewed',
            target: new Target('org', 'org_0190f2a81b3c7abc8123000000000004'),
            outcome: Outcome::Success,
            metadata: [],
            auth: $auth,
            scope: new Scope('org', 'org_0190f2a81b3c7abc8123000000000004'),
        );
        expect($event->auth?->kind)->toBe('system');
        expect($event->auth?->systemId)->toBe('billing-cron');
        expect($event->actorUsrId)->toBeNull();
    });

    it('write stores opaque adopter target.id without decoding', function () {
        $event = $this->store->write(
            occurredAt: new DateTimeImmutable('2026-06-05T13:00:00Z'),
            actorUsrId: 'usr_0190f2a81b3c7abc8123000000000002',
            action: 'proj.archive',
            target: new Target('proj', 'legacy-project-42'),
            outcome: Outcome::Success,
            metadata: [],
        );
        expect($event->target->id)->toBe('legacy-project-42');
    });

    it('get throws NotFoundException for unknown id', function () {
        expect(fn() => $this->store->get('aud_' . str_repeat('0', 32)))
            ->toThrow(NotFoundException::class);
    });

    it('outcome enum covers all four ADR 0019 values', function () {
        $cases = [Outcome::Success, Outcome::Failure, Outcome::Denied, Outcome::Pending];
        foreach ($cases as $outcome) {
            $event = $this->store->write(
                occurredAt: new DateTimeImmutable('2026-06-05T10:00:00Z'),
                actorUsrId: null,
                action: 'test.op',
                target: new Target('doc', 'doc_1'),
                outcome: $outcome,
                metadata: [],
            );
            expect($event->outcome)->toBe($outcome);
        }
        expect(true)->toBeTrue();
    });

    it('write rejects invalid actor_usr_id (must be usr_<32hex>)', function () {
        expect(fn() => $this->store->write(
            occurredAt: new DateTimeImmutable('2026-06-05T10:00:00Z'),
            actorUsrId: 'not-a-valid-id',
            action: 'data.op',
            target: new Target('doc', 'doc_1'),
            outcome: Outcome::Success,
            metadata: [],
        ))->toThrow(InvalidFormatException::class);

        $ex = null;
        try {
            $this->store->write(
                occurredAt: new DateTimeImmutable(),
                actorUsrId: 'org_0190f2a81b3c7abc8123000000000001',
                action: 'data.op',
                target: new Target('doc', 'doc_1'),
                outcome: Outcome::Success,
                metadata: [],
            );
        } catch (InvalidFormatException $e) {
            $ex = $e;
        }
        expect($ex)->not->toBeNull();
        expect($ex->field)->toBe('actor_usr_id');
    });

    it('write rejects target.kind that does not match ^[a-z]{2,6}$', function () {
        $ex = null;
        try {
            $this->store->write(
                occurredAt: new DateTimeImmutable(),
                actorUsrId: null,
                action: 'data.op',
                target: new Target('Too-Long-Kind', 'some_id'),
                outcome: Outcome::Success,
                metadata: [],
            );
        } catch (InvalidFormatException $e) {
            $ex = $e;
        }
        expect($ex)->not->toBeNull();
        expect($ex->field)->toBe('target.kind');
    });

    it('write rejects auth with mismatched id field', function () {
        $ex = null;
        try {
            $this->store->write(
                occurredAt: new DateTimeImmutable(),
                actorUsrId: null,
                action: 'data.op',
                target: new Target('doc', 'doc_1'),
                outcome: Outcome::Success,
                metadata: [],
                auth: new AuthContext(kind: 'pat', sessionId: 'ses_0190f2a81b3c7abc8123000000000007'),
            );
        } catch (InvalidFormatException $e) {
            $ex = $e;
        }
        expect($ex)->not->toBeNull();
        expect($ex->field)->toBe('auth');
    });

    it('write rejects auth with missing id field', function () {
        $ex = null;
        try {
            $this->store->write(
                occurredAt: new DateTimeImmutable(),
                actorUsrId: null,
                action: 'data.op',
                target: new Target('doc', 'doc_1'),
                outcome: Outcome::Success,
                metadata: [],
                auth: new AuthContext(kind: 'session'),
            );
        } catch (InvalidFormatException $e) {
            $ex = $e;
        }
        expect($ex)->not->toBeNull();
        expect($ex->field)->toBe('auth');
    });

    it('write rejects event exceeding 64 KB', function () {
        $ex = null;
        try {
            $this->store->write(
                occurredAt: new DateTimeImmutable(),
                actorUsrId: null,
                action: 'data.op',
                target: new Target('doc', 'doc_1'),
                outcome: Outcome::Success,
                metadata: ['big' => str_repeat('x', 70_000)],
            );
        } catch (InvalidFormatException $e) {
            $ex = $e;
        }
        expect($ex)->not->toBeNull();
        expect($ex->field)->toBe('size');
    });

    it('toArray produces snake_case keys with correct shape', function () {
        $event = $this->store->write(
            occurredAt: new DateTimeImmutable('2026-06-05T10:00:00.000Z'),
            actorUsrId: 'usr_0190f2a81b3c7abc8123000000000001',
            action: 'data.create.record',
            target: new Target('doc', 'doc_abc'),
            outcome: Outcome::Success,
            metadata: ['k' => 'v'],
            auth: new AuthContext(kind: 'session', sessionId: 'ses_0190f2a81b3c7abc8123000000000007'),
            scope: new Scope('org', 'org_xyz'),
        );
        $arr = $event->toArray();
        expect($arr['id'])->toMatch('/^aud_[0-9a-f]{32}$/');
        expect($arr['actor_usr_id'])->toBe('usr_0190f2a81b3c7abc8123000000000001');
        expect($arr['action'])->toBe('data.create.record');
        expect($arr['outcome'])->toBe('success');
        expect($arr['auth'])->toBe(['kind' => 'session', 'session_id' => 'ses_0190f2a81b3c7abc8123000000000007']);
        expect($arr['scope'])->toBe(['kind' => 'org', 'id' => 'org_xyz']);
        expect($arr['target'])->toBe(['kind' => 'doc', 'id' => 'doc_abc']);
        expect($arr['metadata'])->toBe(['k' => 'v']);
        expect(array_key_exists('on_behalf', $arr))->toBeFalse();
        expect(array_key_exists('context', $arr))->toBeFalse();
    });
});
