<?php
// Load back-end only (no output!) so we can safely redirect later.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

// Detect AJAX requests so we can respond with JSON (no full page reload)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

/**
 * Normalize an FK id:
 * - Empty/blank/0/non-numeric => NULL
 * - If numeric, check existence in the given table/column; if not, return NULL
 */
function normalize_fk($raw, string $table, string $pkCol, mysqli $con): ?int {
    if (!isset($raw)) return null;
    $val = trim((string)$raw);
    if ($val === '' || !ctype_digit($val) || (int)$val <= 0) return null;

    $id = (int)$val;
    $sql = "SELECT 1 FROM `$table` WHERE `$pkCol` = ? LIMIT 1";
    $chk = $con->prepare($sql);
    $chk->bind_param("i", $id);
    $chk->execute();
    // Use fetch_row for broad PHP version support
    $exists = (bool) $chk->get_result()->fetch_row();
    $chk->close();

    return $exists ? $id : null;
}

// ----- Handle POST BEFORE any output -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Cleanup ratings where rating_count is 0
    if ($action === 'cleanup_ratings') {
        try {
            $stmt = $con->prepare("
                UPDATE `doctor` 
                SET `ratings(out of 5)` = 0.00 
                WHERE `rating_count` = 0 OR `rating_count` IS NULL
            ");
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            flash("Cleaned up {$affected} doctor record(s) with no ratings", 'success');
        } catch (mysqli_sql_exception $e) {
            flash('Cleanup error: ' . $e->getMessage(), 'danger');
        }
        if (!$isAjax) {
            header('Location: doctors.php');
            exit;
        }
    }

    // Common fields
    $name        = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $url         = trim($_POST['website_url'] ?? '');

    // FKs (nullable, verified)
    $specId     = normalize_fk($_POST['specialization_id'] ?? null, 'specialization', 'specialization_id', $con);
    $hospitalId = normalize_fk($_POST['hospital_id'] ?? null,       'hospital',       'hospital_id',       $con);

    try {
        if ($action === 'create') {
            if ($name !== '') {
                $stmt = $con->prepare(
                    "INSERT INTO `doctor`
                       (`name`,`designation`,`website_url`,`specialization_id`,`hospital_id`)
                     VALUES (?,?,?,?,?)"
                );
                $stmt->bind_param("sssii", $name, $designation, $url, $specId, $hospitalId);
                $stmt->execute();
                $stmt->close();
                flash('Doctor added', 'success');
            } else {
                flash('Name is required', 'warning');
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['doctor_id'] ?? 0);
            if ($id > 0) {
                $stmt = $con->prepare(
                    "UPDATE `doctor`
                        SET `name`=?, `designation`=?, `website_url`=?, `specialization_id`=?, `hospital_id`=?
                      WHERE `doctor_id`=?"
                );
                $stmt->bind_param("sssiii", $name, $designation, $url, $specId, $hospitalId, $id);
                $stmt->execute();
                $stmt->close();

                // For AJAX, return the fresh row data so the table can be updated without reload
                if ($isAjax) {
                    $stmt = $con->prepare("
                        SELECT d.doctor_id, d.name, d.designation, d.website_url,
                               d.`ratings(out of 5)` as ratings, d.rating_count,
                               s.specialization_name, h.hospital_name
                        FROM `doctor` d
                        LEFT JOIN `specialization` s ON d.specialization_id = s.specialization_id
                        LEFT JOIN `hospital`       h ON d.hospital_id       = h.hospital_id
                        WHERE d.doctor_id = ?
                    ");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res->fetch_assoc();
                    $stmt->close();

                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'doctor'  => $row,
                    ]);
                    exit;
                }

                flash('Doctor updated', 'success');
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['doctor_id'] ?? 0);
            if ($id > 0) {
                $stmt = $con->prepare("DELETE FROM `doctor` WHERE `doctor_id`=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                flash('Doctor deleted', 'success');
            }
        }
    } catch (mysqli_sql_exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ]);
            exit;
        } else {
            flash('Database error: ' . $e->getMessage(), 'danger');
        }
    }

    if (!$isAjax) {
        // Redirect BEFORE any output for normal form posts
        header('Location: doctors.php');
        exit;
    }
}

