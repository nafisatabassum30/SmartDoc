<?php
require_once __DIR__ . '/includes/header.php';
require_login();

function normalize_id($raw) {
    return (isset($raw) && ctype_digit((string)$raw) && (int)$raw > 0) ? (int)$raw : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $patient_id = normalize_id($_POST['patient_id'] ?? '');
        $symptom_id = normalize_id($_POST['symptom_id'] ?? '');
        $doctor_id = normalize_id($_POST['doctor_id'] ?? '');
        $consultation_date = $_POST['consultation_date'] ?? null;

        if ($patient_id && $doctor_id && $consultation_date) {
            $stmt = $con->prepare("INSERT INTO `pastconsultation` (patient_id, symptom_id, doctor_id, consultation_date) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiis', $patient_id, $symptom_id, $doctor_id, $consultation_date);
            try {
                $stmt->execute();
                flash('Past consultation added successfully', 'success');
            } catch (mysqli_sql_exception $e) {
                flash('Error: ' . $e->getMessage(), 'danger');
            }
            $stmt->close();
        } else {
            flash('All required fields must be filled', 'danger');
        }
    } elseif ($action === 'update') {
        $consultation_ID = normalize_id($_POST['consultation_ID'] ?? '');
        $patient_id = normalize_id($_POST['patient_id'] ?? '');
        $symptom_id = normalize_id($_POST['symptom_id'] ?? '');
        $doctor_id = normalize_id($_POST['doctor_id'] ?? '');
        $consultation_date = $_POST['consultation_date'] ?? null;

        if ($consultation_ID) {
            $stmt = $con->prepare("UPDATE `pastconsultation` SET patient_id=?, symptom_id=?, doctor_id=?, consultation_date=? WHERE consultation_ID=?");
            $stmt->bind_param('iiisi', $patient_id, $symptom_id, $doctor_id, $consultation_date, $consultation_ID);
            try {
                $stmt->execute();
                flash('Past consultation updated', 'success');
            } catch (mysqli_sql_exception $e) {
                flash('Update failed: ' . $e->getMessage(), 'danger');
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $consultation_ID = normalize_id($_POST['consultation_ID'] ?? '');
        if ($consultation_ID) {
            $stmt = $con->prepare("DELETE FROM `pastconsultation` WHERE consultation_ID=?");
            $stmt->bind_param('i', $consultation_ID);
            try {
                $stmt->execute();
                flash('Past consultation deleted', 'warning');
            } catch (mysqli_sql_exception $e) {
                flash('Delete failed: ' . $e->getMessage(), 'danger');
            }
            $stmt->close();
        }
    }
}

$result = $con->query("
    SELECT pc.consultation_ID, 
           p.name AS patient_name, 
           s.symptom_name, 
           d.name AS doctor_name, 
           pc.consultation_date
    FROM `pastconsultation` pc
    LEFT JOIN `patient` p ON pc.patient_id = p.patient_ID
    LEFT JOIN `symptom` s ON pc.symptom_id = s.symptom_id
    LEFT JOIN `doctor` d ON pc.doctor_id = d.doctor_id
    ORDER BY pc.consultation_date DESC
");
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Past Consultations 🩺</h1>
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-stethoscope me-1"></i> Manage Past Consultations
        </div>
        <div class="card-body">
            <form method="post" class="row g-3 mb-4">
                <input type="hidden" name="action" value="create">
                <div class="col-md-3">
                    <label class="form-label">Patient ID</label>
                    <input type="number" name="patient_id" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Symptom ID</label>
                    <input type="number" name="symptom_id" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Doctor ID</label>
                    <input type="number" name="doctor_id" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Consultation Date</label>
                    <input type="date" name="consultation_date" class="form-control" required>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-success">Add Consultation</button>
                </div>
            </form>

            <table class="table table-bordered table-hover text-center align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Symptom</th>
                        <th>Doctor</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['consultation_ID']) ?></td>
                            <td><?= htmlspecialchars($row['patient_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['symptom_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['doctor_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['consultation_date']) ?></td>
                            <td>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="consultation_ID" value="<?= $row['consultation_ID'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this record?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
