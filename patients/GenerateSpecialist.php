<?php
ob_start();
require_once __DIR__ . '/includes/auth.php';
require_patient_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

error_log('specialist GET param: ' . ($_GET['specialist_name'] ?? 'none'));

// Read filters from query
$symptomIds = array_filter(array_map('intval', explode(',', $_GET['symptom_ids'] ?? '')));
$areaFilter = trim($_GET['area'] ?? '');
$durationFilter = trim($_GET['duration'] ?? '');
$mlSpecialistName = trim($_GET['specialist_name'] ?? '');
$manualSpecialist = trim($_GET['specialist'] ?? '');
if ($manualSpecialist !== '') {
  $mlSpecialistName = $manualSpecialist;
}
$hasMlResult = ($mlSpecialistName !== '');
$hasInputFilters = (!empty($symptomIds) || $areaFilter !== '' || $durationFilter !== '');

$specializationScores = [];
$specializations = [];
$dbTopSpecializationId = null;
$dbTopSpecializationName = null;
$dbMatchingDoctors = [];
$mlSpecialistId = null;
$mlMatchingDoctors = [];

$where = [];
if (!empty($symptomIds)) {
  $in = implode(',', array_map('intval', $symptomIds));
  $where[] = "symptom_id IN ($in)";
}
if ($areaFilter !== '') {
  $safeArea = '%' . $con->real_escape_string($areaFilter) . '%';
  $where[] = "IFNULL(AffectedArea,'') LIKE '$safeArea'";
}
if ($durationFilter !== '') {
  $safeDur = '%' . $con->real_escape_string($durationFilter) . '%';
  $where[] = "IFNULL(AffectedDuration,'') LIKE '$safeDur'";
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

if ($hasInputFilters) {
  $sql = "
    SELECT s.specialization_id, sp.specialization_name, COUNT(*) as score
    FROM `symptom` s
    LEFT JOIN `specialization` sp ON sp.specialization_id = s.specialization_id
    $whereSql
    GROUP BY s.specialization_id, sp.specialization_name
    ORDER BY score DESC, sp.specialization_name ASC
    LIMIT 5
  ";
  $res = $con->query($sql);
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $sid = (int)$row['specialization_id'];
      if ($sid > 0) {
        $specializationScores[$sid] = (int)$row['score'];
        $specializations[$sid] = $row['specialization_name'];
      }
    }
  }
}

if ($hasInputFilters && !empty($specializationScores)) {
  arsort($specializationScores);
  $dbTopSpecializationId = array_key_first($specializationScores);
  $dbTopSpecializationName = $specializations[$dbTopSpecializationId] ?? null;

  $docSql = "
    SELECT d.doctor_id, d.name, d.designation, h.hospital_name, h.address AS hospital_address
    FROM `doctor` d
    LEFT JOIN `hospital` h ON h.hospital_id = d.hospital_id
    WHERE d.specialization_id = {$dbTopSpecializationId}
    ORDER BY d.name ASC
    LIMIT 10
  ";
  $docRes = $con->query($docSql);
  if ($docRes) {
    while ($d = $docRes->fetch_assoc()) {
      $dbMatchingDoctors[] = $d;
    }
  }
}

if ($hasMlResult) {
  // Decode URL encoding
  $mlSpecialistName = urldecode($mlSpecialistName);
  
  // Try exact match first
  $stmt = $con->prepare("SELECT specialization_id FROM `specialization` WHERE specialization_name = ? LIMIT 1");
  $stmt->bind_param('s', $mlSpecialistName);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  if ($row) {
    $mlSpecialistId = (int)$row['specialization_id'];
  }
  $stmt->close();
  
  // If exact match failed, try partial match (for cases like "Dermatologist (Skin & Sex)" -> "Dermatologist")
  if (!$mlSpecialistId) {
    // Extract base name (before parentheses or special characters)
    $baseName = preg_replace('/\s*\(.*?\)\s*/', '', $mlSpecialistName); // Remove (Skin & Sex)
    $baseName = trim($baseName);
    
    // Try LIKE match for base name
    $stmt = $con->prepare("SELECT specialization_id FROM `specialization` WHERE specialization_name LIKE ? LIMIT 1");
    $likePattern = $baseName . '%';
    $stmt->bind_param('s', $likePattern);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
      $mlSpecialistId = (int)$row['specialization_id'];
    }
    $stmt->close();
    
    // If still not found, try matching from the beginning (handles variations)
    if (!$mlSpecialistId) {
      $stmt = $con->prepare("SELECT specialization_id FROM `specialization` WHERE specialization_name LIKE ? LIMIT 1");
      $likePattern = '%' . $baseName . '%';
      $stmt->bind_param('s', $likePattern);
      $stmt->execute();
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      if ($row) {
        $mlSpecialistId = (int)$row['specialization_id'];
      }
      $stmt->close();
    }
  }

  if ($mlSpecialistId) {
    $docSql = "
      SELECT d.doctor_id, d.name, d.designation, h.hospital_name, h.address AS hospital_address
      FROM `doctor` d
      LEFT JOIN `hospital` h ON h.hospital_id = d.hospital_id
      WHERE d.specialization_id = {$mlSpecialistId}
      ORDER BY d.name ASC
      LIMIT 10
    ";
    $docRes = $con->query($docSql);
    if ($docRes) {
      while ($d = $docRes->fetch_assoc()) {
        $mlMatchingDoctors[] = $d;
      }
    }
  }
}

