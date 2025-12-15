<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';
require_patient_login();

$location_id = !empty($_GET['loc']) ? (int)$_GET['loc'] : 0;
$specialization_id = !empty($_GET['spec']) ? (int)$_GET['spec'] : 0;

if ($location_id <= 0 || $specialization_id <= 0) {
    echo '<div class="alert alert-warning">Invalid search parameters.</div>';
    exit;
}

// Build query to find doctors by location and specialization
$sql = "SELECT d.*, 
               s.specialization_name, 
               h.hospital_name,
               h.address as hospital_address,
               l.city,
               l.area_name,
               CONCAT(COALESCE(l.city, ''), CASE WHEN l.city IS NOT NULL AND l.area_name IS NOT NULL THEN ' • ' ELSE '' END, COALESCE(l.area_name, '')) as location_label
        FROM `doctor` d
        LEFT JOIN `specialization` s ON d.specialization_id = s.specialization_id
        LEFT JOIN `hospital` h ON d.hospital_id = h.hospital_id
        LEFT JOIN `location` l ON h.location_id = l.location_id
        WHERE h.location_id = ? AND d.specialization_id = ?
        ORDER BY d.name ASC";

$stmt = $con->prepare($sql);
$stmt->bind_param("ii", $location_id, $specialization_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while($doctor = $result->fetch_assoc()):
?>
<div class="doctor-card">
  <div class="d-flex align-items-start gap-3 mb-3">
    <div class="rounded-3 d-inline-grid" style="width:56px;height:56px;background:#e0f2fe;color:#0369a1;display:grid;place-items:center;flex-shrink:0;">
      <i class="bi bi-person-badge fs-5"></i>
    </div>
    <div class="flex-grow-1">
      <div class="d-flex justify-content-between align-items-start mb-1">
        <h5 class="mb-0 fw-semibold"><?= h($doctor['name']) ?></h5>
        <span class="badge bg-primary rounded-pill px-2 py-1">
          <?= h($doctor['specialization_name'] ?? 'General') ?>
        </span>
      </div>
      <?php if($doctor['designation']): ?>
        <p class="text-muted small mb-2"><?= h($doctor['designation']) ?></p>
      <?php endif; ?>
      
      <?php if($doctor['hospital_name']): ?>
        <div class="small text-muted mb-1">
          <i class="bi bi-building me-1"></i><?= h($doctor['hospital_name']) ?>
        </div>
      <?php endif; ?>
      
      <?php if($doctor['location_label']): ?>
        <div class="small text-muted mb-2">
          <i class="bi bi-geo-alt me-1"></i><?= h($doctor['location_label']) ?>
        </div>
      <?php elseif($doctor['hospital_address']): ?>
        <div class="small text-muted mb-2">
          <i class="bi bi-geo-alt me-1"></i><?= h($doctor['hospital_address']) ?>
        </div>
      <?php endif; ?>
      
      <?php
        $avgRating   = isset($doctor['ratings(out of 5)']) ? (float)$doctor['ratings(out of 5)'] : 0;
        $ratingCount = isset($doctor['rating_count']) ? (int)$doctor['rating_count'] : 0;
      ?>

      <div class="mb-2">
        <span class="text-warning doctor-stars">
          <?php for($i = 1; $i <= 5; $i++): ?>
            <i class="bi <?= $i <= round($avgRating) ? 'bi-star-fill' : 'bi-star' ?>"></i>
          <?php endfor; ?>
        </span>
        <span class="small text-muted rating-summary">
          <?= number_format($avgRating, 1) ?>/5
          <?php if($ratingCount > 0): ?>
            (<?= $ratingCount ?> rating<?= $ratingCount > 1 ? 's' : '' ?>)
          <?php else: ?>
            (no ratings yet)
          <?php endif; ?>
        </span>
      </div>

      <form class="rate-form mt-1" data-doctor-id="<?= (int)$doctor['doctor_id'] ?>">
        <label class="small text-muted me-1">Your rating:</label>
        <select name="rating" class="form-select form-select-sm d-inline-block w-auto">
          <option value="">Select</option>
          <?php for($i = 1; $i <= 5; $i++): ?>
            <option value="<?= $i ?>"><?= $i ?></option>
          <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-secondary ms-1">Rate</button>
        <span class="small ms-2 rate-message text-muted"></span>
      </form>

      <?php if($doctor['website_url']): ?>
        <a href="<?= h($doctor['website_url']) ?>" target="_blank" class="btn btn-sm btn-primary mt-2">
          <i class="bi bi-link-45deg me-1"></i>View Profile
        </a>
      <?php else: ?>
        <a href="consultation-history.php" class="btn btn-sm btn-outline-primary mt-2">
          <i class="bi bi-calendar-plus me-1"></i>See Availability
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
    endwhile;
} else {
    echo '<div class="alert alert-info d-flex align-items-center" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            <div>No doctors found for the selected location and specialization. Try selecting different options.</div>
          </div>';
}

$stmt->close();
?>

