<?php

namespace Tests\Controllers\Site;

use PHPUnit\Framework\TestCase;
use modules\controllers\StaticController;

require_once __DIR__ . '/../../../vendor/autoload.php';

class SiteControllersTest extends TestCase
{
    private StaticController $controller;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $_SESSION = [];
        $this->controller = new StaticController();
    }

    public function testHomepageRenders()
    {
        ob_start();
        $this->controller->homepage();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
    }

    public function testAboutRenders()
    {
        ob_start();
        $this->controller->about();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
    }

    public function testLegalNoticeRenders()
    {
        ob_start();
        $this->controller->legal();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
    }

    public function testSitemapRenders()
    {
        ob_start();
        $this->controller->sitemap();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
    }
}
