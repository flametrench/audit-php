<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit\Exceptions;

/**
 * An event field has a shape or value that violates the AuditEvent schema.
 * Carries a `field` discriminator naming the offending part (ADR 0019 §Errors).
 * This is the same cross-cutting error class used by identity, tenancy, and
 * authorization layers for input-shape/value violations.
 */
final class InvalidFormatException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $field,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: "Invalid format for field '{$field}'", $code, $previous);
    }
}
