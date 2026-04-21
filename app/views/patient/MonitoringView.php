<?php

/**
 * app/views/patient/MonitoringView.php
 *
 * View file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

declare(strict_types=1);

namespace modules\views\patient;

/**
 * Class MonitoringView
 *
 * Full-screen monitoring page view.
 *
 * Displays vital sign monitoring cards in large format.
 *
 * @package DashMed\Modules\Views\Pages\Monitoring
 * @author  DashMed Team
 * @license Proprietary
 */
class MonitoringView
{
    /**
     * @var array<int, \modules\models\entities\Indicator> Patient metrics ready for display
     */
    private array $patientMetrics;

    /**
     * @var array<string, string> Available chart types [code => label]
     */
    private array $chartTypes;

    /**
     * @var int|null Context patient ID
     */
    private ?int $patientId;

    /**
     * @var array<string, mixed> Selected patient data
     */
    private array $patientData;


    /**
     * Constructor.
     *
     * @param array<int, \modules\models\entities\Indicator> $patientMetrics Processed metrics
     * @param array<string, string>                          $chartTypes     Available charts
     * @param int|null                                       $patientId      Patient ID for search context
     * @param array<string, mixed>                           $patientData    Patient info
     */
    public function __construct(
        array $patientMetrics = [],
        array $chartTypes = [],
        ?int $patientId = null,
        array $patientData = []
    ) {
        $this->patientMetrics = $patientMetrics;
        $this->chartTypes = $chartTypes;
        $this->patientId = $patientId;
        $this->patientData = $patientData;
    }

    /**
     * Renders the monitoring page HTML.
     *
     * @return void
     */
    public function show(): void
    {
        /**
         * Stable cache token for local monitoring assets.
         *
         * Using a deterministic version avoids `time()`-based cache misses and
         * reduces needless asset re-downloads on every refresh.
         *
         * @var string $assetVersion
         */
        $assetVersion = rawurlencode((string) (getenv('APP_ASSET_VERSION') ?: '2026-03-30'));

        $layout = new \modules\views\layout\Layout(
            'Monitoring',
            [
                'https://cdn.jsdelivr.net/npm/izitoast@1.4.0/dist/css/iziToast.min.css',
                'assets/css/pages/monitoring.css',
                'assets/css/components/card.css',
                'assets/css/components/popup.css',
                'assets/css/layout/aside/patient-info.css',
                'assets/css/layout/aside/doctor-list.css',
                'assets/css/components/modal.css',
                'assets/css/components/alerts-toast.css',
            ],
            [
                'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js',
                'assets/js/service/stream.js?v=' . $assetVersion,
                'assets/js/service/history-cache.js?v=' . $assetVersion,
                'assets/js/component/charts/sparkline-loader.js?v=' . $assetVersion,
                'assets/js/service/history-sync.js?v=' . $assetVersion,
                'assets/js/component/modal/chart.js?v=' . $assetVersion,
                'assets/js/component/charts/card-sparklines.js?v=' . $assetVersion,
                'assets/js/component/modal/navigation.js',
                'assets/js/component/modal/modal.js',
            ],
            '',
            true,
            true
        );

        $layout->render(
            function () {
                ?>
            <main class="container">
                <section class="dashboard-content-container">

                    <div class="searchbar-with-patient">
                        <span class="patient-name-label">
                            <?php echo htmlspecialchars(
                                trim(
                                    (is_scalar($v = $this->patientData['first_name'] ?? '') ? (string)$v : '') . ' ' .
                                             (is_scalar($v = $this->patientData['last_name'] ?? '') ? (string)$v : '')
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

    </span>
                        <?php include dirname(__DIR__) . '/partials/_searchbar.php'; ?>
                        <div class="live-clock" id="live-clock">
                            <span class="live-clock__time" id="live-clock-time"></span>
                            <span class="live-clock__date" id="live-clock-date"></span>
                        </div>
                    </div>
                    <script>
                        (function () {
                            const timeEl = document.getElementById('live-clock-time');
                            const dateEl = document.getElementById('live-clock-date');
                            const days = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                            const months = ['jan.','fév.','mars','avr.','mai','juin','juil.','août','sept.','oct.','nov.','déc.'];

                            function tick() {
                                const now = new Date();
                                const h = String(now.getHours()).padStart(2, '0');
                                const m = String(now.getMinutes()).padStart(2, '0');
                                const s = String(now.getSeconds()).padStart(2, '0');
                                timeEl.textContent = h + ':' + m + ':' + s;
                                dateEl.textContent = days[now.getDay()] + ' ' + now.getDate() + ' ' + months[now.getMonth()];
                            }

                            tick();
                            setInterval(tick, 1000);
                        })();
                    </script>


                    <input type="hidden" id="context-patient-id" value="<?php echo htmlspecialchars((string) $this->patientId) ?>">

                    <section class="skeleton-wrapper skeleton-monitoring-grid" id="skeleton-monitoring"
                             data-skeleton-for="real-monitoring" data-skeleton-auto data-skeleton-delay="400">
                        <?php for ($i = 0; $i < 6; $i++) : ?>
                            <div class="skeleton-card">
                                <div class="skeleton-card-header">
                                    <div class="skeleton skeleton-text" style="width: 55%; height: 16px;"></div>
                                    <div class="skeleton skeleton-text" style="width: 25%; height: 14px;"></div>
                                </div>
                                <div class="skeleton-card-body">
                                    <div class="skeleton skeleton-text skeleton-text--xl"></div>
                                </div>
                                <div class="skeleton skeleton-card-chart"></div>
                            </div>
                        <?php endfor; ?>
                    </section>

                    <section class="cards-container" id="real-monitoring" style="display: none;">
                        <?php
                        $patientMetrics = $this->patientMetrics;
                        $chartTypes = $this->chartTypes;
                        $showNoLayoutMessage = false;
                        include dirname(__DIR__) . '/partials/_monitoring-cards.php';
                        ?>
                    </section>
                </section>
            </main>
            <div class="modal" id="cardModal">
                <div class="modal-content">
                    <span class="close-button">&times;</span>
                    <div id="modalDetails"></div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const hash = window.location.hash;
                    if (!hash.startsWith('#indicateurs-')) return;

                    const target = document.querySelector(hash);
                    if (!target) return;
                });
            </script>

                <?php
            }
        );
    }
}
