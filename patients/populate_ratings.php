<?php
/**
 * Populate Random Ratings for All Doctors
 * This script assigns random ratings to all doctors from multiple patients
 * to make the system look more realistic with existing ratings.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';

// Allow running without login for this one-time population script
// Remove this file after running for security

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
  <title>Populate Doctor Ratings</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
  </style>
</head>
<body>
  <h1>Populate Random Ratings for All Doctors</h1>
  <?php

try {
  // Get all doctors
  $doctorsRes = $con->query("SELECT `doctor_id`, `name` FROM `doctor` ORDER BY `doctor_id`");
  if (!$doctorsRes) {
    throw new Exception('Failed to fetch doctors: ' . $con->error);
  }
  
  $doctors = [];
  while ($row = $doctorsRes->fetch_assoc()) {
    $doctors[] = $row;
  }
  
  echo "<p class='info'>Found " . count($doctors) . " doctors.</p>";
  
  // Get all patients (or use patient IDs 1-10 as fallback)
  $patientsRes = $con->query("SELECT `patient_ID` FROM `patient` ORDER BY `patient_ID` LIMIT 20");
  $patients = [];
  if ($patientsRes && $patientsRes->num_rows > 0) {
    while ($row = $patientsRes->fetch_assoc()) {
      $patients[] = (int)$row['patient_ID'];
    }
  }
  
  // If no patients found, create some dummy patient IDs for rating purposes
  if (empty($patients)) {
    $patients = range(1, 10);
    echo "<p class='info'>No patients found. Using dummy patient IDs 1-10 for ratings.</p>";
  } else {
    echo "<p class='info'>Found " . count($patients) . " patients to use for ratings.</p>";
  }
  
  // Ensure doctor_ratings table has proper structure
  $checkPK = $con->query("SHOW KEYS FROM `doctor_ratings` WHERE Key_name = 'PRIMARY'");
  if (!$checkPK || $checkPK->num_rows === 0) {
    @$con->query("ALTER TABLE `doctor_ratings` ADD PRIMARY KEY (`doctor_id`, `patient_id`)");
  }
  
  $totalRatings = 0;
  $errors = 0;
  
  echo "<p>Starting to populate ratings...</p>";
  echo "<ul>";
  
  foreach ($doctors as $doctor) {
    $doctorId = (int)$doctor['doctor_id'];
    $doctorName = h($doctor['name']);
    
    // Each doctor gets ratings from 3-8 random patients
    $numRatings = rand(3, 8);
    $numRatings = min($numRatings, count($patients)); // Don't exceed available patients
    
    // Randomly select patients
    $patientKeys = array_rand($patients, $numRatings);
    if (!is_array($patientKeys)) {
      $patientKeys = [$patientKeys];
    }
    $selectedPatients = [];
    foreach ($patientKeys as $key) {
      $selectedPatients[] = $patients[$key];
    }
    
    $doctorRatings = 0;
    
    foreach ($selectedPatients as $patientId) {
      // Generate random rating between 3.0 and 5.0 (more realistic, slightly positive bias)
      // With 20% chance of lower ratings (2.0-2.9)
      $rating = (rand(0, 100) < 20) 
        ? round(rand(20, 29) / 10, 1)  // 2.0-2.9
        : round(rand(30, 50) / 10, 1);  // 3.0-5.0
      
      // Check if rating already exists
      $stmt = $con->prepare("SELECT `rating` FROM `doctor_ratings` WHERE `doctor_id` = ? AND `patient_id` = ? LIMIT 1");
      $stmt->bind_param('ii', $doctorId, $patientId);
      $stmt->execute();
      $existing = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      
      if ($existing) {
        // Update existing rating
        $stmt = $con->prepare("UPDATE `doctor_ratings` SET `rating` = ?, `created_at` = CURRENT_TIMESTAMP() WHERE `doctor_id` = ? AND `patient_id` = ?");
        $stmt->bind_param('dii', $rating, $doctorId, $patientId);
      } else {
        // Insert new rating
        $stmt = $con->prepare("INSERT INTO `doctor_ratings` (`doctor_id`, `patient_id`, `rating`) VALUES (?, ?, ?)");
        $stmt->bind_param('iid', $doctorId, $patientId, $rating);
      }
      
      if ($stmt->execute()) {
        $doctorRatings++;
        $totalRatings++;
      } else {
        $errors++;
        echo "<li class='error'>Error rating doctor #$doctorId ($doctorName): " . $stmt->error . "</li>";
      }
      $stmt->close();
    }
    
    // Recalculate average rating for this doctor
    $stmt = $con->prepare("
      SELECT AVG(`rating`) AS avg_rating, COUNT(*) AS rating_count
      FROM `doctor_ratings`
      WHERE `doctor_id` = ?
    ");
    $stmt->bind_param('i', $doctorId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $avgRating = (float)($stats['avg_rating'] ?? 0);
    $ratingCount = (int)($stats['rating_count'] ?? 0);
    
    // Update doctor table
    $colCheck = $con->query("SHOW COLUMNS FROM `doctor` LIKE 'ratings(out of 5)'");
    if ($colCheck && $colCheck->num_rows > 0) {
      $stmt = $con->prepare("
        UPDATE `doctor`
        SET `ratings(out of 5)` = ?, `rating_count` = ?
        WHERE `doctor_id` = ?
      ");
      $stmt->bind_param('dii', $avgRating, $ratingCount, $doctorId);
      if (!$stmt->execute()) {
        echo "<li class='error'>Failed to update doctor #$doctorId: " . $stmt->error . "</li>";
        $errors++;
      }
      $stmt->close();
    }
    
    if ($doctorRatings > 0) {
      echo "<li class='success'>Doctor #$doctorId ($doctorName): Added $doctorRatings ratings, Average: " . round($avgRating, 2) . "/5 ($ratingCount total)</li>";
    }
  }
  
  echo "</ul>";
  
  echo "<h2 class='success'>✓ Completed!</h2>";
  echo "<p class='success'>Total ratings added/updated: $totalRatings</p>";
  if ($errors > 0) {
    echo "<p class='error'>Errors encountered: $errors</p>";
  }
  
} catch (Exception $e) {
  echo "<p class='error'>Error: " . h($e->getMessage()) . "</p>";
  error_log('Populate ratings error: ' . $e->getMessage());
}

?>
</body>
</html>

