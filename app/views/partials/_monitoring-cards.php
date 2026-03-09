<?php

/**
 * Partial: Monitoring Cards
 *
 * Expected variables:
 * - $patientMetrics : array - List of metrics to display
 * - $chartTypes : array - Chart types configuration
 * - $userLayout : array - User layout preferences
 * - $idPrefix : string (optional) - Prefix for IDs
 * - $useCustomLayout : bool (optional) - Applies custom dimensions/positions
 *
 * @package DashMed\Views\Partials
 */

declare(strict_types=1);

$idPrefix = $idPrefix ?? '';
$useCustomLayout = $useCustomLayout ?? false;
$useCustomSize = $useCustomSize ?? false;

$DEFAULT_WIDTH = 4;
$DEFAULT_HEIGHT = 3;
$WIDGETS_PER_ROW = 3;

$layoutMap = [];
if (!empty($userLayout)) {
    foreach ($userLayout as $layoutItem) {
        $layoutMap[$layoutItem['parameter_id']] = $layoutItem;
    }
}

$useDefaultLayout = empty($layoutMap);
$defaultLayoutIndex = 0;

$escape = static fn(mixed $value): string => htmlspecialchars(
    is_scalar($value) ? (string) $value : '',
    ENT_QUOTES,
    'UTF-8'
);

