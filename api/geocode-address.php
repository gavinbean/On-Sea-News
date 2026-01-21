<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/geocoding.php';

header('Content-Type: application/json');

$address = $_GET['address'] ?? '';

if (empty($address)) {
    echo json_encode([
        'success' => false,
        'message' => 'Address parameter is required'
    ]);
    exit;
}

// Determine which geocoding provider to use
$provider = defined('GEOCODING_PROVIDER') ? GEOCODING_PROVIDER : 'nominatim';

// Geocode the address
if ($provider === 'google' && defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY)) {
    $geocodeResult = geocodeAddressGoogle($address);
} else {
    $geocodeResult = geocodeAddressNominatim($address);
}

if ($geocodeResult['success']) {
    echo json_encode([
        'success' => true,
        'latitude' => $geocodeResult['latitude'],
        'longitude' => $geocodeResult['longitude'],
        'formatted_address' => $geocodeResult['formatted_address'] ?? $address
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $geocodeResult['message'] ?? 'Failed to geocode address'
    ]);
}
