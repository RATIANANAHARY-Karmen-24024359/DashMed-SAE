<?php

/**
 * app/views/patient/PatientrecordView.php
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
 * Class PatientrecordView
 *
 * View for displaying patient record.
 *
 * Handles display of patient info, history, medical team, and consultations.
 *
 * @package DashMed\Modules\Views\Pages
 * @author DashMed Team
 * @license Proprietary
 */
class PatientrecordView
{
    /** @var array<string, mixed> Patient medical/admin data */
    private array $patientData;

    /** @var array<int, \modules\models\entities\Consultation> Past consultations */
    private array $pastConsultations;

    /** @var array<int, \modules\models\entities\Consultation> Future consultations */
    private array $futureConsultations;

    /** @var array<int, array{
     *   id_user: int,
     *   last_name: string,
     *   first_name: string,
     *   profession_name: string
     * }> Doctors assigned to patient */
    private array $doctors;

    /** @var array{type: string, text: string}|null Flash message */
    private ?array $msg;

    /** @var array<int, array<string, mixed>> Alert thresholds per parameter */
    private array $thresholds;

    /**
     * Constructor.
     *
     * Initializes patient record view.
     *
     * @param array<int, \modules\models\entities\Consultation> $consultationsPassees History
     * @param array<int, \modules\models\entities\Consultation> $consultationsFutures Appointments
     * @param array<string, mixed> $patientData Patient Data
     * @param array<int, array{
     *   id_user: int,
     *   last_name: string,
     *   first_name: string,
     *   profession_name: string
     * }> $doctors Medical Team
     * @param array{type: string, text: string}|null $msg Flash Message
     * @param array<int, array<string, mixed>> $thresholds Alert thresholds
     */
    public function __construct(
        array $consultationsPassees = [],
        array $consultationsFutures = [],
        array $patientData = [],
        array $doctors = [],
        ?array $msg = null,
        array $thresholds = []
    ) {
        $this->pastConsultations = $consultationsPassees;
        $this->futureConsultations = $consultationsFutures;
        $this->patientData = $patientData;
        $this->doctors = $doctors;
        $this->msg = $msg;
        $this->thresholds = $thresholds;
        unset($consultationsPassees, $consultationsFutures);
    }