if (!empty($patientMetrics)): ?>
    <?php if (empty($layoutMap)): ?>
        <article class="card" data-no-data="1" style="grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; text-align: center; gap: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-color);">Aucune donnée</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 400px; line-height: 1.5;">
                Nous vous invitons à paramétrer vos indicateurs dans
                <a href="/?page=customization" style="color: var(--primary-color, #275afe); text-decoration: underline; font-weight: 500;">Personnalisation</a>.
            </p>
        </article>
    <?php endif; ?>
    <?php foreach ($patientMetrics as $row): ?>
        <?php
        if ($row instanceof \modules\models\entities\Indicator) {
            $viewData = $row->getViewData();
            $parameterId = $row->getId();
        } else {
            $viewData = $row['view_data'] ?? [];
            $parameterId = $row['parameter_id'] ?? '';
        }

        $display = $viewData['display_name'] ?? '—';
        $description = $viewData['description'] ?? '—';
        $value = $viewData['value'] ?? '';
        $unit = $viewData['unit'] ?? '';
        $slug = $viewData['slug'] ?? 'param';
        $chartType = $viewData['chart_type'] ?? 'line';
        $stateClass = $viewData['card_class'] ?? '';
        $critFlag = (bool) ($viewData['is_crit_flag'] ?? false);
        $category = $viewData['category'] ?? '';
        $chartConfig = $viewData['chart_config'] ?? '{}';
        $chartAllowed = $viewData['chart_allowed'] ?? ['line'];

        $dmax = $viewData['view_limits']['max'] ?? null;
        $nmax = $viewData['thresholds']['nmax'] ?? null;
        $cmax = $viewData['thresholds']['cmax'] ?? null;
        $gaugeMax = str_contains((string) $unit, '%') ? 100 : (
        is_numeric($dmax) ? (float) $dmax : (
        is_numeric($nmax) ? (float) $nmax : (
        is_numeric($cmax) ? (float) $cmax : 100
        )
        )
        );

        $isValueOnly = ($chartType === 'value');
        if ($isValueOnly) {
            $stateClass .= ' card--value-only';
        }

        $gridStyle = '';
        $layout = $layoutMap[$parameterId] ?? null;
        $isHidden = is_array($layout) && !empty($layout['is_hidden']);
        $isVisibleInLayout = ($layout !== null && !$isHidden);

        $w = max(1, (int) ($layout['grid_w'] ?? $DEFAULT_WIDTH));
        $h = max(1, (int) ($layout['grid_h'] ?? $DEFAULT_HEIGHT));

        if ($useCustomLayout) {
            if ($isVisibleInLayout) {
                $x = (int) ($layout['grid_x'] ?? 0);
                $y = (int) ($layout['grid_y'] ?? 0);
                $gridStyle = sprintf(
                        'grid-column: %d / span %d; grid-row: %d / span %d;',
                        $x + 1,
                        $w,
                        $y + 1,
                        $h
                );
            } elseif ($useDefaultLayout) {
                $x = ($defaultLayoutIndex % $WIDGETS_PER_ROW) * $DEFAULT_WIDTH;
                $y = (int) floor($defaultLayoutIndex / $WIDGETS_PER_ROW) * $DEFAULT_HEIGHT;
                $gridStyle = sprintf(
                        'grid-column: %d / span %d; grid-row: %d / span %d;',
                        $x + 1,
                        $DEFAULT_WIDTH,
                        $y + 1,
                        $DEFAULT_HEIGHT
                );
                $defaultLayoutIndex++;
            }
        } elseif ($useCustomSize && $isVisibleInLayout) {
            $gridStyle = sprintf('grid-column: auto / span %d; grid-row: auto / span %d;', $w, $h);
        }

        $inLayout = $isVisibleInLayout ? '1' : '0';
        ?>

        <article id="indicateurs-<?= $escape($parameterId) ?>" class="card <?= $stateClass ?>" style="<?= $gridStyle ?>"
                 data-in-layout="<?= $inLayout ?>" data-category="<?= $escape($category) ?>" data-display="<?= $escape($display) ?>"
                 data-value="<?= $escape($value) ?>" data-crit="<?= $critFlag ? '1' : '0' ?>"
                 data-detail-id="<?= $escape($idPrefix . 'detail-' . $slug) ?>" data-slug="<?= $escape($slug) ?>"
                 data-chart='<?= $escape($chartConfig) ?>' data-chart-type="<?= $escape($chartType) ?>"
                 data-max="<?= $escape($gaugeMax) ?>" data-dmin="<?= $escape($viewData['view_limits']['min'] ?? '') ?>"
                 data-dmax="<?= $escape($viewData['view_limits']['max'] ?? '') ?>"
                 data-nmin="<?= $escape($viewData['thresholds']['nmin'] ?? '') ?>"
                 data-nmax="<?= $escape($viewData['thresholds']['nmax'] ?? '') ?>"
                 data-cmin="<?= $escape($viewData['thresholds']['cmin'] ?? '') ?>"
                 data-cmax="<?= $escape($viewData['thresholds']['cmax'] ?? '') ?>"
                 data-display-duration="<?= $escape($viewData['display_duration'] ?? '0.0333') ?>"
                 data-card-display-duration="<?= $escape($viewData['card_display_duration'] ?? '0.0333') ?>">

            <div class="card-dismiss-btn" title="Masquer l'indicateur"
                style="position: absolute; left: -10px; top: -10px; width: 22px; height: 22px; background: var(--primary-color, #275afe); display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; pointer-events: none; transition: opacity 0.2s, transform 0.15s; transform: scale(0); border-radius: 50%; z-index: 15; box-shadow: 0 2px 6px rgba(0,0,0,0.25);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                    style="color: white;">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </div>

            <div class="card-header">
                <h3>
                    <?= $escape($display) ?>
                </h3>

                <div
                    style="flex: 0 0 auto; display: flex; align-items: center; justify-content: center; height: 100%; padding: 0 4px;">
                    <select class="card-interval-select" title="Durée d'affichage">
                        <?php
                        $cardDuration = (string) ($viewData['card_display_duration'] ?? '0.0333');
                        $cardOptions = [
                            '0.0333' => '2m',
                            'all' => 'Tout',
                            '1' => '1H',
                            '24' => '24H'
                        ];
                        foreach ($cardOptions as $val => $lab): ?>
                            <option value="<?= $val ?>" <?= $cardDuration === $val ? 'selected' : '' ?>><?= $lab ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <p class="value"
                    style="flex: 1; text-align: right; display: <?= $isValueOnly ? 'none' : 'flex' ?>; justify-content: flex-end; align-items: center; gap: 3px; margin: 0; line-height: 1;">
                    <span style="font-size: 0.95rem; font-weight: 700; color: var(--text-main);"><?= $escape($value) ?></span>
                    <span class="unit"
                        style="font-size: 0.7rem; color: var(--text-muted);"><?= $unit !== '' ? ' ' . $escape($unit) : '' ?></span>
                    <span class="value-status-icon status-critical" title="Critique"
                        style="color: var(--color-critical, #EF4444); display: <?= str_contains($stateClass, 'card--alert') ? 'flex' : 'none' ?>;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2
                                2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </span>
                    <span class="value-status-icon status-warning" title="Attention"
                        style="color: var(--color-warning, #F59E0B); display: <?= str_contains($stateClass, 'card--warn') ? 'flex' : 'none' ?>;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71
                                3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </span>
                </p>

            </div>

            <div class="card-value-only-container"
                style="display: <?= $isValueOnly ? 'flex' : 'none' ?>; flex-direction: column; justify-content: center; align-items: center; height: 100%;">
                <p class="big-value">
                    <?= $escape($value) ?>
                </p>
                <p class="unit">
                    <?= $escape($unit) ?>
                </p>
            </div>

            <div class="card-spark" style="display: <?= $isValueOnly ? 'none' : 'block' ?>;">
                <div class="card-chart-loader">
                    <svg class="loader-logo-svg" viewBox="0 0 1024 1024" preserveAspectRatio="xMidYMid meet"
                        style="width: 30px; height: 30px; margin-bottom: 8px;">
                        <g transform="translate(0,1024) scale(0.1,-0.1)" fill="var(--primary-color, #275afe)">
                            <path
                                d="M1740 5215 l0 -1975 643 0 c614 0 844 7 987 31 198 33 389 99 572 197 166 88 280 174 423 317 287 286 451 631 521 1095 22 143 25 500 5 640 -35 254 -100 479 -191 660 -47 94 -154 261 -208 325 -286 341 -656 565 -1064 644 -180 35 -338 41 -1024 41 l-664 0 0 -1975z m1469 1209 c239 -35 433 -135 598 -307 150 -157 240 -335 288 -566 51 -245 35 -604 -36 -821 -106 -323 -355 -574 -669 -674 -167 -53 -207 -58 -537 -63 l-313 -5 0 1226 0 1226 281 0 c204 0 311 -4 388 -16z" />
                            <path
                                d="M4840 6763 l0 -428 29 -80 c16 -44 44 -118 61 -165 50 -136 98 -323 122 -474 26 -170 31 -554 10 -726 -31 -245 -96 -494 -171 -649 l-36 -76 -5 -463 -5 -462 413 0 412 0 0 1241 0 1242 26 -39 c15 -21 127 -181 249 -354 122 -173 353 -503 514 -732 l292 -418 537 767 537 767 3 -1237 2 -1237 398 0 397 0 -3 1975 -2 1975 -384 0 -385 0 -136 -202 c-449 -668 -957 -1413 -963 -1415 -4 -1 -36 41 -72 95 -36 53 -247 363 -470 687 -223 325 -443 645 -489 713 l-83 122 -399 0 -399 0 0 -427z" />
                        </g>
                    </svg>
                    <div class="loader-progress-container">
                        <div class="loader-progress-bar"></div>
                    </div>
                    <span class="loader-progress-text">0%</span>
                </div>
                <div class="card-spark-canvas" id="<?= $escape($idPrefix) ?>spark-<?= $escape($slug) ?>"></div>
                <div class="no-data-placeholder" style="display:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 60" class="no-data-svg">
                        <path d="M10 45 L25 35 L40 40 L55 25 L70 30 L85 20" stroke="currentColor" stroke-width="2" fill="none"
                            stroke-dasharray="4,3" opacity="0.3" />
                        <circle cx="50" cy="35" r="12" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4" />
                        <line x1="45" y1="30" x2="55" y2="40" stroke="currentColor" stroke-width="2" opacity="0.4" />
                        <line x1="55" y1="30" x2="45" y2="40" stroke="currentColor" stroke-width="2" opacity="0.4" />
                    </svg>
                    <span class="no-data-text">Aucune donnée</span>
                </div>
            </div>

            <ul class="card-spark-data" data-spark style="display:none">
                <?php
                $history = $viewData['history_html_data'] ?? [];
                foreach ($history as $historyItem):
                    ?>
                    <li data-time="<?= $escape($historyItem['time_iso'] ?? '') ?>"
                        data-value="<?= $escape($historyItem['value'] ?? '') ?>"
                        data-flag="<?= $escape($historyItem['flag'] ?? '') ?>"></li>
                <?php endforeach; ?>
            </ul>
        </article>

        <div id="<?= $escape($idPrefix) ?>detail-<?= $escape($slug) ?>" style="display:none">
            <div id="<?= $escape($idPrefix) ?>panel-<?= $escape($slug) ?>" class="modal-grid" data-idx="0"
                data-unit="<?= $escape($unit) ?>" data-chart="<?= $escape($viewData['modal_chart_type'] ?? $chartType) ?>"
                data-param-id="<?= $escape($parameterId) ?>" data-chart-allowed="<?= $escape(json_encode($chartAllowed)) ?>"
                data-nmin="<?= $escape($viewData['thresholds']['nmin'] ?? '') ?>"
                data-nmax="<?= $escape($viewData['thresholds']['nmax'] ?? '') ?>"
                data-cmin="<?= $escape($viewData['thresholds']['cmin'] ?? '') ?>"
                data-cmax="<?= $escape($viewData['thresholds']['cmax'] ?? '') ?>"
                data-dmin="<?= $escape($viewData['view_limits']['min'] ?? '') ?>"
                data-dmax="<?= $escape($viewData['view_limits']['max'] ?? '') ?>" data-display="<?= $escape($display) ?>"
                data-value="<?= $escape($value) ?>" data-unit-raw="<?= $escape($unit) ?>">

                <div class="modal-header-row">
                    <div class="column">
                        <h2 class="modal-title">
                            <?= $escape($display) ?>
                        </h2>
                        <p class="modal-description">
                            <?= $escape($description) ?>
                        </p>
                    </div>

                    <div class="modal-header-center" style="display: flex; align-items: center; gap: 10px;">
                        <input type="datetime-local" class="modal-input modal-date-picker"
                            title="Sélectionner une date et heure (fast travel)" max="<?= date('Y-m-d\TH:i') ?>">

                        <a href="#" class="btn-csv-download" title="Télécharger toutes les données (CSV)"
                            style="display: flex; align-items: center; justify-content: center; padding: 8px; border-radius: 6px; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-primary); transition: all 0.2s;"
                            onmouseover="this.style.background='rgba(255,255,255,0.1)'"
                            onmouseout="this.style.background='rgba(255,255,255,0.05)'">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            <span style="font-size: 0.75rem; margin-left: 6px; font-weight: 500;">CSV</span>
                        </a>
                    </div>

                    <div class="modal-chart-types-container"
                        style="display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                        <div class="modal-chart-types" style="display: flex; align-items: center; gap: 6px;">
                            <span class="chart-type-label"
                                style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;">Modale</span>
                            <select class="modal-interval-select"
                                style="font-size: 0.65rem; padding: 1px 4px; border-radius: 4px; border: 1px solid var(--border-color); background: rgba(0,0,0,0.2); color: var(--text-primary); cursor: pointer; outline: none; width: auto; max-width: 80px;">
                                <?php
                                $currentDuration = (string) ($viewData['display_duration'] ?? '0.0333');
                                $options = [
                                    '0.0333' => '2m',
                                    'all' => 'Tout',
                                    '0.0833' => '5m',
                                    '0.25' => '15m',
                                    '0.5' => '30m',
                                    '1' => '1H',
                                    '12' => '12H',
                                    '24' => '24H',
                                    '168' => '7J',
                                    '720' => '30J'
                                ];
                                foreach ($options as $val => $lab): ?>
                                    <option value="<?= $val ?>" <?= $currentDuration === $val ? 'selected' : '' ?>><?= $lab ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="chart-type-group" style="padding: 2px;">

                                <?php foreach ($chartAllowed as $allowedType):
                                    $icon = '';
                                    switch ($allowedType) {
                                        case 'line':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>';
                                            break;
                                        case 'bar':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4">
</line><line x1="6" y1="20" x2="6" y2="16"></line></svg>';
                                            break;
                                        case 'scatter':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/><circle cx="7.5" cy="16.5" r="2.5"/>
<circle cx="16.5" cy="7.5" r="2.5"/></svg>';
                                            break;
                                        case 'pie':
                                        case 'doughnut':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>';
                                            break;
                                        case 'value':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><path d="M12 8v8"></path><path d="M10 10l2-2"></path>
</svg>';
                                            break;
                                        default:
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>';
                                    }
                                    ?>
                                    <button type="button" data-modal-chart-type="<?= $escape($allowedType) ?>"
                                        class="chart-type-btn modal-chart-btn <?= $allowedType === ($viewData['modal_chart_type'] ?? $chartType) ? 'active' : '' ?>"
                                        style="padding: 4px;"
                                        title="Modale : <?= $escape($chartTypes[$allowedType] ?? ucfirst($allowedType)) ?>">
                                        <?= $icon ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="modal-chart-types" style="display: flex; align-items: center; gap: 6px;">
                            <span class="chart-type-label"
                                style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;">Carte</span>
                            <div class="chart-type-group" style="padding: 2px;">

                                <?php foreach ($chartAllowed as $allowedType):
                                    $icon = '';
                                    switch ($allowedType) {
                                        case 'line':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>';
                                            break;
                                        case 'bar':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4">
</line><line x1="6" y1="20" x2="6" y2="16"></line></svg>';
                                            break;
                                        case 'scatter':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/><circle cx="7.5" cy="16.5" r="2.5"/>
<circle cx="16.5" cy="7.5" r="2.5"/></svg>';
                                            break;
                                        case 'pie':
                                        case 'doughnut':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>';
                                            break;
                                        case 'value':
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><path d="M12 8v8"></path><path d="M10 10l2-2">
</path>
</svg>';
                                            break;
                                        default:
                                            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" 
viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>';
                                    }
                                    ?>
                                    <button type="button" data-card-chart-type="<?= $escape($allowedType) ?>"
                                        class="chart-type-btn card-chart-btn <?= $allowedType === $chartType ? 'active' : '' ?>"
                                        style="padding: 4px;"
                                        title="Carte : <?= $escape($chartTypes[$allowedType] ?? ucfirst($allowedType)) ?>">
                                        <?= $icon ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-value-only">
                    <div class="modal-value-only-content">
                        <span class="modal-value-text"></span>
                        <span class="modal-unit-text"></span>
                    </div>
                </div>

                <div class="canvas-wrapper">
                    <div class="modal-chart-loader">
                        <svg class="loader-logo-svg" viewBox="0 0 1024 1024" preserveAspectRatio="xMidYMid meet"
                            style="width: 40px; height: 40px; margin-bottom: 12px;">
                            <g transform="translate(0,1024) scale(0.1,-0.1)" fill="var(--primary-color, #275afe)">
                                <path
                                    d="M1740 5215 l0 -1975 643 0 c614 0 844 7 987 31 198 33 389 99 572 197 166 88 280 174 423 317 287 286 451 631 521 1095 22 143 25 500 5 640 -35 254 -100 479 -191 660 -47 94 -154 261 -208 325 -286 341 -656 565 -1064 644 -180 35 -338 41 -1024 41 l-664 0 0 -1975z m1469 1209 c239 -35 433 -135 598 -307 150 -157 240 -335 288 -566 51 -245 35 -604 -36 -821 -106 -323 -355 -574 -669 -674 -167 -53 -207 -58 -537 -63 l-313 -5 0 1226 0 1226 281 0 c204 0 311 -4 388 -16z" />
                                <path
                                    d="M4840 6763 l0 -428 29 -80 c16 -44 44 -118 61 -165 50 -136 98 -323 122 -474 26 -170 31 -554 10 -726 -31 -245 -96 -494 -171 -649 l-36 -76 -5 -463 -5 -462 413 0 412 0 0 1241 0 1242 26 -39 c15 -21 127 -181 249 -354 122 -173 353 -503 514 -732 l292 -418 537 767 537 767 3 -1237 2 -1237 398 0 397 0 -3 1975 -2 1975 -384 0 -385 0 -136 -202 c-449 -668 -957 -1413 -963 -1415 -4 -1 -36 41 -72 95 -36 53 -247 363 -470 687 -223 325 -443 645 -489 713 l-83 122 -399 0 -399 0 0 -427z" />
                            </g>
                        </svg>
                        <div class="loader-progress-container">
                            <div class="loader-progress-bar"></div>
                        </div>
                        <span class="loader-progress-text">0%</span>
                    </div>
                    <div class="modal-chart chart-<?= $escape($chartType) ?>" tabindex="-1"
                        data-id="<?= $escape($idPrefix) ?>modal-chart-<?= $escape($slug) ?>" style="width: 100%; height: 100%;">
                    </div>
                </div>

                <div class="modal-no-data-placeholder" style="display:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 120" class="no-data-svg-modal">
                        <path d="M20 90 L50 70 L80 80 L110 50 L140 60 L170 40" stroke="currentColor" stroke-width="3"
                            fill="none" stroke-dasharray="8,6" opacity="0.3" />
                        <circle cx="100" cy="65" r="25" fill="none" stroke="currentColor" stroke-width="3" opacity="0.4" />
                        <line x1="90" y1="55" x2="110" y2="75" stroke="currentColor" stroke-width="3" opacity="0.4" />
                        <line x1="110" y1="55" x2="90" y2="75" stroke="currentColor" stroke-width="3" opacity="0.4" />
                    </svg>
                    <span class="no-data-text-modal">Aucune donnée disponible</span>
                </div>

                <ul data-hist style="display:none">
                    <?php foreach ($viewData['history_html_data'] ?? [] as $historyItem): ?>
                        <li data-time="<?= $escape($historyItem['time_iso'] ?? '') ?>"
                            data-value="<?= $escape($historyItem['value'] ?? '') ?>"
                            data-flag="<?= $escape($historyItem['flag'] ?? '') ?>"></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endforeach; ?>

<?php else: ?>
    <article class="card" data-no-data="1"
        style="grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem; text-align: center; gap: 1rem;">
        <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-color);">Aucune donnée</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 400px; line-height: 1.5;">
            Nous vous invitons à paramétrer vos indicateurs dans
            <a href="/?page=customization"
                style="color: var(--primary-color, #275afe); text-decoration: underline; font-weight: 500;">Personnalisation</a>.
        </p>
    </article>
<?php endif; ?>