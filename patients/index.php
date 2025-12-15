<?php
ob_start();
require_once __DIR__ . '/includes/header.php';
require_patient_login();

$patient_id = get_patient_id();
$patient = $con->query("SELECT name,email,age,gender,phone,patient_location FROM `patient` WHERE patient_ID=$patient_id")->fetch_assoc() ?? [];

// Stats (kept for future use if needed) based on pastconsultation
$total_consultations = (int)($con->query("SELECT COUNT(*) c FROM `pastconsultation` WHERE patient_id=$patient_id")->fetch_assoc()['c'] ?? 0);
$completed = (int)($con->query("SELECT COUNT(*) c FROM `pastconsultation` WHERE patient_id=$patient_id AND status='completed'")->fetch_assoc()['c'] ?? 0);
$confirmed = (int)($con->query("SELECT COUNT(*) c FROM `pastconsultation` WHERE patient_id=$patient_id AND status='confirmed'")->fetch_assoc()['c'] ?? 0);
$pending   = (int)($con->query("SELECT COUNT(*) c FROM `pastconsultation` WHERE patient_id=$patient_id AND (status='pending' OR status='scheduled')")->fetch_assoc()['c'] ?? 0);

// Recent symptom checks shown in the "Recent Consultations" section.
// This uses the same source as consultation-history.php (`symptom_check_history`).
$recent = $con->query("
  SELECT sch.*, sp.specialization_name
  FROM `symptom_check_history` sch
  LEFT JOIN `specialization` sp ON sch.recommended_specialist_id = sp.specialization_id
  WHERE sch.patient_id = $patient_id
  ORDER BY sch.created_at DESC
  LIMIT 5
");
?>

<style>
  /* Tighten the space under the navbar just for this page */
  main.container-fluid{padding-top:0 !important; padding-left:0 !important; padding-right:0 !important;}
  .hero-gradient{
    background: linear-gradient(180deg, rgba(14,165,233,0.55) 0%, rgba(14,165,233,0.25) 35%, rgba(14,165,233,0.10) 70%, rgba(14,165,233,0.03) 100%);
    padding-top:2rem; padding-bottom:1rem;
  }
  .kpi-card{transition:transform .25s ease, box-shadow .25s ease; border-radius:16px;}
  .kpi-card:hover{transform:translateY(-8px) scale(1.03); box-shadow:0 10px 18px rgba(0,0,0,.12);}
  .kpi-card .card-body{padding:1.2rem 1rem;}
</style>

<!-- HERO -->
<div class="hero-gradient">
  <div class="container">
    <div class="alert alert-info alert-persistent fade show mb-4 w-100 shadow-sm border-0" role="alert">
      <div class="d-flex align-items-center">
        <div>
          <strong>Welcome, <?= h(get_patient_name()) ?>!</strong>
          <p class="mb-0 small">Find the right doctor for your health.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- DASHBOARD CARDS -->
<div class="container my-4">
  <div class="row g-4 justify-content-center">
    <div class="col-10 col-sm-6 col-md-4 col-lg-3">
      <div class="card kpi-card h-100 text-center">
        <div class="card-body">
          <a href="consultation-history.php" class="text-decoration-none text-dark">
            <div class="bg-primary bg-opacity-10 rounded-3 p-3 d-inline-flex justify-content-center mb-2">
              <i class="bi bi-clock-history text-primary fs-4"></i>
            </div>
            <div class="fw-semibold">View History</div>
          </a>
        </div>
      </div>
    </div>
    <div class="col-10 col-sm-6 col-md-4 col-lg-3">
      <div class="card kpi-card h-100 text-center">
        <div class="card-body">
          <a href="selectSpecialist.php" class="text-decoration-none text-dark">
            <div class="bg-success bg-opacity-10 rounded-3 p-3 d-inline-flex justify-content-center mb-2">
              <i class="bi bi-search text-success fs-4"></i>
            </div>
            <div class="fw-semibold">Search Specialist Type</div>
          </a>
        </div>
      </div>
    </div>
    <div class="col-10 col-sm-6 col-md-4 col-lg-3">
      <div class="card kpi-card h-100 text-center">
        <div class="card-body">
          <a href="find-doctors.php" class="text-decoration-none text-dark">
            <div class="bg-info bg-opacity-10 rounded-3 p-3 d-inline-flex justify-content-center mb-2">
              <i class="bi bi-person-badge text-info fs-4"></i>
            </div>
            <div class="fw-semibold">Browse Doctor</div>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- KPI strip removed as requested -->

<!-- Recent + Quick Actions (restored) -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <h5 class="mb-0">Recent Consultations</h5>
        <!-- Removed header action buttons as requested -->
      </div>
      <div class="card-body">
        <?php if($recent && $recent->num_rows > 0): ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Symptoms</th>
                  <th>Recommended Specialist</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php while($row = $recent->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-calendar3 text-primary"></i>
                        <span><?= $row['created_at'] ? date('M d, Y', strtotime($row['created_at'])) : '—' ?></span>
                      </div>
                    </td>
                    <td>
                      <div class="fw-semibold text-truncate" style="max-width: 260px;">
                        <?= h($row['symptoms'] ?? '') ?>
                      </div>
                    </td>
                    <td><?= h($row['specialization_name'] ?? $row['recommended_specialist_name'] ?? '—') ?></td>
                    <td class="text-end">
                      <a href="consultation-history.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                      </a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="text-center py-5">
            <div class="mb-2">
              <span class="badge rounded-pill text-bg-primary">Welcome</span>
            </div>
            <h6 class="mb-2">No consultation history yet</h6>
            <p class="text-muted mb-0">Start by selecting your symptoms to find the right specialist.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- PATIENT PROFILE -->
<div class="container-fluid">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10">
      <div class="card shadow-sm mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Patient Profile</h5>
          <button class="btn btn-sm btn-primary" id="editProfileBtn">
            <i class="bi bi-pencil-square me-1"></i>Edit
          </button>
        </div>
        <div class="card-body">
          <form id="patientProfileForm" method="post" action="profile_update.php">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="name" value="<?= h($patient['name'] ?? get_patient_name()) ?>" required readonly>
              </div>
              <div class="col-md-3">
                <label class="form-label">Age</label>
                <input type="number" class="form-control" name="age" value="<?= h((string)($patient['age'] ?? '')) ?>" readonly>
              </div>
              <div class="col-md-3">
                <label class="form-label">Gender</label>
                <?php $g = $patient['gender'] ?? ''; ?>
                <select class="form-select" name="gender" disabled>
                  <option value="Female" <?= ($g==='Female'?'selected':'') ?>>Female</option>
                  <option value="Male" <?= ($g==='Male'?'selected':'') ?>>Male</option>
                </select>
              </div>
            </div>

            <!-- Email/phone/location removed to match provided layout -->
            <div class="mt-4 d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" id="cancelEditBtn" style="display:none;">Cancel</button>
              <button type="submit" class="btn btn-success" id="saveBtn" style="display:none;">Save Info</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.getElementById("editProfileBtn").addEventListener("click", function(){
    document.querySelectorAll("#patientProfileForm input, #patientProfileForm select").forEach(el=>{
      el.removeAttribute("readonly"); el.removeAttribute("disabled");
    });
    document.getElementById("saveBtn").style.display="inline-block";
    document.getElementById("cancelEditBtn").style.display="inline-block";
    this.style.display="none";
  });
  document.getElementById("cancelEditBtn").addEventListener("click", function(){
    document.querySelectorAll("#patientProfileForm input").forEach(el=>el.setAttribute("readonly", true));
    document.querySelectorAll("#patientProfileForm select").forEach(el=>el.setAttribute("disabled", true));
    document.getElementById("saveBtn").style.display="none";
    document.getElementById("editProfileBtn").style.display="inline-block";
    this.style.display="none";
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ob_end_flush(); ?>

