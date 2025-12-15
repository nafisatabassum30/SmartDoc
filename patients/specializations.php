<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/header.php';

// Fetch all specializations
$specializations = $con->query("
    SELECT specialization_name, description
    FROM specialization
    ORDER BY specialization_name ASC
");
?>
<style>
.spec-card {
    border: 1px solid #e5e7eb;
    background: white;
    border-radius: 12px;
    padding: 1rem;
    transition: 0.2s;
    height: 100%;
}
.spec-card:hover {
    border-color: #0ea5e9;
    box-shadow: 0px 4px 10px rgba(0,0,0,0.08);
}
.spec-title {
    font-size: 1rem;
    font-weight: 600;
    color: #0b2239;
}
.spec-desc {
    font-size: 0.85rem;
    color: #64748b;
}
</style>

<div class="container my-5">
    <h3 class="fw-semibold mb-4">All Medical Specializations</h3>

    <div class="row g-3">
        <?php while($s = $specializations->fetch_assoc()): ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
            <a href="/SmartDoc/patients/browse-doctors.php?specialization=<?= urlencode($s['specialization_name']) ?>" 
               class="text-decoration-none">
               
                <div class="spec-card">
                    <div class="spec-title"><?= h($s['specialization_name']) ?></div>
                    <div class="spec-desc mt-1">
                        <?= h($s['description']) ?>
                    </div>
                </div>

            </a>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>


