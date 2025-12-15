<?php
ob_start();
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (!empty($_SESSION['patient_logged'])) {
  header('Location: index.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass = trim($_POST['password'] ?? '');
  $confirm = trim($_POST['confirm'] ?? '');
  $age = !empty($_POST['age']) ? (int)$_POST['age'] : null;
  $gender = trim($_POST['gender'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $location = trim($_POST['patient_location'] ?? '');

  if (!$name || !$email || !$pass) {
    $error = 'Name, email, and password are required.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Invalid email address.';
  } elseif ($pass !== $confirm) {
    $error = 'Passwords do not match.';
  } elseif (strlen($pass) < 6) {
    $error = 'Password must be at least 6 characters.';
  } else {
    // Check if email exists
    $chk = $con->prepare("SELECT patient_ID FROM `patient` WHERE email=?");
    $chk->bind_param('s', $email);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()) {
      $error = 'Email already registered.';
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $con->prepare("INSERT INTO patient (name, email, password_hash, age, gender, phone, patient_location) VALUES (?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param('sssisss', $name, $email, $hash, $age, $gender, $phone, $location);
      $stmt->execute();
      $patient_id = $con->insert_id;

      $_SESSION['patient_logged'] = true;
      $_SESSION['patient_id'] = $patient_id;
      $_SESSION['patient_name'] = $name;
      $_SESSION['patient_email'] = $email;
      header('Location: index.php');
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SmartDoc - Patient Registration</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background: #f9fbff;
      height: 100vh;
      margin: 0;
      font-family: Arial, sans-serif;
      overflow-x: hidden;
    }

    .auth-container {
      display: flex;
      height: 100vh;
      min-height: 650px;
    }

    /* Left side: image covers entire area */
    .auth-left {
      flex: 1;
      background: url('../admins/assets/Signup.png') no-repeat center center;
      background-size: cover;
      user-select: none;
    }

    /* Right side - flex column to stack form + info text */
    .auth-right {
      flex: 1;
      background: #fafafa;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 3rem 3rem 5rem 3rem;
      position: relative;
    }

    /* Registration form container */
    .register-form {
      width: 100%;
      max-width: 480px;
      background: #fafafa;
      border-radius: 8px;
      padding: 0;
      z-index: 2;
    }

    .register-form h2 {
      text-align: center;
      margin-bottom: 2rem;
      font-weight: 700;
      font-size: 1.3rem;
      color: #000;
    }

    .register-form .row > [class*="col-"] {
      margin-bottom: 1rem;
    }

    .register-form label {
      font-weight: 600;
      font-size: 0.95rem;
      display: block;
      margin-bottom: 0.4rem;
    }

    /* Large inputs & selects */
    .register-form input.form-control,
    .register-form select.form-select {
      width: 100%;
      padding: 12px 14px;
      font-size: 1rem;
      border: 1px solid #cbd3df;
      border-radius: 6px;
      box-sizing: border-box;
      background-color: #fff;
      transition: border-color 0.3s;
    }

    .register-form input.form-control:focus,
    .register-form select.form-select:focus {
      border-color: #2666f6;
      outline: none;
      background-color: #fff;
    }

    /* Submit button */
    .register-btn {
      width: 100%;
      background-color: #2666f6;
      border: none;
      padding: 14px 0;
      font-size: 1.15rem;
      color: white;
      font-weight: 700;
      border-radius: 6px;
      cursor: pointer;
      user-select: none;
      margin-top: 1.5rem;
      transition: background-color 0.3s;
    }

    .register-btn:hover {
      background-color: #1a4fcc;
    }

    /* Error alert */
    .alert {
      font-size: 0.95rem;
      padding: 12px 15px;
      margin-bottom: 1.5rem;
      border-radius: 6px;
    }

    /* Benefits info text positioned at bottom middle of auth-right */
    .benefits-info {
      position: absolute;
      bottom: 2.5rem;
      left: 50%;
      transform: translateX(-50%);
      max-width: 380px;
      text-align: center;
      color: #000;
      font-weight: 600;
      font-size: 1.15rem;
      line-height: 1.4;
      user-select: none;
      z-index: 1;
    }

    .benefits-info ul {
      list-style: none;
      padding: 0;
      margin-top: 1rem;
    }

    .benefits-info li {
      position: relative;
      padding-left: 1.6rem;
      margin-bottom: 0.9rem;
      font-weight: 500;
      font-size: 1rem;
      line-height: 1.3;
    }

    /* Use simple custom bullets to match WebMD styling */
    .benefits-info li::before {
      content: "•";
      position: absolute;
      left: 0;
      color: #2666f6;
      font-weight: 900;
      font-size: 1.3rem;
      line-height: 1;
      top: 0;
    }

    .signin-text {
      margin-top: 1.8rem;
      text-align: center;
      font-size: 0.95rem;
      font-weight: 500;
      color: #222;
      position: relative;
      z-index: 2;
    }

    .signin-text a {
      color: #2666f6;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
    }

    .signin-text a:hover {
      text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .auth-container {
        flex-direction: column;
        height: auto;
        min-height: initial;
      }

      .auth-left {
        height: 240px;
      }

      .auth-right {
        padding: 3rem 2rem 5rem 2rem;
      }

      .register-form {
        max-width: 100%;
      }

      /* Move benefits-info below form on mobile */
      .benefits-info {
        position: relative;
        bottom: auto;
        left: auto;
        transform: none;
        margin-top: 2rem;
        max-width: 100%;
        font-size: 1rem;
      }
    }
  </style>
</head>

<body>
  <div class="auth-container" role="main">
    <div class="auth-left" aria-hidden="true" aria-label="Illustration for Signup"></div>

    <div class="auth-right">
      <form method="post" class="register-form" novalidate aria-label="Patient registration form">
        <h2>Create Patient Account</h2>
        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
        <?php endif; ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label for="name">Full Name <span class="text-danger">*</span></label>
            <input name="name" type="text" id="name" class="form-control" required value="<?= h($_POST['name'] ?? '') ?>" />
          </div>
          <div class="col-md-6">
            <label for="email">Email <span class="text-danger">*</span></label>
            <input name="email" type="email" id="email" class="form-control" required value="<?= h($_POST['email'] ?? '') ?>" />
          </div>
          <div class="col-md-6">
            <label for="password">Password <span class="text-danger">*</span></label>
            <input name="password" type="password" id="password" minlength="6" class="form-control" required />
          </div>
          <div class="col-md-6">
            <label for="confirm">Confirm Password <span class="text-danger">*</span></label>
            <input name="confirm" type="password" id="confirm" class="form-control" required />
          </div>
          <div class="col-md-4">
            <label for="age">Age</label>
            <input name="age" type="number" id="age" min="0" class="form-control" value="<?= h($_POST['age'] ?? '') ?>" />
          </div>
          <div class="col-md-4">
            <label for="gender">Gender</label>
            <select name="gender" id="gender" class="form-select">
              <option value="">— Select —</option>
              <option value="Male" <?= (($_POST['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= (($_POST['gender'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="phone">Phone</label>
            <input name="phone" type="text" id="phone" placeholder="+8801XXXXXXXXX" class="form-control" value="<?= h($_POST['phone'] ?? '') ?>" />
          </div>
          <div class="col-12">
            <label for="patient_location">Location</label>
            <input name="patient_location" type="text" id="patient_location" placeholder="e.g., Dhaka • Banani" class="form-control" value="<?= h($_POST['patient_location'] ?? '') ?>" />
          </div>
        </div>
        <button type="submit" class="register-btn">Create Account</button>
        <div class="signin-text">
          Already have an account? <a href="login.php">Sign in</a>
        </div>
        <div class="signin-text" style="margin-top:0.3rem;">
          Want to register as an admin? <a href="../admins/setup.php">Create admin account</a>
        </div>
      </form>



  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php ob_end_flush(); ?>
