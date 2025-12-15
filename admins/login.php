<?php
ob_start();
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';

if (admins_count($con) === 0) {
    header('Location: setup.php');
    exit;
}

// Redirect if already logged in
if (!empty($_SESSION['admin_logged'])) {
    header('Location: index.php');
    exit;
}

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $stmt = $con->prepare("SELECT admin_id, email, full_name, password_hash, role FROM `admins` WHERE email=?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (password_verify($pass, $row['password_hash'])) {
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['admin_email'] = $row['email'];
            $_SESSION['admin_name'] = $row['full_name'];
            $_SESSION['admin_role'] = $row['role'];
            header('Location: index.php');
            exit;
    }
  }
    $login_error = 'Invalid email or password';
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>SmartDoc - Admin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      margin: 0;
      height: 100vh;
      font-family: Arial, sans-serif;
      background-color: #f5f7ff;
    }

    .auth-container {
      display: flex;
      height: 100vh;
      min-height: 600px;
    }

    /* Left portion styling */
    .auth-left {
      flex: 1;
      background-color: #c9d4ff;
      padding: 4rem 3rem;
      color: #000;
      display: flex;
      flex-direction: column;
      justify-content: center;
      user-select: none;
    }

    .auth-left h3 {
      font-weight: 700;
      font-size: 1.6rem;
      margin-bottom: 2.5rem;
    }

    .auth-left ul {
      list-style: none;
      padding-left: 0;
      margin: 0;
      font-size: 1.15rem;
      font-weight: 500;
      line-height: 1.5;
    }

    .auth-left li {
      margin-bottom: 2.1rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    /* Circle icon container */
    .icon-circle {
      min-width: 44px;
      min-height: 44px;
      background-color: #475de5;
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      box-shadow: 0 0 12px rgba(71, 93, 229, 0.4);
      flex-shrink: 0;
    }

    /* Icon SVG styling */
    .icon-circle svg {
      width: 26px;
      height: 26px;
      fill: white;
    }

    /* Right portion styling */
    .auth-right {
      flex: 1;
      background-color: #f9fbff;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 3rem 4rem;
    }

    .login-form {
      width: 100%;
      max-width: 380px;
    }

    .login-form h2 {
      font-weight: 700;
      margin-bottom: 2.5rem;
      font-size: 1.5rem;
      text-align: center;
      color: #202124;
    }

    .form-control {
      width: 100%;
      padding: 14px 16px;
      font-size: 1.15rem;
      border: 1.2px solid #dcdcdc;
      border-radius: 8px;
      box-sizing: border-box;
      transition: border-color 0.3s;
    }

    .form-control:focus {
      border-color: #475de5;
      outline: none;
      background-color: #fff;
    }

    .password-container {
      position: relative;
      margin-bottom: 1.7rem;
    }

    .show-password-toggle {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      font-weight: 700;
      font-size: 1rem;
      color: #475de5;
      background: transparent;
      border: none;
      cursor: pointer;
      user-select: none;
    }

    .forgot-remember {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 1rem;
      margin-bottom: 2rem;
    }

    .forgot-password {
      color: #475de5;
      text-decoration: none;
      font-weight: 600;
    }

    .forgot-password:hover {
      text-decoration: underline;
    }

    .remember-me {
      display: flex;
      align-items: center;
      gap: 6px;
      user-select: none;
      color: #222;
    }

    .btn-login {
      width: 100%;
      padding: 16px 0;
      font-size: 1.25rem;
      background-color: #475de5;
      border: none;
      border-radius: 8px;
      color: white;
      font-weight: 700;
      cursor: pointer;
      transition: background-color 0.3s ease;
      user-select: none;
    }

    .btn-login:hover {
      background-color: #3a4ccc;
    }

    /* Alert styling */
    .alert-danger {
      font-size: 1rem;
      padding: 12px 20px;
      margin-bottom: 2rem;
      border-radius: 6px;
      background-color: #f8d7da;
      color: #842029;
      border: 1px solid #f5c2c7;
      user-select: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .auth-container {
        flex-direction: column;
        height: auto;
      }

      .auth-left,
      .auth-right {
        padding: 2rem;
        flex: none;
      }

      .auth-left {
        order: 2;
      }

      .auth-right {
        order: 1;
      }
    }
  </style>
</head>

<body>
  <div class="auth-container" role="main">
    <div class="auth-left" aria-label="Admin dashboard features">
      <h3>Manage your SmartDoc platform</h3>
      <ul>
        <li>
          <div class="icon-circle" aria-hidden="true">
            <!-- Users SVG Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z" />
            </svg>
          </div>
          Manage doctors, patients, and admin users
        </li>
        <li>
          <div class="icon-circle" aria-hidden="true">
            <!-- Hospital SVG Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z" />
            </svg>
          </div>
          Oversee hospitals and specializations
        </li>
        <li>
          <div class="icon-circle" aria-hidden="true">
            <!-- Chart SVG Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M3 3v18h18V3H3zm16 16H5V5h14v14zM7 7h2v8H7V7zm4 4h2v4h-2v-4zm4-2h2v6h-2V9z" />
            </svg>
          </div>
          View system analytics and consultations
        </li>
        <li>
          <div class="icon-circle" aria-hidden="true">
            <!-- Settings SVG Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z" />
            </svg>
          </div>
          Configure system settings and locations
        </li>
      </ul>
    </div>

    <div class="auth-right">
      <form method="post" class="login-form" novalidate aria-label="Admin login form">
        <h2>Admin Log In</h2>

        <?php if ($login_error): ?>
          <div class="alert-danger" role="alert"><?= h($login_error) ?></div>
        <?php endif; ?>

        <input type="email" name="email" placeholder="Email" class="form-control" required autocomplete="email" aria-required="true" />

        <div class="password-container">
          <input type="password" name="password" placeholder="Password" class="form-control" required autocomplete="current-password" aria-required="true" id="passwordInput" />
          <button type="button" class="show-password-toggle" aria-label="Show or hide password" id="togglePassword">SHOW</button>
        </div>

        <div class="forgot-remember">
          <a href="#" class="forgot-password" tabindex="0">Forgot Password?</a>
          <label class="remember-me"><input type="checkbox" id="remember" /> Remember me</label>
        </div>

        <button type="submit" class="btn-login">Log In</button>

        <div class="mt-3 text-center" style="font-size:0.95rem;">
          Want to log in as a patient instead?
          <a href="../patients/login.php">Go to patient login</a>
        </div>

      </form>
    </div>
</div>

  <script>
    const togglePasswordBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');

    togglePasswordBtn.addEventListener('click', () => {
      const type = passwordInput.type === 'password' ? 'text' : 'password';
      passwordInput.type = type;
      togglePasswordBtn.textContent = type === 'password' ? 'SHOW' : 'HIDE';
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php ob_end_flush(); ?>
