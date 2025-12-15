<?php
require_once __DIR__ . '/includes/header.php';
require_patient_login();

// OPTIONAL: Patient name dynamic
$patient_name = $_SESSION['patient_name'] ?? "Patient";

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartDoc - Patient Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body { background-color: #f8f9fa; }

        .kpi-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            border-radius: 12px;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
    <div class="container py-2">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" class="me-2">
                <path d="M12 2l3.5 3.5-3.5 3.5L8.5 5.5 12 2Zm0 7l7 7-3 3-4-4-4 4-3-3 7-7Z" fill="#0ea5e9"/>
            </svg>
            <strong>SmartDoc</strong>
        </a>
    </div>
</nav>

<div class="d-flex">

    <!-- Sidebar -->
    <div class="bg-light border-end p-3 sidebar">
        <div class="d-grid gap-3">
            <a href="selectSpecialist.php" class="btn btn-outline-primary">Select Symptoms</a>
            <a href="consultation-history.php" class="btn btn-outline-primary">History</a>
            <a href="find-doctors.php" class="btn btn-outline-primary">Browse Doctors</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-grow-1 p-4">

        <div class="alert alert-info alert-dismissible fade show mb-4 w-100" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-hand-thumbs-up me-2" style="font-size: 1.25rem;"></i>
                <div>
                    <strong>Welcome, <?= htmlspecialchars($patient_name) ?>!</strong>
                    <p class="mb-0 small">Find the right doctor for your health.</p>
                </div>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
