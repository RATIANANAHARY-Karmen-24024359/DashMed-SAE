<?php

declare(strict_types=1);

namespace modules\views\pages;

/**
 * Fake view for testing purposes.
 *
 * Used to mock the dashboardView in unit tests.
 */
final class DashboardView
{
    public static bool $shown = false;

    public function show(): void
    {
        self::$shown = true;
    }
}
