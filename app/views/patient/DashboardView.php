<?php

namespace modules\views\patient;

/**
 * Class DashboardView
 *
 * View for the main dashboard.
 *
 * Main entry point for authenticated doctors. Aggregates vital metrics,
 * past/future consultations, and room selection.
 *
 * @package DashMed\Modules\Views\Pages
 * @author DashMed Team
 * @license Proprietary
 */
class DashboardView
{
    /** @var array<int, \modules\models\entities\Consultation> Past consultations list */
    private array $consultationsPassees;

    /** @var array<int, \modules\models\entities\Consultation> Future consultations list */
    private array $consultationsFutures;

    /** @var array<int, array{
     *   room_id: int|string,
     *   first_name?: string
     * }> Rooms list with patients */
    private array $rooms;

    /** @var array<int, \modules\models\entities\Indicator|array<string, mixed>> Selected patient metrics */
    private array $patientMetrics;

    /** @var array<string, mixed> Selected patient data */
    private array $patientData;

    /** @var array<string, mixed> Chart types config */
    private array $chartTypes;

    /** @var array<string, mixed> User layout preferences */
    private array $userLayout;

    /** @var array<int, array{id: int, name: string, color: string, indicator_ids: array<int, string>, layout: array<string, array{x: ?int, y: ?int, w: int, h: int}>}> Custom groups */
    private array $customGroups;

    /**
     * Constructor.
     *
     * Initializes dashboard with contextual data.
     *
     * @param array<int, \modules\models\entities\Consultation> $consultationsPassees History
     * @param array<int, \modules\models\entities\Consultation> $consultationsFutures Appointments
     * @param array<int, array{
     *   room_id: int|string,
     *   first_name?: string
     * }> $rooms Occupied rooms
     * @param array<int, \modules\models\entities\Indicator|array<string, mixed>> $patientMetrics Health data
     * @param array<string, mixed> $patientData Patient info
     * @param array<string, mixed> $chartTypes Visualizations
     * @param array<string, mixed> $userLayout Layout prefs
     * @param array<int, array{id: int, name: string, color: string, indicator_ids: array<int, string>, layout: array<string, array{x: ?int, y: ?int, w: int, h: int}>}> $customGroups Custom groups
     */
    public function __construct(
        array $consultationsPassees = [],
        array $consultationsFutures = [],
        array $rooms = [],
        array $patientMetrics = [],
        array $patientData = [],
        array $chartTypes = [],
        array $userLayout = [],
        array $customGroups = []
    ) {
        $this->consultationsPassees = $consultationsPassees;
        $this->consultationsFutures = $consultationsFutures;
        $this->rooms = $rooms;
        $this->patientMetrics = $patientMetrics;
        $this->patientData = $patientData;
        $this->chartTypes = $chartTypes;
        $this->userLayout = $userLayout;
        $this->customGroups = $customGroups;
    }

    /**
     * Generates consultation ID for DOM.
     *
     * @param mixed $consultation
     * @return string
     */
    private function getConsultationId($consultation)
    {
        if ($consultation instanceof \modules\models\entities\Consultation) {
            return 'consultation-' . $consultation->getId();
        }
        return 'consultation-unknown';
    }

    /**
     * Formats date string.
     *
     * @param string $dateStr
     * @return string
     */
    private function formatDate($dateStr)
    {
        try {
            $dateObj = new \DateTime($dateStr);
            return $dateObj->format('d-m-Y à H:i');
        } catch (\Exception $e) {
            return $dateStr;
        }
    }

