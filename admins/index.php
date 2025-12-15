<?php
require_once __DIR__ . '/includes/header.php';
require_login();

// fetch counts once
$doctorCount = (int)($con->query("SELECT COUNT(*) c FROM `doctor`")->fetch_assoc()['c'] ?? 0);
$specCount   = (int)($con->query("SELECT COUNT(*) c FROM `specialization`")->fetch_assoc()['c'] ?? 0);
$hospCount   = (int)($con->query("SELECT COUNT(*) c FROM `hospital`")->fetch_assoc()['c'] ?? 0);
$sympCount   = (int)($con->query("SELECT COUNT(*) c FROM `symptom`")->fetch_assoc()['c'] ?? 0);
$patientCount= (int)($con->query("SELECT COUNT(*) c FROM `patient`")->fetch_assoc()['c'] ?? 0);
$adminCount  = (int)($con->query("SELECT COUNT(*) c FROM `admins`")->fetch_assoc()['c'] ?? 0);
$locCount    = (int)($con->query("SELECT COUNT(*) c FROM `location`")->fetch_assoc()['c'] ?? 0);
$pcCount     = (int)($con->query("SELECT COUNT(*) c FROM `symptom_check_history`")->fetch_assoc()['c'] ?? 0);

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRole = strtoupper($_SESSION['admin_role'] ?? 'ADMIN');
?>

<style>
  /* Live clock + calendar styling (kept lightweight, matches admin theme) */
  .admin-clock-time{
    font-size:1.8rem;
    font-weight:600;
    letter-spacing:0.03em;
  }
  .admin-clock-date{
    font-size:.9rem;
    color:#6b7280;
  }
  .mini-calendar table{
    width:100%;
    font-size:.85rem;
  }
  .mini-calendar th{
    text-align:center;
    padding:.35rem 0;
    color:#6b7280;
    font-weight:600;
  }
  .mini-calendar td{
    width:14.285%;
    text-align:center;
    padding:.25rem 0;
    border-radius:6px;
    cursor:default;
  }
  .mini-calendar td.today{
    background:#0ea5e9;
    color:#fff;
    font-weight:600;
  }
  .mini-calendar td.muted{
    color:#cbd5e1;
  }
  .mini-calendar td:hover{
    background:#e0f2fe;
  }
</style>

<div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
  <div class="d-flex align-items-center">
    <i class="bi bi-info-circle me-2" style="font-size: 1.25rem;"></i>
    <div>
      <strong>Welcome back, <?= h($adminName) ?>!</strong>
      <p class="mb-0 small">You're logged in as <span class="badge bg-primary"><?= h($adminRole) ?></span></p>
    </div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>

