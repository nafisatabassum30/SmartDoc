<?php
ob_start(); // Start output buffering to fix "headers already sent" issue

require_once __DIR__ . '/includes/header.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? '';
  if ($action === 'create'){
    $email = trim($_POST['email'] ?? '');
    $name = trim($_POST['full_name'] ?? '');
    // All admins share the same role now
    $role = 'editor';
    $pass = trim($_POST['password'] ?? '');
    if ($email && $pass){
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $con->prepare('INSERT INTO Admins(email, full_name, password_hash, role) VALUES (?, ?, ?, ?)');
      $stmt->bind_param('ssss', $email, $name, $hash, $role);
      $stmt->execute();
      flash('Admin created');
    }
  }
  // Role editing removed – all admins behave the same
  if ($action === 'reset_password'){
    $id = (int)($_POST['admin_id'] ?? 0);
    $pass = trim($_POST['password'] ?? '');
    if ($id > 0 && $pass){
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $con->prepare('UPDATE Admins SET password_hash=? WHERE admin_id=?');
      $stmt->bind_param('si', $hash, $id);
      $stmt->execute();
      flash('Password reset');
    }
  }
  if ($action === 'delete'){
    $id = (int)($_POST['admin_id'] ?? 0);
    if ($id > 0){
      // Optional safety: prevent deleting yourself
      if ((int)$id === (int)($_SESSION['admin_id'] ?? -1)){
        flash('You cannot delete your own account.');
        header('Location: admins.php');
        exit;
      }
      $stmt = $con->prepare('DELETE FROM `admins` WHERE admin_id=?');
      $stmt->bind_param('i', $id);
      $stmt->execute();
      flash('Admin deleted');
    }
  }
  header('Location: admins.php');
  exit;
}
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="h5 mb-0">Admins</h2>
  <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAdmin">Add admin</button>
</div>
<div class="card"><div class="table-responsive">
<table class="table mb-0 align-middle">
  <thead>
    <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th style="width:220px"></th></tr>
  </thead>
  <tbody>
    <?php $res = $con->query('SELECT admin_id, full_name, email, role, created_at FROM `admins` ORDER BY admin_id DESC'); while ($row = $res->fetch_assoc()): ?>
      <tr>
        <td><?= (int)$row['admin_id'] ?></td>
        <td><?= h($row['full_name']) ?></td>
        <td><?= h($row['email']) ?></td>
        <td>admin</td>
        <td><?= h($row['created_at']) ?></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= (int)$row['admin_id'] ?>">Edit</button>
          <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reset<?= (int)$row['admin_id'] ?>">Reset Password</button>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this admin?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="admin_id" value="<?= (int)$row['admin_id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Delete</button>
          </form>
        </td>
      </tr>

      <!-- Role editing removed -->

      <div class="modal fade" id="reset<?= (int)$row['admin_id'] ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
        <form method="post">
          <input type="hidden" name="action" value="reset_password"><input type="hidden" name="admin_id" value="<?= (int)$row['admin_id'] ?>">
          <div class="modal-header"><h5 class="modal-title">Reset Password</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body"><label class="form-label">New password</label><input name="password" type="password" class="form-control" required></div>
          <div class="modal-footer"><button class="btn btn-warning">Update Password</button></div>
        </form>
      </div></div></div>
    <?php endwhile; ?>
  </tbody>
</table>
</div></div>

<div class="modal fade" id="addAdmin" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form method="post">
    <input type="hidden" name="action" value="create">
    <div class="modal-header"><h5 class="modal-title">New Admin</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-2"><label class="form-label">Full name</label><input name="full_name" class="form-control"></div>
      <div class="mb-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-primary">Create</button></div>
  </form>
</div></div></div>

<?php 
require_once __DIR__ . '/includes/footer.php'; 

ob_end_flush(); // Flush the output buffer
?>
