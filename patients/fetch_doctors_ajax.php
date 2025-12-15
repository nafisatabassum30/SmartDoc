<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';
require_patient_login();

header('Content-Type: application/json');

$specialization_id = !empty($_GET['specialization_id']) ? (int)$_GET['specialization_id'] : 0;
$specialty_name    = trim($_GET['specialty'] ?? '');
$limit             = 20; // Increased limit to show more doctors

// If a specialty name is provided, map it to specialization_id
if ($specialization_id === 0 && $specialty_name !== '') {
    // Decode URL encoding
    $specialty_name = urldecode($specialty_name);
    
    // Try exact match first
    $stmt = $con->prepare("SELECT specialization_id FROM `specialization` WHERE specialization_name = ? LIMIT 1");
    $stmt->bind_param('s', $specialty_name);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
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
        if ($row = $res->fetch_assoc()) {
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
            if ($row = $res->fetch_assoc()) {
                $specialization_id = (int)$row['specialization_id'];
            }
            $stmt->close();
        }
    }
}

if ($specialization_id <= 0) {
    // Log the attempted search for debugging
    error_log("Failed to find specialization: specialty_name='{$specialty_name}', specialization_id={$specialization_id}");
    
    echo json_encode([
        'success' => false,
        'message' => 'Invalid specialization: ' . htmlspecialchars($specialty_name ?: 'not provided'),
        'doctors' => [],
        'debug' => [
            'searched_name' => $specialty_name,
            'specialization_id' => $specialization_id
        ]
    ]);
    exit;
}

try {
    // Check if ratings column exists, if not calculate from doctor_ratings table
    $checkColumn = $con->query("SHOW COLUMNS FROM `doctor` LIKE 'ratings(out of 5)'");
    $hasRatingsColumn = ($checkColumn && $checkColumn->num_rows > 0);
    
    // Build query to fetch doctors with ratings
    if ($hasRatingsColumn) {
        $sql = "SELECT d.*, 
                       COALESCE(d.`ratings(out of 5)`, 0) as ratings,
                       COALESCE(d.rating_count, 0) as rating_count,
                       s.specialization_name, 
                       h.hospital_name, 
                       h.address AS hospital_address,
                       l.area_name,
                       l.city
                FROM `doctor` d
                LEFT JOIN `specialization` s ON d.specialization_id = s.specialization_id
                LEFT JOIN `hospital` h ON d.hospital_id = h.hospital_id
                LEFT JOIN `location` l ON h.location_id = l.location_id
                WHERE d.specialization_id = ?
                ORDER BY ratings DESC, d.rating_count DESC, d.name ASC
                LIMIT ?";
    } else {
        // Calculate ratings from doctor_ratings table if column doesn't exist
        $sql = "SELECT d.*, 
                       COALESCE(AVG(dr.rating), 0) as ratings,
                       COUNT(dr.rating) as rating_count,
                       s.specialization_name, 
                       h.hospital_name, 
                       h.address AS hospital_address,
                       l.area_name,
                       l.city
                FROM `doctor` d
                LEFT JOIN `specialization` s ON d.specialization_id = s.specialization_id
                LEFT JOIN `hospital` h ON d.hospital_id = h.hospital_id
                LEFT JOIN `location` l ON h.location_id = l.location_id
                LEFT JOIN `doctor_ratings` dr ON d.doctor_id = dr.doctor_id
                WHERE d.specialization_id = ?
                GROUP BY d.doctor_id
                ORDER BY ratings DESC, rating_count DESC, d.name ASC
                LIMIT ?";
    }

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        throw new Exception('Query preparation failed: ' . $con->error);
    }
    
    $stmt->bind_param("ii", $specialization_id, $limit);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();

    $doctors = [];
    if ($result) {
        while ($doctor = $result->fetch_assoc()) {
            $doctors[] = [
                'doctor_id' => (int)$doctor['doctor_id'],
                'name' => $doctor['name'] ?? '',
                'designation' => $doctor['designation'] ?? '',
                'specialization_name' => $doctor['specialization_name'] ?? '',
                'hospital_name' => $doctor['hospital_name'] ?? '',
                'hospital_address' => $doctor['hospital_address'] ?? '',
                'website_url' => $doctor['website_url'] ?? '',
                'ratings' => isset($doctor['ratings']) ? (float)$doctor['ratings'] : 0.0,
                'rating_count' => isset($doctor['rating_count']) ? (int)$doctor['rating_count'] : 0,
                'area_name' => $doctor['area_name'] ?? '',
                'city' => $doctor['city'] ?? ''
            ];
        }
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'doctors' => $doctors,
        'count' => count($doctors)
    ]);
    
} catch (Exception $e) {
    error_log('Error in fetch_doctors_ajax.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading doctors: ' . $e->getMessage(),
        'doctors' => []
    ]);
}
?>

