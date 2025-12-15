<?php
ob_start();
require_once __DIR__ . '/includes/header.php';
require_patient_login();

$patient_id = get_patient_id();

// Get patient data (includes password_hash)
$patient = $con->query("SELECT * FROM `patient` WHERE patient_ID = $patient_id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  $action = $_POST['action'] ?? 'profile';

  if ($action === 'password') {
    // Handle password change
    $current = trim($_POST['current_password'] ?? '');
    $new     = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($new === '' || $confirm === '') {
      flash('New password and confirmation are required.', 'warning');
    } elseif (strlen($new) < 8) {
      flash('New password must be at least 8 characters long.', 'warning');
    } elseif ($new !== $confirm) {
      flash('New password and confirmation do not match.', 'warning');
    } elseif (!empty($patient['password_hash']) && !password_verify($current, $patient['password_hash'])) {
      flash('Your current password is incorrect.', 'danger');
    } else {
      $newHash = password_hash($new, PASSWORD_DEFAULT);
      $stmt = $con->prepare("UPDATE `patient` SET password_hash=? WHERE patient_ID=?");
      $stmt->bind_param('si', $newHash, $patient_id);
      if ($stmt->execute()) {
        flash('Password updated successfully.', 'success');
        header('Location: profile.php');
        exit;
      } else {
        flash('Failed to update password. Please try again.', 'danger');
      }
    }
  } else {
    // Handle profile update
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['patient_location'] ?? '');
    
    if (!$name){
      flash('Name is required', 'warning');
    } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)){
      flash('Invalid email address', 'warning');
    } else {
      // Check if email is taken by another patient
      $chk = $con->prepare("SELECT patient_ID FROM `patient` WHERE email=? AND patient_ID != ?");
      $chk->bind_param('si', $email, $patient_id);
      $chk->execute();
      if ($chk->get_result()->fetch_assoc()){
        flash('Email already taken', 'warning');
      } else {
        $stmt = $con->prepare("UPDATE patient SET name=?, email=?, age=?, gender=?, phone=?, patient_location=? WHERE patient_ID=?");
        $stmt->bind_param('ssisssi', $name, $email, $age, $gender, $phone, $location, $patient_id);
        $stmt->execute();
        $_SESSION['patient_name'] = $name;
        $_SESSION['patient_email'] = $email;
        flash('Profile updated successfully');
        header('Location: profile.php');
        exit;
      }
    }
  }
}
?>

<style>
  /* Match dashboard look & feel */
  main.container-fluid{padding-top:0 !important; padding-left:0 !important; padding-right:0 !important;}
  .hero-gradient{
    background: linear-gradient(180deg, rgba(14,165,233,0.55) 0%, rgba(14,165,233,0.25) 35%, rgba(14,165,233,0.10) 70%, rgba(14,165,233,0.03) 100%);
    padding-top:2rem; padding-bottom:1rem;
  }
</style>

<!-- HERO -->
<div class="hero-gradient">
  <div class="container">
    <div class="alert alert-info alert-persistent fade show mb-4 w-100 shadow-sm border-0" role="alert">
      <div class="d-flex align-items-center">
        <div>
          <strong>Profile, <?= h(get_patient_name()) ?>.</strong>
          <p class="mb-0 small">Review and keep your information up to date.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- PROFILE CONTENT -->
<div class="container my-4">
  <div class="row g-4">
    <!-- Left: summary card -->
    <div class="col-lg-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="rounded-circle d-inline-grid place-items-center me-3"
                 style="width:64px;height:64px;background:#e0f2fe;color:#0369a1;display:grid;place-items:center;">
              <i class="bi bi-person fs-3"></i>
            </div>
            <div>
              <div class="h5 mb-1"><?= h($patient['name'] ?? get_patient_name() ?? 'Patient') ?></div>
              <div class="small text-muted">Patient Profile</div>
            </div>
          </div>
          <div class="list-group list-group-flush small">
            <div class="list-group-item px-0 d-flex align-items-center">
              <i class="bi bi-envelope text-primary me-2"></i>
              <span><?= h($patient['email'] ?? ($_SESSION['patient_email'] ?? '—')) ?></span>
            </div>
            <div class="list-group-item px-0 d-flex align-items-center">
              <i class="bi bi-telephone text-primary me-2"></i>
              <span><?= h($patient['phone'] ?? '—') ?></span>
            </div>
            <div class="list-group-item px-0 d-flex align-items-center">
              <i class="bi bi-geo text-primary me-2"></i>
              <span><?= h($patient['patient_location'] ?? '—') ?></span>
            </div>
          </div>
          <hr>
          <div class="small text-muted">
            Tip: Keep your profile updated for better doctor matches and reminders.
          </div>
        </div>
      </div>
    </div>

    <!-- Right: edit form + change password -->
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Edit Profile</h5>
          <span class="badge rounded-pill text-bg-primary">SmartDoc</span>
        </div>
        <div class="card-body">
          <?php flash(); ?>
          <form method="post">
            <input type="hidden" name="action" value="profile">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input name="name" type="text" class="form-control" value="<?= h($patient['name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input name="email" type="email" class="form-control" value="<?= h($patient['email']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Age</label>
                <input name="age" type="number" min="0" class="form-control" value="<?= h($patient['age']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                  <option value="">— Select —</option>
                  <option value="Male" <?= $patient['gender']==='Male' ? 'selected' : '' ?>>Male</option>
                  <option value="Female" <?= $patient['gender']==='Female' ? 'selected' : '' ?>>Female</option>
                  <option value="Other" <?= $patient['gender']==='Other' ? 'selected' : '' ?>>Other</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input name="phone" type="text" class="form-control" value="<?= h($patient['phone']) ?>" placeholder="+8801XXXXXXXXX">
              </div>
              <div class="col-12">
                <label class="form-label">Location</label>
                <input name="patient_location" type="text" class="form-control" value="<?= h($patient['patient_location']) ?>" placeholder="e.g., Dhaka • Banani">
              </div>
            </div>
            <div class="mt-4 d-flex gap-2">
              <button class="btn btn-primary">
                <i class="bi bi-save me-1"></i>Save Changes
              </button>
              <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mt-4">
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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ob_end_flush(); ?>

