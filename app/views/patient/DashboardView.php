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
     */
    public function __construct(
        array $consultationsPassees = [],
        array $consultationsFutures = [],
        array $rooms = [],
        array $patientMetrics = [],
        array $patientData = [],
        array $chartTypes = [],
        array $userLayout = []
    ) {
        $this->consultationsPassees = $consultationsPassees;
        $this->consultationsFutures = $consultationsFutures;
        $this->rooms = $rooms;
        $this->patientMetrics = $patientMetrics;
        $this->patientData = $patientData;
        $this->chartTypes = $chartTypes;
        $this->userLayout = $userLayout;
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

        $h = static function ($v): string {
            return htmlspecialchars(is_scalar($v) ? (string) $v : '', ENT_QUOTES, 'UTF-8');
        };

        $layout = new \modules\views\layout\Layout(
            title: 'Dashboard',
            cssFiles: [
                'assets/css/pages/dashboard.css?v=' . time(),
                'assets/css/pages/monitoring.css',
                'assets/css/components/searchbar/searchbar.css',
                'assets/css/components/card.css',
                'assets/css/components/popup.css',
                'assets/css/components/modal.css?v=' . time(),
                'assets/css/layout/aside/calendar.css',
                'assets/css/layout/aside/patient-info.css',
                'assets/css/layout/aside/events.css',
                'assets/css/layout/aside/doctor-list.css',
                'assets/css/layout/aside/aside.css',
            ],
            jsFiles: [
                'assets/js/consultation-filter.js',
                'assets/js/pages/dash.js',
                'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js',
                'assets/js/service/stream.js?v=' . time(),
                'assets/js/component/modal/chart.js?v=' . time(),
                'assets/js/component/modal/navigation.js',
                'assets/js/component/charts/card-sparklines.js?v=' . time(),
                'assets/js/component/modal/modal.js',
            ],
            inlineStyles: '.evenement-content {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }',
            showSidebar: true,
            showAlerts: true
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
                    <?php include dirname(__DIR__) . '/partials/_searchbar.php'; ?>

                    <input type="hidden" id="context-patient-id" value="<?= $patientIdAttr ?>">

                    <?php
                    $uniqueCategories = [];
                    foreach ($this->patientMetrics as $pmRow) {
                        $cat = '';
                        if ($pmRow instanceof \modules\models\entities\Indicator) {
                            $cat = $pmRow->getCategory();
                        } else {
                            $cat = $pmRow['category'] ?? ($pmRow['view_data']['category'] ?? '');
                        }
                        if ($cat && !in_array($cat, $uniqueCategories, true)) {
                            $uniqueCategories[] = $cat;
                        }
                    }
                    sort($uniqueCategories);
                    ?>

                    <?php if (!empty($uniqueCategories)): ?>
                        <div class="category-filters">
                            <button class="category-filter-btn active" data-filter="all">Toutes</button>
                            <?php foreach ($uniqueCategories as $cat): ?>
                                <button class="category-filter-btn" data-filter="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
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
                                <h1><?= $firstName . ' ' . $lastName ?></h1>
                                <span class="pi-age"><?= $age ?></span>
                            </div>
                            <p class="pi-cause"><?= $admissionCause ?></p>

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
                                        <option value="<?= $room_id ?>" <?= $sel ?>>Chambre <?= $room_id ?>
                                            (<?= htmlspecialchars($s['first_name'] ?? '') ?>)</option>
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
                                                        <?php echo htmlspecialchars($this->formatDate($dateStr)); ?></span>
                                                    <?php if ($isPast):
                                                        ?><span class="status-dot"></span><?php
                                                    endif; ?>
                                                </div>
                                                <strong class="title">
                                                    <?php echo htmlspecialchars((string) $title); ?></strong>
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

                        function getSpan(card) {
                            const colMatch = (card.style.gridColumn || '').match(/span\s+(\d+)/);
                            const rowMatch = (card.style.gridRow || '').match(/span\s+(\d+)/);
                            return {
                                w: colMatch ? parseInt(colMatch[1]) : 4,
                                h: rowMatch ? parseInt(rowMatch[1]) : 3
                            };
                        }

                        function getAlertColor(card) {
                            if (card.classList.contains('card--alert')) return 'var(--color-critical, #EF4444)';
                            if (card.classList.contains('card--warn')) return 'var(--color-warning, #F59E0B)';
                            return 'var(--text-muted, #999)';
                        }

                        function buildSVG(card, color, remaining) {
                            const old = card.querySelector('.hide-progress');
                            if (old) old.remove();

                            const w = card.offsetWidth;
                            const h = card.offsetHeight;
                            if (w === 0 || h === 0) return null;
                            const inset = 2;
                            const rx = 12;
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

                            const safeRemaining = Math.max(1, remaining);
                            const startOffset = ((HIDE_DELAY - safeRemaining) / HIDE_DELAY) * perimeter;

                            const anim = rect.animate([
                                { strokeDashoffset: startOffset },
                                { strokeDashoffset: perimeter }
                            ], { duration: safeRemaining, easing: 'linear' });

                            return anim;
                        }

                        const resizeObserver = new ResizeObserver(entries => {
                            for (const entry of entries) {
                                const card = entry.target;
                                const state = cardStates.get(card);
                                if (!state || !state.isCountingDown || state.hidden) continue;

                                state.svgAnim = buildSVG(card, state.lastAlertColor || 'var(--text-muted, #999)', state.remaining);
                                if (state.svgAnim && state.isHovered) {
                                    state.svgAnim.pause();
                                }
                            }
                        });

                        function showCard(card) {
                            const state = cardStates.get(card);
                            if (!state) return;

                            const prog = card.querySelector('.hide-progress');
                            if (prog) prog.remove();

                            const needsAnim = state.hidden;
                            if (state.hidden) {
                                state.hidden = false;
                                const span = state.span;
                                card.style.gridColumn = `auto / span ${span.w}`;
                                card.style.gridRow = `auto / span ${span.h}`;
                                card.style.display = 'flex';
                            }

                            if (needsAnim && !state.animating) {
                                state.animating = true;
                                const anim = card.animate([
                                    { transform: 'scale(0.5) translateY(20px)', opacity: 0 },
                                    { transform: 'scale(1) translateY(0)', opacity: 1 }
                                ], { duration: 400, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' });
                                anim.onfinish = () => { state.animating = false; };
                            }
                        }

                        function startHideCountdown(card, color) {
                            const state = cardStates.get(card);
                            if (!state || state.hidden || state.isCountingDown || state.animating) return;

                            state.isCountingDown = true;
                            state.remaining = HIDE_DELAY;
                            state.lastTick = Date.now();
                            state.isPaused = false;

                            state.svgAnim = buildSVG(card, color, state.remaining);

                            const isModalOpened = (activeModalCard === card && document.body.classList.contains('modal-open'));
                            if (state.isHovered || isModalOpened) {
                                state.isPaused = true;
                                if (state.svgAnim) state.svgAnim.pause();
                            }

                            resizeObserver.observe(card);
                        }

                        Array.from(mainGrid.querySelectorAll('.card')).forEach(card => {
                            const isAlert = card.classList.contains('card--alert') || card.classList.contains('card--warn');
                            const span = getSpan(card);

                            cardStates.set(card, {
                                hidden: !isAlert,
                                animating: false,
                                isCountingDown: false,
                                isHovered: false,
                                isPaused: false,
                                remaining: isAlert ? HIDE_DELAY : 0,
                                lastTick: Date.now(),
                                svgAnim: null,
                                span: span,
                                lastAlertColor: isAlert ? getAlertColor(card) : null
                            });

                            card.addEventListener('click', () => {
                                activeModalCard = card;
                            });

                            card.addEventListener('mouseenter', () => {
                                const state = cardStates.get(card);
                                if (state) state.isHovered = true;
                            });

                            card.addEventListener('mouseleave', () => {
                                const state = cardStates.get(card);
                                if (state) state.isHovered = false;
                            });

                            if (isAlert) {
                                card.style.gridColumn = `auto / span ${span.w}`;
                                card.style.gridRow = `auto / span ${span.h}`;
                            } else {
                                card.style.display = 'none';
                                card.style.gridColumn = '0';
                                card.style.gridRow = '0';
                            }
                        });

                        setInterval(() => {
                            const now = Date.now();
                            const isModalGloballyOpen = document.body.classList.contains("modal-open");

                            Array.from(mainGrid.querySelectorAll('.card')).forEach(card => {
                                const state = cardStates.get(card);
                                if (!state) return;

                                const isAlert = card.classList.contains('card--alert') || card.classList.contains('card--warn');

                                if (isAlert) {
                                    const wasInCountdown = state.isCountingDown;
                                    if (state.isCountingDown) {
                                        state.isCountingDown = false;
                                        resizeObserver.unobserve(card);
                                    }
                                    state.lastAlertColor = getAlertColor(card);
                                    showCard(card, wasInCountdown);
                                    state.lastTick = now;
                                } else if (!state.hidden && !state.animating) {
                                    if (!state.isCountingDown) {
                                        startHideCountdown(card, state.lastAlertColor || 'var(--text-muted, #999)');
                                    } else {
                                        const isModalOpened = (activeModalCard === card && isModalGloballyOpen);
                                        const shouldBePaused = state.isHovered || isModalOpened;

                                        if (shouldBePaused && !state.isPaused) {
                                            state.isPaused = true;
                                            if (state.svgAnim) state.svgAnim.pause();
                                        } else if (!shouldBePaused && state.isPaused) {
                                            state.isPaused = false;
                                            if (state.svgAnim) state.svgAnim.play();
                                        }

                                        if (!state.isPaused) {
                                            state.remaining -= (now - state.lastTick);
                                        }
                                        state.lastTick = now;

                                        if (state.remaining <= 0) {
                                            state.isCountingDown = false;
                                            resizeObserver.unobserve(card);

                                            state.animating = true;
                                            const prog = card.querySelector('.hide-progress');
                                            if (prog) prog.remove();

                                            const anim = card.animate([
                                                { transform: 'scale(1)', opacity: 1 },
                                                { transform: 'scale(0.5)', opacity: 0 }
                                            ], { duration: 300, easing: 'cubic-bezier(0.4, 0, 0.2, 1)' });

                                            anim.onfinish = () => {
                                                card.style.display = 'none';
                                                card.style.gridColumn = '0';
                                                card.style.gridRow = '0';
                                                state.hidden = true;
                                                state.animating = false;
                                                state.lastAlertColor = null;
                                                if (activeModalCard === card) activeModalCard = null;
                                            };
                                        }
                                    }
                                }
                            });
                        }, 50);
                    });
                </script>
            </main>

            <?php
        });
    }
}
