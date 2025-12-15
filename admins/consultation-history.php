<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

// Fetch all symptom check history
$history = $con->query("
  SELECT 
    sch.check_id,
    sch.symptoms,
    sch.affected_area,
    sch.intensity,
    sch.recommended_specialist_id,
    sch.recommended_specialist_name,
    sch.created_at,
    p.name AS patient_name,
    p.email AS patient_email,
    p.phone AS patient_phone,
    sp.specialization_name,
    sp.description AS specialization_description
  FROM `symptom_check_history` sch
  LEFT JOIN `patient` p ON sch.patient_id = p.patient_ID
  LEFT JOIN `specialization` sp ON sch.recommended_specialist_id = sp.specialization_id
  ORDER BY sch.created_at DESC
");

// Helper to format datetime nicely
function formatDateTimeBangladesh($datetimeStr) {
    if (!$datetimeStr) return "";
    $dt = new DateTime($datetimeStr);
    return $dt->format("d F Y, g:i A"); // e.g., 12 February 2025, 5:30 PM
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SmartDoc Admin — Symptom Check History</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --sd-primary: #0ea5e9;
      --sd-dark: #0b2239;
      --sd-bg: #f8fafc;
    }
    html, body {
      font-family: "Inter", system-ui, sans-serif;
      background: var(--sd-bg);
      color: var(--sd-dark);
    }
    /* Gradient */
    .hero-gradient {
      background: linear-gradient(
        180deg,
        rgba(14,165,233,0.55) 0%,
        rgba(14,165,233,0.25) 35%,
        rgba(14,165,233,0.10) 70%,
        rgba(14,165,233,0.03) 100%
      );
      padding: 2rem 0 1.5rem;
      text-align: center;
    }
    .history-card {
      border-radius: 16px;
      border: none;
      box-shadow: 0 6px 18px rgba(0,0,0,0.08);
      transition: all 0.2s ease;
      background: white;
    }
    .history-card:hover {
      transform: scale(1.02);
      box-shadow: 0 8px 22px rgba(0,0,0,0.12);
    }
    .footer-custom {
      background: #e6f6ff;
      text-align: center;
      padding: 10px;
      font-size: 14px;
      border-top: 1px solid rgba(0,0,0,0.05);
      margin-top: 50px;
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center" href="../LandingPage.html">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" class="me-2">
        <path d="M12 2l3.5 3.5-3.5 3.5L8.5 5.5 12 2Zm0 7l7 7-3 3-4-4-4 4-3-3 7-7Z" fill="#0ea5e9"/>
      </svg>
      <strong>SmartDoc</strong>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="hero-gradient">
  <div class="container">
    <h3 class="fw-semibold">Symptom Check History</h3>
    <p class="text-muted mb-0">Review all patient symptom checks</p>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="container my-5">
  <?php if($history && $history->num_rows > 0): ?>
    <?php while($row = $history->fetch_assoc()): ?>
      <div class="history-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <h5 class="fw-semibold mb-3"><i class="bi bi-clipboard2-heart text-primary me-2"></i>Symptom Check #<?= h($row['check_id']) ?></h5>
            
            <div class="row">
              <div class="col-md-6">
                <?php if($row['patient_name']): ?>
                  <p class="mb-2"><strong><i class="bi bi-person me-2 text-primary"></i>Patient:</strong> <?= h($row['patient_name']) ?></p>
                <?php endif; ?>
                <?php if($row['patient_email']): ?>
                  <p class="mb-2"><strong>Email:</strong> <?= h($row['patient_email']) ?></p>
                <?php endif; ?>
                <?php if($row['patient_phone']): ?>
                  <p class="mb-2"><strong>Phone:</strong> <?= h($row['patient_phone']) ?></p>
                <?php endif; ?>
              </div>
              
              <div class="col-md-6">
                <?php if($row['specialization_name'] || $row['recommended_specialist_name']): ?>
                  <p class="mb-2"><strong><i class="bi bi-heart-pulse me-2 text-success"></i>Recommended Specialist:</strong> <?= h($row['specialization_name'] ?? $row['recommended_specialist_name']) ?></p>
                <?php endif; ?>
                <?php if($row['specialization_description']): ?>
                  <p class="mb-2 text-muted small"><?= h($row['specialization_description']) ?></p>
                <?php endif; ?>
              </div>
            </div>
            
            <?php if($row['symptoms']): ?>
              <p class="mb-2 mt-2"><strong><i class="bi bi-thermometer-half me-2 text-warning"></i>Symptoms:</strong> <?= h($row['symptoms']) ?></p>
            <?php endif; ?>
            
            <?php if($row['affected_area']): ?>
              <p class="mb-2"><strong><i class="bi bi-geo-alt me-2 text-info"></i>Affected Area:</strong> <?= h($row['affected_area']) ?></p>
            <?php endif; ?>
            
            <?php if($row['intensity']): ?>
              <p class="mb-2"><strong><i class="bi bi-bar-chart me-2 text-danger"></i>Intensity:</strong> <?= h(ucfirst($row['intensity'])) ?></p>
            <?php endif; ?>
            
            <?php if($row['created_at']): ?>
              <p class="text-muted small mb-0 mt-2"><i class="bi bi-clock-history me-1"></i><?= formatDateTimeBangladesh($row['created_at']) ?></p>
            <?php endif; ?>
          </div>
          
          <div class="ms-3">
            <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='doctors.php?specialization=<?= urlencode($row['specialization_name'] ?? $row['recommended_specialist_name'] ?? '') ?>'">
              <i class="bi bi-eye me-1"></i>View Doctors
            </button>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <!-- EMPTY STATE (show only if no history found) -->
    <div id="emptyState" class="text-center text-muted my-5">
      <i class="bi bi-journal-x fs-1 text-primary mb-3"></i>
      <p>No symptom check history recorded yet.</p>
    </div>
  <?php endif; ?>
</div>

<!-- FOOTER -->
<div class="footer-custom">
  <small>🇧🇩 Made for Bangladesh • SmartDoc © <?= date('Y') ?></small>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>