    /**
     * Renders the patient record page HTML.
     *
     * Includes CSRF token generation if not present.
     *
     * @return void
     */
    public function show(): void
    {
        if (!isset($_SESSION['csrf_patient'])) {
            $_SESSION['csrf_patient'] = bin2hex(random_bytes(32));
        }
        $csrfToken = $_SESSION['csrf_patient'];

        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $layout = new \modules\views\layout\Layout(
            'Dossier Patient',
            [
                'assets/css/pages/patient-record.css',
                'assets/css/components/searchbar/searchbar.css',
                'assets/css/components/alert-thresholds.css',
            ],
            [
                'assets/js/pages/dash.js',
                'assets/js/pages/dossier_patient.js',
            ],
            '',
            true,
            true
        );

        $layout->render(function () use ($h, $csrfToken) {
            ?>

            <main class="container nav-space">
                <div class="dashboard-content-container">

                    <div class="searchbar-with-patient">
                        <span class="patient-name-label">
                             <?= htmlspecialchars(
                                     trim(
                                             (is_scalar($v = $this->patientData['first_name'] ?? '') ? (string)$v : '') . ' ' .
                                             (is_scalar($v = $this->patientData['last_name'] ?? '') ? (string)$v : '')
                                     ),
                                     ENT_QUOTES, 'UTF-8'
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

                    <input type="hidden" id="context-patient-id" value="<?= $h($this->patientData['id_patient'] ?? '') ?>">

                    <?php
                    $msg = $this->msg;
                    $text = $msg['text'] ?? null;
                    $type = $msg['type'] ?? 'info';
                    ?>

                    <?php if (is_string($text) && $text !== ''): ?>
                        <div class="message-box <?= $h($type) ?>">
                            <div class="message-content">
                                <?= $h($text) ?>
                            </div>
                        </div>
                    <?php endif; ?>


                    <div id="real-patient-content">

                        <header class="patient-header-card">
                            <div class="patient-info-group">
                                <div class="patient-avatar">
                                    <img src="assets/img/icons/profile.svg" alt="Avatar Patient" />
                                </div>
                                <div class="patient-identity">
                                    <?php
                                    $firstName = $this->patientData['first_name'] ?? 'Nom';
                                    $lastName = $this->patientData['last_name'] ?? 'Inconnu';

                                    $firstName = is_scalar($firstName) ? (string) $firstName : 'Nom';
                                    $lastName = is_scalar($lastName) ? (string) $lastName : 'Inconnu';

                                    $lastName = strtoupper($lastName);
                                    ?>


                                    <h1>
                                        <?= $h($firstName) ?>
                                        <strong><?= $h($lastName) ?></strong>
                                    </h1>

                                    <div class="patient-meta">
                                        <span class="badge-age"><?= $h($this->patientData['age'] ?? 0) ?> ans</span>
                                        <span class="meta-divider">•</span>
                                        <span>Né(e) le
                                            <?= $h(date(
                                                'd/m/Y',
                                                is_string($this->patientData['birth_date'] ?? null)
                                                ? (strtotime((string) $this->patientData['birth_date']) ?: time())
                                                : time()
                                            )) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="header-actions" style="display: flex; gap: 12px;">
                                <button type="button" class="btn-edit-patient" onclick="openThresholdsModal()"
                                    aria-label="Modifier les seuils d'alerte">
                                    <img src="assets/img/icons/edit.svg" alt="" />
                                    <span>Modifier Seuils</span>
                                </button>
                                <button class="btn-edit-patient" onclick="openEditModal()" aria-label="Modifier le dossier">
                                    <img src="assets/img/icons/edit.svg" alt="" />
                                    <span>Modifier</span>
                                </button>
                            </div>
                        </header>

                        <div class="patient-grid">

                            <div class="grid-column-left">

                                <section class="card-section medical-info-card">
                                    <div class="card-header">
                                        <h2>Informations Médicales</h2>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-block">
                                            <h3>Motif d'admission</h3>
                                            <p class="text-content">
                                                <?= $h(
                                                    $this->patientData['admission_cause'] ?? 'Aucun motif renseigné.'
                                                ) ?>
                                            </p>
                                        </div>
                                        <div class="info-block">
                                            <h3>Antécédents & Allergies</h3>
                                            <div class="text-content history-content">
                                                <?= nl2br($h($this->patientData['medical_history'] ??
                                                    'Aucun antécédent renseigné.')) ?>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="card-section doctors-card">
                                    <div class="card-header">
                                        <h2>Équipe Médicale</h2>
                                    </div>
                                    <div class="doctors-list">
                                        <?php if (!empty($this->doctors)): ?>
                                            <?php foreach ($this->doctors as $doctor): ?>
                                                <div class="doctor-item" id="doctor-<?= $h($doctor['id_user']) ?>">
                                                    <img src="assets/img/icons/profile.svg" alt="Dr. <?= $h($doctor['last_name']) ?>"
                                                        class="doctor-avatar">
                                                    <div class="doctor-details">
                                                        <span class="doctor-name">Dr. <?= $h($doctor['first_name']) ?>
                                                            <?= $h($doctor['last_name']) ?></span>
                                                        <span class="doctor-specialty">
                                                            <?= $h($doctor['profession_name']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="empty-state">
                                                <p>Aucun médecin assigné à ce patient.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </section>

                            </div>


                        </div>

                    </div>
                </div>
            </main>

            <div id="patientEditModal" class="modal-overlay" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-header">
                        <h2>Modifier le dossier</h2>
                        <button class="btn-close" onclick="closeEditModal()">×</button>
                    </div>
                    <form method="POST" action="/?page=dossierpatient">
                        <input type="hidden" name="csrf" value="<?= $h($csrfToken) ?>">
                        <input type="hidden" name="id_patient" value="<?= $h($this->patientData['id_patient'] ?? '') ?>">

                        <div class="modal-body">
                            <div class="form-row">
                                <div class="form-group half">
                                    <label for="first_name">Prénom</label>
                                    <input type="text" id="first_name" name="first_name" required value="<?= $h($this->patientData['first_name'] ??
                                        '') ?>" placeholder="Jean">
                                </div>
                                <div class="form-group half">
                                    <label for="last_name">Nom</label>
                                    <input type="text" id="last_name" name="last_name" required value="<?= $h($this->patientData['last_name'] ??
                                        '') ?>" placeholder="Dupont">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="birth_date">Date de naissance</label>
                                <input type="date" id="birth_date" name="birth_date"
                                    value="<?= $h($this->patientData['birth_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                                <span class="form-hint">L'âge sera recalculé automatiquement.</span>
                            </div>

                            <div class="form-group">
                                <label for="admission_cause">Motif d'admission</label>
                                <textarea id="admission_cause" name="admission_cause" rows="2" required
                                    placeholder="Motif de l'hospitalisation..."><?= $h($this->patientData['admission_cause'] ?? '') ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="medical_history">Antécédents médicaux</label>
                                <textarea id="medical_history" name="medical_history" rows="3" required
                                    placeholder="Antécédents, allergies, traitements chroniques..."><?= $h($this->patientData['medical_history'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn-secondary" onclick="closeEditModal()">Annuler</button>
                            <button type="submit" class="btn-primary">Enregistrer les modifications</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Thresholds Edit Modal -->
            <div id="thresholdsEditModal" class="modal-overlay" aria-hidden="true">
                <div class="modal-dialog"
                    style="width: 700px; max-width: 95%; max-height: 90vh; display: flex; flex-direction: column;">
                    <div class="modal-header">
                        <h2>Modifier les Seuils d'Alerte</h2>
                        <button class="btn-close" type="button" onclick="closeThresholdsModal()">×</button>
                    </div>
                    <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px;">
                        <p class="form-hint" style="margin-bottom: 20px;">Ajustez les seuils spécifiques pour ce patient. Les
                            valeurs par défaut seront appliquées pour les champs laissés vides.</p>

                        <?php if (!empty($this->thresholds)): ?>
                            <?php
                            $groupedModal = [];
                            foreach ($this->thresholds as $t) {
                                $catVal = $t['category'] ?? 'Autre';
                                $cat = is_scalar($catVal) ? (string) $catVal : 'Autre';
                                $groupedModal[$cat][] = $t;
                            }
                            ?>
                            <div class="thresholds-container">
                                <?php foreach ($groupedModal as $category => $params): ?>
                                    <div class="threshold-category" style="margin-bottom: 12px;">
                                        <button class="category-toggle" type="button" onclick="toggleCategory(this)" aria-expanded="false">
                                            <span class="category-name"><?= $h($category) ?></span>
                                            <span class="category-count"><?= count($params) ?> paramètre(s)</span>
                                            <svg class="toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2">
                                                <polyline points="6 9 12 15 18 9"></polyline>
                                            </svg>
                                        </button>
                                        <div class="category-params" style="display: none;">
                                            <?php foreach ($params as $param):
                                                $pid = $h($param['parameter_id']);
                                                $isCustom = $param['custom_normal_min'] !== null
                                                    || $param['custom_normal_max'] !== null
                                                    || $param['custom_critical_min'] !== null
                                                    || $param['custom_critical_max'] !== null;
                                                ?>
                                                <div class="threshold-param <?= $isCustom ? 'has-custom' : '' ?>">
                                                    <div class="param-header" onclick="toggleParamEdit('modal-<?= $pid ?>')">
                                                        <div class="param-info">
                                                            <span class="param-name">
                                                                <?= $h($param['display_name']) ?>
                                                            </span>
                                                            <span class="param-unit">
                                                                (<?= $h($param['unit']) ?>)
                                                            </span>
                                                            <?php if ($isCustom): ?>
                                                                <span class="badge-custom">Personnalisé</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="param-values-preview">
                                                            <svg class="edit-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                                                stroke="currentColor" stroke-width="2">
                                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                            </svg>
                                                        </div>
                                                    </div>

                                                    <div class="param-edit-form" id="edit-modal-<?= $pid ?>" style="display: none;">
                                                        <form method="POST" action="/?page=dossierpatient" class="threshold-form">
                                                            <input type="hidden" name="action" value="update_thresholds">
                                                            <input type="hidden" name="csrf" value="<?= $h($csrfToken) ?>">
                                                            <input type="hidden" name="parameter_id" value="<?= $pid ?>">
                                                            <input type="hidden" name="id_patient"
                                                                value="<?= $h($this->patientData['id_patient'] ?? '') ?>">

                                                            <div class="threshold-grid">
                                                                <div class="threshold-group normal-group">
                                                                    <h4>Seuils normaux</h4>
                                                                    <div class="threshold-inputs">
                                                                        <div class="input-pair">
                                                                            <label>Min</label>
                                                                            <input type="number" step="0.01" name="normal_min"
                                                                                value="<?= $h($param['effective_normal_min'] ?? '') ?>"
                                                                                placeholder="<?= $h($param['default_normal_min'] ?? '—') ?>">
                                                                        </div>
                                                                        <div class="input-pair">
                                                                            <label>Max</label>
                                                                            <input type="number" step="0.01" name="normal_max"
                                                                                value="<?= $h($param['effective_normal_max'] ?? '') ?>"
                                                                                placeholder="<?= $h($param['default_normal_max'] ?? '—') ?>">
                                                                        </div>
                                                                    </div>
                                                                    <span class="defaults-hint">
                                                                        Défaut :
                                                                        <?= (isset($param['default_normal_min']) && is_numeric($param['default_normal_min'])) ? $h(number_format((float) $param['default_normal_min'], 1)) : '—' ?>
                                                                        →
                                                                        <?= (isset($param['default_normal_max']) && is_numeric($param['default_normal_max'])) ? $h(number_format((float) $param['default_normal_max'], 1)) : '—' ?>
                                                                    </span>
                                                                </div>
                                                                <div class="threshold-group critical-group">
                                                                    <h4>Seuils critiques</h4>
                                                                    <div class="threshold-inputs">
                                                                        <div class="input-pair">
                                                                            <label>Min</label>
                                                                            <input type="number" step="0.01" name="critical_min"
                                                                                value="<?= $h($param['effective_critical_min'] ?? '') ?>"
                                                                                placeholder="<?= $h($param['default_critical_min'] ?? '—') ?>">
                                                                        </div>
                                                                        <div class="input-pair">
                                                                            <label>Max</label>
                                                                            <input type="number" step="0.01" name="critical_max"
                                                                                value="<?= $h($param['effective_critical_max'] ?? '') ?>"
                                                                                placeholder="<?= $h($param['default_critical_max'] ?? '—') ?>">
                                                                        </div>
                                                                    </div>
                                                                    <span class="defaults-hint">
                                                                        Défaut :
                                                                        <?= (isset($param['default_critical_min']) && is_numeric($param['default_critical_min'])) ? $h(number_format((float) $param['default_critical_min'], 1)) : '—' ?>
                                                                        →
                                                                        <?= (isset($param['default_critical_max']) && is_numeric($param['default_critical_max'])) ? $h(number_format((float) $param['default_critical_max'], 1)) : '—' ?>
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div class="threshold-actions">
                                                                <?php if ($isCustom): ?>
                                                                    <button type="submit" name="threshold_action" value="reset"
                                                                        class="btn-reset-threshold"
                                                                        onclick="return confirm('Réinitialiser aux valeurs par défaut ?')">
                                                                        Réinitialiser
                                                                    </button>
                                                                <?php endif; ?>
                                                                <button type="submit" name="threshold_action" value="save"
                                                                    class="btn-save-threshold">
                                                                    Enregistrer
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                function openThresholdsModal() {
                    const el = document.getElementById('thresholdsEditModal');
                    if (el) el.classList.add('active');
                }

                function closeThresholdsModal() {
                    const el = document.getElementById('thresholdsEditModal');
                    if (el) el.classList.remove('active');
                }

                document.getElementById('thresholdsEditModal')?.addEventListener('click', function (e) {
                    if (e.target === this) {
                        closeThresholdsModal();
                    }
                });

                function toggleCategory(btn) {
                    const params = btn.nextElementSibling;
                    const expanded = btn.getAttribute('aria-expanded') === 'true';
                    btn.setAttribute('aria-expanded', !expanded);
                    params.style.display = expanded ? 'none' : 'block';
                    btn.classList.toggle('expanded', !expanded);
                }

                function toggleParamEdit(paramId) {
                    const el = document.getElementById('edit-' + paramId);
                    if (el) {
                        el.style.display = el.style.display === 'none' ? 'block' : 'none';
                    }
                }
            </script>
            <?php
        });
    }
}