// Validate ML recommendation against affected area AND symptoms
// If ML recommends a specialist that doesn't match, prefer database recommendation
$useMlRecommendation = false;
if ($hasMlResult && $mlSpecialistId && $areaFilter !== '') {
  $areaLower = strtolower($areaFilter);
  $mlSpecialistLower = strtolower($mlSpecialistName);
  
  // Get symptom names for context-aware validation
  $symptomNames = [];
  if (!empty($symptomIds)) {
    $in = implode(',', array_map('intval', $symptomIds));
    $symptomRes = $con->query("SELECT symptom_name FROM `symptom` WHERE symptom_id IN ($in)");
    if ($symptomRes) {
      while ($row = $symptomRes->fetch_assoc()) {
        $symptomNames[] = strtolower($row['symptom_name']);
      }
    }
  }
  $symptomText = implode(' ', $symptomNames);
  
  // Special exceptions: symptoms that override area-based validation
  // These are symptoms that are correctly associated with specialists even if area seems wrong
  $symptomExceptions = [
    'edema' => ['cardiologist', 'nephrologist'], // Leg edema can be heart/kidney related
    'swelling in legs' => ['cardiologist', 'nephrologist'],
    'swelling' => ['cardiologist', 'nephrologist'],
    'shortness of breath' => ['cardiologist', 'pulmonologist'],
    'palpitations' => ['cardiologist'],
    'chest pain' => ['cardiologist'],
    'heart' => ['cardiologist'],
    'dizziness' => ['neurologist', 'cardiologist'],
    'vertigo' => ['neurologist', 'otolaryngologist'],
    'headache' => ['neurologist'],
    'severe headache' => ['neurologist'],
    'rash' => ['dermatologist'],
    'itching' => ['dermatologist'],
    'abdominal pain' => ['gastroenterologist'],
    'diarrhea' => ['gastroenterologist'],
    'nausea' => ['gastroenterologist'],
    'vomiting' => ['gastroenterologist']
  ];
  
  // Check if any symptom exception applies
  $hasException = false;
  foreach ($symptomExceptions as $symptomKey => $validSpecialists) {
    if (strpos($symptomText, $symptomKey) !== false) {
      foreach ($validSpecialists as $validSpec) {
        if (strpos($mlSpecialistLower, $validSpec) !== false) {
          $hasException = true;
          $isValidMatch = true;
          break 2;
        }
      }
    }
  }
  
  if (!$hasException) {
    // Area-to-specialist validation mapping (only if no symptom exception)
    $areaSpecialistMap = [
      'brain' => ['neurologist', 'neurosurgeon', 'psychiatrist', 'neuropsychiatrist'],
      'head' => ['neurologist', 'neurosurgeon', 'otolaryngologist', 'ent', 'ophthalmologist'],
      'nose/throat' => ['otolaryngologist', 'ent', 'allergist'],
      'chest/lungs' => ['pulmonologist', 'cardiologist', 'chest specialist'],
      'chest' => ['cardiologist', 'pulmonologist', 'chest specialist'],
      'stomach' => ['gastroenterologist', 'general physician'],
      'abdomen' => ['gastroenterologist', 'general physician'],
      'skin' => ['dermatologist'],
      'eyes' => ['ophthalmologist', 'eye specialist'],
      'knee/hip/shoulder' => ['orthopedist', 'orthopedic surgeon'],
      'legs' => ['orthopedist', 'orthopedic surgeon', 'cardiologist', 'nephrologist'], // Allow cardio/nephro for edema
      'shoulder' => ['orthopedist', 'orthopedic surgeon'],
      'back' => ['orthopedist', 'neurosurgeon', 'spine specialist']
    ];
    
    // Check if ML recommendation is appropriate for the area
    $expectedSpecialists = $areaSpecialistMap[$areaLower] ?? [];
    $isValidMatch = false;
    
    if (!empty($expectedSpecialists)) {
      foreach ($expectedSpecialists as $expected) {
        if (strpos($mlSpecialistLower, $expected) !== false) {
          $isValidMatch = true;
          break;
        }
      }
    } else {
      // If area not in map, allow any specialist (unknown area)
      $isValidMatch = true;
    }
  }
  
  // Check for obvious mismatches (only if no exception)
  if (!$hasException) {
    $mismatches = [
      'brain' => ['dermatologist', 'skin specialist'],
      'head' => ['dermatologist', 'skin specialist'],
      'skin' => ['neurologist', 'neurosurgeon'] // Allow cardiologist for skin (some heart conditions cause skin issues)
    ];
    
    if (isset($mismatches[$areaLower])) {
      foreach ($mismatches[$areaLower] as $mismatch) {
        if (strpos($mlSpecialistLower, $mismatch) !== false) {
          $isValidMatch = false;
          error_log("ML recommendation mismatch: Area='$areaFilter', ML='$mlSpecialistName'");
          break;
        }
      }
    }
  }
  
  $useMlRecommendation = $isValidMatch;
} else {
  // If no area filter or no ML result, use ML if available
  $useMlRecommendation = $hasMlResult && $mlSpecialistId;
}

// Prioritize: Valid ML > Database > Invalid ML
if ($useMlRecommendation && $mlSpecialistId) {
  $ctaSpecializationId = $mlSpecialistId;
  $ctaSpecializationName = $mlSpecialistName;
  $nearbyDoctors = $mlMatchingDoctors;
} elseif ($dbTopSpecializationId) {
  $ctaSpecializationId = $dbTopSpecializationId;
  $ctaSpecializationName = $dbTopSpecializationName;
  $nearbyDoctors = $dbMatchingDoctors;
} elseif ($mlSpecialistId) {
  // Fallback to ML even if invalid (better than nothing)
  $ctaSpecializationId = $mlSpecialistId;
  $ctaSpecializationName = $mlSpecialistName;
  $nearbyDoctors = $mlMatchingDoctors;
} else {
  $ctaSpecializationId = null;
  $ctaSpecializationName = null;
  $nearbyDoctors = [];
}

$hasDbResult = ($dbTopSpecializationName !== null);
$hasRecommendation = ($ctaSpecializationId !== null);
$displaySpecialistName = $ctaSpecializationName ?? 'General Physician';
$displaySpecialistId = $ctaSpecializationId;
$doctorLink = $displaySpecialistId ? ('find-doctors.php?specialty=' . urlencode($displaySpecialistName ?? '')) : 'find-doctors.php';

// Get the current check_id for doctor selection (most recent one for this patient)
$currentCheckId = null;
if ($hasRecommendation && function_exists('get_patient_id')) {
  $patientId = (int) get_patient_id();
  if ($patientId > 0) {
    // Try to get the check_id from the session or query, otherwise get the most recent one
    if (isset($currentCheckId) && $currentCheckId > 0) {
      // Already set above
    } else {
      $checkRes = $con->query("SELECT check_id FROM `symptom_check_history` WHERE patient_id = $patientId ORDER BY created_at DESC LIMIT 1");
      if ($checkRes && $checkRow = $checkRes->fetch_assoc()) {
        $currentCheckId = (int)$checkRow['check_id'];
      }
    }
  }
}

// Fetch specialist description (if available) from specialization table
$specializationDescription = null;
if ($displaySpecialistId) {
  if ($stmtDesc = $con->prepare("SELECT description FROM `specialization` WHERE specialization_id = ? LIMIT 1")) {
    $stmtDesc->bind_param('i', $displaySpecialistId);
    if ($stmtDesc->execute()) {
      $resDesc = $stmtDesc->get_result();
      if ($rowDesc = $resDesc->fetch_assoc()) {
        $specializationDescription = $rowDesc['description'] ?? null;
      }
    }
    $stmtDesc->close();
  }
}

