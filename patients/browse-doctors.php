<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/header.php';
// OPTIONAL LOGIN (disabled for now because it redirects you to login page)
// require_patient_login();
$specialization = $_GET['specialization'] ?? '';

// If a specialization is selected
if ($specialization) {
    $sql = "
        SELECT d.*, s.specialization_name, h.hospital_name, l.city, l.area_name
        FROM doctor d
        LEFT JOIN specialization s ON d.specialization_id = s.specialization_id
        LEFT JOIN hospital h ON d.hospital_id = h.hospital_id
        LEFT JOIN location l ON h.location_id = l.location_id
        WHERE s.specialization_name = ?
        ORDER BY d.name ASC;
    ";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $specialization);
    $stmt->execute();
    $doctors = $stmt->get_result();
} else {
    // If no specialization selected → show ALL doctors
    $doctors = $con->query("
        SELECT d.*, s.specialization_name, h.hospital_name, l.city, l.area_name
        FROM doctor d
        LEFT JOIN specialization s ON d.specialization_id = s.specialization_id
        LEFT JOIN hospital h ON d.hospital_id = h.hospital_id
        LEFT JOIN location l ON h.location_id = l.location_id
        ORDER BY d.name ASC;
    ");
}
?>
<style>
.doctor-card {
    border: 1px solid #e5e7eb;
    background: #fff;
    padding: 1.2rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    transition: 0.2s;
}
.doctor-card:hover {
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
</style>

<div class="container my-5">
    <h3 class="fw-semibold mb-4">
        <?php if ($specialization): ?>
            Doctors specializing in <?= h($specialization) ?>
        <?php else: ?>
            All Doctors
        <?php endif; ?>
    </h3>
    <?php if ($doctors->num_rows == 0): ?>
        <div class="alert alert-info">No doctors found.</div>
    <?php endif; ?>
    <?php while ($d = $doctors->fetch_assoc()): ?>
        <div class="doctor-card">
            <h5 class="fw-bold"><?= h($d['name']) ?></h5>
            <p class="text-muted mb-1">
                <?= h($d['specialization_name']) ?>
            </p>
            <?php if ($d['hospital_name']): ?>
            <p class="mb-1">
                <b>Hospital:</b> <?= h($d['hospital_name']) ?>
            </p>
            <?php endif; ?>
            <?php if ($d['city'] || $d['area_name']): ?>
            <p class="mb-1">
                <b>Location:</b>
                <?= h($d['city']) ?>
                <?= $d['area_name'] ? '• ' . h($d['area_name']) : '' ?>
            </p>
            <?php endif; ?>
            <!-- ⭐ Updated View Profile Button -->
            <?php if (!empty($d['website_url'])): ?>
                <a 
                    href="<?= h($d['website_url']) ?>" 
                    target="_blank"
                    class="btn btn-primary btn-sm mt-2">
                    View Profile
                </a>
            <?php else: ?>
                <button class="btn btn-secondary btn-sm mt-2" disabled>
                    No Website Available
                </button>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