// ---- From here on, rendering is safe ----
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="h5 mb-0">Doctors</h2>
  <div>
    <form method="post" class="d-inline" onsubmit="return confirm('Clean up ratings for doctors with no ratings?');">
      <input type="hidden" name="action" value="cleanup_ratings">
      <button class="btn btn-sm btn-outline-secondary me-2" type="submit">
        <i class="bi bi-arrow-clockwise me-1"></i>Cleanup Ratings
      </button>
    </form>
    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctor">
      <i class="bi bi-person-plus me-1"></i>Add
    </button>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Designation</th>
          <th>Specialization</th>
          <th>Hospital</th>
          <th>Rating</th>
          <th>URL</th>
          <th style="width:160px"></th>
        </tr>
      </thead>
      <tbody>
        <?php
        $res = $con->query("
          SELECT d.*,
                 d.`ratings(out of 5)` as ratings,
                 d.rating_count,
                 s.specialization_name,
                 h.hospital_name
          FROM `doctor` d
          LEFT JOIN `specialization` s ON d.specialization_id = s.specialization_id
          LEFT JOIN `hospital`       h ON d.hospital_id       = h.hospital_id
          ORDER BY d.doctor_id ASC
        ");
        while ($row = $res->fetch_assoc()):
        ?>
          <tr data-doctor-id="<?= (int)$row['doctor_id'] ?>">
            <td><?= (int)$row['doctor_id'] ?></td>
            <td data-field="name"><?= h($row['name']) ?></td>
            <td data-field="designation"><?= h($row['designation']) ?></td>
            <td data-field="specialization"><?= h($row['specialization_name'] ?? '—') ?></td>
            <td data-field="hospital"><?= h($row['hospital_name'] ?? '—') ?></td>
            <td data-field="rating">
              <?php
              $rating = isset($row['ratings']) ? (float)$row['ratings'] : 0.00;
              $ratingCount = isset($row['rating_count']) ? (int)$row['rating_count'] : 0;
              if ($ratingCount > 0) {
                echo number_format($rating, 2) . '/5 (' . $ratingCount . ')';
              } else {
                echo '—';
              }
              ?>
            </td>
            <td data-field="url">
              <?php if (!empty($row['website_url'])): ?>
                <a href="<?= h($row['website_url']) ?>" target="_blank" rel="noopener">link</a>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary"
                      data-bs-toggle="modal"
                      data-bs-target="#edit<?= (int)$row['doctor_id'] ?>">
                Edit
              </button>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this doctor?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="doctor_id" value="<?= (int)$row['doctor_id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="edit<?= (int)$row['doctor_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
              <form method="post" class="doctor-edit-form">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="doctor_id" value="<?= (int)$row['doctor_id'] ?>">
                  <div class="modal-header">
                    <h5 class="modal-title">Edit Doctor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="row g-2">
                      <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input name="name" class="form-control" value="<?= h($row['name']) ?>" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Designation</label>
                        <input name="designation" class="form-control" value="<?= h($row['designation']) ?>">
                      </div>
                      <div class="col-12">
                        <label class="form-label">Website URL</label>
                        <input name="website_url" class="form-control" value="<?= h($row['website_url'] ?? '') ?>">
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Specialization</label>
                        <select name="specialization_id" class="form-select">
                          <option value="">— None —</option>
                          <?php
                          $sp=$con->query('SELECT specialization_id, specialization_name FROM `specialization` ORDER BY specialization_name');
                          while($s=$sp->fetch_assoc()):
                            $sel = ((int)$s['specialization_id'] === (int)$row['specialization_id']) ? 'selected' : '';
                          ?>
                            <option value="<?= (int)$s['specialization_id'] ?>" <?= $sel ?>>
                              <?= h($s['specialization_name']) ?>
                            </option>
                          <?php endwhile; ?>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Hospital</label>
                        <select name="hospital_id" class="form-select">
                          <option value="">— None —</option>
                          <?php
                          $hp=$con->query('SELECT hospital_id, hospital_name FROM `hospital` ORDER BY hospital_name');
                          while($h=$hp->fetch_assoc()):
                            $selH = ((int)$h['hospital_id'] === (int)$row['hospital_id']) ? 'selected' : '';
                          ?>
                            <option value="<?= (int)$h['hospital_id'] ?>" <?= $selH ?>>
                              <?= h($h['hospital_name']) ?>
                            </option>
                          <?php endwhile; ?>
                        </select>
                      </div>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button class="btn btn-primary" type="submit">Save</button>
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
<div class="modal fade" id="addDoctor" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">New Doctor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Designation</label>
              <input name="designation" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label">Website URL</label>
              <input name="website_url" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Specialization</label>
              <select name="specialization_id" class="form-select">
                <option value="">— None —</option>
                <?php
                $sp=$con->query('SELECT specialization_id, specialization_name FROM `specialization` ORDER BY specialization_name');
                while($s=$sp->fetch_assoc()):
                ?>
                  <option value="<?= (int)$s['specialization_id'] ?>">
                    <?= h($s['specialization_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Hospital</label>
              <select name="hospital_id" class="form-select">
                <option value="">— None —</option>
                <?php
                $hp=$con->query('SELECT hospital_id, hospital_name FROM `hospital` ORDER BY hospital_name');
                while($h=$hp->fetch_assoc()):
                ?>
                  <option value="<?= (int)$h['hospital_id'] ?>">
                    <?= h($h['hospital_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
// Enable AJAX editing for doctor forms so multiple edits don't require page reloads
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.doctor-edit-form').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn ? submitBtn.innerHTML : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Saving...';
      }

      try {
        const resp = await fetch('doctors.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });

        const data = await resp.json();
        if (!data || !data.success) {
          alert((data && data.message) || 'Could not save doctor. Please try again.');
          return;
        }

        const d = data.doctor;
        const row = document.querySelector(`tr[data-doctor-id="${d.doctor_id}"]`);
        if (row) {
          const nameCell  = row.querySelector('[data-field="name"]');
          const desigCell = row.querySelector('[data-field="designation"]');
          const specCell  = row.querySelector('[data-field="specialization"]');
          const hospCell  = row.querySelector('[data-field="hospital"]');
          const ratingCell = row.querySelector('[data-field="rating"]');
          const urlCell   = row.querySelector('[data-field="url"]');

          if (nameCell)  nameCell.textContent  = d.name || '';
          if (desigCell) desigCell.textContent = d.designation || '';
          if (specCell)  specCell.textContent  = d.specialization_name || '—';
          if (hospCell)  hospCell.textContent  = d.hospital_name || '—';
          if (ratingCell) {
            const rating = parseFloat(d.ratings || 0);
            const ratingCount = parseInt(d.rating_count || 0);
            if (ratingCount > 0) {
              ratingCell.textContent = rating.toFixed(2) + '/5 (' + ratingCount + ')';
            } else {
              ratingCell.textContent = '—';
            }
          }
          if (urlCell) {
            if (d.website_url) {
              urlCell.innerHTML = `<a href="${d.website_url}" target="_blank" rel="noopener">link</a>`;
            } else {
              urlCell.innerHTML = '';
            }
          }
        }

        // Close the modal after successful save
        const modalEl = form.closest('.modal');
        if (modalEl && window.bootstrap) {
          const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
          modal.hide();
        }
      } catch (err) {
        console.error(err);
        alert('Network error while saving doctor.');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      }
    });
  });
});
</script>
