<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

/** Optional request context — stored verbatim; not part of superset conformance checks. */
final readonly class EventContext
{
    public function __construct(
        public ?string $requestId = null,
        public ?string $ip = null,
        public ?string $userAgent = null,
    ) {}

    public function toArray(): array
    {
        $out = [];
        if ($this->requestId !== null) {
            $out['request_id'] = $this->requestId;
        }
        if ($this->ip !== null) {
            $out['ip'] = $this->ip;
        }
        if ($this->userAgent !== null) {
            $out['user_agent'] = $this->userAgent;
        }
        return $out;
    }
}
