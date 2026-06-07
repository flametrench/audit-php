<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

/**
 * The established authentication principal — ADR 0016's frozen vocabulary.
 * Absent when there is no established principal (pre-auth / anonymous / failed login).
 * Exactly one of the kind-specific id fields MUST be set and MUST match `kind`.
 */
final readonly class AuthContext
{
    public function __construct(
        public string $kind,
        public ?string $sessionId = null,
        public ?string $patId = null,
        public ?string $shareId = null,
        public ?string $systemId = null,
    ) {}

    public function toArray(): array
    {
        $out = ['kind' => $this->kind];
        if ($this->sessionId !== null) {
            $out['session_id'] = $this->sessionId;
        }
        if ($this->patId !== null) {
            $out['pat_id'] = $this->patId;
        }
        if ($this->shareId !== null) {
            $out['share_id'] = $this->shareId;
        }
        if ($this->systemId !== null) {
            $out['system_id'] = $this->systemId;
        }
        return $out;
    }
}
