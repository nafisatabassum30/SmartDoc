<?php
/**
 * Geocoding Proxy - Server-side proxy to avoid CORS issues
 * Calls Nominatim API on the server and returns results to client
 */
// Suppress all output except JSON
ob_start();
error_reporting(0); // Suppress PHP errors from being output
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/util.php';
require_once __DIR__ . '/includes/auth.php';
require_patient_login();

// Clear any output buffer
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'geocode';
$address = $_GET['address'] ?? '';
$lat = $_GET['lat'] ?? '';
$lon = $_GET['lon'] ?? '';

// Rate limiting: Max 1 request per second per user
session_start();
$lastRequest = $_SESSION['geocode_last_request'] ?? 0;
$currentTime = time();
if ($currentTime - $lastRequest < 1) {
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit: Please wait 1 second between requests'
    ]);
    exit;
}
$_SESSION['geocode_last_request'] = $currentTime;

try {
    if ($action === 'geocode' && $address) {
        // Geocode address to coordinates
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'format' => 'json',
            'q' => $address,
            'limit' => 1,
            'countrycodes' => 'bd',
            'addressdetails' => 1
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SmartDoc Medical App');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept-Language: en'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data && count($data) > 0) {
                echo json_encode([
                    'success' => true,
                    'lat' => (float)$data[0]['lat'],
                    'lon' => (float)$data[0]['lon'],
                    'address' => $address
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Address not found'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Geocoding service unavailable'
            ]);
        }
    } elseif ($action === 'reverse' && $lat && $lon) {
        // Reverse geocode coordinates to address
        $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
            'format' => 'json',
            'lat' => $lat,
            'lon' => $lon,
            'zoom' => 18,
            'addressdetails' => 1
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SmartDoc Medical App');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept-Language: en'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data && isset($data['address'])) {
                echo json_encode([
                    'success' => true,
                    'address' => $data['address'],
                    'display_name' => $data['display_name'] ?? ''
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Location not found'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Reverse geocoding service unavailable'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid parameters'
        ]);
    }
} catch (Exception $e) {
    error_log('Geocoding proxy error: ' . $e->getMessage());
    // Clear any output before sending JSON
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
    exit;
}

// Ensure no extra output
ob_end_flush();
?>

