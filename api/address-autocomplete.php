<?php
/**
 * Address Autocomplete API
 * Returns address suggestions from Nominatim (OpenStreetMap)
 */

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

    // Try multiple search strategies for better results
    // Strategy 1: Query with South Africa context
    // Also try with apostrophe variations (e.g., "Bushmans" and "Bushman's")
    $addressWithContext = $query . ', South Africa';
    $encodedAddress = urlencode($addressWithContext);
    $url = "https://nominatim.openstreetmap.org/search?q={$encodedAddress}&format=json&limit=10&addressdetails=1&countrycodes=za&dedupe=1";

    // Also try with apostrophe added if query doesn't have one (e.g., "Bushmans" -> "Bushman's")
    $queryWithApostrophe = null;
    if (stripos($query, "'") === false && stripos($query, "'") === false) {
        // Try adding apostrophe before 's' at the end of words
        $queryWithApostrophe = preg_replace('/\b([a-z]+)s\b/i', "$1's", $query);
        if ($queryWithApostrophe !== $query) {
            $addressWithApostrophe = $queryWithApostrophe . ', South Africa';
            $urlWithApostrophe = "https://nominatim.openstreetmap.org/search?q=" . urlencode($addressWithApostrophe) . "&format=json&limit=10&addressdetails=1&countrycodes=za&dedupe=1";
        }
    }

    // Use cURL for better control and proper headers
    // Nominatim requires a User-Agent header and has rate limiting
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

    $data = [];
    if ($httpCode === 200 && $response !== false) {
        $data = json_decode($response, true);
        // Log error if JSON decode failed
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Address autocomplete JSON decode error: ' . json_last_error_msg());
            error_log('Response: ' . substr($response, 0, 500));
            $data = [];
        }
    } else {
        // Log error but continue to try fallback strategies
        if ($httpCode !== 200) {
            error_log('Nominatim API error - HTTP Code: ' . $httpCode);
        }
        if ($curlError) {
            error_log('cURL Error: ' . $curlError);
        }
    }

// If no results, try with apostrophe variation (e.g., "Bushmans" -> "Bushman's")
if (empty($data) || !is_array($data) || count($data) === 0) {
    // Try adding apostrophe before 's' at the end of words if query doesn't have one
    if (stripos($query, "'") === false && stripos($query, "'") === false) {
        $queryWithApostrophe = preg_replace('/\b([a-z]+)s\b/i', "$1's", $query);
        if ($queryWithApostrophe !== $query) {
            $urlApostrophe = "https://nominatim.openstreetmap.org/search?q=" . urlencode($queryWithApostrophe . ', South Africa') . "&format=json&limit=10&addressdetails=1&countrycodes=za&dedupe=1";
            
            $chApostrophe = curl_init();
            curl_setopt($chApostrophe, CURLOPT_URL, $urlApostrophe);
            curl_setopt($chApostrophe, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chApostrophe, CURLOPT_USERAGENT, 'BushmansRiverKentonOnSeaCommunityApp/1.0 (Contact: webmaster@onseanews.co.za)');
            curl_setopt($chApostrophe, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($chApostrophe, CURLOPT_TIMEOUT, 10);
            curl_setopt($chApostrophe, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Accept-Language: en-US,en;q=0.9'
            ]);
            $responseApostrophe = curl_exec($chApostrophe);
            $httpCodeApostrophe = curl_getinfo($chApostrophe, CURLINFO_HTTP_CODE);
            curl_close($chApostrophe);
            
            if ($httpCodeApostrophe === 200 && $responseApostrophe !== false) {
                $dataApostrophe = json_decode($responseApostrophe, true);
                if (is_array($dataApostrophe) && count($dataApostrophe) > 0) {
                    $data = $dataApostrophe;
                }
            }
        }
    }
    
        // If still no results, try without country code restriction
        if (empty($data) || !is_array($data) || count($data) === 0) {
            $url2 = "https://nominatim.openstreetmap.org/search?q=" . urlencode($query . ', South Africa') . "&format=json&limit=10&addressdetails=1&dedupe=1";
        
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, $url2);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_USERAGENT, 'BushmansRiverKentonOnSeaCommunityApp/1.0');
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
            $response2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            
            if ($httpCode2 === 200 && $response2 !== false) {
                $decodedData = json_decode($response2, true);
                if (is_array($decodedData) && count($decodedData) > 0) {
                    $data = $decodedData;
                }
            }
            
            // If still no results, try just the query without any context
            if (empty($data) || !is_array($data) || count($data) === 0) {
                $url3 = "https://nominatim.openstreetmap.org/search?q=" . urlencode($query) . "&format=json&limit=10&addressdetails=1&dedupe=1";
                
                $ch3 = curl_init();
                curl_setopt($ch3, CURLOPT_URL, $url3);
                curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch3, CURLOPT_USERAGENT, 'BushmansRiverKentonOnSeaCommunityApp/1.0');
                curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch3, CURLOPT_TIMEOUT, 10);
                $response3 = curl_exec($ch3);
                $httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
                curl_close($ch3);
                
                if ($httpCode3 === 200 && $response3 !== false) {
                    $decodedData = json_decode($response3, true);
                    if (is_array($decodedData) && count($decodedData) > 0) {
                        $data = $decodedData;
                    }
                }
            }
        }
    }

    // Ensure $data is an array even if all strategies failed
    if (!is_array($data)) {
        $data = [];
    }

