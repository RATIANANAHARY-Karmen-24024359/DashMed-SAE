<?php

/**
 * API Endpoint to retrieve patient alerts
 * Usage: GET /api-alerts.php?patient_id=1 or ?room=5
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Paris');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/includes/Database.php';

use modules\models\repositories\AlertRepository;
use modules\models\repositories\PatientRepository;
use modules\models\repositories\ConsultationRepository;
use modules\models\repositories\UserRepository;
use modules\services\AlertService;
use assets\includes\Database;

try {
    session_start();

    $pdo = Database::getInstance();
    $userRepo = new UserRepository($pdo);
    $currentUser = null;

    if (isset($_SESSION['user_id'])) {
        $currentUser = $userRepo->getById((int) $_SESSION['user_id']);
    }

    // Handle POST for updating settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['action']) && $input['action'] === 'update_settings') {
            $updateData = [];
            if (isset($input['alert_volume']))
                $updateData['alert_volume'] = (float) $input['alert_volume'];
            if (isset($input['alert_duration']))
                $updateData['alert_duration'] = (int) $input['alert_duration'];
            if (isset($input['alert_dnd']))
                $updateData['alert_dnd'] = (int) $input['alert_dnd'];

            if (!empty($updateData)) {
                $userRepo->updateById($currentUser->getId(), $updateData);
                echo json_encode(['success' => true, 'message' => 'Paramètres mis à jour']);
                exit;
            }
        }
    }

    $patientRepo = new PatientRepository($pdo);
    $patientId = null;

    if (isset($_GET['patient_id'])) {
        $rawId = filter_var($_GET['patient_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $patientId = $rawId !== false ? $rawId : null;
    } elseif (isset($_GET['room'])) {
        $roomId = filter_var($_GET['room'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($roomId !== false) {
            $patientId = $patientRepo->getPatientIdByRoom($roomId);
        }
    } elseif (isset($_COOKIE['room_id'])) {
        $roomId = filter_var($_COOKIE['room_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($roomId !== false) {
            $patientId = $patientRepo->getPatientIdByRoom($roomId);
        }
    }

    $response = [
        'success' => true,
        'alerts' => [],
        'count' => 0,
        'settings' => $currentUser ? [
            'alert_volume' => $currentUser->getAlertVolume(),
            'alert_duration' => $currentUser->getAlertDuration(),
            'alert_dnd' => $currentUser->getAlertDnd()
        ] : null
    ];

    if ($patientId !== null) {
        $alertRepo = new AlertRepository($pdo);
        $alertService = new AlertService();
        $alertMessages = $alertService->buildAlertMessages($alertRepo->getOutOfThresholdAlerts($patientId));

        $consultRepo = new ConsultationRepository($pdo);
        $todayRdv = $consultRepo->getTodayConsultations($patientId);
        foreach ($todayRdv as $rdv) {
            $alertMessages[] = [
                'type' => 'info',
                'title' => '📅 RDV — ' . htmlspecialchars($rdv['title'], ENT_QUOTES, 'UTF-8'),
                'message' => $rdv['time'] . ' — Dr ' . htmlspecialchars($rdv['doctor'], ENT_QUOTES, 'UTF-8'),
                'parameterId' => 'rdv_' . $rdv['id'],
                'rdvType' => $rdv['type'],
                'rdvTime' => $rdv['time'],
                'doctor' => $rdv['doctor']
            ];
        }
        $response['alerts'] = $alertMessages;
        $response['count'] = count($alertMessages);
        $response['patient_id'] = $patientId;
    } else {
        $response['message'] = 'Aucun patient sélectionné';
    }

    echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('[api-alerts] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur',
        'message' => 'Une erreur est survenue lors de la récupération des alertes.'
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