// ------------------------------------------------------------------
// Save this recommendation into patient's consultation history
// so it appears on consultation-history.php
// ------------------------------------------------------------------
if ($hasRecommendation && function_exists('get_patient_id')) {
  $patientId = (int) get_patient_id();

  if ($patientId > 0) {
    // Pull optional context from query so it can be shown in history/dashboard
    $symptomSummary   = trim($_GET['symptoms'] ?? '');
    $affectedArea     = trim($_GET['area'] ?? '');
    $intensity        = trim($_GET['duration'] ?? '');

    // Fallback: if no free‑text summary provided but we received symptom_ids,
    // rebuild a comma‑separated list of symptom names from DB.
    if ($symptomSummary === '' && !empty($symptomIds)) {
      $in = implode(',', array_map('intval', $symptomIds));
      $namesRes = $con->query("SELECT symptom_name FROM `symptom` WHERE symptom_id IN ($in) ORDER BY symptom_name");
      if ($namesRes) {
        $names = [];
        while ($r = $namesRes->fetch_assoc()) {
          if (!empty($r['symptom_name'])) {
            $names[] = $r['symptom_name'];
          }
        }
        if (!empty($names)) {
          $symptomSummary = implode(', ', $names);
        }
      }
    }

    // If area/duration query params are empty, fall back to filters we already parsed
    if ($affectedArea === '' && $areaFilter !== '') {
      $affectedArea = $areaFilter;
    }
    if ($intensity === '' && $durationFilter !== '') {
      $intensity = $durationFilter;
    }

    $recommendedId    = $displaySpecialistId ?: null;
    $recommendedName  = $displaySpecialistName ?: null;

    $stmt = $con->prepare("
      INSERT INTO `symptom_check_history`
        (patient_id, symptoms, affected_area, intensity, recommended_specialist_id, recommended_specialist_name)
      VALUES (?, ?, ?, ?, ?, ?)
    ");

    $currentCheckId = null;
    if ($stmt) {
      $stmt->bind_param(
        'isssis',
        $patientId,
        $symptomSummary,
        $affectedArea,
        $intensity,
        $recommendedId,
        $recommendedName
      );
      $stmt->execute();
      $currentCheckId = $con->insert_id; // Get the ID of the inserted record
      $stmt->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SmartDoc — Specialist Recommendation</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --sd-primary: #0ea5e9;
      --sd-dark: #0b2239;
      --sd-accent: #10b981;
      --sd-bg: #f8fafc;
    }
    html, body {
      font-family: "Inter", system-ui, sans-serif;
      color: var(--sd-dark);
      background: var(--sd-bg);
      scroll-behavior: smooth;
    }
    .hero-gradient {
      background: linear-gradient(180deg, rgba(14,165,233,0.55) 0%, rgba(14,165,233,0.25) 35%, rgba(14,165,233,0.10) 70%, rgba(14,165,233,0.03) 100%);
      padding-top: 2rem;
      padding-bottom: 1rem;
    }
    .result-card {
      border: none;
      border-radius: 16px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      background: white;
    }
    .result-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0,0,0,0.1); }
    .result-card h4 { color: var(--sd-primary); font-weight: 600; }
    #doctors-container .card {
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      box-shadow: 0 6px 18px rgba(16, 24, 40, 0.06);
      transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    #doctors-container .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 24px rgba(16, 24, 40, 0.12);
    }
    .badge-soft {
      background: #ecfeff;
      color: #0369a1;
      border: 1px solid #bae6fd;
    }
    .distance-info {
      margin-top: 0.5rem;
    }
    .card.border-success {
      transition: all 0.3s ease;
    }
    .card.border-success:hover {
      box-shadow: 0 8px 20px rgba(16, 185, 129, 0.2) !important;
    }
    #use-location-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    #location-status {
      display: inline-block;
    }
    .footer-custom {
      background: #e6f6ff;
      text-align: center;
      padding: 10px;
      font-size: 14px;
      color: #0b2239;
      margin-top: 40px;
      border-top: 1px solid rgba(0,0,0,0.05);
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top" aria-label="Primary">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center" href="../LandingPage.html">
      <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="me-2">
        <path d="M12 2l3.5 3.5-3.5 3.5L8.5 5.5 12 2Zm0 7l7 7-3 3-4-4-4 4-3-3 7-7Z" fill="#0ea5e9"/>
      </svg>
      <strong>SmartDoc</strong>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
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
<div class="hero-gradient text-center">
  <div class="container">
    <h3 class="fw-semibold text-dark">Your Specialist Recommendation</h3>
    <p class="text-muted mb-0">SmartDoc has analyzed your symptoms</p>
  </div>
  </div>
<div class="container my-5">
  <div class="result-card p-5 text-center mx-auto" style="max-width: 700px;">
    <?php if (!$hasRecommendation): ?>
      <i class="bi bi-question-circle fs-1 text-primary mb-3"></i>
      <h4>Select symptoms to continue</h4>
      <p class="text-muted mt-3">Please go back and add your symptoms, affected area, or duration so SmartDoc can prepare a recommendation.</p>
      <div class="mt-4 d-flex justify-content-center gap-3">
        <a href="selectSpecialist.php" class="btn btn-primary px-4"><i class="bi bi-sliders me-2"></i>Select Symptoms</a>
        <a href="symptom-checker.php" class="btn btn-outline-secondary px-4"><i class="bi bi-robot me-2"></i>Use Symptom Checker</a>
          </div>
        <?php else: ?>
      <i class="bi bi-heart-pulse-fill fs-1 text-primary mb-3"></i>
      <h4>Based on your symptoms, you should visit a:</h4>
      <h3 class="fw-bold text-success mt-3" id="specialist-name"><?= h($displaySpecialistName ?? 'General Physician') ?></h3>
      <?php if (!empty($specializationDescription)): ?>
        <p class="text-muted mt-2" style="max-width: 560px; margin: 0 auto;">
          <?= nl2br(h($specializationDescription)) ?>
        </p>
      <?php endif; ?>
      <p class="text-muted mt-2">You can now browse nearby doctors based on this specialization.</p>
      <div class="mt-4 d-flex justify-content-center gap-3 flex-wrap">
        <button id="find-doctors-btn" class="btn btn-primary px-4" 
                data-specialization-id="<?= $displaySpecialistId ?>" 
                data-specialty-name="<?= h($displaySpecialistName ?? '') ?>">
          <i class="bi bi-search-heart me-2"></i>Find Nearby Doctors
        </button>
        <a href="selectSpecialist.php" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left-circle me-2"></i>Go Back</a>
                </div>
              <?php endif; ?>
            </div>
      </div>

<!-- Doctors Container (Red Rectangle Area) -->
<div class="container my-5" id="doctors-container" style="display: none;">
  <h4 class="mb-4 text-center fw-semibold">Recommended Doctors</h4>
  
  <!-- Location Button -->
  <div class="mb-3 text-center">
    <button id="use-location-btn" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-geo-alt-fill me-1"></i>Use My Current Location
    </button>
    <span id="location-status" class="ms-2 small text-muted"></span>
  </div>
  
  <div class="row g-3" id="doctors-grid">
    <!-- Doctors will be loaded here via AJAX -->
  </div>
</div>

<div class="footer-custom">
  <small> Made for Bangladesh  SmartDoc © <?= date('Y') ?></small>
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pass PHP check_id to JavaScript
const CURRENT_CHECK_ID = <?= $currentCheckId ?? 'null' ?>;

document.addEventListener('DOMContentLoaded', () => {
  const findDoctorsBtn = document.getElementById('find-doctors-btn');
  const doctorsContainer = document.getElementById('doctors-container');
  const doctorsGrid = document.getElementById('doctors-grid');
  
  if (!findDoctorsBtn) return;
  
  findDoctorsBtn.addEventListener('click', async () => {
    const specializationId = findDoctorsBtn.getAttribute('data-specialization-id');
    const specialtyName = findDoctorsBtn.getAttribute('data-specialty-name');
    
    if (!specializationId && !specialtyName) {
      alert('Invalid specialization');
      return;
    }
    
    // Disable button and show loading
    findDoctorsBtn.disabled = true;
    const originalHtml = findDoctorsBtn.innerHTML;
    findDoctorsBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
    
    try {
      // Build URL with parameters
      const params = new URLSearchParams();
      if (specializationId && specializationId !== 'null' && specializationId !== '0') {
        params.append('specialization_id', specializationId);
      }
      if (specialtyName) {
        // Decode and encode properly to handle special characters
        const decodedName = decodeURIComponent(specialtyName);
        params.append('specialty', decodedName);
      }
      
      const response = await fetch(`fetch_doctors_ajax.php?${params.toString()}`);
      const data = await response.json();
      
      if (!data.success) {
        doctorsGrid.innerHTML = `
          <div class="col-12">
            <div class="alert alert-danger d-flex align-items-center" role="alert">
              <i class="bi bi-exclamation-circle me-2"></i>
              <div>${data.message || 'Error loading doctors. Please try again.'}</div>
            </div>
          </div>
        `;
        doctorsContainer.style.display = 'block';
        findDoctorsBtn.disabled = false;
        findDoctorsBtn.innerHTML = originalHtml;
        doctorsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      
      if (!data.doctors || data.doctors.length === 0) {
        doctorsGrid.innerHTML = `
          <div class="col-12">
            <div class="alert alert-info d-flex align-items-center" role="alert">
              <i class="bi bi-info-circle me-2"></i>
              <div>No doctors found for this specialization. <a href="find-doctors.php?specialty=${encodeURIComponent(specialtyName)}" class="alert-link">View all doctors</a></div>
            </div>
          </div>
        `;
        doctorsContainer.style.display = 'block';
        findDoctorsBtn.disabled = false;
        findDoctorsBtn.innerHTML = originalHtml;
        doctorsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      
      // Clear existing content
      doctorsGrid.innerHTML = '';
      
      // Render doctors (show all returned, up to 20)
      data.doctors.forEach(doctor => {
        const avgRating = doctor.ratings || 0;
        const ratingCount = doctor.rating_count || 0;
        const roundedRating = Math.round(avgRating);
        
        const hospitalAddress = doctor.hospital_address || '';
        const cardHtml = `
          <div class="col-md-6 col-lg-3">
            <div class="card h-100">
              <div class="card-body d-flex flex-column">
                <!-- Header: avatar + name + specialization -->
                <div class="d-flex align-items-start gap-3 mb-2">
                  <div class="rounded-3 d-inline-grid"
                       style="width:56px;height:56px;background:#e0f2fe;color:#0369a1;display:grid;place-items:center;flex-shrink:0;">
                    <i class="bi bi-person-badge fs-5"></i>
                  </div>
                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <h5 class="card-title mb-0" style="font-size: 1rem;">${escapeHtml(doctor.name)}</h5>
                      <span class="badge-soft rounded-pill px-2 py-1" style="font-size: 0.75rem;">
                        ${escapeHtml(doctor.specialization_name || 'General')}
                      </span>
                    </div>
                    ${doctor.designation ? `<div class="text-muted small mt-1">${escapeHtml(doctor.designation)}</div>` : ''}
                  </div>
                </div>

                <!-- Rating block -->
                <div class="mb-2 d-flex align-items-center flex-wrap gap-2">
                  <span class="text-warning doctor-stars">
                    ${Array.from({length: 5}, (_, i) => 
                      `<i class="bi ${i < roundedRating ? 'bi-star-fill' : 'bi-star'}"></i>`
                    ).join('')}
                  </span>
                  <span class="small text-muted rating-summary">
                    ${avgRating.toFixed(1)}/5
                    ${ratingCount > 0 ? `(${ratingCount} rating${ratingCount > 1 ? 's' : ''})` : '(no ratings yet)'}
                  </span>
                </div>

                <!-- Details: hospital + address + distance -->
                <div class="mb-3">
                  ${doctor.hospital_name ? `
                    <div class="small text-muted">
                      <i class="bi bi-building me-1"></i>${escapeHtml(doctor.hospital_name)}
                    </div>
                  ` : ''}
                  ${hospitalAddress ? `
                    <div class="small text-muted">
                      <i class="bi bi-geo-alt me-1"></i>${escapeHtml(hospitalAddress)}
                    </div>
                  ` : ''}
                  ${hospitalAddress ? `
                  <div class="distance-info mt-1"
                       data-address="${escapeHtml(hospitalAddress)}"
                       style="display: none;">
                    <span class="badge bg-secondary">
                      <i class="bi bi-signpost-2 me-1"></i>
                      <span class="distance-text">Calculating...</span>
                    </span>
                  </div>
                  ` : ''}
                </div>

                <!-- Actions -->
                <div class="mt-auto">
                  <button class="btn btn-sm btn-success w-100 mb-2 select-doctor-btn" 
                          data-doctor-id="${doctor.doctor_id}"
                          data-check-id="${CURRENT_CHECK_ID || ''}"
                          data-doctor-name="${escapeHtml(doctor.name)}"
                          data-hospital-name="${escapeHtml(doctor.hospital_name || '')}">
                    <i class="bi bi-check-circle me-1"></i>Select
                  </button>
                  ${doctor.website_url ? `
                    <a href="${escapeHtml(doctor.website_url)}" target="_blank"
                       class="btn btn-sm btn-primary w-100">
                      <i class="bi bi-link-45deg me-1"></i>View Profile
                    </a>
                  ` : `
                    <a href="consultation-history.php"
                       class="btn btn-sm btn-outline-primary w-100">
                      <i class="bi bi-calendar-plus me-1"></i>See Availability
                    </a>
                  `}
                </div>
              </div>
            </div>
          </div>
        `;
        
        doctorsGrid.insertAdjacentHTML('beforeend', cardHtml);
      });
      
      // Show container and scroll to it
      doctorsContainer.style.display = 'block';
      doctorsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
      
    } catch (error) {
      console.error('Error fetching doctors:', error);
      doctorsGrid.innerHTML = `
        <div class="col-12">
          <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>
            <div>Error loading doctors: ${error.message || 'Please check your connection and try again.'}</div>
          </div>
        </div>
      `;
      doctorsContainer.style.display = 'block';
    } finally {
      findDoctorsBtn.disabled = false;
      findDoctorsBtn.innerHTML = originalHtml;
    }
  });
  
  // Helper function to escape HTML
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
});

// ===== LOCATION API FUNCTIONALITY =====
// Geolocation and Distance Calculation
let userLocation = null;

// Haversine formula to calculate distance between two coordinates
function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth's radius in kilometers
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c; // Distance in kilometers
}

// Cache for geocoded addresses (localStorage)
const GEOCODE_CACHE_KEY = 'smartdoc_geocode_cache';
const CACHE_DURATION = 7 * 24 * 60 * 60 * 1000; // 7 days

function getGeocodeCache() {
  try {
    const cached = localStorage.getItem(GEOCODE_CACHE_KEY);
    if (cached) {
      const data = JSON.parse(cached);
      const now = Date.now();
      // Remove expired entries
      Object.keys(data).forEach(addr => {
        if (now - data[addr].timestamp > CACHE_DURATION) {
          delete data[addr];
        }
      });
      return data;
    }
  } catch (e) {
    console.error('Cache read error:', e);
  }
  return {};
}

function setGeocodeCache(address, coords) {
  try {
    const cache = getGeocodeCache();
    cache[address] = {
      ...coords,
      timestamp: Date.now()
    };
    localStorage.setItem(GEOCODE_CACHE_KEY, JSON.stringify(cache));
  } catch (e) {
    console.error('Cache write error:', e);
  }
}

// Geocode address using PHP proxy (avoids CORS issues)
async function geocodeAddress(address, delay = 0) {
  if (!address || address.trim() === '') return null;
  
  // Check cache first
  const cache = getGeocodeCache();
  if (cache[address]) {
    return cache[address];
  }
  
  // Add delay to respect rate limits
  if (delay > 0) {
    await new Promise(resolve => setTimeout(resolve, delay));
  }
  
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
    
    const response = await fetch(
      `geocode_proxy.php?action=geocode&address=${encodeURIComponent(address)}`,
      {
        signal: controller.signal
      }
    );
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const responseText = await response.text();
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (parseError) {
      console.error('Invalid JSON response for', address, ':', responseText.substring(0, 100));
      return null;
    }
    
    if (data.success && data.lat && data.lon) {
      const coords = {
        lat: parseFloat(data.lat),
        lon: parseFloat(data.lon),
        address: address
      };
      // Cache the result
      setGeocodeCache(address, coords);
      return coords;
    }
  } catch (error) {
    if (error.name === 'AbortError') {
      console.error('Geocoding timeout for', address);
    } else {
      console.error('Geocoding error for', address, ':', error);
    }
  }
  return null;
}

// Batch geocode multiple addresses with caching and progressive display
async function geocodeAddressesBatch(addresses, onProgress) {
  const cache = getGeocodeCache();
  const addressMap = new Map();
  const uncachedAddresses = [];
  const cachedResults = [];
  
  // Separate cached and uncached addresses
  addresses.forEach((address, index) => {
    if (cache[address]) {
      addressMap.set(address, cache[address]);
      cachedResults.push({ address, index, coords: cache[address] });
    } else {
      uncachedAddresses.push({ address, index });
    }
  });
  
  // IMMEDIATELY show cached results (no delay, instant display)
  if (cachedResults.length > 0 && onProgress) {
    cachedResults.forEach(({ address, coords }) => {
      onProgress(address, coords);
    });
  }
  
  // If all are cached, return immediately
  if (uncachedAddresses.length === 0) {
    return addressMap;
  }
  
  // Process uncached addresses in background
  processUncachedInBackground(uncachedAddresses, addressMap, onProgress);
  
  return addressMap;
}

// Process uncached addresses in background (DISABLED - too slow, using area matching only)
async function processUncachedInBackground(uncachedAddresses, addressMap, onProgress, statusCallback) {
  // Skip geocoding - it's too slow. Just return immediately.
  // Area matching is instant and sufficient for user needs.
  return addressMap;
}

// Reverse geocode to get area name from coordinates using API
async function getAreaNameFromCoordinates(lat, lon) {
  // Check cache first
  const cacheKey = `reverse_${lat.toFixed(4)}_${lon.toFixed(4)}`;
  const cache = getGeocodeCache();
  if (cache[cacheKey] && cache[cacheKey].area) {
    return cache[cacheKey].area;
  }
  
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);
    
    const response = await fetch(
      `geocode_proxy.php?action=reverse&lat=${lat}&lon=${lon}`,
      {
        signal: controller.signal
      }
    );
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const data = await response.json();
    if (data.success && data.address) {
      // Extract area name from address
      const address = data.address;
      let areaName = null;
      
      // Try to extract suburb, city_district, or city
      if (address.suburb) {
        areaName = address.suburb.toLowerCase().trim();
      } else if (address.city_district) {
        areaName = address.city_district.toLowerCase().trim();
      } else if (address.city) {
        areaName = address.city.toLowerCase().trim();
      } else if (address.neighbourhood) {
        areaName = address.neighbourhood.toLowerCase().trim();
      }
      
      // Normalize area name
      if (areaName) {
        areaName = areaName
          .replace(/\s*residential\s*area/gi, '')
          .replace(/\s*r\/a/gi, '')
          .replace(/\s*ra\b/gi, '')
          .trim();
        
        // Cache the result
        setGeocodeCache(cacheKey, { area: areaName, lat, lon });
        console.log('Detected area from API:', areaName);
        return areaName;
      }
    }
  } catch (error) {
    console.error('Reverse geocoding error:', error);
  }
  
  return null;
}

