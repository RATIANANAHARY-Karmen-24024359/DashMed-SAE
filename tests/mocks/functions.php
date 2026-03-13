<?php

namespace modules\controllers\auth;

use RuntimeException;

/**
 * Override of PHP `header()` function for testing purposes only.
 */
function header(string $string): void
{
    throw new RuntimeException('REDIRECT:' . $string);
}
