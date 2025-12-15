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
$doctor_id  = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
$rating     = isset($_POST['rating']) ? (float)$_POST['rating'] : 0.0;

if ($doctor_id <= 0 || $rating < 0 || $rating > 5) {
  echo json_encode(['success' => false, 'error' => 'INVALID_INPUT']);
  exit;
}

try {
  // Ensure doctor exists
  $stmt = $con->prepare("SELECT `doctor_id` FROM `doctor` WHERE `doctor_id` = ?");
  $stmt->bind_param('i', $doctor_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $stmt->close();

  if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'DOCTOR_NOT_FOUND']);
    exit;
  }

  // Ensure doctor_ratings table has proper structure
  // Check if primary key exists
  $checkPK = $con->query("SHOW KEYS FROM `doctor_ratings` WHERE Key_name = 'PRIMARY'");
  if (!$checkPK || $checkPK->num_rows === 0) {
    // Try to add primary key if missing (may fail if duplicates exist)
    @$con->query("ALTER TABLE `doctor_ratings` ADD PRIMARY KEY (`doctor_id`, `patient_id`)");
  }

  // Use INSERT ... ON DUPLICATE KEY UPDATE (requires primary key)
  // If primary key doesn't exist, fall back to manual check
  $stmt = $con->prepare("SELECT `rating` FROM `doctor_ratings` WHERE `doctor_id` = ? AND `patient_id` = ? LIMIT 1");
  $stmt->bind_param('ii', $doctor_id, $patient_id);
  $stmt->execute();
  $existing = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($existing) {
    // Update existing rating
    $stmt = $con->prepare("UPDATE `doctor_ratings` SET `rating` = ?, `created_at` = CURRENT_TIMESTAMP() WHERE `doctor_id` = ? AND `patient_id` = ?");
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $con->error);
    }
    $stmt->bind_param('dii', $rating, $doctor_id, $patient_id);
  } else {
    // Insert new rating
    $stmt = $con->prepare("INSERT INTO `doctor_ratings` (`doctor_id`, `patient_id`, `rating`) VALUES (?, ?, ?)");
    if (!$stmt) {
      throw new Exception('Prepare failed: ' . $con->error);
    }
    $stmt->bind_param('iid', $doctor_id, $patient_id, $rating);
  }
  
  if (!$stmt->execute()) {
    throw new Exception('Execute failed: ' . $stmt->error . ' (SQL Error: ' . $con->error . ')');
  }
  $stmt->close();

  // Recalculate average and count from doctor_ratings
  $stmt = $con->prepare("
    SELECT AVG(`rating`) AS avg_rating, COUNT(*) AS rating_count
    FROM `doctor_ratings`
    WHERE `doctor_id` = ?
  ");
  $stmt->bind_param('i', $doctor_id);
  $stmt->execute();
  $stats = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $avg_rating   = (float)($stats['avg_rating'] ?? 0);
  $rating_count = (int)($stats['rating_count'] ?? 0);

  // Persist the computed average and count on doctor table
  // Check if the column exists first
  $colCheck = $con->query("SHOW COLUMNS FROM `doctor` LIKE 'ratings(out of 5)'");
  if ($colCheck && $colCheck->num_rows > 0) {
    $stmt = $con->prepare("
      UPDATE `doctor`
      SET `ratings(out of 5)` = ?, `rating_count` = ?
      WHERE `doctor_id` = ?
    ");
    if (!$stmt) {
      throw new Exception('Prepare failed for doctor update: ' . $con->error);
    }
    $stmt->bind_param('dii', $avg_rating, $rating_count, $doctor_id);
    if (!$stmt->execute()) {
      throw new Exception('Failed to update doctor rating: ' . $stmt->error);
    }
    $stmt->close();
  } else {
    // Column doesn't exist, try alternative column name
    error_log('Column "ratings(out of 5)" not found in doctor table');
  }

  echo json_encode([
    'success'       => true,
    'doctor_id'     => $doctor_id,
    'avg_rating'    => round($avg_rating, 2),
    'rating_count'  => $rating_count,
    'your_rating'   => round($rating, 1),
  ]);
} catch (Throwable $e) {
  error_log('Rating error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false, 
    'error' => 'SERVER_ERROR',
    'message' => $e->getMessage() // Include error message for debugging
  ]);
}

ob_end_flush();


