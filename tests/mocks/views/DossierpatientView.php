<?php

declare(strict_types=1);

namespace modules\views\pages;

/**
 * Fake view for testing purposes.
 *
 * Used to mock the dossierpatientView in unit tests.
 */
final class DossierpatientView
{
    public static bool $shown = false;

    public function show(): void
    {
        self::$shown = true;
    }
}
