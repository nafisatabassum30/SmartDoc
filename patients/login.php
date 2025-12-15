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

$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = trim($_POST['password'] ?? '');
  $stmt = $con->prepare("SELECT patient_ID, name, email, password_hash FROM `patient` WHERE email=?");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    if ($row['password_hash'] && password_verify($pass, $row['password_hash'])) {
      $_SESSION['patient_logged'] = true;
      $_SESSION['patient_id'] = $row['patient_ID'];
      $_SESSION['patient_name'] = $row['name'];
      $_SESSION['patient_email'] = $row['email'];
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
  <title>SmartDoc - Patient Login</title>
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

    .forgot-password:hover,
    .signup-link:hover {
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

    .signup-text {
      margin-top: 2.5rem;
      text-align: center;
      font-size: 1.1rem;
      color: #222;
      font-weight: 500;
    }

    .signup-link {
      color: #475de5;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      margin-left: 0.3rem;
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
    <div class="auth-left" aria-label="Benefits of using SmartDoc account">
      <h3>Get access to everything SmartDoc offers</h3>
      <ul>
        <li>
          <div class="icon-circle" aria-hidden="true">
            <!-- Briefcase SVG Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M7 7V6a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1h4v14H3V7h4zm1-1h4v1H8V6zM5 9v10h14V9H5z" />
            </svg>
          </div>
          Personalized tools for managing your health
        </li>
        <li>
          <div class="icon-circle" aria-hidden="true">
            <!-- Envelope SVG Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M2 6v12h20V6H2zm18 2-8 5-8-5V8l8 5 8-5v0z" />
            </svg>
          </div>
          Health and wellness updates delivered to your inbox
        </li>
        <li>
          <div class="icon-circle" aria-hidden="true">
            <!-- Document SVG Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM15 3.5V8h4.5L15 3.5zM8 11h8v2H8v-2zm0 4h5v2H8v-2z" />
            </svg>
          </div>
          Saved articles, conditions and medications
        </li>
        <li>
          <div class="icon-circle" aria-hidden="true">
            <!-- Doctor SVG Icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
              <path d="M12 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm0 1c-2 0-6 1-6 3v4h12v-4c0-2-4-3-6-3z" />
            </svg>
          </div>
          Expert insights and patient stories
        </li>
      </ul>
    </div>

    <div class="auth-right">
      <form method="post" class="login-form" novalidate aria-label="Patient login form">
        <h2>Log In</h2>

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

        <div class="signup-text">
          Don't have an account? <a href="register.php" class="signup-link">Sign Up</a>
        </div>
        <div class="signup-text" style="margin-top:0.5rem;">
          Are you an admin? <a href="../admins/login.php" class="signup-link">Log in as admin</a>
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