// Match address by area name (FAST - no geocoding needed)
// Returns: 'exact' for exact match, 'nearby' for nearby area, false for no match
function matchAddressByArea(address, userArea) {
  if (!userArea || !address) return false;
  const addressLower = address.toLowerCase().trim();
  // Normalize area name - remove "Residential Area", "R/A", etc.
  let areaLower = userArea.toLowerCase().trim()
    .replace(/\s*residential\s*area/gi, '')
    .replace(/\s*r\/a/gi, '')
    .replace(/\s*ra\b/gi, '')
    .trim();
  
  // Comprehensive area variations matching
  const areaVariations = {
    'bashundhara': [
      'bashundhara', 
      'bashundhara r/a', 
      'bashundhara ra', 
      'bashundhara residential area', 
      'bashundhara residential',
      'bashundhara r/a,',
      'bashundhara ra,',
      'bashundhara road',
      'bashundhara eye hospital' // Special case for Bashundhara Eye Hospital
    ],
    'badda': ['badda', 'badda,'],
    'norda': ['norda', 'norda,'],
    'gulshan': ['gulshan', 'gulshan,'],
    'banani': ['banani', 'banani,'],
    'dhanmondi': ['dhanmondi', 'dhanmondi r/a', 'dhanmondi ra', 'dhanmondi,'],
    'mirpur': ['mirpur', 'mirpur,'],
    'uttara': ['uttara', 'uttara,'],
    'jatrabari': ['jatrabari', 'jatrabari,'],
    'panthapath': ['panthapath', 'panthapath,', 'west panthapath']
  };
  
  // Nearby areas mapping (SHORT DISTANCE - only very close areas)
  const nearbyAreas = {
    'bashundhara': ['badda', 'norda'], // Only Badda and Norda (very close to Bashundhara)
    'badda': ['bashundhara', 'norda', 'gulshan'],
    'norda': ['bashundhara', 'badda'],
    'gulshan': ['banani', 'badda'],
    'banani': ['gulshan'],
    'dhanmondi': ['panthapath'],
    'panthapath': ['dhanmondi'],
    'mirpur': ['uttara'],
    'uttara': ['mirpur']
  };
  
  const variations = areaVariations[areaLower] || [areaLower];
  
  // Check for EXACT match first
  for (const variation of variations) {
    // Use word boundary matching for more precise results
    const regex = new RegExp('\\b' + variation.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
    if (regex.test(addressLower) || addressLower.includes(variation)) {
      return 'exact'; // Exact match
    }
  }
  
  // Check for NEARBY areas (short distance only)
  const nearby = nearbyAreas[areaLower];
  if (nearby) {
    for (const nearArea of nearby) {
      const nearVariations = areaVariations[nearArea] || [nearArea];
      for (const variation of nearVariations) {
        const regex = new RegExp('\\b' + variation.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
        if (regex.test(addressLower) || addressLower.includes(variation)) {
          return 'nearby'; // Nearby area match
        }
      }
    }
  }
  
  return false; // No match
}

// Get user's current location
function getUserLocation() {
  const locationStatus = document.getElementById('location-status');
  const useLocationBtn = document.getElementById('use-location-btn');
  
  if (!navigator.geolocation) {
    if (locationStatus) locationStatus.textContent = 'Geolocation is not supported by your browser.';
    return;
  }

  if (locationStatus) locationStatus.textContent = 'Getting your location...';
  if (useLocationBtn) useLocationBtn.disabled = true;

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      userLocation = {
        lat: position.coords.latitude,
        lon: position.coords.longitude
      };
      
      if (locationStatus) {
        locationStatus.innerHTML = '<i class="bi bi-check-circle text-success me-1"></i>Location found! Calculating distances...';
      }
      
      await calculateAndDisplayDistances();
    },
    (error) => {
      let errorMsg = 'Unable to get your location. ';
      switch(error.code) {
        case error.PERMISSION_DENIED:
          errorMsg += 'Please allow location access.';
          break;
        case error.POSITION_UNAVAILABLE:
          errorMsg += 'Location information unavailable.';
          break;
        case error.TIMEOUT:
          errorMsg += 'Location request timed out.';
          break;
        default:
          errorMsg += 'An unknown error occurred.';
          break;
      }
      if (locationStatus) locationStatus.textContent = errorMsg;
      if (useLocationBtn) useLocationBtn.disabled = false;
    }
  );
}