<div class="row g-2 mb-4">
  <div class="col-12">
    <div class="d-flex gap-2 flex-wrap">
      <a href="doctors.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-video me-1"></i>Manage Doctors</a>
      <a href="patients.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-people me-1"></i>Manage Patients</a>
      <a href="specializations.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-briefcase me-1"></i>Specializations</a>
      <a href="hospitals.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-hospital me-1"></i>Hospitals</a>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <a href="doctors.php" class="stat-card-link">
    <div class="card stat-card">
      <div class="card-body text-center">
        <div class="stat-icon"><i class="bi bi-person-video"></i></div>
        <div class="stat-value"><?= number_format($doctorCount) ?></div>
        <div class="stat-label">Doctors</div>
      </div>
    </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="patients.php" class="stat-card-link">
    <div class="card stat-card alt1">
      <div class="card-body text-center">
        <div class="stat-icon"><i class="bi bi-people"></i></div>
        <div class="stat-value"><?= number_format($patientCount) ?></div>
        <div class="stat-label">Patients</div>
      </div>
    </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="specializations.php" class="stat-card-link">
    <div class="card stat-card alt2">
      <div class="card-body text-center">
        <div class="stat-icon"><i class="bi bi-briefcase"></i></div>
        <div class="stat-value"><?= number_format($specCount) ?></div>
        <div class="stat-label">Specializations</div>
      </div>
    </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="consultation-history.php" class="stat-card-link">
    <div class="card stat-card alt3">
      <div class="card-body text-center">
        <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-value"><?= number_format($pcCount) ?></div>
        <div class="stat-label">Consultations</div>
      </div>
    </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="hospitals.php" class="stat-card-link">
    <div class="card stat-card alt4">
      <div class="card-body text-center">
        <div class="stat-icon"><i class="bi bi-hospital"></i></div>
        <div class="stat-value"><?= number_format($hospCount) ?></div>
        <div class="stat-label">Hospitals</div>
      </div>
    </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="symptoms.php" class="stat-card-link">
    <div class="card stat-card alt5">
      <div class="card-body text-center">
        <div class="stat-icon"><i class="bi bi-heart-pulse"></i></div>
        <div class="stat-value"><?= number_format($sympCount) ?></div>
        <div class="stat-label">Symptoms</div>
      </div>
    </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="locations.php" class="stat-card-link">
    <div class="card stat-card alt7">
      <div class="card-body text-center">
        <div class="stat-icon"><i class="bi bi-geo-alt"></i></div>
        <div class="stat-value"><?= number_format($locCount) ?></div>
        <div class="stat-label">Locations</div>
      </div>
    </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="admins.php" class="stat-card-link">
    <div class="card stat-card">
      <div class="card-body text-center">
        <div class="stat-icon"><i class="bi bi-shield-lock"></i></div>
        <div class="stat-value"><?= number_format($adminCount) ?></div>
        <div class="stat-label">Admin Users</div>
      </div>
    </div>
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Live Clock</h6>
        <span class="badge bg-light text-muted border">Bangladesh Time</span>
      </div>
      <div class="card-body d-flex flex-column justify-content-center text-center">
        <div id="adminClockTime" class="admin-clock-time mb-1">--:--:--</div>
        <div id="adminClockDate" class="admin-clock-date">Loading current date…</div>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Calendar</h6>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" type="button" id="calPrevBtn">
            <i class="bi bi-chevron-left"></i>
          </button>
          <span id="calMonthLabel" class="fw-semibold small"></span>
          <button class="btn btn-sm btn-outline-secondary" type="button" id="calNextBtn">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
      <div class="card-body mini-calendar">
        <table class="table table-borderless mb-0">
          <thead>
            <tr>
              <th>Sun</th>
              <th>Mon</th>
              <th>Tue</th>
              <th>Wed</th>
              <th>Thu</th>
              <th>Fri</th>
              <th>Sat</th>
            </tr>
          </thead>
          <tbody id="calBody">
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-activity me-2"></i>System Overview</h5>
        <span class="badge bg-success">Active</span>
      </div>
      <div class="card-body">
        <div class="list-group list-group-flush">
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-person-video me-2" style="color:#667eea;"></i>Total Doctors</span>
            <strong><?= number_format($doctorCount) ?></strong>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-people me-2" style="color:#f5576c;"></i>Total Patients</span>
            <strong><?= number_format($patientCount) ?></strong>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-calendar-check me-2" style="color:#43e97b;"></i>Total Consultations</span>
            <strong><?= number_format($pcCount) ?></strong>
          </div>
          <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
            <span><i class="bi bi-hospital me-2" style="color:#fa709a;"></i>Total Hospitals</span>
            <strong><?= number_format($hospCount) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-lightning-charge me-2"></i>Quick Resources</h5>
      </div>
      <div class="card-body">
        <div class="list-group">
          <a href="doctors.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div><strong>Manage Doctors</strong><div class="small text-muted">Add, edit, or remove doctors</div></div>
            <i class="bi bi-chevron-right"></i>
          </a>
          <a href="patients.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div><strong>Manage Patients</strong><div class="small text-muted">View patient information</div></div>
            <i class="bi bi-chevron-right"></i>
          </a>
          <a href="specializations.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div><strong>Specializations</strong><div class="small text-muted">Manage medical specializations</div></div>
            <i class="bi bi-chevron-right"></i>
          </a>
          <a href="hospitals.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <div><strong>Hospitals</strong><div class="small text-muted">Manage hospital information</div></div>
            <i class="bi bi-chevron-right"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    // Live clock (Bangladesh time)
    const clockTimeEl = document.getElementById('adminClockTime');
    const clockDateEl = document.getElementById('adminClockDate');

    const updateClock = () => {
      const now = new Date();
      // Convert to Bangladesh time using locale options
      const timeStr = now.toLocaleTimeString('en-BD', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: 'Asia/Dhaka'
      });
      const dateStr = now.toLocaleDateString('en-BD', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'Asia/Dhaka'
      });
      if (clockTimeEl) clockTimeEl.textContent = timeStr;
      if (clockDateEl) clockDateEl.textContent = dateStr;
    };

    updateClock();
    setInterval(updateClock, 1000);

    // Mini calendar
    const calBody = document.getElementById('calBody');
    const calMonthLabel = document.getElementById('calMonthLabel');
    const calPrevBtn = document.getElementById('calPrevBtn');
    const calNextBtn = document.getElementById('calNextBtn');

    let current = new Date();

    const renderCalendar = () => {
      if (!calBody || !calMonthLabel) return;

      const year = current.getFullYear();
      const month = current.getMonth();

      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);

      const startDay = firstDay.getDay(); // 0 (Sun) - 6 (Sat)
      const totalDays = lastDay.getDate();

      calMonthLabel.textContent = firstDay.toLocaleDateString('en-BD', {
        month: 'long',
        year: 'numeric'
      });

      calBody.innerHTML = '';

      let date = 1;
      const today = new Date();
      const isSameMonth = today.getFullYear() === year && today.getMonth() === month;

      for (let i = 0; i < 6; i++) {
        const row = document.createElement('tr');

        for (let j = 0; j < 7; j++) {
          const cell = document.createElement('td');

          if (i === 0 && j < startDay) {
            cell.classList.add('muted');
            cell.textContent = '';
          } else if (date > totalDays) {
            cell.classList.add('muted');
            cell.textContent = '';
          } else {
            cell.textContent = date;

            if (isSameMonth && date === today.getDate()) {
              cell.classList.add('today');
            }

            date++;
          }

          row.appendChild(cell);
        }

        calBody.appendChild(row);

        if (date > totalDays) break;
      }
    };

    if (calPrevBtn) {
      calPrevBtn.addEventListener('click', () => {
        current.setMonth(current.getMonth() - 1);
        renderCalendar();
      });
    }

    if (calNextBtn) {
      calNextBtn.addEventListener('click', () => {
        current.setMonth(current.getMonth() + 1);
        renderCalendar();
      });
    }

    renderCalendar();
  });
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
