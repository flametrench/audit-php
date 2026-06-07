<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0
//
// Flametrench v0.4 conformance suite — PHP / PEST harness for the
// audit capability.
//
// Implements the state-machine fixture format defined in
// spec/conformance/fixture.schema.json. Matching for `get` results is
// SUPERSET (result ⊇ expected): the expected object lists the fields
// whose values ADR 0019 pins; server-set fields (recorded_at) and
// additional fields are permitted in the result.

declare(strict_types=1);

use Flametrench\Audit\AuthContext;
use Flametrench\Audit\EventContext;
use Flametrench\Audit\InMemoryAuditStore;
use Flametrench\Audit\OnBehalf;
use Flametrench\Audit\Outcome;
use Flametrench\Audit\Scope;
use Flametrench\Audit\Target;
use Flametrench\Ids\Id;

const AUDIT_FIXTURES_DIR = __DIR__ . '/conformance/fixtures';
const AUDIT_VAR_PATTERN = '/^\{([a-z_][a-z0-9_]*)\}$/';

function loadAuditFixture(string $relativePath): array
{
    $raw = file_get_contents(AUDIT_FIXTURES_DIR . '/' . $relativePath);
    if ($raw === false) {
        throw new RuntimeException("Cannot read fixture: {$relativePath}");
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON in fixture: {$relativePath}");
    }
    return $decoded;
}

function auditResolveVars(mixed $value, array $variables): mixed
{
    if (is_string($value)) {
        if (preg_match(AUDIT_VAR_PATTERN, $value, $matches) === 1) {
            $name = $matches[1];
            if (!array_key_exists($name, $variables)) {
                throw new RuntimeException("Unknown variable in fixture: {{$name}}");
            }
            return $variables[$name];
        }
        return $value;
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = auditResolveVars($v, $variables);
        }
        return $out;
    }
    return $value;
}

/**
 * Build an AuthContext value object from the fixture array form.
 * @param array{kind:string, session_id?:string, pat_id?:string, share_id?:string, system_id?:string} $a
 */
function buildAuthContext(array $a): AuthContext
{
    return new AuthContext(
        kind: $a['kind'],
        sessionId: $a['session_id'] ?? null,
        patId: $a['pat_id'] ?? null,
        shareId: $a['share_id'] ?? null,
        systemId: $a['system_id'] ?? null,
    );
}

/**
 * Build an EventContext value object from the fixture array form.
 * @param array{request_id?:string, ip?:string, user_agent?:string} $c
 */
function buildEventContext(array $c): EventContext
{
    return new EventContext(
        requestId: $c['request_id'] ?? null,
        ip: $c['ip'] ?? null,
        userAgent: $c['user_agent'] ?? null,
    );
}

/**
 * Invoke a fixture op against the store.
 *
 * `write` returns the full AuditEvent (captures "id" from it).
 * `get` returns the full AuditEvent.
 *
 * @param array<string,mixed> $args
 */
function auditInvokeOp(InMemoryAuditStore $store, string $op, array $args): mixed
{
    return match ($op) {
        'write' => $store->write(
            occurredAt: new DateTimeImmutable($args['occurred_at']),
            actorUsrId: array_key_exists('actor_usr_id', $args) ? $args['actor_usr_id'] : null,
            action: $args['action'],
            target: new Target(kind: $args['target']['kind'], id: $args['target']['id']),
            outcome: Outcome::from($args['outcome']),
            metadata: $args['metadata'] ?? [],
            auth: isset($args['auth']) ? buildAuthContext($args['auth']) : null,
            onBehalf: isset($args['on_behalf']) ? new OnBehalf($args['on_behalf']['agent_id']) : null,
            scope: isset($args['scope']) ? new Scope(kind: $args['scope']['kind'], id: $args['scope']['id']) : null,
            context: isset($args['context']) ? buildEventContext($args['context']) : null,
        ),

        'get' => $store->get($args['id']),

        default => throw new RuntimeException("Unknown audit fixture op: {$op}"),
    };
}

/**
 * Walk a dotted path into an AuditEvent's toArray() representation.
 * Paths in the fixture use snake_case; toArray() produces snake_case too.
 */
function auditWalkPath(mixed $value, string $dottedPath): mixed
{
    $current = $value;
    foreach (explode('.', $dottedPath) as $segment) {
        if (is_object($current)) {
            $arr = method_exists($current, 'toArray') ? $current->toArray() : (array) $current;
            if (!array_key_exists($segment, $arr)) {
                throw new RuntimeException("Cannot resolve path segment '{$segment}' in event array");
            }
            $current = $arr[$segment];
        } elseif (is_array($current)) {
            if (!array_key_exists($segment, $current)) {
                throw new RuntimeException("Cannot resolve path segment '{$segment}' in array");
            }
            $current = $current[$segment];
        } else {
            throw new RuntimeException("Cannot walk into scalar at '{$segment}'");
        }
    }
    return $current;
}

/**
 * Assert that $actual is a superset of $expected (result ⊇ expected).
 * Recursively checks every key in $expected exists in $actual with an equal value.
 * Extra keys in $actual are permitted.
 */
function assertAuditSuperset(mixed $expected, mixed $actual, string $path = ''): void
{
    if (is_array($expected)) {
        expect($actual)->toBeArray("Expected array at '{$path}'");
        foreach ($expected as $k => $v) {
            $childPath = $path === '' ? (string) $k : "{$path}.{$k}";
            expect(array_key_exists($k, $actual))->toBeTrue("Expected key '{$childPath}' to be present in result");
            assertAuditSuperset($v, $actual[$k], $childPath);
        }
    } else {
        expect($actual)->toBe($expected, "Value mismatch at '{$path}'");
    }
}

/**
 * Run one state-machine audit conformance test.
 *
 * @param array{users?:list<string>, steps:array<int,array<string,mixed>>} $test
 */
function runAuditTest(array $test): void
{
    $store = new InMemoryAuditStore();

    $variables = [];
    foreach ($test['users'] ?? [] as $name) {
        $variables[$name] = Id::generate('usr');
    }

    foreach ($test['steps'] as $step) {
        $op = $step['op'];
        $resolvedInput = auditResolveVars($step['input'], $variables);

        $result = auditInvokeOp($store, $op, $resolvedInput);

        foreach ($step['captures'] ?? [] as $name => $path) {
            $variables[$name] = auditWalkPath($result, $path);
        }

        if (isset($step['expected']['result'])) {
            $expected = auditResolveVars($step['expected']['result'], $variables);
            $actual = $result->toArray();
            assertAuditSuperset($expected, $actual);
        }
    }

    expect(true)->toBeTrue();
}

// ─── Test factories ───

foreach (
    [
        'audit.write_event_shape' => 'audit/write-event-shape.json',
    ] as $describeName => $fixturePath
) {
    $fixture = loadAuditFixture($fixturePath);
    describe("Conformance · {$describeName} [MUST]", function () use ($fixture) {
        foreach ($fixture['tests'] as $t) {
            it("[{$t['id']}] {$t['description']}", function () use ($t) {
                runAuditTest($t);
            });
        }
    });
}
