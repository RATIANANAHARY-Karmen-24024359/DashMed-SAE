<?php

/**
 * app/views/patient/ExplorerView.php
 *
 * View file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

namespace modules\views\patient;

/**
 * Class ExplorerView
 *
 * High-performance UI for multidimensional medical data exploration.
 * Integrates interactive ECharts, CSV processing, and clinical analytics.
 *
 * Part of the DashMed Visualization Layer.
 *
 * @package DashMed\Modules\Views\Patient
 * @author  DashMed Team
 */
class ExplorerView
{
    /**
     * @var array<string, mixed> Patient info
     */
    private array $patientData;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $patientData Patient info
     */
    public function __construct(array $patientData = [])
    {
        $this->patientData = $patientData;
    }

    /**
     * Renders the Explorer HTML.
     *
     * @return void
     */
    public function show(): void
    {
        $inlineStyles = '
            .explorer-container {
                padding: 5px 30px 30px 30px;
                display: flex;
                flex-direction: column;
                height: calc(100vh - 20px);
                overflow: hidden;
                gap: 0 !important;
                background: radial-gradient(circle at 50% -20%, rgba(39, 90, 254, 0.08) 0%, transparent 50%);
                margin-left: calc(var(--sidebar-w) + 16px);
            }

            .explorer-container .searchbar { margin-bottom: 20px; }
            .explorer-main-content { margin-top: 0; flex: 1; overflow-y: auto; padding-right: 10px; }

            .explorer-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 25px;
                border-bottom: 1px solid var(--explorer-glass-stroke);
                padding-bottom: 15px;
            }

            .explorer-title h1 {
                font-size: 1.8rem;
                font-weight: 700;
                background: var(--explorer-header-gradient);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                margin: 0;
            }

            .explorer-grid { display: grid; grid-template-columns: 340px 1fr; gap: 25px; align-items: start; }

            .explorer-card {
                background: var(--explorer-glass);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-radius: 16px;
                border: 1px solid var(--explorer-glass-stroke);
                padding: 24px;
                box-shadow: var(--shadow-md);
                transition: all 0.3s ease;
            }

            .explorer-card:hover {
                border-color: var(--explorer-accent);
                box-shadow: var(--shadow-lg);
                transform: translateY(-2px);
            }

            .drop-zone {
                border: 1.5px dashed var(--explorer-glass-stroke);
                border-radius: 12px;
                padding: 30px 15px;
                text-align: center;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                background: var(--explorer-input-bg);
                width: 100%;
                box-sizing: border-box;
                overflow: hidden;
            }

            .drop-zone:hover {
                background: rgba(39, 90, 254, 0.08);
                border-color: var(--explorer-accent);
                transform: scale(1.02);
            }

            .drop-zone p {
                font-size: 0.85rem;
                color: var(--text-secondary);
                margin-top: 15px;
                overflow-wrap: anywhere;
                word-break: break-all;
                line-height: 1.4;
                padding: 0 5px;
            }

            .chart-viewer {
                height: calc(100vh - 200px);
                width: 100%;
                position: relative;
                background: var(--explorer-card-bg);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 20px;
                border: 1px solid var(--explorer-glass-stroke);
                overflow: hidden;
                box-shadow: var(--shadow-lg);
                transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            }

            .chart-viewer.full-screen {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 99999;
                height: 100vh;
                width: 100vw;
                border-radius: 0;
                border: none;
            }

            body.chart-full-screen {
                overflow: hidden !important;
            }

            #explorer-chart { width: 100%; height: 100%; }

