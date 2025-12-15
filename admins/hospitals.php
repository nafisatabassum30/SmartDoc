<?php
ob_start(); // Start output buffering to prevent header errors

require_once __DIR__ . '/includes/header.php';
require_login();

function normalize_id($raw){ return (isset($raw) && ctype_digit((string)$raw) && (int)$raw>0) ? (int)$raw : null; }

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if ($action==='create'){
    $name=trim($_POST['hospital_name']??'');
    $address=trim($_POST['address']??'');
    $location_id = normalize_id($_POST['location_id']??null);
    if ($name!==''){
      $stmt=$con->prepare('INSERT INTO `hospital`(hospital_name,address,location_id) VALUES (?,?,?)');
      $stmt->bind_param('ssi',$name,$address,$location_id);
      try{$stmt->execute(); flash('Hospital added','success');}catch(mysqli_sql_exception $e){flash('Create failed: '.$e->getMessage(),'danger');}
      $stmt->close();
    } else { flash('Name is required','warning'); }
  }
  if ($action==='update'){
    $id=(int)($_POST['hospital_id']??0);
    if ($id>0){
      $name=trim($_POST['hospital_name']??'');
      $address=trim($_POST['address']??'');
      $location_id = normalize_id($_POST['location_id']??null);
      $stmt=$con->prepare('UPDATE `hospital` SET hospital_name=?, address=?, location_id=? WHERE hospital_id=?');
      $stmt->bind_param('ssii',$name,$address,$location_id,$id);
      try{$stmt->execute(); flash('Hospital updated','success');}catch(mysqli_sql_exception $e){flash('Update failed: '.$e->getMessage(),'danger');}
      $stmt->close();
    }
  }
  if ($action==='delete'){
    $id=(int)($_POST['hospital_id']??0);
    if ($id>0){ $stmt=$con->prepare('DELETE FROM `hospital` WHERE hospital_id=?'); $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close(); flash('Hospital deleted','success'); }
  }
  header('Location: hospitals.php'); exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="h5 mb-0">Hospitals</h2>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><i class="bi bi-plus"></i> Add</button>
</div>

<div class="card"><div class="table-responsive">
<table class="table mb-0 align-middle">
  <thead><tr>
    <th>ID</th><th>Name</th><th>Address</th><th>Location</th><th style="width:160px"></th>
  </tr></thead>
  <tbody>
<?php
$rs=$con->query("
  SELECT h.*, CONCAT(l.city, CASE WHEN l.city IS NOT NULL AND l.area_name IS NOT NULL THEN ' • ' ELSE '' END, l.area_name) AS loc_label
  FROM `hospital` h
  LEFT JOIN `location` l ON h.location_id=l.location_id
  ORDER BY h.hospital_id ASC
");
while($row=$rs->fetch_assoc()): ?>
  <tr>
    <td><?= (int)$row['hospital_id'] ?></td>
    <td><?= h($row['hospital_name']) ?></td>
    <td><?= h($row['address']) ?></td>
    <td><?= h($row['loc_label'] ?? '—') ?></td>
    <td class="text-end">
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$row['hospital_id'] ?>">Edit</button>
      <form method="post" class="d-inline" onsubmit="return confirm('Delete this hospital?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="hospital_id" value="<?= (int)$row['hospital_id'] ?>">
        <button class="btn btn-sm btn-outline-danger">Delete</button>
      </form>
    </td>
  </tr>
  <!-- Edit Modal -->
  <div class="modal fade" id="edit<?= (int)$row['hospital_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
      <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="hospital_id" value="<?= (int)$row['hospital_id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Hospital</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-2">
            <div class="col-12"><label class="form-label">Name</label><input name="hospital_name" class="form-control" value="<?= h($row['hospital_name']) ?>" required></div>
            <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= h($row['address']) ?>"></div>
            <div class="col-12">
              <label class="form-label">Location</label>
              <select name="location_id" class="form-select">
                <option value="">— None —</option>
                <?php $loc=$con->query("SELECT location_id, CONCAT(city, CASE WHEN city IS NOT NULL AND area_name IS NOT NULL THEN ' • ' ELSE '' END, area_name) AS label FROM `location` ORDER BY city, area_name");
                while($l=$loc->fetch_assoc()):
                  $sel = ((int)$l['location_id'] === (int)$row['location_id']) ? 'selected' : '';
                ?>
                  <option value="<?= (int)$l['location_id'] ?>" <?= $sel ?>><?= h($l['label']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
      </form>
    </div></div>
  </div>
<?php endwhile; ?>
  </tbody>
</table>
</div></div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="modal-header"><h5 class="modal-title">New Hospital</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-12"><label class="form-label">Name</label><input name="hospital_name" class="form-control" required></div>
          <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control"></div>
          <div class="col-12">
            <label class="form-label">Location</label>
            <select name="location_id" class="form-select">
              <option value="">— None —</option>
              <?php $loc=$con->query("SELECT location_id, CONCAT(city, CASE WHEN city IS NOT NULL AND area_name IS NOT NULL THEN ' • ' ELSE '' END, area_name) AS label FROM `location` ORDER BY city, area_name");
              while($l=$loc->fetch_assoc()): ?>
                <option value="<?= (int)$l['location_id'] ?>"><?= h($l['label']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-primary">Create</button></div>
    </form>
  </div></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; 

ob_end_flush(); // Flush the output buffer
?>
