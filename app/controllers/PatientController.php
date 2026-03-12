<?php

/**
 * app/controllers/PatientController.php
 *
 * Controller file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

declare(strict_types=1);

namespace modules\controllers;

use DateTime;
use assets\includes\Database;
use modules\models\repositories\ConsultationRepository;
use modules\models\repositories\CustomGroupRepository;
use modules\models\repositories\PatientRepository;
use modules\models\repositories\UserRepository;
use modules\models\repositories\AlertThresholdRepository;
use modules\models\entities\Consultation;
use modules\models\repositories\MonitorRepository;
use modules\models\repositories\MonitorPreferenceRepository;
use modules\services\MonitoringService;
use modules\services\PatientContextService;
use modules\views\patient\DashboardView;
use modules\views\patient\MedicalprocedureView;
use modules\views\patient\PatientrecordView;
use modules\views\patient\MonitoringView;
use modules\views\patient\ExplorerView;
use PDO;

/**
 * Class PatientController
 *
 * This controller serves as the central hub for all patient-related activities within the DashMed application.
 * It manages dashboard views, real-time monitoring, medical records, consultations, and analytics.
 *
 * Design Pattern: Standard MVC Controller.
 * Refactored from: DashboardController, MonitoringController, PatientrecordController, and MedicalprocedureController.
 *
 * @package DashMed\Modules\Controllers
 * @author DashMed Team
 * @version 2.1.0
 * @license Proprietary
 */
class PatientController
{
    /**
     * @var PDO The active database connection instance for multi-repository management.
     */
    private PDO $pdo;

    /**
     * @var PatientRepository Data access layer for patient biographical and structural information.
     */
    private PatientRepository $patientRepo;

    /**
     * @var ConsultationRepository Management of medical appointments and historical records.
     */
    private ConsultationRepository $consultationRepo;

    /**
     * @var UserRepository Identity management for clinical staff and administrative access.
     */
    private UserRepository $userRepo;

    /**
     * @var MonitorRepository Engine for high-frequency time-series medical data retrieval.
     */
    private MonitorRepository $monitorModel;

    /**
     * @var MonitorPreferenceRepository Persistent user-specific UI layout and chart configurations.
     */
    private MonitorPreferenceRepository $prefModel;

    /**
     * @var MonitoringService Business logic for metric processing and health indicator calculation.
     */
    private MonitoringService $monitoringService;

    /**
     * @var PatientContextService State manager for tracking the currently active patient across sessions.
     */
    private PatientContextService $contextService;

    /** @var AlertThresholdRepository Alert threshold repository */
    private AlertThresholdRepository $thresholdRepo;

    /**
     * Constructor
     *
     * @param PDO|null $pdo Database connection (optional)
     */
    public function __construct(?PDO $pdo = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->pdo = $pdo ?? Database::getInstance();
        $this->patientRepo = new PatientRepository($this->pdo);
        $this->consultationRepo = new ConsultationRepository($this->pdo);
        $this->userRepo = new UserRepository($this->pdo);
        $this->monitorModel = new MonitorRepository($this->pdo, 'patient_data');
        $this->prefModel = new MonitorPreferenceRepository($this->pdo);
        $this->monitoringService = new MonitoringService();
        $this->contextService = new PatientContextService($this->patientRepo);
        $this->thresholdRepo = new AlertThresholdRepository($this->pdo);
    }

    /**
     * Initializes and displays the high-performance Data Explorer and CSV viewer.
     *
     * Retrieves the current patient context and validates their existence before
     * rendering the specialized analytics view.
     *
     * @return void
     * @throws \Exception If patient data cannot be verified.
     */
    public function explorer(): void
    {
        $patientId = $this->contextService->getCurrentPatientId();
        $patientData = $this->patientRepo->findById($patientId);

        if (!$patientData) {
            header('Location: /?page=dashboard');
            exit;
        }

        (new ExplorerView($patientData))->show();
    }

