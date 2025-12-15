<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_patient_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

$patient_id = get_patient_id();

// Handle delete request (support both GET and POST)
$delete_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)($_POST['delete_id'] ?? 0);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete'])) {
    $delete_id = (int)($_GET['delete'] ?? 0);
}

if ($delete_id !== null && $delete_id > 0) {
    // First verify the record exists and belongs to this patient (security check)
    $check_stmt = $con->prepare("SELECT check_id FROM `symptom_check_history` WHERE check_id = ? AND patient_id = ?");
    $check_stmt->bind_param('ii', $delete_id, $patient_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Record exists and belongs to patient, proceed with deletion
        $delete_stmt = $con->prepare("DELETE FROM `symptom_check_history` WHERE check_id = ? AND patient_id = ?");
        $delete_stmt->bind_param('ii', $delete_id, $patient_id);
        
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            $check_stmt->close();
            
            // Set success message
            flash('Symptom check history deleted successfully.', 'success');
            
            // Clear any output and redirect
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Location: consultation-history.php');
            exit;
        } else {
            flash('Failed to delete symptom check history. Error: ' . $con->error, 'danger');
            $check_stmt->close();
            $delete_stmt->close();
        }
    } else {
        flash('Record not found or you do not have permission to delete it.', 'warning');
        $check_stmt->close();
    }
}

// Fetch symptom check history with selected doctor information
$history = $con->query("
  SELECT sch.*, sp.specialization_name, 
         sch.selected_doctor_id, sch.selected_doctor_name, sch.selected_hospital_name
  FROM `symptom_check_history` sch
  LEFT JOIN `specialization` sp ON sch.recommended_specialist_id = sp.specialization_id
  WHERE sch.patient_id = $patient_id
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
  <title>SmartDoc — View History</title>
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
    }
    .history-card:hover {
      transform: scale(1.02);
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
        <li class="nav-item">
          <a class="nav-link" href="index.php">
            <i class="bi bi-house me-1"></i>Home
          </a>
        </li>
        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<div class="hero-gradient">
  <div class="container">
    <h3 class="fw-semibold">Your Health History</h3>
    <p class="text-muted mb-0">Review your past symptom checks</p>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="container my-5">
  <?php flash(); ?>
  <?php if($history && $history->num_rows > 0): ?>
    <?php while($row = $history->fetch_assoc()): ?>
      <!-- Example card (PHP will loop this) -->
      <div class="history-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h5 class="fw-semibold mb-2"><i class="bi bi-clipboard2-heart text-primary me-2"></i>Symptom Check</h5>
            <?php if($row['symptoms']): ?>
              <p class="mb-1"><strong>Symptoms:</strong> <?= h($row['symptoms']) ?></p>
            <?php endif; ?>
            <?php if($row['affected_area']): ?>
              <p class="mb-1"><strong>Affected Area:</strong> <?= h($row['affected_area']) ?></p>
            <?php endif; ?>
            <?php if($row['intensity']): ?>
              <p class="mb-1"><strong>Intensity:</strong> <?= h(ucfirst($row['intensity'])) ?></p>
            <?php endif; ?>
            <?php if($row['specialization_name'] || $row['recommended_specialist_name']): ?>
              <p class="mb-1"><strong>Recommended Specialist:</strong> <?= h($row['specialization_name'] ?? $row['recommended_specialist_name']) ?></p>
            <?php endif; ?>
            <?php if($row['selected_doctor_name']): ?>
              <div class="mb-2 p-2 bg-light rounded border-start border-3 border-success">
                <p class="mb-1"><strong><i class="bi bi-person-check text-success me-1"></i>Selected Doctor:</strong> <?= h($row['selected_doctor_name']) ?></p>
                <?php if($row['selected_hospital_name']): ?>
                  <p class="mb-0 text-muted small"><i class="bi bi-building me-1"></i><?= h($row['selected_hospital_name']) ?></p>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if($row['created_at']): ?>
              <p class="text-muted small mb-0"><i class="bi bi-clock-history me-1"></i><?= formatDateTimeBangladesh($row['created_at']) ?></p>
            <?php endif; ?>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="window.location.href='find-doctors.php?specialty=<?= urlencode($row['specialization_name'] ?? $row['recommended_specialist_name'] ?? '') ?>'">
              <i class="bi bi-eye me-1"></i>View Details
            </button>
            <?php if (isset($row['check_id']) && $row['check_id'] > 0): ?>
              <form method="POST" action="consultation-history.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this symptom check history? This action cannot be undone.');">
                <input type="hidden" name="delete_id" value="<?= (int)$row['check_id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                  <i class="bi bi-trash me-1"></i>Delete
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <!-- EMPTY STATE (show only if no history found) -->
    <div id="emptyState" class="text-center text-muted my-5">
      <i class="bi bi-journal-x fs-1 text-primary mb-3"></i>
      <p>No history recorded yet.</p>
    </div>
  <?php endif; ?>
</div>

<!-- FOOTER -->
<div class="footer-custom">
  <small>🇧🇩 Made for Bangladesh • SmartDoc © <?= date('Y') ?></small>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>

