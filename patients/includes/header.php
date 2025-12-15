<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/auth.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SMARTDOC - Patient Portal</title>

  <!-- Bootstrap and Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{--sd-primary:#0ea5e9;--sd-dark:#0b2239;--sd-accent:#10b981;--sd-bg:#f8fafc;}
    html,body{color:var(--sd-dark);}
    body{background:var(--sd-bg);}
    .patient-header{
      background:linear-gradient(180deg, rgba(14,165,233,.15), rgba(14,165,233,0) 70%), var(--sd-primary);
      color:#fff;
      padding:1.2rem 0 .5rem;
      position:sticky; top:0; z-index:1030;
      box-shadow:0 2px 8px rgba(0,0,0,.12);
    }

    /* Enlarged SmartDoc Brand (matching Admin’s bold look) */
    .patient-brand{
      display:flex; align-items:center; gap:.7rem;
      color:#fff; text-decoration:none;
      font-weight:900; letter-spacing:.5px;
      font-size:2rem;                       /* Bigger like Admin dashboard */
      text-transform:uppercase;
      text-shadow:1px 1px 4px rgba(0,0,0,.25);
      white-space:nowrap;
    }
    .patient-brand:hover{ color:#fff; opacity:.9; transform:scale(1.03); transition:all .25s ease; }
    .patient-brand svg{
      width:32px; height:32px;              /* Slightly larger logo */
      flex-shrink:0;
      filter:drop-shadow(0 2px 2px rgba(0,0,0,.15));
    }

    .patient-nav .nav-link{
      color:rgba(255,255,255,0.9);
      padding:.65rem 1.15rem;
      border-radius:999px;
      transition:all .25s ease;
      font-weight:600;
      font-size:1.05rem;
    }
    .patient-nav .nav-link:hover,
    .patient-nav .nav-link.active{
      color:#0b2239;
      background:#ffffff;
      border-color:#fff;
      box-shadow:0 4px 10px rgba(0,0,0,.12);
      transform:translateY(-1px);
    }

    .subbar{
      padding:.8rem 0 1.2rem;
      color:#dbeafe;
    }
    .subbar .title{
      color:#fff;
      font-weight:700;
      letter-spacing:.3px;
      font-size:1.35rem;
    }
    .subbar .subtitle{color:#e2f2ff;font-size:.9rem}

    .card{border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 6px 18px rgba(16,24,40,.06);transition:transform .18s ease, box-shadow .18s ease;}
    .card:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(16,24,40,.12);}
    .table thead th{background:#f6f9fc;color:#334155;font-weight:700;border-bottom:1px solid #e8edf4;}
    .btn-primary{--bs-btn-bg:var(--sd-primary);--bs-btn-border-color:var(--sd-primary);--bs-btn-hover-bg:#0284c7;--bs-btn-hover-border-color:#0284c7;}
    .btn-outline-primary{--bs-btn-color:#0284c7;--bs-btn-border-color:#7dd3fc;--bs-btn-hover-bg:#e0f2fe;--bs-btn-hover-border-color:#0284c7;--bs-btn-hover-color:#0b2239;}
    .badge-soft{background:#ecfeff;color:#0369a1;border:1px solid #bae6fd;}
    .chip{display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .6rem;border-radius:999px;border:1px solid #e2e8f0;background:#fff;font-size:.85rem;color:#334155}
    .chip .bi{color:#0ea5e9}
    .search-elevated{border:1px solid #e5e7eb;border-radius:16px;padding:1rem;box-shadow:0 6px 18px rgba(16,24,40,.06);background:#fff}
    .muted{color:#64748b}
  </style>
</head>
<body>
<?php
  $page = basename($_SERVER['PHP_SELF'] ?? '');
  $pageTitle = 'Dashboard';
  $pageSubtitle = 'Your health at a glance';
  $ctaHref = 'find-doctors.php'; $ctaLabel = 'Find Doctors'; $ctaIcon = 'bi-search';
    if ($page === 'find-doctors.php'){
    $pageTitle    = 'Find Doctors';
    $pageSubtitle = 'Search and compare specialists';
    $ctaHref      = 'appointments.php';
    $ctaLabel     = 'Consultation History';
    $ctaIcon      = 'bi-clock-history';
  }
  elseif ($page === 'symptom-checker.php'){
    $pageTitle    = 'Check Symptoms';
    $pageSubtitle = 'Select symptoms to get the right specialist';
    $ctaHref      = 'find-doctors.php';
    $ctaLabel     = 'View Doctors';
    $ctaIcon      = 'bi-search';
  }
  elseif ($page === 'consultation-history.php'){
    $pageTitle    = 'Consultation History';
    $pageSubtitle = 'Track your past and upcoming visits';
    $ctaHref      = 'find-doctors.php';
    $ctaLabel     = 'New Consultation';
    $ctaIcon      = 'bi-plus-lg';
  }
  elseif ($page === 'profile.php'){
    $pageTitle    = 'My Profile';
    $pageSubtitle = 'Manage your personal details';
    $ctaHref      = 'find-doctors.php';
    $ctaLabel     = 'Find Doctors';
    $ctaIcon      = 'bi-search';
  }

?>
<header class="shadow-sm">
  <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top" aria-label="Primary">
    <div class="container py-2">
      <a class="navbar-brand d-flex align-items-center" href="../LandingPage.html">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true" class="me-2">
          <path d="M12 2l3.5 3.5-3.5 3.5L8.5 5.5 12 2Zm0 7l7 7-3 3-4-4-4 4-3-3 7-7Z" fill="#0ea5e9"/>
        </svg>
        <strong>SmartDoc</strong>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav ms-auto align-items-lg-center">
          <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i>Home</a></li>
          <li class="nav-item"><a class="nav-link<?= basename($_SERVER['PHP_SELF'])==='profile.php' ? ' active' : '' ?>" href="profile.php">Profile</a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<main class="container-fluid px-3 py-4">
  <?php flash(); ?>
