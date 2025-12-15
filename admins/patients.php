<?php
ob_start(); // Start output buffering to fix header already sent issue

require_once __DIR__ . '/includes/header.php';
require_login();

/**
 * Tiny helpers
 */
function norm($v) { $v = trim((string)($v ?? '')); return ($v === '') ? null : $v; } // empty->NULL
function norm_int($v) { $v = trim((string)($v ?? '')); return ($v === '' || !ctype_digit($v)) ? null : (int)$v; }
function norm_gender($v) {
  $g = strtolower(trim((string)($v ?? '')));
  $allowed = ['male','female','other'];
  return in_array($g, $allowed, true) ? ucfirst($g) : null; // store as 'Male'/'Female'/'Other' or NULL
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $name    = norm($_POST['name'] ?? null);
    $email   = norm($_POST['email'] ?? null);
    $age     = norm_int($_POST['age'] ?? null);
    $gender  = norm_gender($_POST['gender'] ?? null);
    $phone   = norm($_POST['phone'] ?? null);
    $loc     = norm($_POST['patient_location'] ?? null);

    // Minimal validation
    if (!$name)   { flash('Name is required', 'warning'); header('Location: patients.php'); exit; }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Invalid email address', 'warning'); header('Location: patients.php'); exit; }

    try {
      $stmt = $con->prepare("INSERT INTO `patient` (`name`,`email`,`age`,`gender`,`phone`,`patient_location`)
                             VALUES (?,?,?,?,?,?)");
      // age is INT/NULL ⇒ use i/s null handling via bind_param; for NULLs we still pass null
      $stmt->bind_param("ssisss", $name, $email, $age, $gender, $phone, $loc);
      $stmt->execute(); $stmt->close();
      flash('Patient added','success');
    } catch (mysqli_sql_exception $e) {
      flash('Create failed: '.$e->getMessage(),'danger');
    }
    header('Location: patients.php'); exit;
  }

  if ($action === 'update') {
    $id      = (int)($_POST['patient_ID'] ?? 0);
    $name    = norm($_POST['name'] ?? null);
    $email   = norm($_POST['email'] ?? null);
    $age     = norm_int($_POST['age'] ?? null);
    $gender  = norm_gender($_POST['gender'] ?? null);
    $phone   = norm($_POST['phone'] ?? null);
    $loc     = norm($_POST['patient_location'] ?? null);

    if ($id <= 0) { header('Location: patients.php'); exit; }
    if (!$name)   { flash('Name is required','warning'); header('Location: patients.php'); exit; }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('Invalid email address', 'warning'); header('Location: patients.php'); exit; }

    try {
      $stmt = $con->prepare("UPDATE `patient`
                                SET `name`=?, `email`=?, `age`=?, `gender`=?, `phone`=?, `patient_location`=?
                              WHERE `patient_ID`=?");
      $stmt->bind_param("ssisssi", $name, $email, $age, $gender, $phone, $loc, $id);
      $stmt->execute(); $stmt->close();
      flash('Patient updated','success');
    } catch (mysqli_sql_exception $e) {
      flash('Update failed: '.$e->getMessage(),'danger');
    }
    header('Location: patients.php'); exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['patient_ID'] ?? 0);
    if ($id > 0) {
      try {
        $stmt = $con->prepare("DELETE FROM `patient` WHERE `patient_ID`=?");
        $stmt->bind_param("i", $id);
        $stmt->execute(); $stmt->close();
        flash('Patient deleted','success');
      } catch (mysqli_sql_exception $e) {
        flash('Delete failed: '.$e->getMessage(),'danger');
      }
    }
    header('Location: patients.php'); exit;
  }
}
?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="h5 mb-0">Patients</h2>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPatient">
    <i class="bi bi-person-plus me-1"></i>Add
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Name</th>
          <th>Email</th>
          <th style="width:90px">Age</th>
          <th style="width:110px">Gender</th>
          <th>Phone</th>
          <th>Location</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rs = $con->query("SELECT * FROM `patient` ORDER BY `patient_ID` ASC");
        while($row = $rs->fetch_assoc()):
        ?>
          <tr>
            <td><?= (int)$row['patient_ID'] ?></td>
            <td><?= h($row['name']) ?></td>
            <td><?= h($row['email']) ?></td>
            <td><?= h($row['age']) ?></td>
            <td><?= h($row['gender']) ?></td>
            <td><?= h($row['phone']) ?></td>
            <td><?= h($row['patient_location']) ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                      data-bs-target="#edit<?= (int)$row['patient_ID'] ?>">
                Edit
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this patient?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="patient_ID" value="<?= (int)$row['patient_ID'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="edit<?= (int)$row['patient_ID'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <form method="post">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="patient_ID" value="<?= (int)$row['patient_ID'] ?>">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit Patient</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input name="name" class="form-control" value="<?= h($row['name']) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input name="email" type="email" class="form-control" value="<?= h($row['email']) ?>">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label">Age</label>
                        <input name="age" type="number" min="0" class="form-control" value="<?= h($row['age']) ?>">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                          <?php
                            $g = strtolower((string)$row['gender']);
                            $opts = ['' => '— None —', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'];
                            foreach ($opts as $val => $label):
                              $sel = ($val !== '' && strtolower($val) === strtolower((string)$row['gender'])) ? 'selected' : '';
                          ?>
                            <option value="<?= h($val) ?>" <?= $sel ?>><?= h($label) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input name="phone" class="form-control" value="<?= h($row['phone']) ?>">
                      </div>
                      <div class="col-12">
                        <label class="form-label">Location</label>
                        <input name="patient_location" class="form-control" value="<?= h($row['patient_location']) ?>" placeholder="e.g., Dhaka • Banani">
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button class="btn btn-primary">Save</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addPatient" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">New Patient</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input name="email" type="email" class="form-control" placeholder="name@example.com">
            </div>
            <div class="col-md-3">
              <label class="form-label">Age</label>
              <input name="age" type="number" min="0" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-select">
                <option value="">— None —</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input name="phone" class="form-control" placeholder="+8801XXXXXXXXX">
            </div>
            <div class="col-12">
              <label class="form-label">Location</label>
              <input name="patient_location" class="form-control" placeholder="e.g., Dhaka • Banani">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; 

ob_end_flush(); // Flush the output buffer
?>
