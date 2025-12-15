<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';

// Allow setup.php to be accessed even when accounts exist
// Removed redirect to login.php so users can create accounts from landing page

$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $email = trim($_POST['email']??'');
  $name  = trim($_POST['full_name']??'');
  $pass  = trim($_POST['password']??'');
  $confirm = trim($_POST['confirm']??'');
  if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && $pass && $pass===$confirm){
    // Check if email already exists
    $chk = $con->prepare("SELECT admin_id FROM `admins` WHERE email=?");
    $chk->bind_param('s', $email);
    $chk->execute();
    if ($chk->get_result()->fetch_assoc()){
      $err = 'This email is already registered. Please use a different email or log in instead.';
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      // All admins created with a single role
      $stmt = $con->prepare("INSERT INTO `admins`(email,full_name,password_hash,role) VALUES (?,?,?,'editor')");
      $stmt->bind_param('sss',$email,$name,$hash);
      if ($stmt->execute()){
        $_SESSION['admin_logged']=true; $_SESSION['admin_id']=$stmt->insert_id; $_SESSION['admin_email']=$email; $_SESSION['admin_name']=$name; $_SESSION['admin_role']='editor';
        $stmt->close();
        header('Location: index.php'); exit;
      } else {
        $err = 'Failed to create account. Please try again.';
      }
      $stmt->close();
    }
    $chk->close();
  } else { $err='Provide a valid email and matching passwords.'; }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SmartDoc - Admin Setup</title>
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

    /* Make image panel slightly narrower and reduce zoom */
    .auth-left {
      flex: 0.9;
      background: url('assets/admin-signup.png') no-repeat left center;
      background-size: contain;
      background-color: #f9fbff;
      user-select: none;
    }

    .auth-right {
      flex: 1.1;
      background: #fafafa;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 3rem 3rem 5rem 3rem;
      position: relative;
    }

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

    .register-form input.form-control {
      width: 100%;
      padding: 12px 14px;
      font-size: 1rem;
      border: 1px solid #cbd3df;
      border-radius: 6px;
      box-sizing: border-box;
      background-color: #fff;
      transition: border-color 0.3s;
    }

    .register-form input.form-control:focus {
      border-color: #2666f6;
      outline: none;
      background-color: #fff;
    }

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

    .alert {
      font-size: 0.95rem;
      padding: 12px 15px;
      margin-bottom: 1.5rem;
      border-radius: 6px;
    }

    .benefits-info {
      position: absolute;
      bottom: 2.5rem;
      left: 50%;
      transform: translateX(-50%);
      max-width: 380px;
      text-align: center;
      color: #000;
      font-weight: 600;
      font-size: 1.05rem;
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
      font-size: 0.95rem;
      line-height: 1.3;
    }

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

      .benefits-info {
        position: relative;
        bottom: auto;
        left: auto;
        transform: none;
        margin-top: 2rem;
        max-width: 100%;
        font-size: 0.95rem;
      }
    }
  </style>
</head>
<body>
  <div class="auth-container" role="main">
    <div class="auth-left" aria-hidden="true" aria-label="Illustration for Admin Signup"></div>

    <div class="auth-right">
      <form method="post" class="register-form" novalidate aria-label="Admin setup form">
        <h2>Create Admin Account</h2>
        <?php if ($err): ?>
          <div class="alert alert-danger" role="alert"><?= h($err) ?></div>
        <?php endif; ?>
        <div class="row g-3">
          <div class="col-12">
            <label for="full_name">Full Name <span class="text-danger">*</span></label>
            <input name="full_name" type="text" id="full_name" class="form-control" required value="<?= h($_POST['full_name'] ?? '') ?>" />
          </div>
          <div class="col-12">
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
        </div>
        <button type="submit" class="register-btn">Create Admin Account</button>
      </form>

      <div class="signin-text" style="margin-top:1rem; text-align:center; font-size:0.95rem; font-weight:500;">
        Want to sign up as a patient instead?
        <a href="../patients/register.php">Go to patient signup</a>
      </div>

      <div class="benefits-info">
        Manage SmartDoc with full control:
        <ul>
          <li>Configure hospitals, doctors, and specializations.</li>
          <li>Monitor consultations and patient activity.</li>
          <li>Securely manage admin roles and access.</li>
        </ul>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