// Calculate distances for all doctors (FAST - area matching only, no geocoding)
async function calculateAndDisplayDistances() {
  const distanceInfos = document.querySelectorAll('#doctors-grid .distance-info');
  const locationStatus = document.getElementById('location-status');
  const useLocationBtn = document.getElementById('use-location-btn');
  
  if (!userLocation || distanceInfos.length === 0) return;
  
  if (locationStatus) {
    locationStatus.innerHTML = '<i class="bi bi-hourglass-split text-primary me-1"></i>Finding your area...';
  }
  
  // Get user area (instant, no API call)
  let userArea = await getAreaNameFromCoordinates(userLocation.lat, userLocation.lon);
  
  // Normalize area name - remove "Residential Area", "R/A", etc. for matching
  if (userArea) {
    userArea = userArea
      .replace(/\s*residential\s*area/gi, '')
      .replace(/\s*r\/a/gi, '')
      .replace(/\s*ra/gi, '')
      .trim();
  }
  
  // Debug logging
  console.log('User location:', userLocation);
  console.log('Detected area (raw):', await getAreaNameFromCoordinates(userLocation.lat, userLocation.lon));
  console.log('Detected area (normalized):', userArea);
  
  if (!userArea) {
    if (locationStatus) {
      locationStatus.innerHTML = '<i class="bi bi-info-circle text-warning me-1"></i>Area not recognized. Showing all doctors.';
    }
    if (useLocationBtn) useLocationBtn.disabled = false;
    // Still try to match by checking all addresses for common area names
    // This is a fallback if GPS area detection fails
    console.log('Area detection failed, trying fallback matching...');
  }
  
  // Collect ALL addresses for geocoding (SHORT DISTANCE RANGE - 5km)
  const addressToElements = new Map();
  const allAddresses = [];
  const MAX_DISTANCE_KM = 5; // SHORT RANGE: Only show doctors within 5km
  
  distanceInfos.forEach((info) => {
    const address = info.getAttribute('data-address');
    if (address && address.trim() !== '') {
      if (!addressToElements.has(address)) {
        addressToElements.set(address, []);
        allAddresses.push(address);
      }
      addressToElements.get(address).push(info);
    } else {
      info.style.display = 'none';
    }
  });
  
  if (locationStatus) {
    locationStatus.innerHTML = `<i class="bi bi-hourglass-split text-primary me-1"></i>Calculating distances for ${allAddresses.length} doctor${allAddresses.length !== 1 ? 's' : ''} (within 5km)...`;
  }
  
  // Geocode ALL addresses and calculate distances (SHORT RANGE)
  const uniqueAddresses = Array.from(new Set(allAddresses));
  const BATCH_SIZE = 3;
  const DELAY_BETWEEN_BATCHES = 1000;
  let doctorsInRange = 0;
  
  for (let i = 0; i < uniqueAddresses.length; i += BATCH_SIZE) {
    const batch = uniqueAddresses.slice(i, i + BATCH_SIZE);
    
    const batchPromises = batch.map((address, batchIdx) => {
      const delay = batchIdx * 300;
      return geocodeAddress(address, delay).then(coords => {
        if (coords && userLocation) {
          const distance = calculateDistance(
            userLocation.lat,
            userLocation.lon,
            coords.lat,
            coords.lon
          );
          
          // Check if address contains Bashundhara/Badda/Norda (priority areas)
          const addressLower = address.toLowerCase();
          const isBashundhara = addressLower.includes('bashundhara') || addressLower.includes('evercare') || 
                               addressLower.includes('afroza begum') || addressLower.includes('bashundhara eye');
          const isBadda = addressLower.includes('badda');
          const isNorda = addressLower.includes('norda');
          const isPriorityArea = isBashundhara || isBadda || isNorda;
          
          // Show if within SHORT RANGE (5km) OR in priority areas (always show priority areas)
          if (distance <= MAX_DISTANCE_KM || isPriorityArea) {
            doctorsInRange++;
            
            const elements = addressToElements.get(address) || [];
            elements.forEach(info => {
              const distanceText = info.querySelector('.distance-text');
              const badge = info.querySelector('.badge');
              const card = info.closest('.col-md-6, .col-lg-3');
              
              if (card) {
                // Priority areas get lower distance value for sorting
                if (isBashundhara) {
                  card.setAttribute('data-distance', '0.1'); // Highest priority
                  card.setAttribute('data-area-match', 'true');
                } else if (isBadda || isNorda) {
                  card.setAttribute('data-distance', '0.2'); // Second priority
                  card.setAttribute('data-area-match', 'nearby');
                } else {
                  card.setAttribute('data-distance', distance.toFixed(2));
                }
              }
              
              if (distanceText) {
                if (isBashundhara) {
                  distanceText.textContent = 'Same area';
                } else if (isBadda || isNorda) {
                  distanceText.textContent = 'Nearby area';
                } else if (distance < 1) {
                  distanceText.textContent = `${Math.round(distance * 1000)}m away`;
                } else {
                  distanceText.textContent = `${distance.toFixed(1)}km away`;
                }
              }
              
              if (badge) {
                badge.classList.remove('bg-secondary');
                if (isBashundhara) {
                  badge.classList.add('bg-success');
                } else if (isBadda || isNorda) {
                  badge.classList.add('bg-info');
                } else {
                  badge.classList.add('bg-success');
                }
              }
              
              info.style.display = 'block';
            });
            
            triggerSort(); // Sort after each update
          } else {
            // Too far - hide it
            const elements = addressToElements.get(address) || [];
            elements.forEach(info => {
              info.style.display = 'none';
              const card = info.closest('.col-md-6, .col-lg-3');
              if (card) {
                card.setAttribute('data-distance', '999');
              }
            });
          }
        } else {
          // Geocoding failed - check if it's a priority area (Bashundhara/Badda/Norda)
          const addressLower = address.toLowerCase();
          const isBashundhara = addressLower.includes('bashundhara') || addressLower.includes('evercare') || 
                               addressLower.includes('afroza begum') || addressLower.includes('bashundhara eye');
          const isBadda = addressLower.includes('badda');
          const isNorda = addressLower.includes('norda');
          const isPriorityArea = isBashundhara || isBadda || isNorda;
          
          if (isPriorityArea) {
            // Show priority areas even if geocoding failed
            doctorsInRange++;
            const elements = addressToElements.get(address) || [];
            elements.forEach(info => {
              const distanceText = info.querySelector('.distance-text');
              const badge = info.querySelector('.badge');
              const card = info.closest('.col-md-6, .col-lg-3');
              
              if (card) {
                if (isBashundhara) {
                  card.setAttribute('data-distance', '0.1');
                  card.setAttribute('data-area-match', 'true');
                } else if (isBadda || isNorda) {
                  card.setAttribute('data-distance', '0.2');
                  card.setAttribute('data-area-match', 'nearby');
                }
              }
              
              if (distanceText) {
                distanceText.textContent = isBashundhara ? 'Same area' : 'Nearby area';
              }
              
              if (badge) {
                badge.classList.remove('bg-secondary');
                badge.classList.add(isBashundhara ? 'bg-success' : 'bg-info');
              }
              
              info.style.display = 'block';
            });
            triggerSort();
          } else {
            // Not priority area and geocoding failed - hide it
            const elements = addressToElements.get(address) || [];
            elements.forEach(info => {
              info.style.display = 'none';
              const card = info.closest('.col-md-6, .col-lg-3');
              if (card) {
                card.setAttribute('data-distance', '999');
              }
            });
          }
        }
        
        // Update status
        const remaining = uniqueAddresses.length - (i + batch.length);
        if (remaining > 0 && locationStatus) {
          locationStatus.innerHTML = `<i class="bi bi-hourglass-split text-primary me-1"></i>Processing... (${i + batch.length}/${uniqueAddresses.length})`;
        }
      });
    });
    
    await Promise.all(batchPromises);
    
    if (i + BATCH_SIZE < uniqueAddresses.length) {
      await new Promise(resolve => setTimeout(resolve, DELAY_BETWEEN_BATCHES));
    }
  }
  
  // Update final status
  const displayArea = userArea ? (userArea.charAt(0).toUpperCase() + userArea.slice(1).toLowerCase()) : 'your location';
  
  if (locationStatus) {
    if (doctorsInRange > 0) {
      locationStatus.innerHTML = `<i class="bi bi-check-circle text-success me-1"></i>Found ${doctorsInRange} doctor${doctorsInRange !== 1 ? 's' : ''} within 5km of ${displayArea}!`;
    } else {
      locationStatus.innerHTML = `<i class="bi bi-info-circle text-warning me-1"></i>No doctors found within 5km of ${displayArea}. Showing all doctors.`;
    }
  }
  
  // Final sort
  triggerSort();
  
  // Re-enable button
  if (useLocationBtn) useLocationBtn.disabled = false;
}

