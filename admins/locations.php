<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';

/**
 * Trim to NULL helper
 */
function norm($v){ $v = trim((string)($v ?? '')); return ($v === '') ? null : $v; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'create') {
    $city = norm($_POST['city'] ?? null);
    $area = norm($_POST['area_name'] ?? null);

    // At least one piece of info should be present
    if ($city || $area) {
      try {
        $stmt = $con->prepare("INSERT INTO `location`(city, area_name) VALUES (?, ?)");
        $stmt->bind_param("ss", $city, $area);
        $stmt->execute();
        $stmt->close();
        flash('Location added','success');
      } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'uq_location_city_area')) {
          flash('Duplicate city + area. This location already exists.','warning');
        } else {
          flash('Create failed: '.$e->getMessage(),'danger');
        }
      }
    } else {
      flash('Enter at least City or Area','warning');
    }
  }

  if ($action === 'update') {
    $id   = (int)($_POST['location_id'] ?? 0);
    $city = norm($_POST['city'] ?? null);
    $area = norm($_POST['area_name'] ?? null);

    if ($id > 0) {
      try {
        $stmt = $con->prepare("UPDATE `location` SET city=?, area_name=? WHERE location_id=?");
        $stmt->bind_param("ssi", $city, $area, $id);
        $stmt->execute();
        $stmt->close();
        flash('Location updated','success');
      } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'uq_location_city_area')) {
          flash('Duplicate city + area. This location already exists.','warning');
        } else {
          flash('Update failed: '.$e->getMessage(),'danger');
        }
      }
    }
  }

  if ($action === 'delete') {
    $id = (int)($_POST['location_id'] ?? 0);
    if ($id > 0) {
      try {
        // Hospital.location_id uses ON DELETE SET NULL → safe to delete
        $stmt = $con->prepare("DELETE FROM `location` WHERE location_id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        flash('Location deleted','success');
      } catch (mysqli_sql_exception $e) {
        flash('Delete failed: '.$e->getMessage(),'danger');
      }
    }
  }

  header('Location: locations.php'); exit;
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="h5 mb-0">Locations</h2>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLocation">
    <i class="bi bi-geo-alt-fill me-1"></i>Add
  </button>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead>
        <tr>
          <th style="width:80px">ID</th>
          <th>City</th>
          <th>Area</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rs = $con->query("SELECT * FROM `location` ORDER BY COALESCE(city,'') ASC, COALESCE(area_name,'') ASC, location_id DESC");
        while($row = $rs->fetch_assoc()):
        ?>
          <tr>
            <td><?= (int)$row['location_id'] ?></td>
            <td><?= h($row['city']) ?></td>
            <td><?= h($row['area_name']) ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$row['location_id'] ?>">
                Edit
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this location? Hospitals linked will keep NULL.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="location_id" value="<?= (int)$row['location_id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="edit<?= (int)$row['location_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="post">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="location_id" value="<?= (int)$row['location_id'] ?>">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="row g-2">
                      <div class="col-12">
                        <label class="form-label">City</label>
                        <input name="city" class="form-control" value="<?= h($row['city']) ?>">
                      </div>
                      <div class="col-12">
                        <label class="form-label">Area</label>
                        <input name="area_name" class="form-control" value="<?= h($row['area_name']) ?>">
                      </div>
                      <div class="small text-muted">* Combination (City + Area) must be unique.</div>
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
<div class="modal fade" id="addLocation" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">New Location</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label">City</label>
              <input name="city" class="form-control" placeholder="e.g., Dhaka">
            </div>
            <div class="col-12">
              <label class="form-label">Area</label>
              <input name="area_name" class="form-control" placeholder="e.g., Banani">
            </div>
            <div class="small text-muted">* Enter at least one of City/Area. Both preferred.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
