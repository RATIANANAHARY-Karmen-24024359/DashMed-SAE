<?php

namespace modules\views\patient;

/**
 * Class ExplorerView
 *
 * View for the Data Explorer and CSV Viewer.
 *
 * Provides a specialized environment for analyzing large datasets,
 * uploading CSV files, and visual navigation.
 */
class ExplorerView
{
    /** @var array<string, mixed> Patient info */
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
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <title>DashMed - Explorateur de données</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link rel="stylesheet" href="assets/css/themes/light.css">
            <link rel="stylesheet" href="assets/css/themes/dark.css">
            <link rel="stylesheet" href="assets/css/base/style.css">
            <link rel="stylesheet" href="assets/css/layout/sidebar.css">
            <link rel="stylesheet" href="assets/css/components/searchbar/searchbar.css">
            <link rel="stylesheet" href="assets/css/pages/dashboard.css">
            <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
            <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
            <style>
                .explorer-container { padding: 5px 30px 30px 30px; display: flex; flex-direction: column; height: 100vh; overflow: hidden; gap: 0 !important; }
                .explorer-container .searchbar { margin-bottom: 5px; }
                .explorer-main-content { margin-top: 0; flex: 1; overflow-y: auto; padding-right: 10px; }
                .explorer-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
                .explorer-title h1 { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0; }
                .explorer-grid { display: grid; grid-template-columns: 300px 1fr; gap: 20px; align-items: start; }
                .explorer-card { background: var(--bg-surface); border-radius: 12px; border: 1px solid var(--border-subtle); padding: 15px; box-shadow: var(--shadow-sm); }
                .drop-zone { border: 2px dashed var(--border-color); border-radius: 8px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.2s; }
                .drop-zone:hover { background: rgba(39, 90, 254, 0.05); border-color: var(--primary-color); }
                .drop-zone p { font-size: 0.85rem; color: var(--text-secondary); margin-top: 10px; }
                .chart-viewer { height: 78vh; width: 100%; position: relative; background: var(--bg-surface); border-radius: 12px; border: 1px solid var(--border-subtle); overflow: hidden; }
                #explorer-chart { width: 100%; height: 100%; }
                .data-controls { margin-top: 15px; display: flex; flex-direction: column; gap: 12px; }
                .control-group { display: flex; flex-direction: column; gap: 8px; }
                .control-group label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }
                select, input[type="file"] { width: 100%; padding: 8px; border-radius: 6px; background: var(--bg-surface-hover); border: 1px solid var(--border-color); color: var(--text-primary); cursor: pointer; font-size: 0.9rem; }
                .btn-primary { background: var(--bg-primary); color: white; border: none; padding: 10px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 0.9rem; }
                .btn-primary:hover { background: var(--bg-primary-hover); transform: translateY(-1px); }
                #stats-panel { margin-top: 10px; font-size: 0.85rem; }
                .stat-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border-subtle); }
                .stat-row:last-child { border-bottom: none; }
                .stat-value { font-weight: 700; color: var(--primary-color); }
                
                /* Layout refinements for scroll */
                .container { max-width: 1800px; margin: 0 auto; }
            </style>
        </head>
        <body class="nav-space">

            <?php include dirname(__DIR__, 2) . '/views/partials/_sidebar.php'; ?>

            <main class="explorer-container container">
                
                <?php include dirname(__DIR__, 2) . '/views/partials/_searchbar.php'; ?>

                <!-- Hidden context -->
                <input type="hidden" id="context-patient-id" value="<?= htmlspecialchars((string)$this->patientData['id_patient']) ?>">

                <div class="explorer-main-content">
                    <div class="explorer-header">
                        <div class="explorer-title">
                            <h1>Explorateur & Viewer CSV</h1>
                            <p style="color: var(--text-secondary); font-size: 0.9rem;">Analyse bidirectionnelle et visualisation haute performance</p>
                        </div>
                    </div>

                <div class="explorer-grid">
                    <div class="explorer-card">
                        <h2 style="font-size: 1rem; margin-top: 0;">Source de données</h2>
                        <div class="data-controls">
                            <div class="control-group">
                                <label>Sélectionner une mesure patient</label>
                                <select id="param-selector">
                                    <option value="">-- Charger depuis le patient --</option>
                                </select>
                            </div>
                            
                            <div style="text-align: center; margin: 10px 0; color: var(--text-secondary); font-size: 0.75rem;">— OU —</div>

                            <div class="control-group">
                                <label>Charger un fichier CSV local</label>
                                <div id="csv-drop-zone" class="drop-zone">
                                    <img src="assets/img/icons/folder.svg" style="width: 24px; opacity: 0.5;" alt="">
                                    <p id="drop-text">Glisser un CSV ou cliquer ici</p>
                                    <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                                </div>
                            </div>

                            <div class="control-group">
                                <label>Angle d'analyse</label>
                                <select id="analysis-angle">
                                    <option value="raw">Données Brutes (Temps Réel)</option>
                                    <option value="ma-5">Moyenne Mobile (5 pts)</option>
                                    <option value="ma-20">Moyenne Mobile (20 pts)</option>
                                    <option value="peaks">Détection de Pics</option>
                                </select>
                            </div>

                            <div id="stats-panel" class="explorer-card" style="padding: 12px; background: rgba(0,0,0,0.02); margin-top: 10px;">
                                <h3 style="font-size: 0.85rem; margin-top: 0; margin-bottom: 10px;">Statistiques du segment</h3>
                                <div class="stat-row"><span>Points:</span> <span class="stat-value" id="stat-count">-</span></div>
                                <div class="stat-row"><span>Moyenne:</span> <span class="stat-value" id="stat-avg">-</span></div>
                                <div class="stat-row"><span>Maximum:</span> <span class="stat-value" id="stat-max">-</span></div>
                                <div class="stat-row"><span>Minimum:</span> <span class="stat-value" id="stat-min">-</span></div>
                            </div>

                            <button id="export-segment-btn" class="btn-primary" style="margin-top: 15px;">
                                <img src="assets/img/icons/download.svg" style="width: 16px; filter: brightness(0) invert(1);" alt="">
                                Exporter le segment
                            </button>
                        </div>
                    </div>

                    <div class="chart-viewer">
                        <div id="explorer-chart"></div>
                    </div>
                </div>
            </main>

            <script src="assets/js/pages/explorer.js?v=<?= time() ?>"></script>
        </body>
        </html>
        <?php
    }
}
