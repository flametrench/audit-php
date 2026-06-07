<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

/**
 * Append-only audit event store (ADR 0019).
 *
 * Core v0.4 operations: write (durable append) and get (fetch by id).
 * query / count / export land when spec PRs #43 (errors) and #46
 * (cursor/ordering) merge and lock the full operation set.
 */
interface AuditStore
{
    /**
     * Append an audit event. Synchronous and durable-before-return.
     * `recorded_at` is set by the store; emitters MUST NOT supply it.
     *
     * @param array<string, mixed> $metadata
     */
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
    ): AuditEvent;

    public function get(string $id): AuditEvent;
}
