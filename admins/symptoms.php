<?php
ob_start();  // Start output buffering to fix header already sent issue

require_once __DIR__ . '/includes/header.php';
require_login();

/* helpers */
function norm($v){ $v = trim((string)($v ?? '')); return $v === '' ? null : $v; }
function norm_int($v){ $v = trim((string)($v ?? '')); return ($v === '' || !ctype_digit($v)) ? null : (int)$v; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $name      = norm($_POST['symptom_name'] ?? null);
    $area      = norm($_POST['AffectedArea'] ?? null);
    $duration  = norm($_POST['AffectedDuration'] ?? null);
    $spec_id   = norm_int($_POST['specialization_id'] ?? null);

    if (!$name) { flash('Name is required', 'warning'); header('Location: symptoms.php'); exit; }

    try {
      $stmt = $con->prepare("INSERT INTO `symptom` (`symptom_name`,`AffectedArea`,`AffectedDuration`,`specialization_id`)
                             VALUES (?,?,?,?)");
      $stmt->bind_param("sssi", $name, $area, $duration, $spec_id);
      $stmt->execute(); $stmt->close();
      flash('Symptom added','success');
    } catch (mysqli_sql_exception $e) {
      flash('Create failed: '.$e->getMessage(),'danger');
    }
    header('Location: symptoms.php'); exit;
  }

  if ($action === 'update') {
    $id        = (int)($_POST['symptom_id'] ?? 0);
    $name      = norm($_POST['symptom_name'] ?? null);
    $area      = norm($_POST['AffectedArea'] ?? null);
    $duration  = norm($_POST['AffectedDuration'] ?? null);
    $spec_id   = norm_int($_POST['specialization_id'] ?? null);

    if ($id <= 0) { header('Location: symptoms.php'); exit; }
    if (!$name)   { flash('Name is required','warning'); header('Location: symptoms.php'); exit; }

    try {
      $stmt = $con->prepare("UPDATE `symptom`
                                SET `symptom_name`=?,
                                    `AffectedArea`=?,
                                    `AffectedDuration`=?,
                                    `specialization_id`=?
                              WHERE `symptom_id`=?");
      $stmt->bind_param("sssii", $name, $area, $duration, $spec_id, $id);
      $stmt->execute(); $stmt->close();
      flash('Symptom updated','success');
    } catch (mysqli_sql_exception $e) {
      flash('Update failed: '.$e->getMessage(),'danger');
    }
    header('Location: symptoms.php'); exit;
  }

  if ($action === 'delete') {
    $id = (int)($_POST['symptom_id'] ?? 0);
    if ($id > 0) {
      try {
        $stmt = $con->prepare("DELETE FROM `symptom` WHERE `symptom_id`=?");
        $stmt->bind_param("i", $id);
        $stmt->execute(); $stmt->close();
        flash('Symptom deleted','success');
      } catch (mysqli_sql_exception $e) {
        flash('Delete failed: '.$e->getMessage(),'danger');
      }
    }
    header('Location: symptoms.php'); exit;
  }
}
?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="h5 mb-0">Symptoms</h2>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus"></i> Add
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>Name</th>
          <th>Affected Area</th>
          <th>Duration</th>
          <th>Specialization</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
        <?php
          $sql = "SELECT sy.*, sp.specialization_name
                    FROM `symptom` sy
               LEFT JOIN `specialization` sp
                      ON sp.specialization_id = sy.specialization_id
                ORDER BY sy.symptom_id DESC";
          $rs = $con->query($sql);
          while($row = $rs->fetch_assoc()):
        ?>
        <tr>
          <td><?= (int)$row['symptom_id'] ?></td>
          <td><?= h($row['symptom_name']) ?></td>
          <td><?= h($row['AffectedArea']) ?></td>
          <td><?= h($row['AffectedDuration']) ?></td>
          <td><?= h($row['specialization_name'] ?? '—') ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#edit<?= (int)$row['symptom_id'] ?>">
              Edit
            </button>
            <form method="post" class="d-inline" onsubmit="return confirm('Delete this symptom?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="symptom_id" value="<?= (int)$row['symptom_id'] ?>">
              <button class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          </td>
        </tr>

        <!-- Edit Modal -->
        <div class="modal fade" id="edit<?= (int)$row['symptom_id'] ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog"><div class="modal-content">
            <form method="post">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="symptom_id" value="<?= (int)$row['symptom_id'] ?>">
              <div class="modal-header">
                <h5 class="modal-title">Edit Symptom</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input name="symptom_name" class="form-control" value="<?= h($row['symptom_name']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Affected Area</label>
                    <input name="AffectedArea" class="form-control" value="<?= h($row['AffectedArea']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Duration</label>
                    <input name="AffectedDuration" class="form-control" value="<?= h($row['AffectedDuration']) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Specialization</label>
                    <select name="specialization_id" class="form-select">
                      <option value="">— None —</option>
                      <?php
                        $sp = $con->query("SELECT specialization_id, specialization_name FROM `specialization` ORDER BY specialization_name");
                        while($s = $sp->fetch_assoc()):
                          $sel = ((int)$s['specialization_id'] === (int)$row['specialization_id']) ? 'selected' : '';
                      ?>
                        <option value="<?= (int)$s['specialization_id'] ?>" <?= $sel ?>>
                          <?= h($s['specialization_name']) ?>
                        </option>
                      <?php endwhile; ?>
                    </select>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button class="btn btn-primary">Save</button>
              </div>
            </form>
          </div></div>
        </div>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h5 class="modal-title">New Symptom</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Name</label>
            <input name="symptom_name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Affected Area</label>
            <input name="AffectedArea" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Duration</label>
            <input name="AffectedDuration" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Specialization</label>
            <select name="specialization_id" class="form-select">
              <option value="">— None —</option>
              <?php
                $sp = $con->query("SELECT specialization_id, specialization_name FROM `specialization` ORDER BY specialization_name");
                while($s = $sp->fetch_assoc()):
              ?>
                <option value="<?= (int)$s['specialization_id'] ?>"><?= h($s['specialization_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Create</button>
      </div>
    </form>
  </div></div>
</div>

<?php 
require_once __DIR__ . '/includes/footer.php'; 
ob_end_flush();  // Flush output buffer at the end
?>
