<?php
ob_start();  // Add this line at the very top

require_once __DIR__ . '/includes/header.php';
require_login();

if ($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'';
  if ($action==='create'){
    $name=trim($_POST['specialization_name']??'');
    if ($name!==''){ $stmt=$con->prepare('INSERT INTO `specialization` (specialization_name) VALUES (?)'); $stmt->bind_param('s',$name); $stmt->execute(); flash('Specialization added'); }
  }
  if ($action==='update'){
    $id=(int)($_POST['specialization_id']??0); $name=trim($_POST['specialization_name']??'');
    if ($id>0 && $name!==''){ $stmt=$con->prepare('UPDATE `specialization` SET specialization_name=? WHERE specialization_id=?'); $stmt->bind_param('si',$name,$id); $stmt->execute(); flash('Specialization updated'); }
  }
  if ($action==='delete'){
    $id=(int)($_POST['specialization_id']??0);
    if ($id>0){ $stmt=$con->prepare('DELETE FROM `specialization` WHERE specialization_id=?'); $stmt->bind_param('i',$id); $stmt->execute(); flash('Specialization deleted'); }
  }
  header('Location: specializations.php'); exit;
}
?>
<!-- your existing HTML and PHP as is -->
<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="h5 mb-0">Specializations</h2>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSpec">Add</button>
</div>
<div class="card"><div class="table-responsive">
<table class="table mb-0 align-middle">
  <thead><tr><th>ID</th><th>Name</th><th style="width:140px"></th></tr></thead>
  <tbody>
    <?php $res=$con->query('SELECT specialization_id,specialization_name FROM `specialization` ORDER BY specialization_id DESC'); while($row=$res->fetch_assoc()): ?>
      <tr>
        <td><?= (int)$row['specialization_id'] ?></td>
        <td><?= h($row['specialization_name']) ?></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$row['specialization_id'] ?>">Edit</button>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this specialization?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="specialization_id" value="<?= (int)$row['specialization_id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </td>
      </tr>
      <div class="modal fade" id="edit<?= (int)$row['specialization_id'] ?>" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
          <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="specialization_id" value="<?= (int)$row['specialization_id'] ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Specialization</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><label class="form-label">Name</label><input name="specialization_name" class="form-control" value="<?= h($row['specialization_name']) ?>" required></div>
            <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
          </form>
        </div></div>
      </div>
    <?php endwhile; ?>
  </tbody>
</table>
</div></div>

<div class="modal fade" id="addSpec" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <input type="hidden" name="action" value="create">
    <div class="modal-header"><h5 class="modal-title">New Specialization</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><label class="form-label">Name</label><input name="specialization_name" class="form-control" placeholder="Cardiology" required></div>
    <div class="modal-footer"><button class="btn btn-primary">Create</button></div>
  </form>
</div></div></div>
<?php require_once __DIR__ . '/includes/footer.php';

ob_end_flush(); // Flush the output buffer

?>