            .data-controls { display: flex; flex-direction: column; gap: 20px; }
            .control-group { display: flex; flex-direction: column; gap: 10px; }
            .control-group label {
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 700;
                color: var(--text-secondary);
                opacity: 0.8;
            }
            .explorer-container select {
                width: 100%;
                padding: 12px 15px;
                border-radius: 10px;
                background: var(--bg-surface);
                border: 1px solid var(--explorer-glass-stroke);
                color: var(--text-main);
                cursor: pointer;
                font-size: 0.95rem;
                transition: all 0.2s;
                outline: none;
                appearance: none;
                -webkit-appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%2364748b\' stroke-width=\'2\' stroke-linecap=\'round\' stroke-linejoin=\'round\'%3E%3Cpolyline points=\'6 9 12 15 18 9\'%3E%3C/polyline%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 12px center;
            }

            .explorer-container select:hover, .explorer-container select:focus {
                border-color: var(--explorer-accent);
                background-color: var(--bg-surface-hover);
            }

            .explorer-container select option {
                background-color: var(--bg-surface);
                color: var(--text-main);
                padding: 10px;
            }

            .explorer-container .btn-primary {
                background: linear-gradient(135deg, #275afe 0%, #1a3fb5 100%);
                color: white;
                border: none;
                padding: 14px;
                border-radius: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                font-size: 0.95rem;
                box-shadow: 0 4px 15px var(--explorer-accent-glow);
                border: 1px solid var(--explorer-accent);
            }

            .explorer-container .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px var(--explorer-accent-glow);
                filter: brightness(1.1);
            }

            .explorer-container .btn-primary:active { transform: translateY(0); }

            .stats-panel-inner {
                background: var(--explorer-input-bg);
                border-radius: 12px;
                padding: 18px;
                border: 1px solid var(--explorer-glass-stroke);
            }

            .chart-overlay-controls {
                position: absolute;
                top: 20px;
                right: 20px;
                display: flex;
                gap: 10px;
                z-index: 10;
            }

            .chart-btn {
                background: var(--explorer-glass);
                border: 1px solid var(--explorer-accent);
                color: var(--text-main);
                padding: 8px 12px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 0.8rem;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 6px;
                backdrop-filter: blur(8px);
                transition: all 0.2s;
            }

            .chart-btn:hover {
                background: rgba(39, 90, 254, 0.2);
                border-color: var(--explorer-accent);
                transform: translateY(-1px);
            }

            .chart-btn svg {
                opacity: 0.8;
            }

