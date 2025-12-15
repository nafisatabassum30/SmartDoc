<?php
ob_start();
require_once __DIR__ . '/includes/header.php';
require_patient_login();

$patient_id = get_patient_id();

// Default location from patient profile (if available)
$default_location = '';
if ($patient_id > 0) {
    $stmt = $con->prepare("SELECT patient_location FROM `patient` WHERE patient_ID = ?");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $default_location = $row['patient_location'] ?? '';
    }
    $stmt->close();
}

$location_input = trim($_POST['location'] ?? $default_location);

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['symptoms'] ?? [];

    if (empty($selected)) {
        flash('Please select at least one symptom.', 'warning');
    } else {
        // Make sure they are ints
        $ids = array_map('intval', $selected);
        $ids = array_values(array_filter($ids));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));

            $sql = "SELECT specialization_id 
                    FROM `symptom` 
                    WHERE symptom_id IN ($placeholders)
                      AND specialization_id IS NOT NULL";

            $stmt = $con->prepare($sql);
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();

            $counts = [];
            while ($row = $res->fetch_assoc()) {
                $sid = (int)($row['specialization_id'] ?? 0);
                if ($sid > 0) {
                    $counts[$sid] = ($counts[$sid] ?? 0) + 1;
                }
            }
            $stmt->close();

            if (!empty($counts)) {
                // Pick specialization with highest count
                arsort($counts);
                $bestSpecId = (int)array_key_first($counts);

                // Redirect to find-doctors with specialization + location
                $query = [
                    'specialization_id' => $bestSpecId,
                ];
                if (!empty($location_input)) {
                    $query['location'] = $location_input;
                }

                $qs = http_build_query($query);
                header('Location: find-doctors.php?' . $qs);
                exit;
            } else {
                flash('No matching specialist found for the selected symptoms. Try selecting different symptoms.', 'info');
            }
        } else {
            flash('Invalid symptom selection.', 'danger');
        }
    }
}

// Fetch all symptoms for selection
$symptoms = $con->query("
    SELECT sym.*, sp.specialization_name
    FROM `symptom` sym
    LEFT JOIN `specialization` sp 
      ON sym.specialization_id = sp.specialization_id
    ORDER BY sym.symptom_name
");
?>

<div class="card mb-4">
  <div class="card-header">
    <h5 class="mb-0">
      <i class="bi bi-activity me-1"></i>
      Check Symptoms &amp; Get Specialist
    </h5>
  </div>
  <div class="card-body">
    <form method="post">
      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label muted">Your Location</label>
          <input 
            type="text" 
            name="location" 
            class="form-control" 
            placeholder="e.g. Dhaka • Banani"
            value="<?= h($location_input) ?>"
          >
          <div class="form-text">We’ll try to show doctors near this area.</div>
        </div>
      </div>

      <div class="mb-2">
        <label class="form-label muted">Select your symptoms</label>
        <p class="small text-muted mb-2">
          Tick one or more symptoms that match what you’re feeling.  
          We’ll suggest a specialist based on your selection.
        </p>
      </div>

      <div class="row g-3">
        <?php if ($symptoms && $symptoms->num_rows > 0): ?>
          <?php while($sym = $symptoms->fetch_assoc()): ?>
            <?php 
              $sid   = (int)$sym['symptom_id'];
              $sname = $sym['symptom_name'];
              $area  = $sym['AffectedArea'] ?? '';
              $dur   = $sym['AffectedDuration'] ?? '';
              $spec  = $sym['specialization_name'] ?? '';
            ?>
            <div class="col-md-4">
              <label class="card h-100" style="cursor:pointer;">
                <div class="card-body">
                  <div class="form-check">
                    <input 
                      class="form-check-input" 
                      type="checkbox" 
                      name="symptoms[]" 
                      value="<?= $sid ?>" 
                      id="sym<?= $sid ?>"
                    >
                    <label class="form-check-label fw-semibold" for="sym<?= $sid ?>">
                      <?= h($sname) ?>
                    </label>
                  </div>

                  <?php if($area || $dur): ?>
                    <div class="small text-muted mt-1">
                      <?php if($area): ?>
                        <i class="bi bi-geo-alt me-1"></i><?= h($area) ?>
                      <?php endif; ?>
                      <?php if($dur): ?>
                        <span class="ms-1"><i class="bi bi-hourglass-split me-1"></i><?= h($dur) ?></span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>

                  <?php if($spec): ?>
                    <div class="mt-2">
                      <span class="badge-soft rounded-pill px-2 py-1">
                        Suggested: <?= h($spec) ?>
                      </span>
                    </div>
                  <?php endif; ?>
                </div>
              </label>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="col-12">
            <div class="alert alert-info">No symptoms configured yet. Please contact admin.</div>
          </div>
        <?php endif; ?>
      </div>

      <div class="mt-4 d-flex justify-content-end">
        <button class="btn btn-primary">
          <i class="bi bi-search-heart me-1"></i>
          Find specialist &amp; doctors
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ob_end_flush(); ?>
