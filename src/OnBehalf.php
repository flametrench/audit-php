<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

/**
 * A delegated non-human actor (e.g. an AI agent) — orthogonal to `auth.kind`.
 * Present IFF the action was performed by a delegated non-human.
 */
final readonly class OnBehalf
{
    public function __construct(
        public string $agentId,
    ) {}

    public function toArray(): array
    {
        return ['agent_id' => $this->agentId];
    }
}