/**
 * Address term synonyms - maps common variations to a standard form
 */
function getAddressSynonyms() {
    return [
        'street' => ['st', 'str', 'street'],
        'road' => ['rd', 'road'],
        'avenue' => ['ave', 'av', 'avenue'],
        'drive' => ['dr', 'drive'],
        'lane' => ['ln', 'lane'],
        'court' => ['ct', 'court'],
        'place' => ['pl', 'place'],
        'boulevard' => ['blvd', 'boulevard'],
        'circle' => ['cir', 'circle'],
        'crescent' => ['cres', 'crescent'],
        'close' => ['cl', 'close'],
        'way' => ['way'],
        'terrace' => ['ter', 'terrace'],
        'park' => ['park'],
        'gardens' => ['gdn', 'gardens'],
        'square' => ['sq', 'square']
    ];
}

/**
 * Check if two words are synonyms (e.g., "street" and "st")
 */
function areSynonyms($word1, $word2) {
    $synonyms = getAddressSynonyms();
    $word1 = strtolower(trim($word1));
    $word2 = strtolower(trim($word2));
    
    foreach ($synonyms as $standard => $variations) {
        $inFirst = in_array($word1, $variations) || $word1 === $standard;
        $inSecond = in_array($word2, $variations) || $word2 === $standard;
        if ($inFirst && $inSecond) {
            return true;
        }
    }
    return false;
}

/**
 * Normalize text by removing apostrophes and converting to lowercase
 * This helps match "bushman's" with "bushmans"
 */
function normalizeText($text) {
    $text = strtolower(trim($text));
    // Remove apostrophes and convert to lowercase
    $text = str_replace(["'", "'", "`"], '', $text);
    return $text;
}

/**
 * Calculate fuzzy match score between query and text
 * Returns a score from 0-100, higher is better match
 */
