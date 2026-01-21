<?php
/**
 * Geocoding Functions
 * Supports both Nominatim (OpenStreetMap) and Google Maps Geocoding API
 */

require_once __DIR__ . '/../config/database.php';

// Load geocoding configuration if it exists
$geocodingConfigFile = __DIR__ . '/../config/geocoding.php';
if (file_exists($geocodingConfigFile)) {
    require_once $geocodingConfigFile;
} else {
    // Defaults
    define('GEOCODING_PROVIDER', 'nominatim');
    define('GOOGLE_MAPS_API_KEY', '');
    define('USE_GOOGLE_FOR_HOUSE_NUMBERS', false);
}

/**
 * Geocode an address using Google Maps Geocoding API
 * Returns array with success, latitude, longitude, and formatted_address
 */
function geocodeAddressGoogle($address, $expectedHouseNumber = null) {
    $apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
    
    if (empty($apiKey)) {
        return [
            'success' => false,
            'message' => 'Google Maps API key not configured'
        ];
    }
    
    // Build address string if array
    if (is_array($address)) {
        $addressParts = [];
        if (!empty($address['house_number']) && !empty($address['street'])) {
            $addressParts[] = $address['house_number'] . ' ' . $address['street'];
        } elseif (!empty($address['street'])) {
            $addressParts[] = $address['street'];
        }
        if (!empty($address['city'])) $addressParts[] = $address['city'];
        if (!empty($address['state'])) $addressParts[] = $address['state'];
        if (!empty($address['country'])) $addressParts[] = $address['country'];
        $address = implode(', ', $addressParts);
    }
    
    $encodedAddress = urlencode($address);
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$encodedAddress}&key={$apiKey}&region=za";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || $response === false) {
        return [
            'success' => false,
            'message' => 'Failed to connect to Google Maps Geocoding API'
        ];
    }
    
    $data = json_decode($response, true);
    
    if ($data && $data['status'] === 'OK' && !empty($data['results'])) {
        $result = $data['results'][0];
        $location = $result['geometry']['location'];
        $latitude = (float)$location['lat'];
        $longitude = (float)$location['lng'];
        
        $formattedAddress = $result['formatted_address'];
        
        // Check if house number is in the result
        $hasHouseNumber = false;
        if ($expectedHouseNumber) {
            $expectedHouseNumberStr = (string)$expectedHouseNumber;
            // Check address components for explicit street_number
            foreach ($result['address_components'] as $component) {
                if (in_array('street_number', $component['types'])) {
                    // Check if the value matches our expected house number
                    if (isset($component['long_name']) && $component['long_name'] == $expectedHouseNumberStr) {
                        $hasHouseNumber = true;
                        break;
                    }
                    if (isset($component['short_name']) && $component['short_name'] == $expectedHouseNumberStr) {
                        $hasHouseNumber = true;
                        break;
                    }
                }
            }
            // Check formatted address - look for house number at start of address or before street name
            if (!$hasHouseNumber) {
                // Check if house number appears at the beginning of the formatted address
                if (preg_match('/^' . preg_quote($expectedHouseNumberStr, '/') . '\s+/i', $formattedAddress)) {
                    $hasHouseNumber = true;
                }
                // Check if house number appears anywhere in the formatted address (as a whole word)
                elseif (preg_match('/\b' . preg_quote($expectedHouseNumberStr, '/') . '\b/i', $formattedAddress)) {
                    $hasHouseNumber = true;
                }
            }
            // Also check street name component - sometimes house number is included there
            if (!$hasHouseNumber) {
                foreach ($result['address_components'] as $component) {
                    if (in_array('route', $component['types']) || in_array('street_address', $component['types'])) {
                        $streetName = $component['long_name'] ?? $component['short_name'] ?? '';
                        if (preg_match('/^' . preg_quote($expectedHouseNumberStr, '/') . '\s+/i', $streetName) ||
                            preg_match('/\b' . preg_quote($expectedHouseNumberStr, '/') . '\b/i', $streetName)) {
                            $hasHouseNumber = true;
                            break;
                        }
                    }
                }
            }
        }
        
        // If we have an expected house number and coordinates are precise, consider it as having house number
        // even if not explicitly found (the service likely used it to get precise coordinates)
        if (!$hasHouseNumber && $expectedHouseNumber) {
            $locationType = $result['geometry']['location_type'] ?? '';
            if ($locationType === 'ROOFTOP' || $locationType === 'RANGE_INTERPOLATED') {
                $hasHouseNumber = true; // Precise coordinates suggest house number was used
            }
        }
        
        return [
            'success' => true,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'formatted_address' => $formattedAddress,
            'has_house_number' => $hasHouseNumber,
            'approximate' => $result['geometry']['location_type'] !== 'ROOFTOP' && $result['geometry']['location_type'] !== 'RANGE_INTERPOLATED'
        ];
    } else {
        $errorMessage = 'Address not found';
        if (isset($data['error_message'])) {
            $errorMessage = $data['error_message'];
        } elseif (isset($data['status'])) {
            $errorMessage = 'Geocoding failed: ' . $data['status'];
        }
        
        return [
            'success' => false,
            'message' => $errorMessage
        ];
    }
}

