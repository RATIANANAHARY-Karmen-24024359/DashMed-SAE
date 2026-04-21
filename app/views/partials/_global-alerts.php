<?php

/**
 * Partial: Global Alerts
 *
 * Include in all views for notifications.
 *
 * @package DashMed\Views\Partials
 */

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast@1.4.0/dist/css/iziToast.min.css">
<link rel="stylesheet" href="assets/css/components/alerts-toast.css">

<script src="https://cdn.jsdelivr.net/npm/izitoast@1.4.0/dist/js/iziToast.min.js"></script>

<?php
/**
 * Absolute filesystem path to the alert polling script used for fallback versioning.
 *
 * @var non-falsy-string $alertsScriptPath
 */
$alertsScriptPath = dirname(__DIR__, 3) . '/public/assets/js/alerts-global.js';
/**
 * Raw asset version from environment (preferred) or computed fallback.
 *
 * `APP_ASSET_VERSION` allows deterministic cache invalidation across nodes.
 *
 * @var string|false $assetVersionRaw
 */
$assetVersionRaw = getenv('APP_ASSET_VERSION');
if ($assetVersionRaw === false || $assetVersionRaw === '') {
    $assetVersionRaw = file_exists($alertsScriptPath) ? (string) filemtime($alertsScriptPath) : '2026-03-30';
}
/**
 * URL-safe cache token appended to script query string.
 *
 * @var non-falsy-string $assetVersion
 */
$assetVersion = rawurlencode((string) $assetVersionRaw);
?>
<script src="assets/js/alerts-global.js?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