function calculateFuzzyScore($query, $text) {
    $originalQuery = strtolower(trim($query));
    $originalText = strtolower(trim($text));
    
    if (empty($originalQuery) || empty($originalText)) {
        return 0;
    }
    
    // Exact match gets highest score
    if ($originalText === $originalQuery) {
        return 100;
    }
    
    // Normalized versions (without apostrophes) for fuzzy matching
    $normalizedQuery = normalizeText($query);
    $normalizedText = normalizeText($text);
    
    // Check normalized exact match (handles apostrophe differences)
    if ($normalizedText === $normalizedQuery) {
        return 95; // Slightly lower than true exact match
    }
    
    // Check if query is contained in text (substring match)
    if (strpos($originalText, $originalQuery) !== false) {
        $position = strpos($originalText, $originalQuery);
        // Earlier in the string = higher score
        $score = 90 - ($position * 2);
        return max(70, $score);
    }
    
    // Check normalized substring match
    if (strpos($normalizedText, $normalizedQuery) !== false) {
        $position = strpos($normalizedText, $normalizedQuery);
        $score = 85 - ($position * 2);
        return max(65, $score);
    }
    
    // Check if query words appear in text
    $queryWords = preg_split('/\s+/', $originalQuery);
    $textWords = preg_split('/\s+/', $originalText);
    $matchedWords = 0;
    $exactWordMatches = 0;
    $synonymMatches = 0;
    
    foreach ($queryWords as $qWord) {
        $normalizedQWord = normalizeText($qWord);
        $wordMatched = false;
        
        foreach ($textWords as $tWord) {
            $normalizedTWord = normalizeText($tWord);
            
            // Exact word match (highest priority)
            if ($tWord === $qWord) {
                $exactWordMatches++;
                $matchedWords++;
                $wordMatched = true;
                break;
            }
            
            // Normalized word match (handles apostrophes)
            if ($normalizedTWord === $normalizedQWord) {
                $matchedWords++;
                $wordMatched = true;
                break;
            }
            
            // Address term abbreviation match (e.g., "street" matches "st", but NOT "road")
            // Note: "street" and "road" are in different arrays, so they won't match
            if (areSynonyms($qWord, $tWord)) {
                $synonymMatches++;
                $matchedWords++;
                $wordMatched = true;
                break;
            }
            
            // Substring word match
            if (strpos($tWord, $qWord) !== false || strpos($qWord, $tWord) !== false) {
                $matchedWords++;
                $wordMatched = true;
                break;
            }
            
            // Normalized substring word match
            if (strpos($normalizedTWord, $normalizedQWord) !== false || strpos($normalizedQWord, $normalizedTWord) !== false) {
                $matchedWords++;
                $wordMatched = true;
                break;
            }
        }
    }
    
    if ($matchedWords > 0) {
        // Higher score if more words match exactly
        $exactBonus = ($exactWordMatches / count($queryWords)) * 15;
        // Synonym matches get good score but lower than exact
        $synonymBonus = ($synonymMatches / count($queryWords)) * 10;
        $wordScore = ($matchedWords / count($queryWords)) * 60 + $exactBonus + $synonymBonus;
        return $wordScore;
    }
    
    // Calculate Levenshtein distance on normalized text
    $maxLen = max(strlen($normalizedQuery), strlen($normalizedText));
    if ($maxLen === 0) return 0;
    
    $distance = levenshtein($normalizedQuery, $normalizedText);
    $similarity = (1 - ($distance / $maxLen)) * 50;
    
    return max(0, $similarity);
}

$suggestions = [];
if (!empty($data) && is_array($data)) {
    foreach ($data as $result) {
        if (isset($result['display_name'])) {
            $address = $result['address'] ?? [];
            
            // Parse address components
            $streetNumber = $address['house_number'] ?? $address['house'] ?? '';
            $streetName = $address['road'] ?? $address['street'] ?? '';
            $suburb = $address['suburb'] ?? $address['neighbourhood'] ?? $address['village'] ?? '';
            $town = $address['town'] ?? $address['city'] ?? $address['municipality'] ?? '';
            
            // If town is still empty, try other fields
            if (empty($town)) {
                $town = $address['county'] ?? '';
            }
            
            // Only add if it's in South Africa (check country code)
            $country = $address['country_code'] ?? '';
            if (strtolower($country) === 'za' || empty($country)) {
                // Calculate fuzzy match score
                $displayName = $result['display_name'];
                $searchText = $displayName . ' ' . $streetName . ' ' . $suburb . ' ' . $town;
                $score = calculateFuzzyScore($query, $searchText);
                
                $suggestions[] = [
                    'display_name' => $displayName,
                    'street_number' => $streetNumber,
                    'street_name' => $streetName,
                    'suburb' => $suburb,
                    'town' => $town,
                    'lat' => isset($result['lat']) ? (float)$result['lat'] : null,
                    'lon' => isset($result['lon']) ? (float)$result['lon'] : null,
                    'relevance_score' => $score
                ];
            }
            }
        }
    }

    // Sort suggestions by relevance score (highest first)
    usort($suggestions, function($a, $b) {
        return ($b['relevance_score'] ?? 0) <=> ($a['relevance_score'] ?? 0);
    });

    // Include debug info in development (remove in production)
    $debug = [
        'query' => $query,
        'url_used' => $url,
        'results_count' => count($suggestions),
        'raw_results_count' => is_array($data) ? count($data) : 0
    ];

    // Clear any output buffer and send JSON
    ob_clean();
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions,
        'debug' => $debug
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


