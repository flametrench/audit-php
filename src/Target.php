<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

/**
 * The entity acted upon. `kind` is a Flametrench entity type or adopter object_type;
 * `id` is a Flametrench wire id OR an opaque adopter resource id — never decoded.
 */
final readonly class Target
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
