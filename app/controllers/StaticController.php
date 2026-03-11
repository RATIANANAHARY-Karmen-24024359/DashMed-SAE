<?php

/**
 * app/controllers/StaticController.php
 *
 * Controller file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

declare(strict_types=1);

namespace modules\controllers;

use modules\views\static\AboutView;
use modules\views\static\HomepageView;
use modules\views\static\LegalnoticeView;
use modules\views\static\SitemapView;

/**
 * Class StaticController
 *
 * Handles static pages (Homepage, About, Legal Notice, Sitemap).
 *
 * @package DashMed\Modules\Controllers
 * @author DashMed Team
 * @license Proprietary
 */
class StaticController
{
    /**
     * Helper to check login status.
     *
     * @return bool
     */
    private function isUserLoggedIn(): bool
    {
        return isset($_SESSION['email']);
    }

    /**
     * Redirects to dashboard if logged in.
     */
    private function redirectIfLoggedIn(): void
    {
        if ($this->isUserLoggedIn()) {
            header('Location: /?page=dashboard');
            exit;
        }
    }

    /**
     * Homepage.
     */
    public function homepage(): void
    {
        $this->redirectIfLoggedIn();
        (new HomepageView())->show();
    }

    /**
     * About page.
     */
    public function about(): void
    {
        $this->redirectIfLoggedIn();
        (new AboutView())->show();
    }

    /**
     * Legal Notice page.
     */
    public function legal(): void
    {
        $this->redirectIfLoggedIn();
        (new LegalnoticeView())->show();
    }

    /**
     * Sitemap page.
     */
    public function sitemap(): void
    {
        $this->redirectIfLoggedIn();
        (new SitemapView())->show();
    }
}
