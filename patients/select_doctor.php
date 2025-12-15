<?php
ob_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (!is_patient_logged_in()) {
  echo json_encode(['success' => false, 'error' => 'LOGIN_REQUIRED']);
  exit;
}

$patient_id = get_patient_id();
$check_id   = isset($_POST['check_id']) ? (int)$_POST['check_id'] : 0;
$doctor_id  = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;

if ($check_id <= 0 || $doctor_id <= 0) {
  echo json_encode(['success' => false, 'error' => 'INVALID_INPUT']);
  exit;
}

try {
  // Verify the check_id belongs to this patient
  $check_stmt = $con->prepare("SELECT check_id FROM `symptom_check_history` WHERE check_id = ? AND patient_id = ?");
  $check_stmt->bind_param('ii', $check_id, $patient_id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  
  if ($check_result->num_rows === 0) {
    $check_stmt->close();
    echo json_encode(['success' => false, 'error' => 'CHECK_NOT_FOUND']);
    exit;
  }
  $check_stmt->close();

  // Fetch doctor and hospital information
  $doc_stmt = $con->prepare("
    SELECT d.doctor_id, d.name, h.hospital_name
    FROM `doctor` d
    LEFT JOIN `hospital` h ON d.hospital_id = h.hospital_id
    WHERE d.doctor_id = ?
  ");
  $doc_stmt->bind_param('i', $doctor_id);
  $doc_stmt->execute();
  $doc_result = $doc_stmt->get_result();
  $doctor = $doc_result->fetch_assoc();
  $doc_stmt->close();

  if (!$doctor) {
    echo json_encode(['success' => false, 'error' => 'DOCTOR_NOT_FOUND']);
    exit;
  }

  // Update the symptom_check_history with selected doctor information
  $update_stmt = $con->prepare("
    UPDATE `symptom_check_history`
    SET `selected_doctor_id` = ?,
        `selected_doctor_name` = ?,
        `selected_hospital_name` = ?
    WHERE `check_id` = ? AND `patient_id` = ?
  ");
  $doctor_name = $doctor['name'];
  $hospital_name = $doctor['hospital_name'] ?? null;
  $update_stmt->bind_param('issii', $doctor_id, $doctor_name, $hospital_name, $check_id, $patient_id);
  $update_stmt->execute();
  $update_stmt->close();

  echo json_encode([
    'success' => true,
    'doctor_id' => $doctor_id,
    'doctor_name' => $doctor_name,
    'hospital_name' => $hospital_name
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'SERVER_ERROR', 'message' => $e->getMessage()]);
}

ob_end_flush();

