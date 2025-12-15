<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$current = basename($_SERVER['PHP_SELF']);
function active($file, $current){ return $current === $file ? ' active' : ''; }
?>
<nav class="list-group list-group-flush">
  <a class="list-group-item list-group-item-action<?= active('index.php',$current) ?>" href="index.php">
    <i class="bi bi-speedometer2 me-2"></i> Dashboard
  </a>
  <a class="list-group-item list-group-item-action<?= active('doctors.php',$current) ?>" href="doctors.php">
    <i class="bi bi-people me-2"></i> Doctors
  </a>
  <a class="list-group-item list-group-item-action<?= active('hospitals.php',$current) ?>" href="hospitals.php">
    <i class="bi bi-building me-2"></i> Hospitals
  </a>
  <a class="list-group-item list-group-item-action<?= active('specializations.php',$current) ?>" href="specializations.php">
    <i class="bi bi-ui-checks me-2"></i> Specializations
  </a>
  <a class="list-group-item list-group-item-action<?= active('consultation-history.php',$current) ?>" href="consultation-history.php">
    <i class="bi bi-clock-history me-2"></i> Consultation History
  </a>
  <a class="list-group-item list-group-item-action<?= active('symptoms.php',$current) ?>" href="symptoms.php">
    <i class="bi bi-thermometer-half me-2"></i> Symptoms
  </a>
  <a class="list-group-item list-group-item-action<?= active('patients.php',$current) ?>" href="patients.php">
    <i class="bi bi-person-hearts me-2"></i> Patients
  </a>
  <a class="list-group-item list-group-item-action<?= active('admins.php',$current) ?>" href="admins.php">
    <i class="bi bi-shield-lock me-2"></i> Admins
  </a>
</nav>


