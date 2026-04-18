<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Michael Slusarz <slusarz@horde.org>
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 */

namespace Horde\Smtp;

/**
 * Marker interface for pluggable client behavior.
 *
 * Aspect-specific sub-interfaces (SendDataStrategy, etc.) declare
 * the actual hooks. SmtpClient checks the strategy via instanceof
 * and invokes only the aspects it implements.
 *
 * @api-unstable Interface may change before 2.0 stable.
 *     Do not implement outside of horde packages.
 */
interface Strategy {}
