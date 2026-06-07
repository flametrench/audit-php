<?php

// Copyright 2026 NDC Digital, LLC
// SPDX-License-Identifier: Apache-2.0

declare(strict_types=1);

namespace Flametrench\Audit;

enum Outcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Denied  = 'denied';
    case Pending = 'pending';
}