    /**
     * Entry point for the patient dashboard, handling both read and update operations.
     *
     * Routes POST requests to preference management and GET requests to the main
     * clinical overview display.
     *
     * @return void
     */
    public function dashboard(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleChartPreferenceUpdate();
        }
        $this->dashboardGet();
    }

    /**
     * Displays the main dashboard.
     *
     * @return void
     */
    private function dashboardGet(): void
    {
        $userId = $this->requireAuth();

        $this->contextService->handleRequest();
        $patientId = $this->contextService->getCurrentPatientId();

        $rooms = $this->loadRooms();
        $patientData = $this->loadPatientData($patientId);
        [$pastConsultations, $futureConsultations] = $this->loadConsultations($patientId);
        [$processedMetrics, $userLayout] = $this->loadMonitoringData($userId, $patientId);
        $chartTypes = $this->monitorModel->getAllChartTypes();

        $customGroupRepo = new CustomGroupRepository($this->pdo);
        $rawGroups = $customGroupRepo->getGroupsByUser($userId);
        $customGroups = [];
        foreach ($rawGroups as $group) {
            $gid = (int) $group['id'];
            $layoutRows = $customGroupRepo->getGroupIndicatorsWithLayout($gid, $userId);
            $layoutMap = [];
            foreach ($layoutRows as $lr) {
                $layoutMap[$lr['id']] = [
                    'x' => $lr['x'],
                    'y' => $lr['y'],
                    'w' => $lr['w'],
                    'h' => $lr['h'],
                ];
            }
            $customGroups[] = [
                'id' => $gid,
                'name' => (string) $group['name'],
                'color' => (string) $group['color'],
                'indicator_ids' => $customGroupRepo->getIndicatorsByGroup($gid),
                'layout' => $layoutMap,
            ];
        }

        /** @var array<int, \modules\models\entities\Consultation> $pastCons */
        $pastCons = array_values($pastConsultations);
        /** @var array<int, \modules\models\entities\Consultation> $futCons */
        $futCons = array_values($futureConsultations);

        $view = new DashboardView(
            $pastCons,
            $futCons,
            $rooms,
            $processedMetrics,
            $patientData,
            $chartTypes,
            $userLayout,
            $customGroups
        );
        $view->show();
    }

    /**
     * Entry point for real-time patient monitoring and physiological waveforms.
     *
     * Manages asynchronous preference updates via POST and coordinate-based
     * clinical data rendering via GET.
     *
     * @return void
     */
    public function monitoring(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleChartPreferenceUpdate();
        }
        $this->monitoringGet();
    }

    /**
     * Displays the monitoring view.
     *
     * @return void
     */
    private function monitoringGet(): void
    {
        try {
            $this->requireAuth();

            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                header('Location: /?page=login');
                exit();
            }

            $this->contextService->handleRequest();
            $patientId = $this->contextService->getCurrentPatientId();
            $metrics = [];
            $rawHistory = [];

            if ($patientId) {
                $metrics = $this->monitorModel->getLatestMetrics($patientId);
                $rawHistory = [];
            }

            $rawUserId = $_SESSION['user_id'] ?? 0;
            $prefs = $this->prefModel->getUserPreferences(is_numeric($rawUserId) ? (int) $rawUserId : 0);

            $processedMetrics = $this->monitoringService->processMetrics($metrics, $rawHistory, $prefs, true);

            $chartTypes = $this->monitorModel->getAllChartTypes();

            $patientData = $this->loadPatientData($patientId);
            $view = new MonitoringView($processedMetrics, $chartTypes, $patientId, $patientData);
            $view->show();
        } catch (\Exception $e) {
            error_log("PatientController::monitoring Error: " . $e->getMessage());
            header('Location: /?page=error&msg=monitoring_error');
            exit();
        }
    }


    /**
     * Patient record entry point (GET & POST).
     *
     * @return void
     */
    public function record(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'update_thresholds') {
                $this->handleThresholdUpdate();
            } else {
                $this->recordPost();
            }
        } else {
            $this->recordGet();
        }
    }

    /**
     * Displays the patient record view.
     *
     * @return void
     */
    private function recordGet(): void
    {
        $this->requireAuth();

        $this->contextService->handleRequest();
        $idPatient = $this->contextService->getCurrentPatientId();

        try {
            $patientData = $this->patientRepo->findById($idPatient);

            if (!$patientData) {
                $patientData = [
                    'id_patient' => $idPatient,
                    'first_name' => 'Patient',
                    'last_name' => 'Inconnu',
                    'birth_date' => null,
                    'gender' => 'U',
                    'admission_cause' => 'Dossier non trouvé ou inexistant.',
                    'medical_history' => '',
                    'age' => 0
                ];
            } else {
                $patientData['age'] = $this->calculateAge($patientData['birth_date'] ?? null);
            }

            $doctors = $this->patientRepo->getDoctors($idPatient);

            $allConsultations = $this->consultationRepo->getConsultationsByPatientId($idPatient);
            $pastConsultations = [];
            $futureConsultations = [];
            $today = new \DateTime();

            foreach ($allConsultations as $consultation) {
                $dStr = $consultation->getDate();
                $dObj = \DateTime::createFromFormat('d/m/Y', $dStr);
                if (!$dObj) {
                    $dObj = \DateTime::createFromFormat('Y-m-d', $dStr);
                }
                if ($dObj && $dObj < $today) {
                    $pastConsultations[] = $consultation;
                } else {
                    $futureConsultations[] = $consultation;
                }
            }

            /** @var array{type: string, text: string}|null $msg */
            $msg = isset($_SESSION['patient_msg']) && is_array($_SESSION['patient_msg'])
                ? $_SESSION['patient_msg']
                : null;
            if (isset($_SESSION['patient_msg'])) {
                unset($_SESSION['patient_msg']);
            }

            $safeDoctors = array_map(function ($d) {
                $d['profession_name'] = (string) ($d['profession_name'] ?? '');
                return $d;
            }, $doctors);

            $thresholds = $this->thresholdRepo->getThresholdsForPatient($idPatient);

            $view = new PatientrecordView(
                $pastConsultations,
                $futureConsultations,
                $patientData,
                $safeDoctors,
                $msg,
                $thresholds
            );
            $view->show();
        } catch (\Throwable $e) {
            error_log("[PatientController::record] Critical Error: " . $e->getMessage());
            $view = new PatientrecordView(
                [],
                [],
                [],
                [],
                ['type' => 'error', 'text' => 'Une erreur interne est survenue lors du chargement du dossier.']
            );
            $view->show();
        }
    }

    /**
     * Processes patient record update (POST).
     *
     * @return void
     */
    private function recordPost(): void
    {
        $this->requireAuth();

        $sessionCsrf = isset($_SESSION['csrf_patient']) && is_string($_SESSION['csrf_patient'])
            ? $_SESSION['csrf_patient']
            : '';
        $postCsrf = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
        if ($sessionCsrf === '' || $postCsrf === '' || !hash_equals($sessionCsrf, $postCsrf)) {
            $_SESSION['patient_msg'] = ['type' => 'error', 'text' => 'Session expirée. Veuillez rafraîchir la page.'];
            header('Location: /?page=dossierpatient');
            exit;
        }

        $this->contextService->handleRequest();
        $idPatient = $this->contextService->getCurrentPatientId();

        $rawFirstName = $_POST['first_name'] ?? '';
        $firstName = trim(is_string($rawFirstName) ? $rawFirstName : '');
        $rawLastName = $_POST['last_name'] ?? '';
        $lastName = trim(is_string($rawLastName) ? $rawLastName : '');
        $rawAdmCause = $_POST['admission_cause'] ?? '';
        $admissionCause = trim(is_string($rawAdmCause) ? $rawAdmCause : '');
        $rawMedHistory = $_POST['medical_history'] ?? '';
        $medicalHistory = trim(is_string($rawMedHistory) ? $rawMedHistory : '');
        $rawBirthDate = $_POST['birth_date'] ?? '';
        $birthDate = trim(is_string($rawBirthDate) ? $rawBirthDate : '');

        if ($firstName === '' || $lastName === '' || $admissionCause === '') {
            $_SESSION['patient_msg'] = ['type' => 'error', 'text' => 'Merci de remplir tous les champs obligatoires.'];
            header('Location: /?page=dossierpatient');
            exit;
        }

        if ($birthDate !== '') {
            $dateObj = \DateTime::createFromFormat('Y-m-d', $birthDate);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $birthDate) {
                $_SESSION['patient_msg'] =
                    ['type' => 'error', 'text' => 'Le format de la date de naissance est invalide.'];
                header('Location: /?page=dossierpatient');
                exit;
            }
            if ($dateObj > new \DateTime()) {
                $_SESSION['patient_msg'] =
                    ['type' => 'error', 'text' => 'La date de naissance ne peut pas être future.'];
                header('Location: /?page=dossierpatient');
                exit;
            }
        }

        try {
            $updateData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birth_date' => $birthDate,
                'admission_cause' => $admissionCause,
                'medical_history' => $medicalHistory
            ];

            $success = $this->patientRepo->update($idPatient, $updateData);

            if ($success) {
                $_SESSION['patient_msg'] =
                    ['type' => 'success', 'text' => 'Dossier mis à jour avec succès.'];
            } else {
                $_SESSION['patient_msg'] =
                    ['type' => 'error', 'text' => 'Aucune modification détectée ou erreur de sauvegarde.'];
            }
        } catch (\Exception $e) {
            error_log("[PatientController::recordPost] Erreur UPDATE: " . $e->getMessage());
            $_SESSION['patient_msg'] =
                ['type' => 'error', 'text' => 'Erreur technique lors de la sauvegarde.'];
        }

        header('Location: /?page=dossierpatient');
        exit;
    }

    /**
     * Consultations entry point (GET & POST).
     *
     * @return void
     */
    public function consultations(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->consultationsPost();
        }
        $this->consultationsGet();
    }

    /**
     * Displays consultations list.
     *
     * @return void
     */
    private function consultationsGet(): void
    {
        $this->requireAuth();

        $this->contextService->handleRequest();
        $patientId = $this->contextService->getCurrentPatientId();

        $consultations = [];
        if ($patientId) {
            $consultations = $this->consultationRepo->getConsultationsByPatientId($patientId);
        }

        usort($consultations, function ($a, $b) {
            $dateA = \DateTime::createFromFormat('Y-m-d', $a->getDate());
            $dateB = \DateTime::createFromFormat('Y-m-d', $b->getDate());
            if (!$dateA) {
                return 1;
            }
            if (!$dateB) {
                return -1;
            }
            return $dateB <=> $dateA;
        });

        $doctors = $this->userRepo->getAllDoctors();

        $currentUserId = isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])
            ? (int) $_SESSION['user_id']
            : 0;
        $isAdmin = $this->isAdminUser($currentUserId);

        $patientData = $this->loadPatientData($patientId);
        $view = new MedicalprocedureView($consultations, $doctors, $isAdmin, $currentUserId, $patientId, $patientData);
        $view->show();
    }

    /**
     * Processes consultation POST actions (add/update/delete).
     *
     * @return void
     */
    private function consultationsPost(): void
    {
        if (!isset($_POST['action'])) {
            return;
        }

        $this->requireAuth();

        $this->contextService->handleRequest();
        $patientId = $this->contextService->getCurrentPatientId();
        $currentUserId = $_SESSION['user_id'] ?? null;

        if (!$patientId || !$currentUserId) {
            return;
        }

        $rawCurrentUserId = $_SESSION['user_id'] ?? 0;
        $isAdmin = $this->isAdminUser(is_numeric($rawCurrentUserId) ? (int) $rawCurrentUserId : 0);

        $rawAction = $_POST['action'];
        $action = is_string($rawAction) ? $rawAction : '';
        $currentUserInt = is_numeric($rawCurrentUserId) ? (int) $rawCurrentUserId : 0;

        if ($action === 'add_consultation') {
            $this->handleAddConsultation($patientId, $currentUserInt, $isAdmin);
        } elseif ($action === 'update_consultation') {
            $this->handleUpdateConsultation($patientId, $currentUserInt, $isAdmin);
        } elseif ($action === 'delete_consultation') {
            $this->handleDeleteConsultation($patientId);
        }
    }

    /**
     * Adds a consultation.
     */
    private function handleAddConsultation(int $patientId, int $currentUserId, bool $isAdmin): void
    {
        $rawDoctorId = $_POST['doctor_id'] ?? null;
        $doctorId = ($isAdmin && $rawDoctorId !== null && is_numeric($rawDoctorId) && (int) $rawDoctorId > 0)
            ? (int) $rawDoctorId
            : $currentUserId;

        $rawTitle = $_POST['consultation_title'] ?? '';
        $title = trim(is_string($rawTitle) ? $rawTitle : '');
        $rawDate = $_POST['consultation_date'] ?? '';
        $date = is_string($rawDate) ? $rawDate : '';
        $rawTime = $_POST['consultation_time'] ?? '';
        $time = is_string($rawTime) ? $rawTime : '';
        $rawType = $_POST['consultation_type'] ?? 'Autre';
        $type = is_string($rawType) ? $rawType : 'Autre';
        $rawNote = $_POST['consultation_note'] ?? '';
        $note = trim(is_string($rawNote) ? $rawNote : '');

        if ($title !== '' && $date !== '' && $time !== '') {
            $fullDate = $date . ' ' . $time . ':00';
            $success = $this->consultationRepo->createConsultation(
                $patientId,
                $doctorId,
                $fullDate,
                $type,
                $note,
                $title
            );
            if ($success) {
                header("Location: ?page=medicalprocedure&id_patient=" . $patientId);
                exit;
            }
        }
    }

    /**
     * Updates a consultation.
     */
    private function handleUpdateConsultation(int $patientId, int $currentUserId, bool $isAdmin): void
    {
        $rawConsId = $_POST['id_consultation'] ?? 0;
        $consultationId = is_numeric($rawConsId) ? (int) $rawConsId : 0;

        if ($consultationId) {
            $consultation = $this->consultationRepo->getConsultationById($consultationId);
            if (!$consultation) {
                return;
            }
            if (!$isAdmin && $consultation->getDoctorId() !== $currentUserId) {
                error_log(
                    "Access denied: User $currentUserId tried to modify consultation $consultationId"
                );
                return;
            }
        }

        $rawDoctorId2 = $_POST['doctor_id'] ?? null;
        $doctorId = ($isAdmin && $rawDoctorId2 !== null && is_numeric($rawDoctorId2) && (int) $rawDoctorId2 > 0)
            ? (int) $rawDoctorId2
            : $currentUserId;

        $rawTitle2 = $_POST['consultation_title'] ?? '';
        $title = trim(is_string($rawTitle2) ? $rawTitle2 : '');
        $rawDate2 = $_POST['consultation_date'] ?? '';
        $date = is_string($rawDate2) ? $rawDate2 : '';
        $rawTime2 = $_POST['consultation_time'] ?? '';
        $time = is_string($rawTime2) ? $rawTime2 : '';
        $rawType2 = $_POST['consultation_type'] ?? 'Autre';
        $type = is_string($rawType2) ? $rawType2 : 'Autre';
        $rawNote2 = $_POST['consultation_note'] ?? '';
        $note = trim(is_string($rawNote2) ? $rawNote2 : '');

        if ($consultationId && $title !== '' && $date !== '' && $time !== '') {
            $fullDate = $date . ' ' . $time . ':00';
            $success = $this->consultationRepo->updateConsultation(
                $consultationId,
                $doctorId,
                $fullDate,
                $type,
                $note,
                $title
            );
            if ($success) {
                header("Location: ?page=medicalprocedure&id_patient=" . $patientId);
                exit;
            }
        }
    }

    /**
     * Deletes a consultation.
     */
    private function handleDeleteConsultation(int $patientId): void
    {
        $rawDelConsId = $_POST['id_consultation'] ?? 0;
        $consultationId = is_numeric($rawDelConsId) ? (int) $rawDelConsId : 0;
        $rawDelUserId = $_SESSION['user_id'] ?? 0;
        $currentUserId = is_numeric($rawDelUserId) ? (int) $rawDelUserId : 0;
        $isAdmin = $this->isAdminUser($currentUserId);

        if ($consultationId) {
            $consultation = $this->consultationRepo->getConsultationById($consultationId);
            if (!$consultation) {
                return;
            }
            if (!$isAdmin && $consultation->getDoctorId() !== $currentUserId) {
                error_log(
                    "Access denied: User $currentUserId tried to delete consultation $consultationId"
                );
                return;
            }
            $success = $this->consultationRepo->deleteConsultation($consultationId);
            if ($success) {
                header("Location: ?page=medicalprocedure&id_patient=" . $patientId);
                exit;
            }
        }
    }

    /**
     * Requires authentication, redirects to login if not.
     *
     * @return int User ID
     */
    private function requireAuth(): int
    {
        if (!isset($_SESSION['email'])) {
            header('Location: /?page=login');
            exit();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            header('Location: /?page=login');
            exit();
        }
        return (int) $userId;
    }

    /**
     * Handles alert threshold update via POST.
     *
     * @return void
     */
    private function handleThresholdUpdate(): void
    {
        $this->requireAuth();

        $sessionCsrf = isset($_SESSION['csrf_patient']) && is_string($_SESSION['csrf_patient'])
            ? $_SESSION['csrf_patient']
            : '';
        $postCsrf = isset($_POST['csrf']) && is_string($_POST['csrf']) ? $_POST['csrf'] : '';
        if ($sessionCsrf === '' || $postCsrf === '' || !hash_equals($sessionCsrf, $postCsrf)) {
            $_SESSION['patient_msg'] = ['type' => 'error', 'text' => 'Session expirée. Veuillez rafraîchir la page.'];
            header('Location: /?page=dossierpatient');
            exit;
        }

        $this->contextService->handleRequest();
        $idPatient = $this->contextService->getCurrentPatientId();

        if (!$idPatient) {
            $_SESSION['patient_msg'] = ['type' => 'error', 'text' => 'Patient non identifié.'];
            header('Location: /?page=dossierpatient');
            exit;
        }

        $rawUserId = $_SESSION['user_id'] ?? 0;
        $userId = is_numeric($rawUserId) ? (int) $rawUserId : null;

        $thresholdAction = $_POST['threshold_action'] ?? 'save';
        $parameterId = isset($_POST['parameter_id']) && is_string($_POST['parameter_id'])
            ? trim($_POST['parameter_id'])
            : '';

        if ($parameterId === '') {
            $_SESSION['patient_msg'] = ['type' => 'error', 'text' => 'Paramètre non spécifié.'];
            header('Location: /?page=dossierpatient');
            exit;
        }

        if ($thresholdAction === 'reset') {
            $this->thresholdRepo->resetThreshold($idPatient, $parameterId);
            $_SESSION['patient_msg'] = ['type' => 'success', 'text' => 'Seuils réinitialisés aux valeurs par défaut.'];
            header('Location: /?page=dossierpatient');
            exit;
        }

        $parseFloat = function (string $key): ?float {
            $raw = $_POST[$key] ?? '';
            if (!is_string($raw) || trim($raw) === '') {
                return null;
            }
            $val = str_replace(',', '.', trim($raw));
            return is_numeric($val) ? (float) $val : null;
        };

        $normalMin = $parseFloat('normal_min');
        $normalMax = $parseFloat('normal_max');
        $criticalMin = $parseFloat('critical_min');
        $criticalMax = $parseFloat('critical_max');

        if ($normalMin !== null && $normalMax !== null && $normalMin >= $normalMax) {
            $_SESSION['patient_msg'] = ['type' => 'error', 'text' => 'Le seuil min normal doit être inférieur au seuil max normal.'];
            header('Location: /?page=dossierpatient');
            exit;
        }
        if ($criticalMin !== null && $criticalMax !== null && $criticalMin >= $criticalMax) {
            $_SESSION['patient_msg'] = ['type' => 'error', 'text' => 'Le seuil min critique doit être inférieur au seuil max critique.'];
            header('Location: /?page=dossierpatient');
            exit;
        }

        $success = $this->thresholdRepo->saveThreshold(
            $idPatient,
            $parameterId,
            $normalMin,
            $normalMax,
            $criticalMin,
            $criticalMax,
            $userId
        );

        if ($success) {
            $_SESSION['patient_msg'] = ['type' => 'success', 'text' => 'Seuils d\'alerte mis à jour avec succès.'];
        } else {
            $_SESSION['patient_msg'] = ['type' => 'error', 'text' => 'Erreur lors de la mise à jour des seuils.'];
        }

        header('Location: /?page=dossierpatient');
        exit;
    }

    /**
     * Checks if user is admin.
     *
     * @param int $userId
     * @return bool
     */
    private function isAdminUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $user = $this->userRepo->getById($userId);
        return $user !== null && $user->isAdmin();
    }

    /**
     * Handles chart preference update via POST.
     *
     * @return void
     */
    private function handleChartPreferenceUpdate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['chart_pref_submit'])) {
            return;
        }

        try {
            $userId = $_SESSION['user_id'] ?? null;
            $parameterId = (string) ($_POST['parameter_id'] ?? '');
            $chartType = (string) ($_POST['chart_type'] ?? '');
            $isModal = isset($_POST['is_modal_pref']) && $_POST['is_modal_pref'] === '1';
            $prefType = (string) ($_POST['preference_type'] ?? ($isModal ? 'modal_chart' : 'chart'));

            if (is_numeric($userId) && $parameterId !== '' && $chartType !== '') {
                $this->prefModel->saveUserChartPreference((int) $userId, $parameterId, $chartType, $prefType);

                if ($isModal || $prefType === 'duration') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit();
                } else {
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit();
                }
            }
        } catch (\Exception $e) {
            error_log('[PatientController] handleChartPreferenceUpdate error: ' . $e->getMessage());
        }
    }


    /**
     * Retrieves historical data for a specific medical parameter.
     *
     * Uses query parameters to determine the scope and applies Largest Triangle Three Buckets (LTTB)
     * downsampling if the dataset exceeds 5000 points to optimize client-side rendering performance.
     * Unbuffered streaming is used for large requests to maintain a low memory footprint.
     * Outputs JSON-encoded history data.
     *
     * @return void
     */
    public function apiHistory(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Non autorisé']);
                return;
            }

            session_write_close();

            $roomId = $this->getRoomId();
            $patientId = null;

            if ($roomId) {
                $patientId = $this->patientRepo->getPatientIdByRoom($roomId);
            }
            if (!$patientId) {
                $this->contextService->handleRequest();
                $patientId = $this->contextService->getCurrentPatientId();
            }

            if (!$patientId) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Patient introuvable']);
                return;
            }

            $rawParameterId = $_GET['param'] ?? '';
            $parameterId = is_string($rawParameterId) ? $rawParameterId : '';
            $rawTargetDate = $_GET['date'] ?? null;
            $targetDate = is_string($rawTargetDate) ? $rawTargetDate : null;

            if ($parameterId === '') {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Paramètre manquant']);
                return;
            }

            $isRaw = isset($_GET['raw']) && $_GET['raw'] === '1';
            $isCsv = isset($_GET['format']) && $_GET['format'] === 'csv';


            if ($isRaw) {
                if ($isCsv) {
                    $timestamp = date('Ymd_His');
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="export_patient_' . $patientId . '_' . $parameterId . '_' . $timestamp . '.csv"');

                    $out = fopen('php://output', 'w');
                    if ($out === false) {
                        echo "timestamp,value,alert_flag\n";
                        return;
                    }

                    fputcsv($out, ['timestamp', 'value', 'alert_flag']);

                    $stream = $this->monitorModel->streamRawHistoryByParameter($patientId, $parameterId, $targetDate, 0);
                    foreach ($stream as $row) {
                        $ts = $row['timestamp'];
                        $rawTs = (strpos($ts, '+') === false && strpos($ts, 'Z') === false) ? $ts . ' UTC' : $ts;

                        $val = $row['value'];
                        fputcsv($out, [
                            date('c', (int) strtotime($rawTs)),
                            is_numeric($val) ? round((float) $val, 2) : ''
                        ]);
                    }
                    fclose($out);
                } else {
                    header('Content-Type: application/json');
                    echo '[';
                    $stream = $this->monitorModel->streamRawHistoryByParameter($patientId, $parameterId, $targetDate, 0);
                    $first = true;
                    foreach ($stream as $row) {
                        if (!$first) {
                            echo ',';
                        }
                        $ts = $row['timestamp'];
                        $rawTs = (strpos($ts, '+') === false && strpos($ts, 'Z') === false) ? $ts . ' UTC' : $ts;
                        $val = $row['value'];
                        echo json_encode([
                            'time_iso' => date('c', (int) strtotime($rawTs)),
                            'value' => is_numeric($val) ? (string) round((float) $val, 2) : '0',
                            'flag' => (string) $row['alert_flag'],
                        ]);
                        $first = false;
                    }
                    echo ']';
                }
                return;
            }

            header('Content-Type: application/json');

            $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '';
            $enableCache = $appEnv !== 'testing';

            $cacheKey = md5("history_v2_{$patientId}_{$parameterId}_{$targetDate}");
            $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dashmed_cache' . DIRECTORY_SEPARATOR . $cacheKey . '.json';

            if ($enableCache && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 30)) {
                echo file_get_contents($cacheFile);
                return;
            }


            $count = $this->monitorModel->countRawHistoryByParameter($patientId, $parameterId, $targetDate);
            $threshold = 5000;

            if ($count > $threshold) {
                $monitorModel = $this->monitorModel;
                $stream = $monitorModel->streamRawHistoryByParameter($patientId, $parameterId, $targetDate, 0);
                $downsampling = new \modules\services\DownsamplingService();

                $formattedStream = function () use ($stream) {
                    foreach ($stream as $row) {
                        $ts = $row['timestamp'];
                        $rawTs = (strpos($ts, '+') === false && strpos($ts, 'Z') === false) ? $ts . ' UTC' : $ts;

                        $rawVal = $row['value'];
                        $valStr = is_numeric($rawVal) ? (string) round((float) $rawVal, 2) : '0';

                        $rawFlag = $row['alert_flag'];
                        $flagStr = is_numeric($rawFlag) ? (string) ((int) $rawFlag) : (string) $rawFlag;

                        yield [
                            'time_iso' => $ts !== '' ? date('c', (int) strtotime($rawTs)) : '',
                            'value' => $valStr,
                            'flag' => $flagStr,
                        ];
                    }
                };

                $generator = $formattedStream();
                $formatted = $downsampling->downsampleLTTBStream(new \IteratorIterator($generator), $count, $threshold);
            } else {
                $history = $this->monitorModel->getRawHistoryByParameter($patientId, $parameterId, $targetDate, 0);
                $formatted = [];
                foreach ($history as $hItem) {
                    $ts = $hItem['timestamp'];
                    $rawTs = (strpos($ts, '+') === false && strpos($ts, 'Z') === false) ? $ts . ' UTC' : $ts;

                    $val = $hItem['value'];
                    $formatted[] = [
                        'time_iso' => date('c', (int) strtotime($rawTs)),
                        'value' => $val !== null ? (string) round((float) $val, 2) : null,
                        'flag' => (string) $hItem['alert_flag'],
                    ];
                }
            }

            $jsonResult = json_encode($formatted);


            if ($enableCache) {
                if (!is_dir(dirname($cacheFile))) {
                    mkdir(dirname($cacheFile), 0777, true);
                }
                file_put_contents($cacheFile, $jsonResult);
            }

            echo $jsonResult;
        } catch (\Exception $e) {
            error_log('[PatientController] apiHistory error: ' . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne']);
        }
    }

    /**
     * Retrieves the latest real-time metrics for the current patient.
     *
     * Identifies the patient context, fetches the most recent metric values,
     * processes them through the MonitoringService to apply user preferences,
     * and rigorously formats timestamps into UTC ISO-8601 for frontend synchronization.
     * Outputs a JSON array of processed metrics.
     *
     * @return void
     */
    public function apiLiveMetrics(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                echo json_encode(['error' => 'Non autorisé']);
                return;
            }

            session_write_close();

            $roomId = $this->getRoomId();
            $patientId = null;

            // Priority to explicit parameter for API tools/Explorer
            $getPatientId = $_GET['patient_id'] ?? null;
            if ($getPatientId && is_numeric($getPatientId)) {
                $patientId = (int) $getPatientId;
            }

            if (!$patientId && $roomId) {
                $patientId = $this->patientRepo->getPatientIdByRoom($roomId);
            }
            if (!$patientId) {
                $this->contextService->handleRequest();
                $patientId = $this->contextService->getCurrentPatientId();
            }

            if (!$patientId) {
                echo json_encode(['error' => 'Patient introuvable']);
                return;
            }

            $metrics = $this->monitorModel->getLatestMetrics($patientId);

            $rawUserId = $_SESSION['user_id'] ?? 0;
            $prefs = $this->prefModel->getUserPreferences(is_numeric($rawUserId) ? (int) $rawUserId : 0);


            $rawHistory = $this->monitorModel->getRawHistory($patientId, 1);

            $processedMetrics = $this->monitoringService->processMetrics($metrics, $rawHistory, $prefs, true);
            $formatted = [];

            foreach ($processedMetrics as $metric) {
                $viewData = $metric->getViewData();

                $historyHtmlData = $viewData['history_html_data'] ?? [];
                $latestTimeIso = '';
                if (is_array($historyHtmlData) && !empty($historyHtmlData)) {
                    $last = end($historyHtmlData);
                    if (is_array($last)) {
                        $rawLatest = $last['time_iso'] ?? '';
                        $latestTimeIso = is_string($rawLatest) ? $rawLatest : '';
                    }
                    reset($historyHtmlData);
                }

                $timeRaw = $metric->getTimestamp();
                $rawTs = (is_string($timeRaw) && strpos($timeRaw, '+') === false && strpos($timeRaw, 'Z') === false)
                    ? $timeRaw . ' UTC'
                    : $timeRaw;

                $timeIso = $latestTimeIso;
                if (is_string($rawTs) && is_string($timeRaw) && $timeRaw !== '') {
                    $timeIso = date('c', (int) strtotime($rawTs));
                }

                $formatted[] = [
                    'parameter_id' => $metric->getId(),
                    'slug' => is_string($viewData['slug'] ?? null) ? $viewData['slug'] : 'param',
                    'value' => $viewData['value'] ?? '',
                    'unit' => is_string($viewData['unit'] ?? null) ? $viewData['unit'] : '',
                    'state_class' => is_string($viewData['card_class'] ?? null) ? $viewData['card_class'] : '',
                    'is_crit_flag' => (bool) ($viewData['is_crit_flag'] ?? false),
                    'time_iso' => $timeIso,
                    'chart_type' => is_string($viewData['chart_type'] ?? null) ? $viewData['chart_type'] : 'line',
                    'display_name' => is_string($viewData['display_name'] ?? null) ? $viewData['display_name'] : '',
                    'thresholds' => $viewData['thresholds'] ?? null,
                ];
            }

            echo json_encode($formatted);
        } catch (\Exception $e) {
            error_log('[PatientController] apiLiveMetrics error: ' . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne']);
        }
    }

    /**
     * Retrieves room ID from GET or COOKIE.
     *
     * @return int|null
     */
    private function getRoomId(): ?int
    {
        $rawRoom = $_GET['room'] ?? null;
        $rawCookie = $_COOKIE['room_id'] ?? null;
        if ($rawRoom !== null && is_numeric($rawRoom)) {
            return (int) $rawRoom;
        }
        if ($rawCookie !== null && is_numeric($rawCookie)) {
            return (int) $rawCookie;
        }
        return null;
    }

    /**
     * Loads list of rooms with patients.
     *
     * @return array<int, array{room_id: int|string, first_name?: string}>
     */
    private function loadRooms(): array
    {
        try {
            /** @var array<int, array{room_id: int|string, first_name?: string}> */
            return $this->patientRepo->getAllRoomsWithPatients();
        } catch (\Throwable $e) {
            error_log('[PatientController] loadRooms error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Loads patient data.
     *
     * @param int|null $patientId
     * @return array<string, mixed>
     */
    private function loadPatientData(?int $patientId): array
    {
        if ($patientId !== null) {
            $data = $this->patientRepo->findById($patientId);
            if (is_array($data)) {
                return $data;
            }
        }
        return [
            'first_name' => 'Patient',
            'last_name' => 'Inconnu',
            'birth_date' => null,
            'admission_cause' => 'Aucun patient sélectionné | No patient selected',
            'id_patient' => 0,
        ];
    }

    /**
     * Loads and sorts consultations.
     *
     * @param int|null $patientId
     * @return array{0: array<int, Consultation>, 1: array<int, Consultation>}
     */
    private function loadConsultations(?int $patientId): array
    {
        if ($patientId === null) {
            return [[], []];
        }

        /** @var array<int, Consultation> $allConsultations */
        $allConsultations = $this->consultationRepo->getConsultationsByPatientId($patientId);

        $today = new DateTime();
        $past = [];
        $future = [];

        foreach ($allConsultations as $consultation) {
            try {
                $consultationDate = new DateTime($consultation->getDate());
            } catch (\Throwable) {
                continue;
            }

            if ($consultationDate < $today) {
                $past[] = $consultation;
            } else {
                $future[] = $consultation;
            }
        }

        return [$past, $future];
    }

    /**
     * Loads monitoring data and user layout.
     *
     * @param int $userId
     * @param int|null $patientId
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function loadMonitoringData(int $userId, ?int $patientId): array
    {
        /** @var array<int, array<string, mixed>> $processedMetrics */
        $processedMetrics = [];
        /** @var array<string, mixed> $userLayout */
        $userLayout = [];

        if ($patientId === null) {
            return [$processedMetrics, $userLayout];
        }

        try {
            $metrics = $this->monitorModel->getLatestMetrics($patientId);

            /**
             * PERFORMANCE OPTIMIZATION:
             * Instead of loading the entire history table for all parameters (O(N)),
             * we fetch only the last 1000 points per parameter (O(Params * 1000)).
             * This prevents memory exhaustion on the initial page load while
             * keeping sparklines perfectly accurate.
             */
            // PERF: do not pre-load full sparkline history on initial Dashboard render.
            // The frontend loads history lazily (exact chunks) and caches it.
            $rawHistory = [];

            $prefs = $this->prefModel->getUserPreferences($userId);
            $userLayout = (array) $this->prefModel->getUserLayoutSimple($userId);

            $visibleIds = [];
            if (!empty($userLayout)) {
                foreach ($userLayout as $item) {
                    if (is_array($item) && empty($item['is_hidden'])) {
                        $visibleIds[] = $item['parameter_id'];
                    }
                }
            } else {
                $visibleIds = array_slice(array_keys($metrics), 0, 6);
            }

            $customGroupRepo = new CustomGroupRepository($this->pdo);
            $groups = $customGroupRepo->getGroupsByUser($userId);
            $groupIdents = [];
            foreach ($groups as $g) {
                $groupIdents = array_merge($groupIdents, $customGroupRepo->getIndicatorsByGroup((int) $g['id']));
            }

            $requiredHistoryIds = array_unique(array_merge($visibleIds, $groupIdents));

            $rawHistory = [];
            if (!empty($requiredHistoryIds)) {
                $rawHistory = $this->monitorModel->getLatestHistoryForSpecificParameters($patientId, $requiredHistoryIds, 1000);
            }

            $processedMetrics = $this->monitoringService->processMetrics($metrics, $rawHistory, $prefs, true);

        } catch (\Exception $e) {
            error_log('[PatientController] loadMonitoringData error: ' . $e->getMessage());
        }

        return [$processedMetrics, $userLayout];
    }

    /**
     * Calculates age from birth date.
     *
     * @param string|null $birthDateString
     * @return int
     */
    private function calculateAge(?string $birthDateString): int
    {
        if (empty($birthDateString)) {
            return 0;
        }
        try {
            $birthDate = new \DateTime($birthDateString);
            $today = new \DateTime();
            return $today->diff($birthDate)->y;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Streams real-time metrics for the current patient using SSE (Server-Sent Events).
     *
     * @return void
     */
    public function apiStream(): void
    {

        set_time_limit(0);
        ignore_user_abort(false);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                echo "data: " . json_encode(['error' => 'Non autorisé']) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                return;
            }

            session_write_close();

            $roomId = $this->getRoomId();
            $patientId = null;

            if ($roomId) {
                $patientId = $this->patientRepo->getPatientIdByRoom($roomId);
            }
            if (!$patientId) {
                $this->contextService->handleRequest();
                $patientId = $this->contextService->getCurrentPatientId();
            }

            if (!$patientId) {
                echo "data: " . json_encode(['error' => 'Patient introuvable']) . "\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                return;
            }

            $rawUserId = $_SESSION['user_id'] ?? 0;
            $prefs = $this->prefModel->getUserPreferences(is_numeric($rawUserId) ? (int) $rawUserId : 0);

            /** @var \modules\models\entities\Indicator[] $indicators */
            $indicators = $this->monitorModel->getLatestMetrics($patientId);
            $indicatorsById = [];
            $lastSentTimestamp = '1970-01-01 00:00:00';

            foreach ($indicators as $ind) {
                $indicatorsById[$ind->getId()] = $ind;
                $ts = $ind->getTimestamp();
                if (is_string($ts) && $ts > $lastSentTimestamp) {
                    $lastSentTimestamp = $ts;
                }
            }

            while (true) {
                if (connection_aborted()) {
                    break;
                }


                $allNewHistory = $this->monitorModel->getRawHistory($patientId, 0, $lastSentTimestamp);

                if (!empty($allNewHistory)) {
                    $formatted = [];
                    foreach ($allNewHistory as $hItem) {
                        $pid = $hItem['parameter_id'];
                        $ts = $hItem['timestamp'];
                        $val = $hItem['value'];
                        $flag = $hItem['alert_flag'];

                        if ($ts > $lastSentTimestamp) {
                            $lastSentTimestamp = $ts;
                        }

                        $indicator = $indicatorsById[$pid] ?? null;
                        if ($indicator) {
                            $indicator->setValue(is_numeric($val) ? (float) $val : null);
                            $indicator->setTimestamp($ts);
                            $indicator->setAlertFlag((int) $flag);

                            $vd = $this->monitoringService->prepareViewData($indicator);
                            $rawTs = (strpos($ts, '+') === false && strpos($ts, 'Z') === false) ? $ts . ' UTC' : $ts;

                            $formatted[] = [
                                'parameter_id' => $pid,
                                'slug' => $vd['slug'] ?? 'param',
                                'value' => $vd['value'] ?? '',
                                'unit' => $vd['unit'] ?? '',
                                'state_class' => $vd['card_class'] ?? '',
                                'is_crit_flag' => (bool) ($vd['is_crit_flag'] ?? false),
                                'time_iso' => date('c', (int) strtotime($rawTs)),
                                'display_name' => $vd['display_name'] ?? ''
                            ];
                        }
                    }

                    if (!empty($formatted)) {
                        echo "data: " . json_encode($formatted) . "\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                }

                sleep(1);
            }
        } catch (\Exception $e) {
            error_log('[PatientController] apiStream error: ' . $e->getMessage());
            echo "data: " . json_encode(['error' => 'Erreur interne']) . "\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }
    }
    /**
     * API: Returns the latest N points (tail) for a given parameter.
     *
     * Use cases:
     * - Fast initial render of sparklines (dashboard/monitoring)
     * - Low-latency UI updates without scanning the full history
     *
     * Contract:
     * - Always returns points in chronological order (ASC)
     * - Points are exact (no downsampling)
     * - Intended to be small (hard-capped)
     *
     * Query params:
     * - patient_id (int, optional): explicit patient override
     * - param (string, required): parameter id
     * - limit (int, optional): number of points (default 250, max 5000)
     * - date (string, optional): upper bound
     *
     * @return void Outputs JSON.
     */
    public function apiHistoryTail(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                echo json_encode(['error' => 'Non autorisé']);
                return;
            }

            // Release session lock
            session_write_close();

            $roomId = $this->getRoomId();
            $patientId = null;

            $getPatientId = $_GET['patient_id'] ?? null;
            if ($getPatientId && is_numeric($getPatientId)) {
                $patientId = (int) $getPatientId;
            }

            if (!$patientId && $roomId) {
                $patientId = $this->patientRepo->getPatientIdByRoom($roomId);
            }
            if (!$patientId) {
                $this->contextService->handleRequest();
                $patientId = $this->contextService->getCurrentPatientId();
            }

            if (!$patientId) {
                echo json_encode(['error' => 'Patient introuvable']);
                return;
            }

            $rawParameterId = $_GET['param'] ?? '';
            $parameterId = is_string($rawParameterId) ? $rawParameterId : '';
            if ($parameterId === '') {
                echo json_encode(['error' => 'Paramètre manquant']);
                return;
            }

            $limit = 250;
            if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
                $limit = max(10, min(5000, (int) $_GET['limit']));
            }

            $targetDate = null;
            if (isset($_GET['date']) && is_string($_GET['date']) && $_GET['date'] !== '') {
                $targetDate = $_GET['date'];
            }

            $cacheKey = md5("tail_v1_{$patientId}_{$parameterId}_{$limit}_{$targetDate}");
            $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dashmed_cache' . DIRECTORY_SEPARATOR . $cacheKey . '.json';
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 10)) {
                echo (string) file_get_contents($cacheFile);
                return;
            }

            $history = $this->monitorModel->getTailHistoryByParameter($patientId, $parameterId, $limit, $targetDate);
            $formatted = [];
            foreach ($history as $row) {
                $ts = $row['timestamp'];
                $rawTs = (strpos($ts, '+') === false && strpos($ts, 'Z') === false) ? $ts . ' UTC' : $ts;

                $val = $row['value'];
                $formatted[] = [
                    'time_iso' => date('c', (int) strtotime($rawTs)),
                    'value' => $val !== null ? (string) round((float) $val, 2) : null,
                    'flag' => (string) $row['alert_flag'],
                ];
            }

            $json = json_encode([
                'patient_id' => $patientId,
                'param' => $parameterId,
                'points' => $formatted,
                'last_time_iso' => !empty($formatted) ? $formatted[count($formatted) - 1]['time_iso'] : null,
            ]);

            if (!is_dir(dirname($cacheFile))) {
                mkdir(dirname($cacheFile), 0777, true);
            }
            file_put_contents($cacheFile, $json);

            echo $json;
        } catch (\Throwable $e) {
            error_log('[PatientController] apiHistoryTail error: ' . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne']);
        }
    }

    /**
     * API: Returns an exact history chunk for one parameter.
     *
     * This endpoint is designed for background synchronization:
     * - stable pagination using `seq` (preferred)
     * - resumable downloads
     * - backpressure-friendly chunk sizes
     *
     * Query params:
     * - patient_id (int, optional)
     * - param (string, required)
     * - limit (int, optional, 100..20000)
     * - after_seq (int, optional): robust cursor (exclusive)
     * - after (string, optional): timestamp cursor (exclusive) if cursor=ts
     * - cursor=ts (string, optional): forces legacy timestamp cursor mode
     *
     * Response:
     * - points: array of {time_iso, value, flag, seq}
     * - next_after_seq: the last seq returned (for cursor)
     *
     * @return void Outputs JSON.
     */
    public function apiHistoryChunk(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                echo json_encode(['error' => 'Non autorisé']);
                return;
            }

            session_write_close();

            $roomId = $this->getRoomId();
            $patientId = null;

            $getPatientId = $_GET['patient_id'] ?? null;
            if ($getPatientId && is_numeric($getPatientId)) {
                $patientId = (int) $getPatientId;
            }

            if (!$patientId && $roomId) {
                $patientId = $this->patientRepo->getPatientIdByRoom($roomId);
            }
            if (!$patientId) {
                $this->contextService->handleRequest();
                $patientId = $this->contextService->getCurrentPatientId();
            }

            if (!$patientId) {
                echo json_encode(['error' => 'Patient introuvable']);
                return;
            }

            $rawParameterId = $_GET['param'] ?? '';
            $parameterId = is_string($rawParameterId) ? $rawParameterId : '';
            if ($parameterId === '') {
                echo json_encode(['error' => 'Paramètre manquant']);
                return;
            }

            $after = $_GET['after'] ?? null;
            $afterTs = (is_string($after) && $after !== '') ? $after : null;

            $limit = 5000;
            if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
                $limit = max(100, min(20000, (int) $_GET['limit']));
            }

            $afterSeq = 0;
            if (isset($_GET['after_seq']) && is_numeric($_GET['after_seq'])) {
                $afterSeq = max(0, (int) $_GET['after_seq']);
            }

            $useSeq = !isset($_GET['cursor']) || $_GET['cursor'] !== 'ts';

            if ($useSeq) {
                $rows = $this->monitorModel->getHistoryChunkAfterSeq($patientId, $parameterId, $afterSeq > 0 ? $afterSeq : null, $limit);
            } else {
                $rows = $this->monitorModel->getHistoryChunkAfter($patientId, $parameterId, $afterTs, $limit);
            }

            $formatted = [];
            $lastIso = null;
            $lastSeq = null;
            foreach ($rows as $row) {
                $ts = $row['timestamp'];
                $rawTs = (strpos($ts, '+') === false && strpos($ts, 'Z') === false) ? $ts . ' UTC' : $ts;
                $iso = date('c', (int) strtotime($rawTs));
                $lastIso = $iso;

                if (isset($row['seq'])) {
                    $lastSeq = (int) $row['seq'];
                }

                $val = $row['value'];

                $formatted[] = [
                    'time_iso' => $iso,
                    'value' => $val !== null ? (string) round((float) $val, 2) : null,
                    'flag' => (string) $row['alert_flag'],
                    'seq' => isset($row['seq']) ? (int) $row['seq'] : null,
                ];
            }

            echo json_encode([
                'patient_id' => $patientId,
                'param' => $parameterId,
                'points' => $formatted,
                'next_after' => $lastIso,
                'next_after_seq' => $lastSeq,
                'has_more' => count($formatted) >= $limit,
            ]);
        } catch (\Throwable $e) {
            error_log('[PatientController] apiHistoryChunk error: ' . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne']);
        }
    }

    /**
     * API: Returns lightweight metadata (watermarks) for a given parameter.
     *
     * Clients use this to determine whether their local cache is fully synced.
     * `max_seq` is the preferred watermark for the robust chunk cursor.
     *
     * Query params:
     * - patient_id (int, optional)
     * - param (string, required)
     *
     * @return void Outputs JSON.
     */
    public function apiHistoryMeta(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                echo json_encode(['error' => 'Non autorisé']);
                return;
            }

            session_write_close();

            $roomId = $this->getRoomId();
            $patientId = null;

            $getPatientId = $_GET['patient_id'] ?? null;
            if ($getPatientId && is_numeric($getPatientId)) {
                $patientId = (int) $getPatientId;
            }

            if (!$patientId && $roomId) {
                $patientId = $this->patientRepo->getPatientIdByRoom($roomId);
            }
            if (!$patientId) {
                $this->contextService->handleRequest();
                $patientId = $this->contextService->getCurrentPatientId();
            }

            if (!$patientId) {
                echo json_encode(['error' => 'Patient introuvable']);
                return;
            }

            $rawParameterId = $_GET['param'] ?? '';
            $parameterId = is_string($rawParameterId) ? $rawParameterId : '';
            if ($parameterId === '') {
                echo json_encode(['error' => 'Paramètre manquant']);
                return;
            }

            $meta = $this->monitorModel->getHistoryMeta($patientId, $parameterId);
            echo json_encode([
                'patient_id' => $patientId,
                'param' => $parameterId,
                'max_ts' => $meta['max_ts'] ?? null,
                'max_seq' => $meta['max_seq'] ?? null,
                'count' => $meta['count'] ?? null,
                'server_time' => date('c'),
                'schema_version' => 1,
            ]);
        } catch (\Throwable $e) {
            error_log('[PatientController] apiHistoryMeta error: ' . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne']);
        }
    }

    /**
     * Retrieves the name of a patient by their ID.
     *
     * @return void
     */
    public function apiPatientName(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Non autorisé']);
                return;
            }

            $rawId = $_GET['id'] ?? '';
            $patientId = is_numeric($rawId) ? (int) $rawId : 0;

            if ($patientId <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'ID invalide']);
                return;
            }

            $patient = $this->patientRepo->findById($patientId);
            if (!$patient) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Patient introuvable']);
                return;
            }

            header('Content-Type: application/json');
            echo json_encode([
                'id_patient' => $patient['id_patient'],
                'first_name' => $patient['first_name'],
                'last_name' => $patient['last_name']
            ]);
        } catch (\Exception $e) {
            error_log('[PatientController] apiPatientName error: ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Erreur interne']);
        }
    }
}