            .stat-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid var(--explorer-stat-row-border);
            }

            .stat-row:last-child { border-bottom: none; }
            .stat-label { color: var(--text-secondary); font-size: 0.85rem; }
            .stat-value { font-weight: 600; color: var(--text-primary); font-family: "Monaco", "Consolas", monospace; }

            .explorer-container ::-webkit-scrollbar { width: 6px; }
            .explorer-container ::-webkit-scrollbar-track { background: transparent; }
            .explorer-container ::-webkit-scrollbar-thumb { background: var(--explorer-glass-stroke); border-radius: 10px; }
            .explorer-container ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }
        ';

        $layout = new \modules\views\layout\Layout(
            'Explorateur de données',
            [
                'assets/css/components/searchbar/searchbar.css',
            ],
            [
                'https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js',
                'https://cdn.jsdelivr.net/npm/echarts@5.5.0/theme/vintage.js',
                'https://cdn.jsdelivr.net/npm/echarts@5.5.0/theme/macarons.js',
                'https://cdn.jsdelivr.net/npm/echarts@5.5.0/theme/shine.js',
                'assets/js/pages/explorer.js?v=' . time(),
            ],
            $inlineStyles,
            true,
            true
        );

        $patientData = $this->patientData;

        $layout->render(
            function () use ($patientData) {
                ?>
            <main class="explorer-container">

                <?php include dirname(__DIR__) . '/partials/_searchbar.php'; ?>

                <?php
                $ctxPid = $patientData['id_patient'] ?? '';
                $ctxFirst = $patientData['first_name'] ?? '';
                $ctxLast = $patientData['last_name'] ?? '';
                $ctxName = (is_scalar($ctxFirst) ? (string) $ctxFirst : '') . ' ' . (is_scalar($ctxLast) ? (string) $ctxLast : '');
                ?>
                <input type="hidden" id="context-patient-id" value="<?php echo htmlspecialchars(is_scalar($ctxPid) ? (string) $ctxPid : '') ?>">
                <input type="hidden" id="context-patient-name" value="<?php echo htmlspecialchars(trim($ctxName)) ?>">

                <div class="explorer-main-content">
                    <div class="explorer-header">
                        <div class="explorer-title">
                            <h1>Explorateur & Viewer CSV</h1>
                            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-top: 5px;">Analyse bidirectionnelle et visualisation haute performance</p>
                        </div>
                        <div id="explorer-context-display" style="text-align: right; background: var(--explorer-glass); padding: 10px 18px; border-radius: 12px; border: 1px solid var(--explorer-glass-stroke); backdrop-filter: blur(8px); display: none; max-width: 450px; overflow-wrap: anywhere; word-break: break-all;">
                            <span style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: var(--text-secondary); display: block; margin-bottom: 6px; opacity: 0.8;">Analyse en cours</span>
                            <div id="current-context-name" style="font-weight: 600; color: var(--explorer-accent); font-size: 0.9rem; line-height: 1.5;">-</div>
                        </div>
                    </div>

                    <div class="explorer-grid">
                        <div class="explorer-card">
                            <h2 style="font-size: 1.1rem; font-weight: 600; margin-top: 0; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--explorer-accent); box-shadow: 0 0 10px var(--explorer-accent);"></div>
                                Configuration
                            </h2>

                            <div class="data-controls">
                                <div class="control-group">
                                    <label>Mesure Patient</label>
                                    <select id="param-selector">
                                        <option value="">-- Charger depuis le patient --</option>
                                    </select>
                                </div>

                                <div style="display: flex; align-items: center; gap: 15px; margin: 5px 0;">
                                    <div style="flex: 1; height: 1px; background: var(--explorer-glass-stroke);"></div>
                                    <div style="color: var(--text-secondary/40); font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">OU</div>
                                    <div style="flex: 1; height: 1px; background: var(--explorer-glass-stroke);"></div>
                                </div>

                                <div class="control-group">
                                    <label>Import Manuel</label>
                                    <div id="csv-drop-zone" class="drop-zone">
                                        <div style="font-size: 2rem; margin-bottom: 5px; opacity: 0.5;">📁</div>
                                        <p id="drop-text">Glisser-déposer un CSV</p>
                                        <div style="font-size: 0.7rem; opacity: 0.5; margin-top: 5px;">(Format: timestamp, valeur)</div>
                                        <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                                    </div>
                                </div>

                                <div class="control-group">
                                    <label>Filtre d'Analyse</label>
                                    <select id="analysis-angle">
                                        <option value="raw">Données Brutes (Temps Réel)</option>
                                        <option value="ma-5">Moyenne Mobile (Lissage LTI)</option>
                                        <option value="ma-20">Moyenne Mobile (Tendance Longue)</option>
                                        <option value="median-5">Filtre Médian (Anti-Bruit)</option>
                                        <option value="z-score">Standardisation (Z-Score)</option>
                                        <option value="derivative">Vitesse de Variation (Dérivée)</option>
                                        <option value="savgol">Lissage Savitzky-Golay (Physiologique)</option>
                                        <option value="peaks">Algorithme de Détection de Pics</option>
                                    </select>
                                </div>

                                <div style="display: flex; align-items: center; gap: 15px; margin: 5px 0;">
                                    <div style="flex: 1; height: 1px; background: var(--explorer-glass-stroke);"></div>
                                    <div style="color: var(--text-secondary/40); font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">AESTHETICS</div>
                                    <div style="flex: 1; height: 1px; background: var(--explorer-glass-stroke);"></div>
                                </div>

                                <div class="control-group">
                                    <label>Type de Graphique</label>
                                    <select id="chart-type-selector">
                                        <optgroup label="Lignes">
                                            <option value="line">Ligne Standard</option>
                                            <option value="smooth-line">Ligne Lissée</option>
                                            <option value="step-line">Ligne en Escalier</option>
                                        </optgroup>
                                        <optgroup label="Surfaces">
                                            <option value="area">Aire</option>
                                            <option value="smooth-area">Aire Lissée</option>
                                        </optgroup>
                                        <optgroup label="Distribution & Statistiques">
                                            <option value="histogram">Histogramme (Fréquence)</option>
                                            <option value="boxplot">Boîte à moustaches (Quartiles)</option>
                                            <option value="candlestick">Bougies Médicales (O-H-L-C)</option>
                                            <option value="density">Courbe de Densité</option>
                                        </optgroup>
                                        <optgroup label="Points & Alertes">
                                            <option value="bar">Histogramme (Barres temporelles)</option>
                                            <option value="scatter">Nuage de Points</option>
                                            <option value="effectScatter">Points Pulsantes (Alertes)</option>
                                            <option value="heatmap">Heatmap Temporelle</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div class="control-group" id="candlestick-granularity-group" style="display: none;">
                                    <label>Granularité des Bougies</label>
                                    <select id="candlestick-granularity">
                                        <option value="auto">Auto (Points distribués)</option>
                                        <option value="1">Toutes les minutes</option>
                                        <option value="5">Toutes les 5 minutes</option>
                                        <option value="15">Toutes les 15 minutes</option>
                                        <option value="60">Toutes les heures</option>
                                        <option value="1440">Tous les jours</option>
                                    </select>
                                </div>

                                <div class="control-group">
                                    <label>Thème Visuel</label>
                                    <select id="theme-selector">
                                        <option value="dark">Dark Modern (Par défaut)</option>
                                        <option value="light">Light Crystal</option>
                                        <option value="vintage">Vintage Medical</option>
                                        <option value="macarons">Soft Macarons</option>
                                        <option value="shine">Vibrant Shine</option>
                                    </select>
                                </div>

                                <div id="stats-panel" style="margin-top: 10px;">
                                    <div class="stats-panel-inner">
                                        <h3 style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: var(--text-secondary); margin-top: 0; margin-bottom: 15px; opacity: 0.8;">Métriques du Segment</h3>
                                        <div class="stat-row"><span class="stat-label">Échantillons</span> <span class="stat-value" id="stat-count">-</span></div>
                                        <div class="stat-row"><span class="stat-label">Moyenne</span> <span class="stat-value" id="stat-avg" style="color: #275afe;">-</span></div>
                                        <div class="stat-row"><span class="stat-label">Maximum</span> <span class="stat-value" id="stat-max" style="color: #ef4444;">-</span></div>
                                        <div class="stat-row"><span class="stat-label">Minimum</span> <span class="stat-value" id="stat-min" style="color: #34d399;">-</span></div>
                                    </div>
                                </div>

                                <button id="export-segment-btn" class="btn-primary" style="margin-top: 10px;">
                                    <span>Exporter les données</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                </button>
                            </div>
                        </div>

                        <div class="chart-viewer" id="chart-viewer-container">
                            <div class="chart-overlay-controls">
                                <button id="reset-zoom-btn" class="chart-btn" title="Réinitialiser le zoom">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>
                                    Reset
                                </button>
                                <button id="full-screen-btn" class="chart-btn" title="Plein écran">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 3 6 6"></path><path d="m9 21-6-6"></path><path d="M21 3v6h-6"></path><path d="M3 21v-6h6"></path><path d="m21 3-9 9"></path><path d="m3 21 9-9"></path></svg>
                                    Plein écran
                                </button>
                            </div>
                            <div id="explorer-chart"></div>
                        </div>
                    </div>
                </div>
            </main>
                <?php
            }
        );
    }
}
