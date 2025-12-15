<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/auth.php';
require_login();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SmartDoc Admin</title>

  <!-- Frameworks -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Custom dashboard styling (edit this file to tweak the look) -->
  <link href="assets/admin.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- ✅ NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top" aria-label="Primary">
<div class="container py-2">
    <a class="navbar-brand d-flex align-items-center" href="../LandingPage.html">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="me-2">
        <path d="M12 2l3.5 3.5-3.5 3.5L8.5 5.5 12 2Zm0 7l7 7-3 3-4-4-4 4-3-3 7-7Z" fill="#0ea5e9"/>
      </svg>
      <strong>SmartDoc</strong>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i>Home</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<main class="container mt-4 mb-5">
  <?php flash(); ?>