// Helper function to extract rating from card
function getRatingFromCard(card) {
  const ratingSummary = card.querySelector('.rating-summary');
  if (ratingSummary) {
    const text = ratingSummary.textContent.trim();
    // Extract rating from text like "4.5/5" or "0.0/5"
    const match = text.match(/(\d+\.?\d*)\/5/);
    if (match) {
      return parseFloat(match[1]);
    }
  }
  return 0; // Default to 0 if no rating found
}

// Sort function - sort doctors by area match priority, then rating, then distance
function triggerSort() {
  const container = document.getElementById('doctors-grid');
  if (!container) return;
  
  const allCards = Array.from(container.querySelectorAll('.col-md-6, .col-lg-3'));
  
  // Separate cards: exact matches, nearby matches, then others
  const exactMatchCards = allCards.filter(card => card.getAttribute('data-area-match') === 'true');
  const nearbyMatchCards = allCards.filter(card => card.getAttribute('data-area-match') === 'nearby');
  const otherCards = allCards.filter(card => !card.hasAttribute('data-area-match') || 
                                             (card.getAttribute('data-area-match') !== 'true' && 
                                              card.getAttribute('data-area-match') !== 'nearby'));
  
  // Sort exact matches: first by rating (highest first), then by distance (nearest first)
  exactMatchCards.sort((a, b) => {
    const ratingA = getRatingFromCard(a);
    const ratingB = getRatingFromCard(b);
    const distA = parseFloat(a.getAttribute('data-distance') || '999');
    const distB = parseFloat(b.getAttribute('data-distance') || '999');
    
    // First priority: rating (highest first)
    if (ratingB !== ratingA) {
      return ratingB - ratingA; // Higher rating first
    }
    // Second priority: distance (nearest first)
    return distA - distB;
  });
  
  // Sort nearby matches: first by rating (highest first), then by distance (nearest first)
  nearbyMatchCards.sort((a, b) => {
    const ratingA = getRatingFromCard(a);
    const ratingB = getRatingFromCard(b);
    const distA = parseFloat(a.getAttribute('data-distance') || '999');
    const distB = parseFloat(b.getAttribute('data-distance') || '999');
    
    // First priority: rating (highest first)
    if (ratingB !== ratingA) {
      return ratingB - ratingA; // Higher rating first
    }
    // Second priority: distance (nearest first)
    return distA - distB;
  });
  
  // Sort other cards: first by rating (highest first), then by distance (nearest first)
  otherCards.sort((a, b) => {
    const ratingA = getRatingFromCard(a);
    const ratingB = getRatingFromCard(b);
    const distA = parseFloat(a.getAttribute('data-distance') || '999');
    const distB = parseFloat(b.getAttribute('data-distance') || '999');
    
    // First priority: rating (highest first)
    if (ratingB !== ratingA) {
      return ratingB - ratingA; // Higher rating first
    }
    // Second priority: distance (nearest first)
    if (isNaN(distA)) return 1;
    if (isNaN(distB)) return -1;
    return distA - distB;
  });
  
  // Clear existing badges
  document.querySelectorAll('#doctors-grid .nearest-badge').forEach(badge => badge.remove());
  document.querySelectorAll('#doctors-grid .card.border-success, #doctors-grid .card.border-info').forEach(card => {
    card.classList.remove('border-success', 'border-info', 'border-2');
  });
  
  // Clear container
  container.innerHTML = '';
  
  // Reorder: exact matches first (sorted by rating), then nearby (sorted by rating), then others (sorted by rating)
  exactMatchCards.forEach(card => container.appendChild(card));
  nearbyMatchCards.forEach(card => container.appendChild(card));
  otherCards.forEach(card => container.appendChild(card));
  
  // Add visual indicator for BEST MATCH (highest rated exact match)
  if (exactMatchCards.length > 0) {
    const bestMatchCard = exactMatchCards[0];
    const nearestCard = bestMatchCard.querySelector('.card');
    if (nearestCard) {
      nearestCard.classList.add('border-success', 'border-2');
      nearestCard.style.position = 'relative';
      const nearestBadge = document.createElement('div');
      nearestBadge.className = 'position-absolute top-0 start-0 m-2 nearest-badge';
      nearestBadge.style.zIndex = '10';
      const rating = getRatingFromCard(bestMatchCard);
      nearestBadge.innerHTML = `<span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>Best Match${rating > 0 ? ` (${rating.toFixed(1)}★)` : ''}</span>`;
      nearestCard.appendChild(nearestBadge);
    }
  } else if (nearbyMatchCards.length > 0) {
    // If no exact matches, highlight best nearby match
    const bestNearbyCard = nearbyMatchCards[0];
    const nearestCard = bestNearbyCard.querySelector('.card');
    if (nearestCard) {
      nearestCard.classList.add('border-info', 'border-2');
      nearestCard.style.position = 'relative';
      const nearestBadge = document.createElement('div');
      nearestBadge.className = 'position-absolute top-0 start-0 m-2 nearest-badge';
      nearestBadge.style.zIndex = '10';
      const rating = getRatingFromCard(bestNearbyCard);
      nearestBadge.innerHTML = `<span class="badge bg-info"><i class="bi bi-star-fill me-1"></i>Nearby Best${rating > 0 ? ` (${rating.toFixed(1)}★)` : ''}</span>`;
      nearestCard.appendChild(nearestBadge);
    }
  }
}