/**
 * Geocode an address using Nominatim (OpenStreetMap)
 * Returns array with success, latitude, longitude, and formatted_address
 * 
 * @param string|array $address - Either a string address or array with structured components
 */
function geocodeAddressNominatim($address, $expectedHouseNumber = null) {
    // If address is an array, use structured query format
    if (is_array($address)) {
        $params = [];
        if (!empty($address['house_number'])) $params['house_number'] = $address['house_number'];
        if (!empty($address['street'])) $params['street'] = $address['street'];
        if (!empty($address['city'])) $params['city'] = $address['city'];
        if (!empty($address['state'])) $params['state'] = $address['state'];
        if (!empty($address['country'])) $params['country'] = $address['country'];
        if (!empty($address['postcode'])) $params['postcode'] = $address['postcode'];
        
        $url = "https://nominatim.openstreetmap.org/search?" . http_build_query($params) . "&format=json&limit=1&addressdetails=1";
        $expectedHouseNumber = $address['house_number'] ?? null;
    } else {
        $encodedAddress = urlencode($address);
        $url = "https://nominatim.openstreetmap.org/search?q={$encodedAddress}&format=json&limit=1&addressdetails=1&countrycodes=za";
    }
    
    // Use cURL for better control
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'BushmansRiverKentonOnSeaCommunityApp/1.0 (Contact: webmaster@onseanews.co.za)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en-US,en;q=0.9'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || $response === false) {
        // If country code restriction fails, try without it
        if (strpos($url, 'countrycodes=za') !== false) {
            $url2 = str_replace('&countrycodes=za', '', $url);
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $url2);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_USERAGENT, 'BushmansRiverKentonOnSeaCommunityApp/1.0 (Contact: webmaster@onseanews.co.za)');
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch2);
            $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
        }
        
        if ($httpCode !== 200 || $response === false) {
            return [
                'success' => false,
                'message' => 'Failed to connect to geocoding service. Please try again.'
            ];
        }
    }
    
    $data = json_decode($response, true);
    
    if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
        $result = $data[0];
        $latitude = (float)$result['lat'];
        $longitude = (float)$result['lon'];
        
        // Build formatted address from display_name or address components
        $formattedAddress = $result['display_name'] ?? (is_array($address) ? implode(', ', array_filter($address)) : $address);
        
        // Check if the returned result actually includes the house number
        $hasHouseNumber = false;
        $explicitlyFound = false; // Track if we explicitly found it in the result
        if ($expectedHouseNumber) {
            $expectedHouseNumberStr = (string)$expectedHouseNumber;
            if (!empty($result['address'])) {
                $addressDetails = $result['address'];
                // Check various fields where house number might appear explicitly
                if (!empty($addressDetails['house_number']) && 
                    ($addressDetails['house_number'] == $expectedHouseNumberStr || 
                     strpos($addressDetails['house_number'], $expectedHouseNumberStr) !== false)) {
                    $hasHouseNumber = true;
                    $explicitlyFound = true;
                }
                if (!$hasHouseNumber && !empty($addressDetails['house']) && 
                    ($addressDetails['house'] == $expectedHouseNumberStr || 
                     strpos($addressDetails['house'], $expectedHouseNumberStr) !== false)) {
                    $hasHouseNumber = true;
                    $explicitlyFound = true;
                }
            }
            // Check if house number appears in display_name (more flexible matching)
            if (!$hasHouseNumber && !empty($result['display_name'])) {
                $displayName = $result['display_name'];
                // Check if house number appears at the beginning
                if (preg_match('/^' . preg_quote($expectedHouseNumberStr, '/') . '\s+/i', $displayName)) {
                    $hasHouseNumber = true;
                    $explicitlyFound = true;
                }
                // Check if house number appears anywhere as a whole word
                elseif (preg_match('/\b' . preg_quote($expectedHouseNumberStr, '/') . '\b/i', $displayName)) {
                    $hasHouseNumber = true;
                    $explicitlyFound = true;
                }
            }
            // Also check the road/street name - sometimes house number is included there
            if (!$hasHouseNumber && !empty($result['address']['road'])) {
                $roadName = $result['address']['road'];
                if (preg_match('/^' . preg_quote($expectedHouseNumberStr, '/') . '\s+/i', $roadName) ||
                    preg_match('/\b' . preg_quote($expectedHouseNumberStr, '/') . '\b/i', $roadName)) {
                    $hasHouseNumber = true;
                    $explicitlyFound = true;
                }
            }
        }
        
        // If we have an expected house number and got coordinates, consider it as having house number
        // The geocoding service likely used the house number to get the coordinates
        if (!$hasHouseNumber && $expectedHouseNumber) {
            // If we successfully geocoded with a house number provided, assume it was used
            $hasHouseNumber = true;
        }
        
        return [
            'success' => true,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'formatted_address' => $formattedAddress,
            'has_house_number' => $hasHouseNumber,
            'approximate' => $expectedHouseNumber && !$explicitlyFound // Approximate if we expected a house number but didn't explicitly find it in result
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Address not found. Please check your address and try again.'
        ];
    }
}

