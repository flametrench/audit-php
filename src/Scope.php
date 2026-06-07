<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

/**
 * The tenancy boundary the action occurred within (typically kind="org").
 * Absent for global / non-org-scoped events (e.g. login, system actor with no scope).
 */
final readonly class Scope
{
    public function __construct(
        public string $kind,
        public string $id,
    ) {}

    public function toArray(): array
    {
        return ['kind' => $this->kind, 'id' => $this->id];
    }
}
