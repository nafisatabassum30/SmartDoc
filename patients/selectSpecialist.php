<?php



require_once __DIR__ . '/includes/auth.php';

require_patient_login();

require_once __DIR__ . '/includes/db.php';

require_once __DIR__ . '/includes/util.php';

// Load dynamic options from DB

$symptoms = [];

$areas = [];

$durations = [];

$result = $con->query("SELECT symptom_id, symptom_name, AffectedArea, AffectedDuration FROM `symptom` ORDER BY symptom_name");

if ($result) {

  while ($row = $result->fetch_assoc()) {

    $symptoms[] = $row;

    $area = trim((string)($row['AffectedArea'] ?? ''));

    $dur  = trim((string)($row['AffectedDuration'] ?? ''));

    if ($area !== '' && !in_array($area, $areas, true)) { $areas[] = $area; }

    if ($dur  !== '' && !in_array($dur,  $durations, true)) { $durations[] = $dur; }

  }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Select Specialist</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>

:root {

  --sd-primary: #0ea5e9;

  --sd-bg: #f8fafc;

}

body {

  font-family: "Inter", system-ui, sans-serif;

  background: var(--sd-bg);

  color: #0b2239;

}

/* Gradient header */

.hero-gradient {

  background: linear-gradient(180deg, rgba(14,165,233,0.35), rgba(14,165,233,0.05) 70%);

  padding: 2rem 0 1.5rem;

  text-align: center;

}

/* Search bar */

#symptomSearch {

  padding: 10px 15px;

  border-radius: 8px;

}

/* Dropdown */

#suggestions {

  position: absolute;

  z-index: 1000;

  background: white;

  width: 100%;

  border-radius: 6px;

  box-shadow: 0 4px 10px rgba(0,0,0,0.1);

  display: none;

}

#suggestions li {

  padding: 10px;

  cursor: pointer;

}

#suggestions li:hover {

  background: #f0f9ff;

}

/* Selected symptom tags */

.tag {

  background: #0ea5e9;

  color: white;

  padding: 7px 12px;

  border-radius: 20px;

  margin: 5px;

  display: inline-flex;

  align-items: center;

  font-size: 14px;

}

.tag i {

  margin-left: 8px;

  cursor: pointer;

}

.tag-container {

  margin-top: 10px;

}

/* Keep existing design for area and duration buttons */

.area-btn.active, .intensity-btn.active {

  background: var(--sd-primary) !important;

  color: white !important;

}

</style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">

  <div class="container py-2">

    <a class="navbar-brand d-flex align-items-center" href="../LandingPage.html">

      <svg width="28" height="28" fill="none" class="me-2">

        <path d="M12 2l3.5 3.5-3.5 3.5L8.5 5.5 12 2Zm0 7l7 7-3 3-4-4-4 4-3-3 7-7Z" fill="#0ea5e9"/>

      </svg>

      <strong>SmartDoc</strong>

    </a>

    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav">

      <span class="navbar-toggler-icon"></span>

    </button>

    <div class="collapse navbar-collapse" id="nav">

      <ul class="navbar-nav ms-auto">

        <li class="nav-item">
          <a class="nav-link" href="index.php">Home</a>
        </li>

        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>

        <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>

      </ul>

    </div>

  </div>

</nav>

<!-- HEADER -->

<div class="hero-gradient">

  <h2 class="fw-semibold">Select Your Symptoms</h2>

  <p class="text-muted">Choose symptoms to find the right specialist</p>

</div>

<!-- MAIN CARD -->

<div class="container my-4">

  <div class="card shadow-sm border-0 p-4">

    <!-- NEW SEARCH BAR -->

    <h6 class="fw-semibold mb-2 text-secondary">Search Symptoms</h6>

    <div class="position-relative">

      <input type="text" id="symptomSearch" class="form-control" placeholder="Type a symptom...">

      <ul id="suggestions" class="list-group"></ul>

    </div>

    <!-- Selected Symptoms -->

    <div id="selectedSymptoms" class="tag-container"></div>

    <hr class="my-4">

    <!-- KEEP OLD AREA + DURATION EXACTLY SAME -->

    <div class="mb-4">

      <h6 class="fw-semibold mb-2 text-secondary">Select affected area</h6>

      <div class="d-flex flex-wrap gap-2">

        <?php foreach ($areas as $a): ?>

          <button class="btn btn-outline-info area-btn"><?= h($a) ?></button>

        <?php endforeach; ?>

      </div>

    </div>

    <div class="mb-4">

      <h6 class="fw-semibold mb-2 text-secondary">Select affected duration</h6>

      <div class="d-flex flex-wrap gap-2">

        <?php foreach ($durations as $d): ?>

          <button class="btn btn-outline-danger intensity-btn"><?= h($d) ?></button>

        <?php endforeach; ?>

      </div>

    </div>

    <!-- GENERATE -->

    <div class="text-center">

      <button id="generate-btn" class="btn btn-outline-info px-4 py-2">

        <i class="bi bi-cpu me-2"></i>Generate Specialist Suggestion

      </button>

    </div>

  </div>