/**
 * Validate and geocode address during registration
 * Accepts either a string address or address components array
 */
function validateAndGeocodeAddress($addressOrComponents) {
    // Handle both string and array inputs
    if (is_array($addressOrComponents)) {
        $addressParts = [];
        // Combine street number and street name as "123 Main Street" (not "123, Main Street")
        if (!empty($addressOrComponents['street_number']) && !empty($addressOrComponents['street_name'])) {
            $addressParts[] = trim($addressOrComponents['street_number'] . ' ' . $addressOrComponents['street_name']);
        } elseif (!empty($addressOrComponents['street_number'])) {
            $addressParts[] = $addressOrComponents['street_number'];
        } elseif (!empty($addressOrComponents['street_name'])) {
            $addressParts[] = $addressOrComponents['street_name'];
        }
        if (!empty($addressOrComponents['suburb'])) $addressParts[] = $addressOrComponents['suburb'];
        if (!empty($addressOrComponents['town'])) $addressParts[] = $addressOrComponents['town'];
        
        // Validate required fields
        if (empty($addressOrComponents['street_name']) || empty($addressOrComponents['town'])) {
            return [
                'success' => false,
                'message' => 'Street name and town are required'
            ];
        }
        
        $address = implode(', ', $addressParts);
    } else {
        $address = $addressOrComponents;
    }
    
    if (empty($address)) {
        return [
            'success' => false,
            'message' => 'Address is required'
        ];
    }
    
    // Determine which provider to use
    $useGoogle = false;
    $provider = defined('GEOCODING_PROVIDER') ? GEOCODING_PROVIDER : 'nominatim';
    
    // Use Google Maps if:
    // 1. Provider is set to 'google', OR
    // 2. USE_GOOGLE_FOR_HOUSE_NUMBERS is true and we have a street number
    if ($provider === 'google' || 
        (defined('USE_GOOGLE_FOR_HOUSE_NUMBERS') && USE_GOOGLE_FOR_HOUSE_NUMBERS && 
         is_array($addressOrComponents) && !empty($addressOrComponents['street_number']))) {
        $useGoogle = true;
        $apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
        if (!empty($apiKey)) {
            $useGoogle = true;
        } else {
            $useGoogle = false; // Fall back to Nominatim if no API key
        }
    }
    
    // Try Google Maps first if configured
    if ($useGoogle && is_array($addressOrComponents)) {
        $googleAddress = [
            'house_number' => $addressOrComponents['street_number'] ?? '',
            'street' => $addressOrComponents['street_name'] ?? '',
            'city' => $addressOrComponents['town'] ?? '',
            'state' => '',
            'country' => 'South Africa'
        ];
        if (!empty($addressOrComponents['suburb'])) {
            $googleAddress['city'] = $addressOrComponents['suburb'] . ', ' . $googleAddress['city'];
        }
        
        $result = geocodeAddressGoogle($googleAddress, $addressOrComponents['street_number'] ?? null);
        
        // If Google succeeds but doesn't include house number and we have one, try Nominatim as fallback
        if ($result['success'] && !empty($addressOrComponents['street_number']) && 
            isset($result['has_house_number']) && !$result['has_house_number'] && 
            $provider !== 'google') {
            // Try Nominatim to see if it can find the house number
            $addressWithContext = $address . ', South Africa';
            $nominatimResult = geocodeAddressNominatim($addressWithContext, $addressOrComponents['street_number']);
            // Use Nominatim result if it includes the house number
            if ($nominatimResult['success'] && isset($nominatimResult['has_house_number']) && $nominatimResult['has_house_number']) {
                return $nominatimResult;
            }
        }
        
        // If Google succeeds, return it
        if ($result['success']) {
            return $result;
        }
        // If Google fails and provider is 'google', don't fall back to Nominatim
        if ($provider === 'google') {
            return $result;
        }
        // Otherwise, fall through to Nominatim
    }
    
    // Use Nominatim (either as primary or fallback)
    // Strategy 1: Full address with street number and South Africa context
    $addressWithContext = $address . ', South Africa';
    $result = geocodeAddressNominatim($addressWithContext, is_array($addressOrComponents) ? ($addressOrComponents['street_number'] ?? null) : null);
    
    // If Nominatim succeeds but doesn't include house number and we have one, try Google as fallback
    if ($result['success'] && is_array($addressOrComponents) && !empty($addressOrComponents['street_number']) && 
        isset($result['has_house_number']) && !$result['has_house_number']) {
        $apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
        if (!empty($apiKey)) {
            $googleAddress = [
                'house_number' => $addressOrComponents['street_number'] ?? '',
                'street' => $addressOrComponents['street_name'] ?? '',
                'city' => $addressOrComponents['town'] ?? '',
                'state' => '',
                'country' => 'South Africa'
            ];
            if (!empty($addressOrComponents['suburb'])) {
                $googleAddress['city'] = $addressOrComponents['suburb'] . ', ' . $googleAddress['city'];
            }
            $googleResult = geocodeAddressGoogle($googleAddress, $addressOrComponents['street_number'] ?? null);
            // Use Google result if it includes the house number, or if it's more precise (not approximate)
            if ($googleResult['success']) {
                if (isset($googleResult['has_house_number']) && $googleResult['has_house_number']) {
                    return $googleResult; // Google has house number, use it
                } elseif (!isset($googleResult['approximate']) || !$googleResult['approximate']) {
                    // Google doesn't have house number but is precise, prefer it over Nominatim
                    return $googleResult;
                }
                // Otherwise, keep Nominatim result
            }
        }
    }
    
    // Strategy 2: Full address without country context
    if (!$result['success']) {
        $result = geocodeAddressNominatim($address, is_array($addressOrComponents) ? ($addressOrComponents['street_number'] ?? null) : null);
    }
    
    // Strategy 3: If still fails and we have street number, try with street number + street name + town
    if (!$result['success'] && is_array($addressOrComponents) && !empty($addressOrComponents['street_number']) && !empty($addressOrComponents['street_name']) && !empty($addressOrComponents['town'])) {
        $withNumber = $addressOrComponents['street_number'] . ' ' . $addressOrComponents['street_name'] . ', ' . $addressOrComponents['town'] . ', South Africa';
        $result = geocodeAddressNominatim($withNumber, $addressOrComponents['street_number']);
        if ($result['success'] && !isset($result['approximate'])) {
            $result['approximate'] = false;
        }
    }
    
    // Strategy 4: If still fails, try with just street name and town (no number) - mark as approximate
    if (!$result['success'] && is_array($addressOrComponents) && !empty($addressOrComponents['street_name']) && !empty($addressOrComponents['town'])) {
        $streetAndTown = $addressOrComponents['street_name'] . ', ' . $addressOrComponents['town'] . ', South Africa';
        $result = geocodeAddressNominatim($streetAndTown);
        if ($result['success']) {
            $result['approximate'] = true;
        }
    }
    
    // Strategy 5: If still fails, try with just town and country - mark as approximate
    if (!$result['success'] && is_array($addressOrComponents) && !empty($addressOrComponents['town'])) {
        $townOnly = $addressOrComponents['town'] . ', South Africa';
        $result = geocodeAddressNominatim($townOnly);
        if ($result['success']) {
            $result['approximate'] = true;
        }
    }
    
    // If all strategies fail, return success with null coordinates (address is still valid)
    if (!$result['success']) {
        return [
            'success' => true,
            'latitude' => null,
            'longitude' => null,
            'formatted_address' => $address,
            'approximate' => false,
            'geocoded' => false,
            'message' => 'Address saved but could not be geocoded. Map features may not be available.'
        ];
    }
    
    return $result;
}
