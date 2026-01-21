<?php
/**
 * Address Autocomplete API
 * Returns address suggestions from Google Maps Places API
 */

// Load geocoding configuration
$geocodingConfigFile = __DIR__ . '/../config/geocoding.php';
if (file_exists($geocodingConfigFile)) {
    require_once $geocodingConfigFile;
}

// Start output buffering immediately to catch any output
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Disable error display but log them - use @ to suppress any errors
@ini_set('display_errors', 0);
@ini_set('log_errors', 1);
@error_reporting(0); // Suppress all errors to prevent HTML output

// Set JSON header immediately - use @ to suppress warnings
@header('Content-Type: application/json', true);
@header('X-Content-Type-Options: nosniff', true);

// Register shutdown function to ensure JSON is always returned
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error occurred',
            'message' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

try {
    $query = $_GET['q'] ?? '';
    if (empty($query) || strlen($query) < 3) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'suggestions' => []
        ]);
        exit;
    }

    // Get Google Maps API key
    $apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
    
    if (empty($apiKey)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Google Maps API key not configured',
            'suggestions' => []
        ]);
        exit;
    }

    // Use Google Geocoding API for address search
    // Add South Africa context for better results
    $searchQuery = $query . ', South Africa';
    $encodedQuery = urlencode($searchQuery);
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$encodedQuery}&key={$apiKey}&region=za";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $suggestions = [];
    
    if ($httpCode === 200 && $response !== false) {
        $data = json_decode($response, true);
        
        if ($data && $data['status'] === 'OK' && !empty($data['results'])) {
            // Limit to 10 results
            $results = array_slice($data['results'], 0, 10);
            
            foreach ($results as $result) {
                $addressComponents = $result['address_components'] ?? [];
                $geometry = $result['geometry'] ?? [];
                $location = $geometry['location'] ?? [];
                $formattedAddress = $result['formatted_address'] ?? '';
                
                // Extract address components
                $streetNumber = '';
                $streetName = '';
                $suburb = '';
                $town = '';
                
                foreach ($addressComponents as $component) {
                    $types = $component['types'] ?? [];
                    $longName = $component['long_name'] ?? '';
                    
                    if (in_array('street_number', $types)) {
                        $streetNumber = $longName;
                    } elseif (in_array('route', $types)) {
                        $streetName = $longName;
                    } elseif (in_array('sublocality', $types) || in_array('sublocality_level_1', $types) || in_array('neighborhood', $types)) {
                        if (empty($suburb)) {
                            $suburb = $longName;
                        }
                    } elseif (in_array('locality', $types)) {
                        $town = $longName;
                    } elseif (in_array('administrative_area_level_2', $types)) {
                        if (empty($town)) {
                            $town = $longName;
                        }
                    }
                }
                
                // If we have a formatted address but missing components, try to parse it
                if (!empty($formattedAddress)) {
                    // Try to extract street number from formatted address if not found in components
                    if (empty($streetNumber)) {
                        if (preg_match('/^(\d+)\s+/', $formattedAddress, $matches)) {
                            $streetNumber = $matches[1];
                        }
                    }
                    
                    // Try to extract street name if missing
                    if (empty($streetName) && !empty($streetNumber)) {
                        $formattedWithoutNumber = preg_replace('/^' . preg_quote($streetNumber, '/') . '\s+/', '', $formattedAddress);
                        $parts = explode(',', $formattedWithoutNumber);
                        if (!empty($parts[0])) {
                            $streetName = trim($parts[0]);
                        }
                    } elseif (empty($streetName)) {
                        // No street number, try first part as street name
                        $parts = explode(',', $formattedAddress);
                        if (!empty($parts[0])) {
                            $streetName = trim($parts[0]);
                        }
                    }
                }
                
                $suggestions[] = [
                    'display_name' => $formattedAddress,
                    'street_number' => $streetNumber,
                    'street_name' => $streetName,
                    'suburb' => $suburb,
                    'town' => $town,
                    'lat' => isset($location['lat']) ? (float)$location['lat'] : null,
                    'lon' => isset($location['lng']) ? (float)$location['lng'] : null,
                    'relevance_score' => 90 // Google results are generally high quality
                ];
            }
        } elseif ($data && isset($data['status'])) {
            // Log error status
            error_log('Google Geocoding API error - Status: ' . $data['status']);
            if (isset($data['error_message'])) {
                error_log('Error message: ' . $data['error_message']);
            }
        }
    } else {
        // Log HTTP error
        if ($httpCode !== 200) {
            error_log('Google Geocoding API HTTP error - Code: ' . $httpCode);
        }
        if ($curlError) {
            error_log('cURL Error: ' . $curlError);
        }
    }

    // Clear any output buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    // Clear output buffer and send error JSON
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'message' => $e->getMessage()
    ]);
    exit;
} catch (Error $e) {
    // Catch fatal errors too
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error occurred',
        'message' => $e->getMessage()
    ]);
    exit;
}