</div>

<script>

// PHP symptom list → JS array

const SYMPTOMS = <?= json_encode($symptoms) ?>;

const searchInput = document.getElementById("symptomSearch");

const suggestions = document.getElementById("suggestions");

const selectedBox = document.getElementById("selectedSymptoms");

let selectedSymptoms = []; // store objects {id, name}

searchInput.addEventListener("input", function () {

  const q = this.value.toLowerCase().trim();

  suggestions.innerHTML = "";

  if (!q) { suggestions.style.display = "none"; return; }

  const filtered = SYMPTOMS.filter(s => 

    s.symptom_name.toLowerCase().includes(q)

  ).slice(0, 8);

  if (filtered.length === 0) { suggestions.style.display = "none"; return; }

  filtered.forEach(sym => {

    const li = document.createElement("li");

    li.classList.add("list-group-item", "list-group-item-action");

    li.textContent = sym.symptom_name;

    li.onclick = () => addSymptom(sym);

    suggestions.appendChild(li);

  });

  suggestions.style.display = "block";

});

// Add symptom as tag (max 6)

function addSymptom(sym) {

  if (selectedSymptoms.length >= 6) {

    alert("You can select up to 6 symptoms.");

    return;

  }

  if (selectedSymptoms.some(s => s.symptom_id === sym.symptom_id)) return;

  selectedSymptoms.push(sym);

  renderSelected();

  searchInput.value = "";

  suggestions.style.display = "none";

}

// Remove symptom

function removeSymptom(id) {

  selectedSymptoms = selectedSymptoms.filter(s => s.symptom_id !== id);

  renderSelected();

}

// Display selected symptoms

function renderSelected() {

  selectedBox.innerHTML = "";

  selectedSymptoms.forEach(sym => {

    selectedBox.innerHTML += `

      <span class="tag">

        ${sym.symptom_name}

        <i class="bi bi-x" onclick="removeSymptom(${sym.symptom_id})"></i>

      </span>`;

  });

}

/* KEEP YOUR ORIGINAL AREA & DURATION & ML API LOGIC */

document.querySelectorAll('.area-btn, .intensity-btn').forEach(btn => {

  btn.addEventListener('click', () => btn.classList.toggle('active'));

});

const genBtn = document.getElementById("generate-btn");

const API_URL = "http://127.0.0.1:5000/predict";

genBtn.addEventListener("click", async () => {

  if (selectedSymptoms.length === 0) {

    alert("Please select at least one symptom.");

    return;

  }

  const area = document.querySelector('.area-btn.active')?.textContent.trim() || "";

  const duration = document.querySelector('.intensity-btn.active')?.textContent.trim() || "";

  const payload = [

    ...selectedSymptoms.map(s => s.symptom_name),

    area,

    duration

  ].filter(Boolean);

  genBtn.disabled = true;

  genBtn.innerHTML = "Analyzing...";

  try {

    const res = await fetch(API_URL, {

      method: "POST",

      headers: {"Content-Type":"application/json"},

      body: JSON.stringify({ symptoms: payload })

    });

    const data = await res.json();

    const specialist = data.specialist;

    const ids = selectedSymptoms.map(s => s.symptom_id).join(",");

    window.location.href = `GenerateSpecialist.php?specialist_name=${specialist}&symptom_ids=${ids}&area=${area}&duration=${duration}`;

  }

  catch(err) {

    alert("API Error: " + err.message);

  }

  finally {

    genBtn.disabled = false;

    genBtn.innerHTML = `<i class="bi bi-cpu me-2"></i>Generate Specialist Suggestion`;

  }

});

</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>