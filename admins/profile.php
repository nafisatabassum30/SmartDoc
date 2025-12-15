<?php
ob_start();
require_once __DIR__ . '/includes/header.php';
require_login();

$admin_id = (int)($_SESSION['admin_id'] ?? 0);

// Get admin data (includes password_hash)
$stmt = $con->prepare("SELECT * FROM `admins` WHERE admin_id = ?");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? 'profile';

  if ($action === 'password') {
    // Change password
    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($new === '' || $confirm === '') {
      flash('New password and confirmation are required.', 'warning');
    } elseif (strlen($new) < 8) {
      flash('New password must be at least 8 characters long.', 'warning');
    } elseif ($new !== $confirm) {
      flash('New password and confirmation do not match.', 'warning');
    } elseif (empty($admin['password_hash']) || !password_verify($current, $admin['password_hash'])) {
      flash('Your current password is incorrect.', 'danger');
    } else {
      $newHash = password_hash($new, PASSWORD_DEFAULT);
      $stmt = $con->prepare("UPDATE `admins` SET password_hash=? WHERE admin_id=?");
      $stmt->bind_param('si', $newHash, $admin_id);
      if ($stmt->execute()) {
        flash('Password updated successfully.', 'success');
        header('Location: profile.php');
        exit;
      } else {
        flash('Failed to update password. Please try again.', 'danger');
      }
    }
  } else {
    // Update basic profile info
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (!$name){
      flash('Full name is required', 'warning');
    } elseif (!$email){
      flash('Email is required', 'warning');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)){
      flash('Invalid email address', 'warning');
    } else {
      // Check if email is taken by another admin
      $chk = $con->prepare("SELECT admin_id FROM `admins` WHERE email=? AND admin_id != ?");
      $chk->bind_param('si', $email, $admin_id);
      $chk->execute();
      if ($chk->get_result()->fetch_assoc()){
        flash('Email already taken', 'warning');
      } else {
        $stmt = $con->prepare("UPDATE admins SET full_name=?, email=? WHERE admin_id=?");
        $stmt->bind_param('ssi', $name, $email, $admin_id);
        $stmt->execute();
        $_SESSION['admin_name'] = $name;
        $_SESSION['admin_email'] = $email;
        flash('Profile updated successfully');
        header('Location: profile.php');
        exit;
      }
    }
  }
}
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="rounded-circle d-inline-grid place-items-center me-3" style="width:64px;height:64px;background:#e0f2fe;color:#0369a1;display:grid;place-items:center;">
            <i class="bi bi-shield-lock fs-3"></i>
          </div>
          <div>
            <div class="h5 mb-1"><?= h($admin['full_name'] ?? 'Admin') ?></div>
            <div class="small text-muted">Admin Profile</div>
          </div>
        </div>
        <div class="list-group list-group-flush small">
          <div class="list-group-item px-0 d-flex align-items-center">
            <i class="bi bi-envelope text-primary me-2"></i>
            <span><?= h($admin['email'] ?? '—') ?></span>
          </div>
          <div class="list-group-item px-0 d-flex align-items-center">
            <i class="bi bi-shield-check text-primary me-2"></i>
            <span><span class="badge bg-primary">ADMIN</span></span>
          </div>
          <div class="list-group-item px-0 d-flex align-items-center">
            <i class="bi bi-calendar text-primary me-2"></i>
            <span><?= !empty($admin['created_at']) ? date('M d, Y', strtotime($admin['created_at'])) : '—' ?></span>
          </div>
        </div>
        <hr>
        <div class="small text-muted">Tip: Keep your profile updated for better account management.</div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Edit Profile</h5>
          <span class="badge rounded-pill text-bg-primary">SmartDoc Admin</span>
        </div>
      </div>
      <div class="card-body">
        <?php flash(); ?>
        <form method="post">
          <input type="hidden" name="action" value="profile">
          <div class="row g-3">
            <div class="col-md-12">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input name="full_name" type="text" class="form-control" value="<?= h($admin['full_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-12">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input name="email" type="email" class="form-control" value="<?= h($admin['email'] ?? '') ?>" required>
            </div>
            <!-- Role editing note removed; all admins have the same capabilities -->
          </div>
          <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header">
        <h5 class="mb-0">Change Password</h5>
      </div>
      <div class="card-body">
        <form method="post" autocomplete="off">
          <input type="hidden" name="action" value="password">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" autocomplete="new-password" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
            </div>
          </div>
          <div class="mt-4">
            <button class="btn btn-outline-primary">
              <i class="bi bi-shield-lock me-1"></i>Update Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ob_end_flush(); ?>