    /**
     * Renders the complete dashboard HTML.
     *
     * Includes sidebar, searchbar, patient info, calendar, etc.
     *
     * @return void
     */
    public function show(): void
    {
        $current = $_COOKIE['room_id'] ?? null;
        if ($current !== null && $current !== '' && is_numeric($current)) {
            $current = (int) $current;
        } else {
            $current = null;
        }

        $h = static function ($v) {
            return htmlspecialchars(is_scalar($v) ? (string) $v : '', ENT_QUOTES, 'UTF-8');
        };

        $layout = new \modules\views\layout\Layout(
            'Dashboard',
            [
                'assets/css/pages/dashboard.css?v=' . time(),
                'assets/css/pages/monitoring.css',
                'assets/css/components/searchbar/searchbar.css',
                'assets/css/components/card.css',
                'assets/css/components/popup.css',
                'assets/css/components/modal.css?v=' . time(),
                'assets/css/layout/aside/patient-info.css',
                'assets/css/layout/aside/events.css',
                'assets/css/layout/aside/doctor-list.css',
                'assets/css/layout/aside/aside.css',
            ],
            [
                'assets/js/consultation-filter.js',
                'assets/js/pages/dash.js',
                'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                'https://cdn.jsdelivr.net/npm/moment@2.30.1/moment.min.js',
                'https://cdn.jsdelivr.net/npm/moment@2.30.1/locale/fr.js',
                'https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.1/dist/chartjs-adapter-moment.min.js',
                'https://cdn.jsdelivr.net/npm/hammerjs@2.0.8',
                'https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom@2.0.1/dist/chartjs-plugin-zoom.min.js',
                'assets/js/service/stream.js?v=' . time(),
                'assets/js/component/modal/chart.js?v=' . time(),
                'assets/js/component/modal/navigation.js',
                'assets/js/component/modal/modal.js',
                'assets/js/component/charts/card-sparklines.js?v=' . time(),
            ],
            '.evenement-content {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }',
            true,
            true
        );


        $layout->render(function () use ($current, $h) {
            $patientId = $this->patientData['id_patient'] ?? '';
            if (!is_scalar($patientId)) {
                $patientId = '';
            }
            $patientIdAttr = htmlspecialchars((string) $patientId, ENT_QUOTES, 'UTF-8');
            ?>

            <main class="container nav-space aside-space">



                <section class="dashboard-content-container">

                    <div class="searchbar-with-patient">
                        <span class="patient-name-label">
                            <?= htmlspecialchars(
                                trim(
                                    (is_scalar($v = $this->patientData['first_name'] ?? '') ? (string) $v : '') . ' ' .
                                    (is_scalar($v = $this->patientData['last_name'] ?? '') ? (string) $v : '')
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
                            const days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
                            const months = ['jan.', 'fév.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

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

                    <input type="hidden" id="context-patient-id" value="<?= $patientIdAttr ?>">

                    <?php
                    $uniqueCategories = [];
                    foreach ($this->patientMetrics as $pmRow) {
                        $cat = '';
                        if ($pmRow instanceof \modules\models\entities\Indicator) {
                            $cat = $pmRow->getCategory();
                        } else {
                            /** @var array{category?: string, view_data?: array{category?: string}} $pmRow */
                            $cat = (string) ($pmRow['category'] ?? ($pmRow['view_data']['category'] ?? ''));
                        }
                        if ($cat && !in_array($cat, $uniqueCategories, true)) {
                            $uniqueCategories[] = $cat;
                        }
                    }
                    sort($uniqueCategories);
                    ?>

                    <?php if (!empty($uniqueCategories)): ?>
                        <div class="category-filters">
                            <button class="category-filter-btn urgent-filter" data-filter="urgent"
                                style="color: var(--color-critical, #EF4444); font-weight: bold; display: flex; align-items: center; gap: 8px;">
                                URGENT
                                <span id="urgent-badge"
                                    style="background: var(--color-critical, #EF4444); color: white; border-radius: 50%; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; padding: 0 6px;">0</span>
                            </button>
                            <button class="category-filter-btn active" data-filter="all">Toutes</button>
                            <?php foreach ($uniqueCategories as $cat): ?>
                                <button class="category-filter-btn"
                                    data-filter="<?= htmlspecialchars((string) $cat, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) $cat, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
                            <?php if (!empty($this->customGroups)): ?>
                                <div class="category-vert-separator"></div>
                                <?php foreach ($this->customGroups as $cg): ?>
                                    <button class="category-filter-btn category-filter-btn--custom" data-filter="custom_group"
                                        data-group-id="<?= (int) $cg['id'] ?>"
                                        data-group-indicators="<?= htmlspecialchars(implode(',', $cg['indicator_ids']), ENT_QUOTES, 'UTF-8') ?>"
                                        data-group-layout='<?= htmlspecialchars((string) json_encode($cg['layout']), ENT_QUOTES, 'UTF-8') ?>'
                                        style="--cg-color:
                <?= htmlspecialchars($cg['color'], ENT_QUOTES, 'UTF-8') ?>;">
                                        <?= htmlspecialchars($cg['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>


                    <section class="skeleton-wrapper skeleton-monitoring-grid" id="skeleton-cards" data-skeleton-for="real-cards"
                        data-skeleton-auto data-skeleton-delay="400">
                        <?php for ($i = 0; $i < 6; $i++): ?>
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

                    <section class="cards-container cards-grid" id="real-cards" style="width: 100%; display: none;">
                        <?php
                        $patientMetrics = $this->patientMetrics;
                        $chartTypes = $this->chartTypes;
                        $userLayout = $this->userLayout;
                        $useCustomLayout = true;
                        $componentPath = dirname(__DIR__) . '/partials/_monitoring-cards.php';
                        if (file_exists($componentPath)) {
                            include $componentPath;
                        }
                        ?>
                    </section>
                </section>
                <button id="aside-restore-btn" onclick="toggleDesktopAside()" title="Afficher / Masquer le menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>
                <button id="aside-show-btn" onclick="toggleAside()">☰</button>
                <aside id="aside">
                    <div class="skeleton-wrapper skeleton-aside-section" id="skeleton-aside" data-skeleton-for="real-aside"
                        data-skeleton-auto data-skeleton-delay="350">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                            <div class="skeleton skeleton-circle" style="width: 48px; height: 48px;"></div>
                            <div style="flex: 1; display: flex; flex-direction: column; gap: 6px;">
                                <div class="skeleton skeleton-text skeleton-text--lg" style="width: 70%;"></div>
                                <div class="skeleton skeleton-text skeleton-text--sm" style="width: 40%;"></div>
                            </div>
                        </div>
                        <div class="skeleton skeleton-text" style="width: 90%; height: 12px;"></div>
                        <div class="skeleton skeleton-select"></div>
                        <div style="margin-top: 16px;">
                            <div class="skeleton skeleton-text skeleton-text--lg" style="width: 50%; margin-bottom: 16px;">
                            </div>
                            <?php for ($j = 0; $j < 4; $j++): ?>
                                <div class="skeleton-aside-consultation">
                                    <div class="skeleton skeleton-rect" style="width: 90px; height: 28px;"></div>
                                    <div class="skeleton skeleton-text" style="flex: 1; height: 14px;"></div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div id="real-aside" style="display: none;">
                        <section class="patient-infos">
                            <?php
                            $firstName = htmlspecialchars(
                                is_scalar($v = $this->patientData['first_name']) ? (string) $v : 'Patient'
                            );
                            $lastName = htmlspecialchars(
                                is_scalar($v = $this->patientData['last_name']) ? (string) $v : 'Inconnu'
                            );
                            $birthDateStr = is_scalar($v = $this->patientData['birth_date']) ? (string) $v : '';
                            $age = 'Âge inconnu';
                            if ($birthDateStr !== '') {
                                $bDate = date_create($birthDateStr);
                                $nowDate = date_create('today');
                                if ($bDate) {
                                    $age = date_diff($bDate, $nowDate)->y . ' ans';
                                }
                            }
                            $admissionCause = htmlspecialchars(
                                is_scalar(
                                    $v = $this->patientData['admission_cause']
                                ) ?
                                (string) $v : 'Aucun motif renseigné'
                            );
                            ?>
                            <div class="pi-header">
                                <h1>
                                    <?= $firstName . ' ' . $lastName ?>
                                </h1>
                                <span class="pi-age">
                                    <?= $age ?>
                                </span>
                            </div>
                            <p class="pi-cause">
                                <?= $admissionCause ?>
                            </p>

                            <select id="id_rooms" name="room" onchange="location.href='/?page=dashboard&room=' + this.value"
                                style="margin-top: 15px; width: 100%; padding: 8px;">
                                <option value="" <?= $current === null ? 'selected' : '' ?>>
                                    -- Sélectionnez une chambre --
                                </option>
                                <?php if (!empty($this->rooms)): ?>
                                    <?php foreach ($this->rooms as $s):
                                        $room_id = (int) $s['room_id'];
                                        if ($room_id <= 0) {
                                            continue;
                                        }
                                        $sel = ($current !== null && $current === $room_id) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $room_id ?>" <?= $sel ?>>Chambre
                                            <?= $room_id ?>
                                            (
                                            <?= htmlspecialchars($s['first_name'] ?? '') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </section>
                        <div class="consultations-sidebar-wrapper">
                            <div class="consultation-section-header">
                                <h1>Consultations</h1>
                                <div class="filter-buttons-container">
                                    <div id="sort-container">
                                        <button id="sort-btn">Trier ▾</button>
                                        <div id="sort-menu">
                                            <button class="sort-option" data-order="asc">Ordre croissant</button>
                                            <button class="sort-option" data-order="desc">Ordre décroissant</button>
                                        </div>
                                    </div>
                                    <div id="sort-container2">
                                        <button id="sort-btn2">Options ▾</button>
                                        <div id="sort-menu2">
                                            <button class="sort-option2">Rendez-vous à venir</button>
                                            <button class="sort-option2">Rendez-vous passé</button>
                                            <button class="sort-option2">Tout mes rendez-vous</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php
                            $toutesConsultations = array_merge(
                                $this->consultationsPassees,
                                $this->consultationsFutures
                            );

                            if (!empty($toutesConsultations)):
                                $consultationsAffichees = $toutesConsultations;
                                ?>
                                <section class="evenement" id="consultation-list">
                                    <?php foreach ($consultationsAffichees as $consultation):
                                        $dateStr = (string) $consultation->getDate();
                                        try {
                                            $dateObj = new \DateTime($dateStr);
                                            $isoDate = $dateObj->format('Y-m-d');
                                        } catch (\Exception $e) {
                                            $isoDate = $dateStr;
                                        }

                                        $title = $consultation->getTitle();
                                        if (empty($title)) {
                                            $title = $consultation->getEvenementType();
                                        }
                                        $title = (string) ($title ?: 'Consultation');

                                        $isPast = false;
                                        try {
                                            $cDate = new \DateTime($consultation->getDate());
                                            $now = new \DateTime();
                                            if ($cDate < $now) {
                                                $isPast = true;
                                            }
                                        } catch (\Exception $e) {
                                        }
                                        ?>
                                        <a href="/?page=medicalprocedure&id_patient=
                                    <?php echo urlencode((string) $patientId); ?>
                                    #<?php echo $this->getConsultationId($consultation); ?>" class="consultation-link"
                                            data-date="<?php echo $isoDate; ?>">
                                            <div class="evenement-content">
                                                <div class="date-container <?php if ($isPast) {
                                                    echo 'has-tooltip';
                                                } ?>" <?php if ($isPast) {
                                                     echo 'data-tooltip="Consultation déjà effectuée"';
                                                 } ?>>
                                                    <span class="date">
                                                        <?php echo htmlspecialchars($this->formatDate($dateStr)); ?>
                                                    </span>
                                                    <?php if ($isPast):
                                                        ?><span class="status-dot"></span>
                                                        <?php
                                                    endif; ?>
                                                </div>
                                                <strong class="title">
                                                    <?php echo htmlspecialchars((string) $title); ?>
                                                </strong>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </section>
                            <?php else: ?>
                                <p>Aucune consultation</p>
                            <?php endif; ?>



                            <a href="/?page=medicalprocedure&id_patient=<?php echo urlencode((string) $patientId); ?>"
                                style="text-decoration: none; color: inherit;">
                                <p class="bouton-consultations">Afficher plus de contenu</p>
                            </a>
                            <br>
                        </div>

                    </div>

                </aside>


                <div class="modal" id="cardModal">
                    <div class="modal-content">
                        <span class="close-button">&times;</span>
                        <div id="modalDetails"></div>
                    </div>
                </div>


                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        if (typeof ConsultationManager !== 'undefined') {
                            new ConsultationManager({
                                containerSelector: '#consultation-list',
                                itemSelector: '.consultation-link',
                                dateAttribute: 'data-date',
                                sortBtnId: 'sort-btn',
                                sortMenuId: 'sort-menu',
                                sortOptionSelector: '.sort-option',
                                filterBtnId: 'sort-btn2',
                                filterMenuId: 'sort-menu2',
                                filterOptionSelector: '.sort-option2'
                            });
                        }

                        const mainGrid = document.getElementById('real-cards');
                        if (!mainGrid) return;

                        mainGrid.style.gridAutoFlow = 'dense';

                        const HIDE_DELAY = 20000;
                        const cardStates = new WeakMap();
                        let nextAlertOrder = 1;
                        window.activeModalCard = null;

                        function getActiveFilter() {
                            const btn = document.querySelector('.category-filter-btn.active');
                            return btn ? btn.getAttribute('data-filter') : 'all';
                        }

                        function isUrgentFilter() {
                            return getActiveFilter() === 'urgent';
                        }

                        function isAlertCard(card) {
                            return card.classList.contains('card--alert') || card.classList.contains('card--warn');
                        }

                        function getAlertColor(card) {
                            if (card.classList.contains('card--alert')) return 'var(--color-critical, #EF4444)';
                            if (card.classList.contains('card--warn')) return 'var(--color-warning, #F59E0B)';
                            return 'var(--text-muted, #999)';
                        }

                        function getSpan(card) {
                            const colMatch = (card.style.gridColumn || '').match(/span\s+(\d+)/);
                            const rowMatch = (card.style.gridRow || '').match(/span\s+(\d+)/);
                            return {
                                w: colMatch ? parseInt(colMatch[1], 10) : 4,
                                h: rowMatch ? parseInt(rowMatch[1], 10) : 3
                            };
                        }

                        function buildSVG(card, color, remaining) {
                            const old = card.querySelector('.hide-progress');
                            if (old) old.remove();
                            const w = card.offsetWidth;
                            const h = card.offsetHeight;
                            if (w < 10 || h < 10) return null;
                            const inset = 2, rx = 12;
                            const rw = w - inset * 2;
                            const rh = h - inset * 2;
                            const perimeter = 2 * (rw + rh) - (8 - 2 * Math.PI) * rx;

                            const ns = 'http://www.w3.org/2000/svg';
                            const svg = document.createElementNS(ns, 'svg');
                            svg.classList.add('hide-progress');
                            svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
                            svg.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;z-index:10;pointer-events:none;';

                            const rect = document.createElementNS(ns, 'rect');
                            rect.setAttribute('x', inset);
                            rect.setAttribute('y', inset);
                            rect.setAttribute('width', rw);
                            rect.setAttribute('height', rh);
                            rect.setAttribute('rx', rx);
                            rect.setAttribute('ry', rx);
                            rect.setAttribute('fill', 'none');
                            rect.setAttribute('stroke', color);
                            rect.setAttribute('stroke-width', '3');
                            rect.setAttribute('stroke-dasharray', perimeter);
                            rect.setAttribute('stroke-dashoffset', '0');
                            rect.setAttribute('stroke-linecap', 'round');

                            svg.appendChild(rect);
                            card.style.position = 'relative';
                            card.appendChild(svg);
                            svg.dataset.w = w;
                            svg.dataset.h = h;
                            const safeRemaining = Math.max(1, remaining);
                            const startOffset = ((HIDE_DELAY - safeRemaining) / HIDE_DELAY) * perimeter;

                            const anim = rect.animate([
                                { strokeDashoffset: startOffset },
                                { strokeDashoffset: perimeter }
                            ], { duration: safeRemaining, easing: 'linear' });

                            return anim;
                        }

                        function rebuildSVGIfStale(card, state) {
                            if (!state.isCountingDown || state.hidden) return;
                            const svg = card.querySelector('.hide-progress');
                            const currentW = card.offsetWidth;
                            const currentH = card.offsetHeight;
                            if (!svg || Math.abs(currentW - Number(svg.dataset.w)) > 2 || Math.abs(currentH - Number(svg.dataset.h)) > 2) {
                                state.svgAnim = buildSVG(card, state.lastAlertColor || '#999', state.remaining);
                                if (state.svgAnim && state.isPaused) state.svgAnim.pause();
                            }
                        }

                        const resizeObserver = new ResizeObserver(entries => {
                            for (const entry of entries) {
                                const card = entry.target;
                                const state = cardStates.get(card);
                                if (!state || !state.isCountingDown || state.hidden) continue;
                                rebuildSVGIfStale(card, state);
                            }
                        });

                        function startCooldown(card, color) {
                            const state = cardStates.get(card);
                            if (!state || state.hidden || state.isCountingDown || state.animating) return;
                            state.isCountingDown = true;
                            state.remaining = HIDE_DELAY;
                            state.lastTick = Date.now();
                            state.isPaused = false;
                            state.lastAlertColor = color;
                            state.svgAnim = buildSVG(card, color, state.remaining);
                            resizeObserver.observe(card);
                        }

                        function stopCooldown(card) {
                            const state = cardStates.get(card);
                            if (!state || !state.isCountingDown) return;
                            state.isCountingDown = false;
                            resizeObserver.unobserve(card);
                            const prog = card.querySelector('.hide-progress');
                            if (prog) prog.remove();
                            state.svgAnim = null;
                        }

                        function showCard(card, options = {}) {
                            const state = cardStates.get(card);
                            if (!state) return;

                            const animate = options.animate === true;
                            const prioritize = options.prioritize === true;
                            const compact = options.compact === true;
                            const targetActiveFilter = getActiveFilter();
                            const isCustomGroup = targetActiveFilter === 'custom_group';
                            const customLayout = (isCustomGroup && options.customLayout) ? options.customLayout : null;
                            const wasHidden = state.hidden;

                            state.hidden = false;
                            card.style.display = 'flex';

                            if (customLayout) {
                                card.style.gridColumn = customLayout.col;
                                card.style.gridRow = customLayout.row;
                            } else {
                                card.style.gridColumn = compact ? `auto / span ${state.span.w}` : state.origCol;
                                card.style.gridRow = compact ? `auto / span ${state.span.h}` : state.origRow;
                            }
                            card.style.order = prioritize ? String(nextAlertOrder++) : String(state.baseOrder);

                            if (wasHidden && animate && !state.animating) {
                                state.animating = true;
                                const anim = card.animate([
                                    { transform: 'scale(0.5) translateY(20px)', opacity: 0 },
                                    { transform: 'scale(1) translateY(0)', opacity: 1 }
                                ], { duration: 400, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' });
                                anim.onfinish = () => {
                                    state.animating = false;
                                };
                            }
                        }

                        function hideCard(card, options = {}) {
                            const state = cardStates.get(card);
                            if (!state) return;

                            const animate = options.animate === true;
                            let siblings = [];
                            let firstPositions = new Map();

                            if (animate) {
                                siblings = Array.from(mainGrid.querySelectorAll('.card')).filter(c => {
                                    const s = cardStates.get(c);
                                    return s && !s.hidden && c !== card;
                                });
                                siblings.forEach(c => firstPositions.set(c, c.getBoundingClientRect()));
                            }

                            state.hidden = true;
                            card.style.display = 'none';
                            card.style.gridColumn = 'auto';
                            card.style.gridRow = 'auto';
                            card.style.order = String(state.baseOrder);

                            if (animate) {
                                requestAnimationFrame(() => {
                                    siblings.forEach(c => {
                                        const first = firstPositions.get(c);
                                        if (!first) return;
                                        const last = c.getBoundingClientRect();
                                        const dx = first.left - last.left;
                                        const dy = first.top - last.top;
                                        if (Math.abs(dx) > 1 || Math.abs(dy) > 1) {
                                            c.animate([
                                                { transform: `translate(${dx}px, ${dy}px)` },
                                                { transform: 'translate(0, 0)' }
                                            ], { duration: 400, easing: 'cubic-bezier(0.4, 0, 0.2, 1)' });
                                        }
                                    });
                                });
                            }
                        }

                        function syncCard(card, options = {}) {
                            const state = cardStates.get(card);
                            if (!state) return;

                            const filter = getActiveFilter();
                            const isAlert = isAlertCard(card);
                            const category = card.getAttribute('data-category') || '';
                            const animate = options.animate === true;

                            let shouldShow = false;
                            let prioritize = false;
                            let compact = false;

                            let customLayout = null;

                            if (filter === 'urgent') {
                                shouldShow = state.isUrgentlyVisible && !state.dismissed;
                                prioritize = shouldShow;
                                compact = shouldShow;
                            } else if (filter === 'all') {
                                shouldShow = state.inLayout;
                            } else if (filter === 'custom_group') {
                                const activeBtn = document.querySelector('.category-filter-btn.active');
                                if (activeBtn) {
                                    const indicatorIdsStr = activeBtn.getAttribute('data-group-indicators') || '';
                                    const indicatorIds = indicatorIdsStr.split(',').map(id => id.trim()).filter(id => id !== '');
                                    const cardParameterId = card.getAttribute('data-parameter-id') || '';

                                    shouldShow = indicatorIds.includes(cardParameterId);

                                    if (shouldShow) {
                                        const layoutStr = activeBtn.getAttribute('data-group-layout');
                                        if (layoutStr) {
                                            try {
                                                const layout = JSON.parse(layoutStr);
                                                const itemLayout = layout[cardParameterId];
                                                if (itemLayout) {
                                                    const xVal = Number(itemLayout.x);
                                                    const yVal = Number(itemLayout.y);
                                                    const wVal = Number(itemLayout.w) || 4;
                                                    const hVal = Number(itemLayout.h) || 3;

                                                    if (!isNaN(xVal) && !isNaN(yVal)) {
                                                        customLayout = {
                                                            col: `${xVal + 1} / span ${wVal}`,
                                                            row: `${yVal + 1} / span ${hVal}`
                                                        };
                                                        console.log(`[Dashboard] Applied custom layout for ${cardParameterId}: col=${customLayout.col}, row=${customLayout.row}`);
                                                    }
                                                }
                                            } catch (e) {
                                                console.error('[Dashboard] Error parsing group layout:', e);
                                            }
                                        }
                                    }
                                }
                            } else {
                                shouldShow = (category === filter);
                                compact = shouldShow;
                            }

                            if (shouldShow) {
                                showCard(card, { animate, prioritize, compact, customLayout });
                                return;
                            }

                            hideCard(card);
                        }

                        function syncAllCards(options = {}) {
                            if (isUrgentFilter()) nextAlertOrder = 1;

                            const cards = Array.from(mainGrid.querySelectorAll('.card'));

                            const isCustomGroup = getActiveFilter() === 'custom_group';
                            mainGrid.style.gridAutoFlow = isCustomGroup ? 'initial' : 'dense';

                            cards.forEach(card => {
                                syncCard(card, options);
                            });
                        }

                        Array.from(mainGrid.querySelectorAll('.card')).forEach((card, index) => {
                            const inLayout = card.getAttribute('data-in-layout') === '1' || card.hasAttribute('data-no-data');

                            cardStates.set(card, {
                                hidden: false,
                                inLayout,
                                animating: false,
                                isHovered: false,
                                isPaused: false,
                                dismissed: false,
                                isCountingDown: false,
                                countdownComplete: false,
                                isUrgentlyVisible: isAlertCard(card),
                                remaining: 0,
                                lastTick: Date.now(),
                                svgAnim: null,
                                span: getSpan(card),
                                baseOrder: index + 1,
                                origCol: card.style.gridColumn || 'auto',
                                origRow: card.style.gridRow || 'auto',
                                lastAlertColor: isAlertCard(card) ? getAlertColor(card) : null
                            });

                            card.addEventListener('click', (event) => {
                                window.activeModalCard = card;
                            });

                            const dismissBtn = card.querySelector('.card-dismiss-btn');
                            if (dismissBtn) {
                                dismissBtn.addEventListener('click', e => {
                                    e.stopPropagation();
                                    if (!isUrgentFilter()) return;

                                    const state = cardStates.get(card);
                                    if (!state || state.animating) return;
                                    if (isAlertCard(card) || state.isCountingDown) return;

                                    state.dismissed = true;
                                    state.animating = true;
                                    state.isUrgentlyVisible = false;

                                    const anim = card.animate([
                                        { transform: 'scale(1)', opacity: 1 },
                                        { transform: 'scale(0.5)', opacity: 0 }
                                    ], { duration: 300, easing: 'cubic-bezier(0.4, 0, 0.2, 1)' });

                                    anim.onfinish = () => {
                                        state.animating = false;
                                        card.classList.remove('card--alert', 'card--warn');
                                        state.lastAlertColor = null;
                                        hideCard(card, { animate: true });
                                        if (window.activeModalCard === card) {
                                            window.activeModalCard = null;
                                        }
                                    };
                                });
                            }

                            card.addEventListener('mouseenter', () => {
                                const state = cardStates.get(card);
                                if (state) state.isHovered = true;
                                if (dismissBtn && isUrgentFilter()) {
                                    if (!isAlertCard(card) && !state.isCountingDown && state.isUrgentlyVisible && !state.hidden) {
                                        dismissBtn.style.opacity = '1';
                                        dismissBtn.style.pointerEvents = 'auto';
                                        dismissBtn.style.transform = 'scale(1)';
                                    }
                                }
                            });

                            card.addEventListener('mouseleave', () => {
                                const state = cardStates.get(card);
                                if (state) state.isHovered = false;
                                if (dismissBtn) {
                                    dismissBtn.style.opacity = '0';
                                    dismissBtn.style.pointerEvents = 'none';
                                    dismissBtn.style.transform = 'scale(0)';
                                }
                            });
                        });

                        document.querySelectorAll('.category-filter-btn').forEach(btn => {
                            btn.addEventListener('click', () => {
                                document.querySelectorAll('.category-filter-btn').forEach(b => b.classList.remove('active'));
                                btn.classList.add('active');

                                Array.from(mainGrid.querySelectorAll('.card')).forEach(card => {
                                    const state = cardStates.get(card);
                                    if (!state) return;
                                    if (!isUrgentFilter()) {
                                        state.dismissed = false;
                                        state.lastAlertColor = null;
                                        state.isUrgentlyVisible = false;
                                        state.countdownComplete = false;
                                        if (state.isCountingDown) stopCooldown(card);
                                    } else {
                                        if (!isAlertCard(card)) {
                                            state.isUrgentlyVisible = false;
                                            state.countdownComplete = false;
                                        }
                                    }
                                });

                                syncAllCards({ animate: isUrgentFilter() });
                            });
                        });

                        syncAllCards();

                        setInterval(() => {
                            const now = Date.now();
                            const isModalGlobalOpen = document.body.classList.contains('modal-open');
                            const urgentActive = isUrgentFilter();
                            let activeAlertsCount = 0;

                            Array.from(mainGrid.querySelectorAll('.card')).forEach(card => {
                                const state = cardStates.get(card);
                                if (!state) return;

                                const isAlert = isAlertCard(card);
                                const dismissBtn = card.querySelector('.card-dismiss-btn');

                                if (isAlert && !state.dismissed) {
                                    activeAlertsCount++;
                                } else if ((state.isCountingDown || state.isUrgentlyVisible) && !state.dismissed) {
                                    activeAlertsCount++;
                                }

                                if (urgentActive) {
                                    if (isAlert) {
                                        if (state.isCountingDown) stopCooldown(card);
                                        state.lastAlertColor = getAlertColor(card);
                                        state.lastTick = now;
                                        state.dismissed = false;
                                        state.countdownComplete = false;
                                        state.isUrgentlyVisible = true;
                                        if (state.hidden) showCard(card, { animate: true, prioritize: true, compact: true });
                                    } else if (!state.hidden && !state.animating) {
                                        if (!state.isCountingDown && !state.countdownComplete && state.lastAlertColor) {
                                            startCooldown(card, state.lastAlertColor);
                                        } else if (state.isCountingDown) {
                                            const isModalOpen = window.activeModalCard === card && isModalGlobalOpen;
                                            const shouldBePaused = state.isHovered || isModalOpen;
                                            if (shouldBePaused && !state.isPaused) {
                                                state.isPaused = true;
                                                if (state.svgAnim) state.svgAnim.pause();
                                            } else if (!shouldBePaused && state.isPaused) {
                                                state.isPaused = false;
                                                if (state.svgAnim) state.svgAnim.play();
                                            }
                                            if (!state.isPaused) state.remaining -= (now - state.lastTick);
                                            state.lastTick = now;
                                            rebuildSVGIfStale(card, state);
                                            if (state.remaining <= 0) {
                                                stopCooldown(card);
                                                card.classList.remove('card--alert', 'card--warn');
                                                const critIcon = card.querySelector('.status-critical');
                                                if (critIcon) critIcon.style.display = 'none';
                                                const warnIcon = card.querySelector('.status-warning');
                                                if (warnIcon) warnIcon.style.display = 'none';
                                                state.lastAlertColor = null;
                                                state.countdownComplete = true;
                                            }
                                        }
                                    }

                                    const canDismiss = !isAlert && !state.isCountingDown && state.isUrgentlyVisible && !state.hidden;
                                    if (dismissBtn) {
                                        if (canDismiss && state.isHovered) {
                                            dismissBtn.style.opacity = '1';
                                            dismissBtn.style.pointerEvents = 'auto';
                                            dismissBtn.style.transform = 'scale(1)';
                                        } else {
                                            dismissBtn.style.opacity = '0';
                                            dismissBtn.style.pointerEvents = 'none';
                                            dismissBtn.style.transform = 'scale(0)';
                                        }
                                    }
                                } else {
                                    if (dismissBtn) {
                                        dismissBtn.style.opacity = '0';
                                        dismissBtn.style.pointerEvents = 'none';
                                        dismissBtn.style.transform = 'scale(0)';
                                    }
                                }
                            });

                            const badge = document.getElementById('urgent-badge');
                            if (badge) badge.textContent = String(activeAlertsCount);
                        }, 250);
                    });
                </script>
                <?php include dirname(__DIR__) . '/partials/_scroll-to-top.php'; ?>
            </main>

            <?php
        });
    }
}