// Event listener for "Use My Location" button
document.addEventListener('DOMContentLoaded', () => {
  const useLocationBtn = document.getElementById('use-location-btn');
  if (useLocationBtn) {
    useLocationBtn.addEventListener('click', getUserLocation);
  }
  
  // Handle doctor selection buttons (using event delegation for dynamically added buttons)
  document.addEventListener('click', async (e) => {
    if (e.target.closest('.select-doctor-btn')) {
      const btn = e.target.closest('.select-doctor-btn');
      const doctorId = btn.getAttribute('data-doctor-id');
      const checkId = btn.getAttribute('data-check-id');
      const doctorName = btn.getAttribute('data-doctor-name');
      const hospitalName = btn.getAttribute('data-hospital-name');
      
      if (!checkId || checkId === '') {
        alert('Unable to select doctor. Please refresh the page and try again.');
        return;
      }
      
      // Disable button and show loading
      btn.disabled = true;
      const originalHtml = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Selecting...';
      
      try {
        const formData = new FormData();
        formData.append('check_id', checkId);
        formData.append('doctor_id', doctorId);
        
        const response = await fetch('select_doctor.php', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });
        
        const data = await response.json();
        
        if (!data.success) {
          alert(data.error === 'LOGIN_REQUIRED' 
            ? 'Please log in to select a doctor.'
            : data.error === 'CHECK_NOT_FOUND'
            ? 'Unable to find your symptom check. Please refresh the page.'
            : 'Could not select doctor. Please try again.');
          btn.disabled = false;
          btn.innerHTML = originalHtml;
          return;
        }
        
        // Change button to "Selected" state
        btn.classList.remove('btn-success');
        btn.classList.add('btn-secondary');
        btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Selected';
        btn.disabled = true;
        
        // Disable all other select buttons in the same grid
        document.querySelectorAll('#doctors-grid .select-doctor-btn').forEach(otherBtn => {
          if (otherBtn !== btn && !otherBtn.disabled) {
            otherBtn.disabled = true;
            otherBtn.classList.remove('btn-success');
            otherBtn.classList.add('btn-outline-secondary');
            const otherOriginalHtml = otherBtn.innerHTML;
            if (!otherOriginalHtml.includes('Selected')) {
              otherBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Not Selected';
            }
          }
        });
        
        // Show success message
        const successMsg = document.createElement('div');
        successMsg.className = 'alert alert-success alert-dismissible fade show mt-2';
        successMsg.innerHTML = `
          <i class="bi bi-check-circle me-2"></i>Doctor selected successfully! 
          <a href="consultation-history.php" class="alert-link">View in history</a>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        btn.closest('.card-body').appendChild(successMsg);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
          if (successMsg.parentNode) {
            successMsg.remove();
          }
        }, 5000);
        
      } catch (error) {
        console.error('Error selecting doctor:', error);
        alert('Error selecting doctor. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      }
    }
  });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
