<?php
ob_start();
require_once __DIR__ . '/includes/header.php';
require_patient_login();

$patientId = get_patient_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index.php'); exit;
}

function norm($v){ $v = trim((string)($v ?? '')); return $v === '' ? null : $v; }

$name    = norm($_POST['name'] ?? null);
$age     = norm($_POST['age'] ?? null);
$gender  = norm($_POST['gender'] ?? null);
$phone   = norm($_POST['phone'] ?? null);
$loc     = norm($_POST['patient_location'] ?? null);

// Validation
$errors = [];
if (!$name) $errors[] = 'Name is required';
if ($age !== null) {
  if (!ctype_digit((string)$age) || (int)$age < 0) $errors[] = 'Age must be a non-negative number';
}
if ($gender !== null && !in_array($gender, ['Male','Female'], true)) $errors[] = 'Invalid gender';
if ($phone !== null && strlen($phone) > 30) $errors[] = 'Phone too long';
if ($loc !== null && strlen($loc) > 250) $errors[] = 'Location too long';

if ($errors) {
  flash(implode('. ', $errors), 'danger');
  header('Location: index.php'); exit;
}

// Build update with only provided fields; keep unspecified as-is
$stmt = $con->prepare("
  UPDATE `patient`
     SET name = COALESCE(?, name),
         age = COALESCE(?, age),
         gender = COALESCE(?, gender),
         phone = COALESCE(?, phone),
         patient_location = COALESCE(?, patient_location)
   WHERE patient_ID = ?
");
$ageParam = $age !== null ? (int)$age : null;
$stmt->bind_param('sisssi', $name, $ageParam, $gender, $phone, $loc, $patientId);

try {
  $stmt->execute();
  // Refresh session name if changed
  if ($name !== null) { $_SESSION['patient_name'] = $name; }
  flash('Profile updated successfully','success');
} catch (mysqli_sql_exception $e) {
  flash('Update failed: '.$e->getMessage(), 'danger');
}

header('Location: index.php'); exit;

