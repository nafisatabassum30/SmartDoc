<?php
ob_start();
require_once __DIR__ . '/includes/header.php';
require_patient_login();

$search            = trim($_GET['search'] ?? '');
$specialization_id = !empty($_GET['specialization_id']) ? (int)$_GET['specialization_id'] : 0;
$specialty_name    = trim($_GET['specialty'] ?? '');
$location          = trim($_GET['location'] ?? '');

// If a specialty name is provided, map it to specialization_id
if ($specialization_id === 0 && $specialty_name !== ''){
  // Decode URL encoding
  $specialty_name = urldecode($specialty_name);
  
  // Try exact match first
  $stmt = $con->prepare("SELECT specialization_id FROM `specialization` WHERE specialization_name = ?");
  $stmt->bind_param('s', $specialty_name);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()){
    $specialization_id = (int)$row['specialization_id'];
  }
  $stmt->close();
  
  // If exact match failed, try partial match (for cases like "Dermatologist (Skin & Sex)" -> "Dermatologist")
  if ($specialization_id === 0) {
    // Extract base name (before parentheses or special characters)
    $baseName = preg_replace('/\s*\(.*?\)\s*/', '', $specialty_name); // Remove (Skin & Sex)
    $baseName = trim($baseName);
    
    // Try LIKE match for base name
    $stmt = $con->prepare("SELECT specialization_id FROM `specialization` WHERE specialization_name LIKE ? LIMIT 1");
    $likePattern = $baseName . '%';
    $stmt->bind_param('s', $likePattern);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()){
      $specialization_id = (int)$row['specialization_id'];
    }
    $stmt->close();
    
    // If still not found, try matching from the beginning (handles variations)
    if ($specialization_id === 0) {
      $stmt = $con->prepare("SELECT specialization_id FROM `specialization` WHERE specialization_name LIKE ? LIMIT 1");
      $likePattern = '%' . $baseName . '%';
      $stmt->bind_param('s', $likePattern);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()){
        $specialization_id = (int)$row['specialization_id'];
      }
      $stmt->close();
    }
  }
}

// Build query with proper escaping
$where = [];

if ($search){
  $searchEscaped = $con->real_escape_string($search);
  $where[] = "d.name LIKE '%$searchEscaped%'";
}

if ($specialization_id > 0){
  $where[] = "d.specialization_id = " . (int)$specialization_id;
}

if ($location){
  $locEscaped = $con->real_escape_string($location);
  // Try to match by hospital name or address text
  $where[] = "(h.hospital_name LIKE '%$locEscaped%' OR h.address LIKE '%$locEscaped%')";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT d.*, s.specialization_name, h.hospital_name, h.address AS hospital_address
        FROM `doctor` d
        LEFT JOIN `specialization` s ON d.specialization_id = s.specialization_id
        LEFT JOIN `hospital` h ON d.hospital_id = h.hospital_id
        $whereClause
        ORDER BY d.name ASC";

$doctors        = $con->query($sql);
$specializations = $con->query("SELECT * FROM `specialization` ORDER BY specialization_name");
?>
<style>
  .distance-info {
    margin-top: 0.5rem;
  }
  .card.border-success {
    transition: all 0.3s ease;
  }
  .card.border-success:hover {
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.2) !important;
  }
  #use-location-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  #location-status {
    display: inline-block;
  }
</style>

