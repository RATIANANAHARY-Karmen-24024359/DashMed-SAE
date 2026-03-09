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
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/theme/vintage.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/theme/macarons.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/theme/shine.js"></script>
            <style>
                :root {
                    --explorer-glass: rgba(255, 255, 255, 0.05);
                    --explorer-glass-stroke: rgba(255, 255, 255, 0.1);
                    --explorer-accent: #275afe;
                    --explorer-accent-glow: rgba(39, 90, 254, 0.3);
                }

                body { font-family: 'Outfit', sans-serif; }

                .explorer-container { 
                    padding: 5px 30px 30px 30px; 
                    display: flex; 
                    flex-direction: column; 
                    height: 100vh; 
                    overflow: hidden; 
                    gap: 0 !important; 
                    background: radial-gradient(circle at 50% -20%, rgba(39, 90, 254, 0.08) 0%, transparent 50%);
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
                    background: linear-gradient(135deg, var(--text-primary) 0%, rgba(255,255,255,0.7) 100%);
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
                    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
                    transition: all 0.3s ease;
                }
                
                .explorer-card:hover {
                    border-color: rgba(39, 90, 254, 0.3);
                    box-shadow: 0 8px 40px rgba(0,0,0,0.4), 0 0 20px rgba(39, 90, 254, 0.05);
                }

                .drop-zone { 
                    border: 1.5px dashed var(--explorer-glass-stroke); 
                    border-radius: 12px; 
                    padding: 40px 20px; 
                    text-align: center; 
                    cursor: pointer; 
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                    background: rgba(255, 255, 255, 0.02);
                }
                
                .drop-zone:hover { 
                    background: rgba(39, 90, 254, 0.08); 
                    border-color: var(--explorer-accent);
                    transform: scale(1.02);
                }
                
                .drop-zone p { font-size: 0.9rem; color: var(--text-secondary); margin-top: 15px; }
                
                .chart-viewer { 
                    height: calc(100vh - 200px); 
                    width: 100%; 
                    position: relative; 
                    background: var(--explorer-glass);
                    backdrop-filter: blur(12px);
                    -webkit-backdrop-filter: blur(12px);
                    border-radius: 16px; 
                    border: 1px solid var(--explorer-glass-stroke); 
                    overflow: hidden; 
                    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
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
                
                select { 
                    width: 100%; 
                    padding: 12px 15px; 
                    border-radius: 10px; 
                    background: rgba(0, 0, 0, 0.2); 
                    border: 1px solid var(--explorer-glass-stroke); 
                    color: var(--text-primary); 
                    cursor: pointer; 
                    font-size: 0.95rem; 
                    transition: all 0.2s;
                    outline: none;
                }
                
                select:hover, select:focus {
                    border-color: var(--explorer-accent);
                    background: rgba(39, 90, 254, 0.05);
                }
                
                .btn-primary { 
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
                }
                
                .btn-primary:hover { 
                    transform: translateY(-2px); 
                    box-shadow: 0 6px 20px var(--explorer-accent-glow);
                    filter: brightness(1.1);
                }
                
                .btn-primary:active { transform: translateY(0); }

                .stats-panel-inner {
                    background: rgba(0,0,0,0.15);
                    border-radius: 12px;
                    padding: 18px;
                    border: 1px solid var(--explorer-glass-stroke);
                }
                
                .stat-row { 
                    display: flex; 
                    justify-content: space-between; 
                    padding: 10px 0; 
                    border-bottom: 1px solid rgba(255,255,255,0.05); 
                }
                
                .stat-row:last-child { border-bottom: none; }
                .stat-label { color: var(--text-secondary); font-size: 0.85rem; }
                .stat-value { font-weight: 600; color: var(--text-primary); font-family: 'Monaco', 'Consolas', monospace; }
                
                .container { max-width: 1920px; margin: 0 auto; }
                
                ::-webkit-scrollbar { width: 6px; }
                ::-webkit-scrollbar-track { background: transparent; }
                ::-webkit-scrollbar-thumb { background: var(--explorer-glass-stroke); border-radius: 10px; }
                ::-webkit-scrollbar-thumb:hover { background: var(--text-secondary); }
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
                            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-top: 5px;">Analyse bidirectionnelle et visualisation haute performance</p>
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
                                        <optgroup label="Distribution">
                                            <option value="bar">Histogramme (Barres)</option>
                                            <option value="scatter">Nuage de Points</option>
                                            <option value="effectScatter">Points Pulsantes (Alertes)</option>
                                            <option value="heatmap">Heatmap Temporelle</option>
                                        </optgroup>
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

                        <div class="chart-viewer">
                            <div id="explorer-chart"></div>
                        </div>
                    </div>
                </div>
            </main>

            <script src="assets/js/pages/explorer.js?v=<?= time() ?>"></script>
        </body>
        </html>
        <?php
    }
}
?>