<div class="row mb-4">
  <div class="col-12">
    <div class="search-elevated">
      <form method="get" class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label muted">Doctor</label>
          <input name="search" type="text" class="form-control" placeholder="Search by doctor name..." value="<?= h($search) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label muted">Specialization</label>
          <select name="specialization_id" class="form-select">
            <option value="">All Specializations</option>
            <?php while($spec = $specializations->fetch_assoc()): ?>
              <option value="<?= $spec['specialization_id'] ?>" <?= $specialization_id == $spec['specialization_id'] ? 'selected' : '' ?>>
                <?= h($spec['specialization_name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label muted">Location</label>
          <input name="location" type="text" class="form-control" placeholder="e.g., Dhaka" value="<?= h($location) ?>">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Search</button>
        </div>
      </form>
      
      <div class="mt-3">
        <button id="use-location-btn" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-geo-alt-fill me-1"></i>Use My Current Location
        </button>
        <span id="location-status" class="ms-2 small text-muted"></span>
      </div>

      <div class="mt-2 d-flex flex-wrap gap-2">
        <?php if($specialization_id): ?>
          <?php 
            $specRow = $con->query("SELECT specialization_name FROM `specialization` WHERE specialization_id=".(int)$specialization_id)->fetch_assoc();
            $specName = $specRow['specialization_name'] ?? 'Selected';
          ?>
          <span class="chip">
            <i class="bi bi-ui-checks"></i>
            <?= h($specName) ?>
          </span>
        <?php endif; ?>

        <?php if($location): ?>
          <span class="chip">
            <i class="bi bi-geo-alt"></i><?= h($location) ?>
          </span>
        <?php endif; ?>

        <?php if($search): ?>
          <span class="chip">
            <i class="bi bi-person"></i><?= h($search) ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <?php if($doctors->num_rows > 0): ?>
    <?php while($doctor = $doctors->fetch_assoc()): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100">
          <div class="card-body d-flex flex-column">

            <!-- Header: avatar + name + specialization -->
            <div class="d-flex align-items-start gap-3 mb-2">
              <div class="rounded-3 d-inline-grid"
                   style="width:56px;height:56px;background:#e0f2fe;color:#0369a1;display:grid;place-items:center;">
                <i class="bi bi-person-badge fs-5"></i>
              </div>
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                  <h5 class="card-title mb-0"><?= h($doctor['name']) ?></h5>
                  <span class="badge-soft rounded-pill px-2 py-1">
                    <?= h($doctor['specialization_name'] ?? 'General') ?>
                  </span>
                </div>
                <?php if(!empty($doctor['designation'])): ?>
                  <div class="text-muted small mt-1">
                    <?= h($doctor['designation']) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <?php
              $avgRating   = isset($doctor['ratings(out of 5)']) ? (float)$doctor['ratings(out of 5)'] : 0;
              $ratingCount = isset($doctor['rating_count']) ? (int)$doctor['rating_count'] : 0;
            ?>

            <!-- Rating block -->
            <div class="mb-2 d-flex align-items-center flex-wrap gap-2">
              <span class="text-warning doctor-stars">
                <?php for($i = 1; $i <= 5; $i++): ?>
                  <i class="bi <?= $i <= round($avgRating) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                <?php endfor; ?>
              </span>
              <span class="small text-muted rating-summary">
                <?= number_format($avgRating, 1) ?>/5
                <?php if($ratingCount > 0): ?>
                  (<?= $ratingCount ?> rating<?= $ratingCount > 1 ? 's' : '' ?>)
                <?php else: ?>
                  (no ratings yet)
                <?php endif; ?>
              </span>
            </div>

            <!-- Details: hospital + address + distance -->
            <div class="mb-3">
              <?php if($doctor['hospital_name']): ?>
                <div class="small text-muted">
                  <i class="bi bi-building me-1"></i><?= h($doctor['hospital_name']) ?>
                </div>
              <?php endif; ?>

              <?php if($doctor['hospital_address']): ?>
                <div class="small text-muted">
                  <i class="bi bi-geo-alt me-1"></i><?= h($doctor['hospital_address']) ?>
                </div>
              <?php endif; ?>

              <div class="distance-info mt-1"
                   data-address="<?= h($doctor['hospital_address'] ?? '') ?>"
                   style="display: none;">
                <span class="badge bg-success">
                  <i class="bi bi-signpost-2 me-1"></i>
                  <span class="distance-text">Calculating...</span>
                </span>
              </div>
            </div>

            <!-- Actions: rating form + button area -->
            <div class="mt-auto">
              <form class="rate-form mb-2 d-flex align-items-center flex-wrap gap-2"
                    data-doctor-id="<?= (int)$doctor['doctor_id'] ?>">
                <label class="small text-muted mb-0">Your rating:</label>
                <input type="number" name="rating" class="form-control form-control-sm" 
                       placeholder="0.0" min="0" max="5" step="0.1" 
                       style="width: 80px;" required>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Rate</button>
                <span class="small rate-message text-muted"></span>
              </form>

              <?php if($doctor['website_url']): ?>
                <a href="<?= h($doctor['website_url']) ?>" target="_blank"
                   class="btn btn-sm btn-primary w-100">
                  <i class="bi bi-link-45deg me-1"></i>View Profile
                </a>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
    <?php endwhile; ?>
  <?php else: ?>
    <div class="col-12">
      <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <div>No doctors found. Try adjusting your search or select a different specialization.</div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
// Geolocation and Distance Calculation
let userLocation = null;

// Haversine formula to calculate distance between two coordinates
function calculateDistance(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth's radius in kilometers
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c; // Distance in kilometers
}

// Cache for geocoded addresses (localStorage)
const GEOCODE_CACHE_KEY = 'smartdoc_geocode_cache';
const CACHE_DURATION = 7 * 24 * 60 * 60 * 1000; // 7 days

function getGeocodeCache() {
  try {
    const cached = localStorage.getItem(GEOCODE_CACHE_KEY);
    if (cached) {
      const data = JSON.parse(cached);
      const now = Date.now();
      // Remove expired entries
      Object.keys(data).forEach(addr => {
        if (now - data[addr].timestamp > CACHE_DURATION) {
          delete data[addr];
        }
      });
      return data;
    }
  } catch (e) {
    console.error('Cache read error:', e);
  }
  return {};
}

function setGeocodeCache(address, coords) {
  try {
    const cache = getGeocodeCache();
    cache[address] = {
      ...coords,
      timestamp: Date.now()
    };
    localStorage.setItem(GEOCODE_CACHE_KEY, JSON.stringify(cache));
  } catch (e) {
    console.error('Cache write error:', e);
  }
}

// Geocode address using PHP proxy (avoids CORS issues)
async function geocodeAddress(address, delay = 0) {
  if (!address || address.trim() === '') return null;
  
  // Check cache first
  const cache = getGeocodeCache();
  if (cache[address]) {
    return cache[address];
  }
  
  // Add delay to respect rate limits
  if (delay > 0) {
    await new Promise(resolve => setTimeout(resolve, delay));
  }
  
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout
    
    const response = await fetch(
      `geocode_proxy.php?action=geocode&address=${encodeURIComponent(address)}`,
      {
        signal: controller.signal
      }
    );
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const responseText = await response.text();
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (parseError) {
      console.error('Invalid JSON response for', address, ':', responseText.substring(0, 100));
      return null;
    }
    
    if (data.success && data.lat && data.lon) {
      const coords = {
        lat: parseFloat(data.lat),
        lon: parseFloat(data.lon),
        address: address
      };
      // Cache the result
      setGeocodeCache(address, coords);
      return coords;
    }
  } catch (error) {
    if (error.name === 'AbortError') {
      console.error('Geocoding timeout for', address);
    } else {
      console.error('Geocoding error for', address, ':', error);
    }
  }
  return null;
}

// Batch geocode multiple addresses with caching and progressive display - OPTIMIZED
async function geocodeAddressesBatch(addresses, onProgress) {
  const cache = getGeocodeCache();
  const addressMap = new Map();
  const uncachedAddresses = [];
  const cachedResults = [];
  
  // Separate cached and uncached addresses
  addresses.forEach((address, index) => {
    if (cache[address]) {
      addressMap.set(address, cache[address]);
      cachedResults.push({ address, index, coords: cache[address] });
    } else {
      uncachedAddresses.push({ address, index });
    }
  });
  
  // IMMEDIATELY show cached results (no delay, instant display)
  if (cachedResults.length > 0 && onProgress) {
    // Process all cached results synchronously for instant display
    cachedResults.forEach(({ address, coords }) => {
      onProgress(address, coords);
    });
    // Update count immediately for cached results
    if (typeof geocodedCount !== 'undefined') {
      geocodedCount = cachedResults.length;
      if (typeof updateStatus === 'function') {
        updateStatus();
      }
    }
  }
  
  // If all are cached, return immediately (no API calls needed)
  if (uncachedAddresses.length === 0) {
    return addressMap;
  }
  
  // For uncached addresses, process in background (don't block UI)
  // Start processing immediately but don't wait for all to complete
  processUncachedInBackground(uncachedAddresses, addressMap, onProgress);
  
  return addressMap; // Return immediately with cached results
}

// Process uncached addresses in background with geocoding (SHORT RANGE - 5km max)
async function processUncachedInBackground(uncachedAddresses, addressMap, onProgress, statusCallback) {
  const MAX_DISTANCE_KM = 5; // Only show doctors within 5km
  let processedCount = 0;
  const totalUncached = uncachedAddresses.length;
  const BATCH_SIZE = 3; // Process 3 at a time
  const DELAY_BETWEEN_BATCHES = 1000; // 1 second between batches
  
  for (let i = 0; i < uncachedAddresses.length; i += BATCH_SIZE) {
    const batch = uncachedAddresses.slice(i, i + BATCH_SIZE);
    
    const batchPromises = batch.map((item, batchIdx) => {
      const delay = batchIdx * 300; // 300ms stagger within batch
      return geocodeAddress(item.address, delay).then(coords => {
        processedCount++;
        if (statusCallback) {
          statusCallback(processedCount, totalUncached);
        }
        
        if (coords) {
          addressMap.set(item.address, coords);
          if (onProgress) {
            onProgress(item.address, coords);
          }
        } else {
          if (onProgress) {
            onProgress(item.address, null);
          }
        }
        return { address: item.address, coords };
      }).catch(err => {
        processedCount++;
        if (statusCallback) {
          statusCallback(processedCount, totalUncached);
        }
        console.error('Failed to geocode', item.address, err);
        if (onProgress) {
          onProgress(item.address, null);
        }
        return { address: item.address, coords: null };
      });
    });
    
    await Promise.all(batchPromises);
    
    if (i + BATCH_SIZE < uncachedAddresses.length) {
      await new Promise(resolve => setTimeout(resolve, DELAY_BETWEEN_BATCHES));
    }
  }
  
  return addressMap;
}

// Get user's current location
function getUserLocation() {
  if (!navigator.geolocation) {
    document.getElementById('location-status').textContent = 'Geolocation is not supported by your browser.';
    return;
  }

  document.getElementById('location-status').textContent = 'Getting your location...';
  document.getElementById('use-location-btn').disabled = true;

  navigator.geolocation.getCurrentPosition(
    async (position) => {
      userLocation = {
        lat: position.coords.latitude,
        lon: position.coords.longitude
      };
      
      document.getElementById('location-status').innerHTML = 
        '<i class="bi bi-check-circle text-success me-1"></i>Location found! Calculating distances...';
      
      await calculateAndDisplayDistances();
    },
    (error) => {
      let errorMsg = 'Unable to get your location. ';
      switch(error.code) {
        case error.PERMISSION_DENIED:
          errorMsg += 'Please allow location access.';
          break;
        case error.POSITION_UNAVAILABLE:
          errorMsg += 'Location information unavailable.';
          break;
        case error.TIMEOUT:
          errorMsg += 'Location request timed out.';
          break;
        default:
          errorMsg += 'An unknown error occurred.';
          break;
      }
      document.getElementById('location-status').textContent = errorMsg;
      document.getElementById('use-location-btn').disabled = false;
    }
  );
}

// Reverse geocode to get area name from coordinates using API
async function getAreaNameFromCoordinates(lat, lon) {
  // Check cache first
  const cacheKey = `reverse_${lat.toFixed(4)}_${lon.toFixed(4)}`;
  const cache = getGeocodeCache();
  if (cache[cacheKey] && cache[cacheKey].area) {
    return cache[cacheKey].area;
  }
  
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);
    
    const response = await fetch(
      `geocode_proxy.php?action=reverse&lat=${lat}&lon=${lon}`,
      {
        signal: controller.signal
      }
    );
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const data = await response.json();
    if (data.success && data.address) {
      // Extract area name from address
      const address = data.address;
      let areaName = null;
      
      // Try to extract suburb, city_district, or city
      if (address.suburb) {
        areaName = address.suburb.toLowerCase().trim();
      } else if (address.city_district) {
        areaName = address.city_district.toLowerCase().trim();
      } else if (address.city) {
        areaName = address.city.toLowerCase().trim();
      } else if (address.neighbourhood) {
        areaName = address.neighbourhood.toLowerCase().trim();
      }
      
      // Normalize area name
      if (areaName) {
        areaName = areaName
          .replace(/\s*residential\s*area/gi, '')
          .replace(/\s*r\/a/gi, '')
          .replace(/\s*ra\b/gi, '')
          .trim();
        
        // Cache the result
        setGeocodeCache(cacheKey, { area: areaName, lat, lon });
        console.log('Detected area from API:', areaName);
      return areaName;
    }
    }
  } catch (error) {
    console.error('Reverse geocoding error:', error);
  }
  
  return null;
  if (cached) {
    return cached;
  }
  
  try {
    const response = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1`,
      { headers: { 'User-Agent': 'SmartDoc Medical App' } }
    );
    const data = await response.json();
    if (data && data.address) {
      const area = data.address.suburb || data.address.neighbourhood || data.address.quarter;
      if (area) {
        localStorage.setItem(cacheKey, area);
        return area;
      }
    }
  } catch (e) {
    console.error('Reverse geocoding error:', e);
  }
  
  return null;
}

// Match address by area name (FAST - no geocoding needed)
function matchAddressByArea(address, userArea) {
  if (!userArea || !address) return false;
  const addressLower = address.toLowerCase();
  const areaLower = userArea.toLowerCase();
  
  // Normalize area name variations
  const areaVariations = {
    'bashundhara': ['bashundhara', 'bashundhara r/a', 'bashundhara ra', 'bashundhara residential area', 'bashundhara residential'],
    'badda': ['badda'],
    'norda': ['norda'],
    'gulshan': ['gulshan'],
    'banani': ['banani'],
    'dhanmondi': ['dhanmondi'],
    'mirpur': ['mirpur'],
    'uttara': ['uttara'],
    'jatrabari': ['jatrabari'],
    'panthapath': ['panthapath']
  };
  
  // Get all variations for the user's area
  const variations = areaVariations[areaLower] || [areaLower];
  
  // Check if address contains ANY variation of the area name
  for (const variation of variations) {
    if (addressLower.includes(variation)) {
      return true;
    }
  }
  
  // Also check for nearby areas (e.g., Bashundhara -> Badda, Norda are nearby)
  const nearbyAreas = {
    'bashundhara': ['badda', 'norda', 'gulshan', 'banani'],
    'badda': ['bashundhara', 'norda', 'gulshan'],
    'norda': ['bashundhara', 'badda', 'gulshan'],
    'gulshan': ['banani', 'badda', 'bashundhara'],
    'banani': ['gulshan', 'badda'],
    'dhanmondi': ['panthapath', 'new market'],
    'mirpur': ['uttara', 'kalshi']
  };
  
  const nearby = nearbyAreas[areaLower];
  if (nearby) {
    return nearby.some(nearArea => {
      // Check for variations of nearby areas too
      const nearVariations = areaVariations[nearArea] || [nearArea];
      return nearVariations.some(variation => addressLower.includes(variation));
    });
  }
  
  return false;
}

// Calculate distances for all doctors - OPTIMIZED: Area matching first, then geocoding
async function calculateAndDisplayDistances() {
  const distanceInfos = document.querySelectorAll('.distance-info');
  
  // Step 1: Get user's area name from coordinates (FAST)
  document.getElementById('location-status').innerHTML = 
    '<i class="bi bi-hourglass-split text-primary me-1"></i>Finding your area...';
  
  const userArea = await getAreaNameFromCoordinates(userLocation.lat, userLocation.lon);
  
  // Step 2: Collect ALL addresses for geocoding (SHORT DISTANCE RANGE - 5km)
  const addressToElements = new Map();
  const allAddresses = [];
  const MAX_DISTANCE_KM = 5; // SHORT RANGE: Only show doctors within 5km
  
  distanceInfos.forEach((info) => {
    const address = info.getAttribute('data-address');
    if (address && address.trim() !== '') {
      if (!addressToElements.has(address)) {
        addressToElements.set(address, []);
        allAddresses.push(address);
      }
      addressToElements.get(address).push(info);
    } else {
      info.style.display = 'none';
    }
  });
  
  if (document.getElementById('location-status')) {
    document.getElementById('location-status').innerHTML = 
      `<i class="bi bi-hourglass-split text-primary me-1"></i>Calculating distances for ${allAddresses.length} doctor${allAddresses.length !== 1 ? 's' : ''} (within 5km)...`;
  }
  
  // Geocode ALL addresses and calculate distances (SHORT RANGE)
  const uniqueAddresses = Array.from(new Set(allAddresses));
  const BATCH_SIZE = 3;
  const DELAY_BETWEEN_BATCHES = 1000;
  let doctorsInRange = 0;
  
  for (let i = 0; i < uniqueAddresses.length; i += BATCH_SIZE) {
    const batch = uniqueAddresses.slice(i, i + BATCH_SIZE);
    
    const batchPromises = batch.map((address, batchIdx) => {
      const delay = batchIdx * 300;
      return geocodeAddress(address, delay).then(coords => {
        if (coords && userLocation) {
          const distance = calculateDistance(
            userLocation.lat,
            userLocation.lon,
            coords.lat,
            coords.lon
          );
          
          // Check if address contains Bashundhara/Badda/Norda (priority areas)
          const addressLower = address.toLowerCase();
          const isBashundhara = addressLower.includes('bashundhara') || addressLower.includes('evercare') || 
                               addressLower.includes('afroza begum') || addressLower.includes('bashundhara eye');
          const isBadda = addressLower.includes('badda');
          const isNorda = addressLower.includes('norda');
          const isPriorityArea = isBashundhara || isBadda || isNorda;
          
          // Show if within SHORT RANGE (5km) OR in priority areas (always show priority areas)
          if (distance <= MAX_DISTANCE_KM || isPriorityArea) {
            doctorsInRange++;
            
            const elements = addressToElements.get(address) || [];
            elements.forEach(info => {
              const distanceText = info.querySelector('.distance-text');
              const badge = info.querySelector('.badge');
              const card = info.closest('.col-md-6, .col-lg-4');
              
              if (card) {
                // Priority areas get lower distance value for sorting
                if (isBashundhara) {
                  card.setAttribute('data-distance', '0.1'); // Highest priority
                  card.setAttribute('data-area-match', 'true');
                } else if (isBadda || isNorda) {
                  card.setAttribute('data-distance', '0.2'); // Second priority
                  card.setAttribute('data-area-match', 'nearby');
                } else {
                  card.setAttribute('data-distance', distance.toFixed(2));
                }
              }
              
              if (distanceText) {
                if (isBashundhara) {
                  distanceText.textContent = 'Same area';
                } else if (isBadda || isNorda) {
                  distanceText.textContent = 'Nearby area';
                } else if (distance < 1) {
                  distanceText.textContent = `${Math.round(distance * 1000)}m away`;
                } else {
                  distanceText.textContent = `${distance.toFixed(1)}km away`;
                }
              }
              
              if (badge) {
                badge.classList.remove('bg-secondary');
                if (isBashundhara) {
                  badge.classList.add('bg-success');
                } else if (isBadda || isNorda) {
                  badge.classList.add('bg-info');
                } else {
                  badge.classList.add('bg-success');
                }
              }
              
              info.style.display = 'block';
            });
            
            triggerSort(); // Sort after each update
          } else {
            // Too far - hide it
            const elements = addressToElements.get(address) || [];
            elements.forEach(info => {
              info.style.display = 'none';
              const card = info.closest('.col-md-6, .col-lg-4');
              if (card) {
                card.setAttribute('data-distance', '999');
              }
            });
          }
        } else {
          // Geocoding failed - check if it's a priority area (Bashundhara/Badda/Norda)
          const addressLower = address.toLowerCase();
          const isBashundhara = addressLower.includes('bashundhara') || addressLower.includes('evercare') || 
                               addressLower.includes('afroza begum') || addressLower.includes('bashundhara eye');
          const isBadda = addressLower.includes('badda');
          const isNorda = addressLower.includes('norda');
          const isPriorityArea = isBashundhara || isBadda || isNorda;
          
          if (isPriorityArea) {
            // Show priority areas even if geocoding failed
            doctorsInRange++;
            const elements = addressToElements.get(address) || [];
            elements.forEach(info => {
              const distanceText = info.querySelector('.distance-text');
              const badge = info.querySelector('.badge');
              const card = info.closest('.col-md-6, .col-lg-4');
              
              if (card) {
                if (isBashundhara) {
                  card.setAttribute('data-distance', '0.1');
                  card.setAttribute('data-area-match', 'true');
                } else if (isBadda || isNorda) {
                  card.setAttribute('data-distance', '0.2');
                  card.setAttribute('data-area-match', 'nearby');
                }
              }
              
              if (distanceText) {
                distanceText.textContent = isBashundhara ? 'Same area' : 'Nearby area';
              }
              
              if (badge) {
                badge.classList.remove('bg-secondary');
                badge.classList.add(isBashundhara ? 'bg-success' : 'bg-info');
              }
              
              info.style.display = 'block';
            });
            triggerSort();
          } else {
            // Not priority area and geocoding failed - hide it
            const elements = addressToElements.get(address) || [];
            elements.forEach(info => {
              info.style.display = 'none';
              const card = info.closest('.col-md-6, .col-lg-4');
              if (card) {
                card.setAttribute('data-distance', '999');
              }
            });
          }
        }
        
        // Update status
        const remaining = uniqueAddresses.length - (i + batch.length);
        if (remaining > 0 && document.getElementById('location-status')) {
          document.getElementById('location-status').innerHTML = 
            `<i class="bi bi-hourglass-split text-primary me-1"></i>Processing... (${i + batch.length}/${uniqueAddresses.length})`;
        }
      });
    });
    
    await Promise.all(batchPromises);
    
    if (i + BATCH_SIZE < uniqueAddresses.length) {
      await new Promise(resolve => setTimeout(resolve, DELAY_BETWEEN_BATCHES));
    }
  }
  
  // Update final status
  const displayArea = userArea ? (userArea.charAt(0).toUpperCase() + userArea.slice(1).toLowerCase()) : 'your location';
  
  if (document.getElementById('location-status')) {
    if (doctorsInRange > 0) {
      document.getElementById('location-status').innerHTML = 
        `<i class="bi bi-check-circle text-success me-1"></i>Found ${doctorsInRange} doctor${doctorsInRange !== 1 ? 's' : ''} within 5km of ${displayArea}!`;
    } else {
      document.getElementById('location-status').innerHTML = 
        `<i class="bi bi-info-circle text-warning me-1"></i>No doctors found within 5km of ${displayArea}. Showing all doctors.`;
    }
  }
  
  // Sort immediately (area-matched doctors first)
  triggerSort();
  
  // Re-enable button immediately
  document.getElementById('use-location-btn').disabled = false;
  
  // OLD CODE REMOVED - No geocoding needed (causes CORS/503 errors)
  // All processing is done above using area matching only
  
  // Helper function to extract rating from card
  function getRatingFromCard(card) {
    const ratingSummary = card.querySelector('.rating-summary');
    if (ratingSummary) {
      const text = ratingSummary.textContent.trim();
      // Extract rating from text like "4.5/5" or "0.0/5"
      const match = text.match(/(\d+\.?\d*)\/5/);
      if (match) {
        return parseFloat(match[1]);
      }
    }
    return 0; // Default to 0 if no rating found
  }
  
  // Step 4: Sort function (called after each update) - Sort by area match priority, then rating, then distance
  function triggerSort() {
    const container = document.querySelector('.row.g-3');
    if (!container) return;
    
    const allCards = Array.from(container.querySelectorAll('.col-md-6, .col-lg-4'));
    const cardsWithDistance = allCards.filter(card => {
      const dist = card.getAttribute('data-distance');
      return dist && !isNaN(parseFloat(dist)) && parseFloat(dist) > 0;
    });
    const cardsWithoutDistance = allCards.filter(card => !card.hasAttribute('data-distance') || isNaN(parseFloat(card.getAttribute('data-distance'))));
    
    if (cardsWithDistance.length === 0) return;
    
    // Separate: exact matches, nearby matches, then others
    const exactMatchCards = cardsWithDistance.filter(card => card.getAttribute('data-area-match') === 'true');
    const nearbyMatchCards = cardsWithDistance.filter(card => card.getAttribute('data-area-match') === 'nearby');
    const otherCards = cardsWithDistance.filter(card => !card.hasAttribute('data-area-match') || 
                                             (card.getAttribute('data-area-match') !== 'true' && 
                                              card.getAttribute('data-area-match') !== 'nearby'));
    
    // Sort exact matches: first by rating (highest first), then by distance (nearest first)
    exactMatchCards.sort((a, b) => {
      const ratingA = getRatingFromCard(a);
      const ratingB = getRatingFromCard(b);
      const distA = parseFloat(a.getAttribute('data-distance') || '999');
      const distB = parseFloat(b.getAttribute('data-distance') || '999');
      
      // First priority: rating (highest first)
      if (ratingB !== ratingA) {
        return ratingB - ratingA; // Higher rating first
      }
      // Second priority: distance (nearest first)
      return distA - distB;
    });
    
    // Sort nearby matches: first by rating (highest first), then by distance (nearest first)
    nearbyMatchCards.sort((a, b) => {
      const ratingA = getRatingFromCard(a);
      const ratingB = getRatingFromCard(b);
      const distA = parseFloat(a.getAttribute('data-distance') || '999');
      const distB = parseFloat(b.getAttribute('data-distance') || '999');
      
      // First priority: rating (highest first)
      if (ratingB !== ratingA) {
        return ratingB - ratingA; // Higher rating first
      }
      // Second priority: distance (nearest first)
      return distA - distB;
    });
    
    // Sort other cards: first by rating (highest first), then by distance (nearest first)
    otherCards.sort((a, b) => {
      const ratingA = getRatingFromCard(a);
      const ratingB = getRatingFromCard(b);
      const distA = parseFloat(a.getAttribute('data-distance') || '999');
      const distB = parseFloat(b.getAttribute('data-distance') || '999');
      
      // First priority: rating (highest first)
      if (ratingB !== ratingA) {
        return ratingB - ratingA; // Higher rating first
      }
      // Second priority: distance (nearest first)
      if (isNaN(distA)) return 1;
      if (isNaN(distB)) return -1;
      return distA - distB;
    });
    
    // Combine: exact first (sorted by rating), then nearby (sorted by rating), then others (sorted by rating)
    const sortedCards = [...exactMatchCards, ...nearbyMatchCards, ...otherCards];
    
    // Remove all existing badges first
    document.querySelectorAll('.nearest-badge').forEach(badge => badge.remove());
    document.querySelectorAll('.card.border-success, .card.border-info').forEach(card => {
      card.classList.remove('border-success', 'border-info', 'border-2');
    });
    
    // Reorder: exact matches first, then nearby, then others
    sortedCards.forEach(card => container.appendChild(card));
    cardsWithoutDistance.forEach(card => container.appendChild(card));
    
    // Add visual indicator for BEST MATCH (highest rated exact match)
    if (exactMatchCards.length > 0) {
      const bestMatchCard = exactMatchCards[0];
      const nearestCard = bestMatchCard.querySelector('.card');
      if (nearestCard) {
        nearestCard.classList.add('border-success', 'border-2');
        nearestCard.style.position = 'relative';
        const nearestBadge = document.createElement('div');
        nearestBadge.className = 'position-absolute top-0 start-0 m-2 nearest-badge';
        nearestBadge.style.zIndex = '10';
        const rating = getRatingFromCard(bestMatchCard);
        nearestBadge.innerHTML = `<span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>Best Match${rating > 0 ? ` (${rating.toFixed(1)}★)` : ''}</span>`;
        nearestCard.appendChild(nearestBadge);
      }
    } else if (nearbyMatchCards.length > 0) {
      // If no exact matches, highlight best nearby match
      const bestNearbyCard = nearbyMatchCards[0];
      const nearestCard = bestNearbyCard.querySelector('.card');
      if (nearestCard) {
        nearestCard.classList.add('border-info', 'border-2');
        nearestCard.style.position = 'relative';
        const nearestBadge = document.createElement('div');
        nearestBadge.className = 'position-absolute top-0 start-0 m-2 nearest-badge';
        nearestBadge.style.zIndex = '10';
        const rating = getRatingFromCard(bestNearbyCard);
        nearestBadge.innerHTML = `<span class="badge bg-info"><i class="bi bi-star-fill me-1"></i>Nearby Best${rating > 0 ? ` (${rating.toFixed(1)}★)` : ''}</span>`;
        nearestCard.appendChild(nearestBadge);
      }
    }
  }

  // Sorting is already done above - no need to call again
}

// Event listener for "Use My Location" button
document.getElementById('use-location-btn').addEventListener('click', getUserLocation);

// Rating submission (AJAX)
document.querySelectorAll('.rate-form').forEach(form => {
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const doctorId = form.getAttribute('data-doctor-id');
    const input    = form.querySelector('input[name=\"rating\"]');
    const rating   = parseFloat(input.value);
    const msgEl    = form.querySelector('.rate-message');

    if (!input.value || isNaN(rating) || rating < 0 || rating > 5) {
      msgEl.textContent = 'Please enter a rating between 0 and 5 (e.g., 4.6).';
      msgEl.classList.remove('text-success');
      msgEl.classList.add('text-danger');
      return;
    }

    msgEl.textContent = 'Submitting...';
    msgEl.classList.remove('text-danger', 'text-success');

    try {
      const resp = await fetch('rate_doctor.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({ doctor_id: doctorId, rating: rating.toFixed(1) })
      });

      const data = await resp.json();
      if (!data.success) {
        msgEl.textContent = data.error === 'LOGIN_REQUIRED'
          ? 'Please log in to rate.'
          : 'Could not save rating.';
        msgEl.classList.add('text-danger');
        return;
      }

      // Update stars and text
       const cardBody = form.closest('.card-body');
       if (cardBody) {
         const ratingText = cardBody.querySelector('.rating-summary');
         const starsWrap  = cardBody.querySelector('.doctor-stars');
        if (starsWrap) {
          const avg = data.avg_rating || 0;
          const rounded = Math.round(avg);
          starsWrap.innerHTML = '';
          for (let i = 1; i <= 5; i++) {
            const icon = document.createElement('i');
            icon.className = 'bi ' + (i <= rounded ? 'bi-star-fill' : 'bi-star');
            starsWrap.appendChild(icon);
          }
        }
        if (ratingText) {
          ratingText.textContent = `${(data.avg_rating ?? 0).toFixed(1)}/5 (${data.rating_count} rating${data.rating_count === 1 ? '' : 's'})`;
        }
      }

      msgEl.textContent = 'Thanks for rating!';
      msgEl.classList.add('text-success');
    } catch (err) {
      console.error(err);
      msgEl.textContent = 'Error submitting rating.';
      msgEl.classList.add('text-danger');
    }
  });
});

// Auto-get location if coming from GenerateSpecialist page with specialty
<?php if($specialty_name): ?>
// Auto-request location when page loads with a specialty filter
window.addEventListener('DOMContentLoaded', () => {
  setTimeout(() => {
    getUserLocation();
  }, 500);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ob_end_flush(); ?>
