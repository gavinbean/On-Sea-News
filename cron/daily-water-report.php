<?php
/**
 * Daily Water Availability Report
 * 
 * This script should be run daily via cron job or scheduled task
 * Example cron: 0 23 * * * /usr/bin/php /path/to/cron/daily-water-report.php
 * (Runs at 11 PM daily)
 * 
 * IMPORTANT: This script MUST be run via PHP CLI, not via web browser.
 * Do NOT use wget/curl to call this script via HTTP.
 */

// Ensure logs directory exists and set error_log early so all logs go to the same place
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
$errorLogPath = $logsDir . '/cron-error.log';
if (is_dir($logsDir) && is_writable($logsDir)) {
    ini_set('error_log', $errorLogPath);
}

// Only run the cron job if this file is executed directly (not included)
// This allows the functions to be used by other files without executing the cron job
$isCronExecution = false;
$isCLI = (php_sapi_name() === 'cli');

// Log diagnostic info for debugging
$sapiName = php_sapi_name();
$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? 'NOT_SET';
$requestUri = $_SERVER['REQUEST_URI'] ?? 'NOT_SET';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Try to log to a file we know exists (use error_log which will go to configured location)
error_log("Daily water report: Script accessed - SAPI: $sapiName, Script: $scriptFilename, URI: $requestUri, Referer: " . ($referer ?: 'NONE') . ", UserAgent: " . ($userAgent ?: 'NONE'));

if ($isCLI) {
    // Running from command line - always execute
    $isCronExecution = true;
    error_log("Daily water report: CLI mode detected - execution allowed");
} else {
    // Not CLI - could be web-based cron (cgi-fcgi, apache2handler, etc.)
    // Check if this file is the one being executed (not included)
    $scriptFile = null;
    $thisFile = realpath(__FILE__);
    
    // Try multiple ways to detect the script being executed
    if (isset($_SERVER['SCRIPT_FILENAME'])) {
        $scriptFile = realpath($_SERVER['SCRIPT_FILENAME']);
    } elseif (isset($_SERVER['PHP_SELF']) && !empty($_SERVER['PHP_SELF'])) {
        // Fallback: try to construct path from PHP_SELF
        $phpSelf = $_SERVER['PHP_SELF'];
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot && file_exists($docRoot . $phpSelf)) {
            $scriptFile = realpath($docRoot . $phpSelf);
        }
    } elseif (isset($_SERVER['SCRIPT_NAME'])) {
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot && file_exists($docRoot . $scriptName)) {
            $scriptFile = realpath($docRoot . $scriptName);
        }
    }
    
    // If we can't determine the script file, check if it's likely a cron job
    // (cgi-fcgi with no referer/user agent is typically a cron wrapper)
    if (!$scriptFile) {
        $hasReferer = !empty($referer);
        $hasUserAgent = !empty($userAgent);
        $isCgiFcgi = ($sapiName === 'cgi-fcgi');
        
        // Allow if: cgi-fcgi mode with no referer and no user agent (typical cron wrapper)
        if ($isCgiFcgi && !$hasReferer && !$hasUserAgent) {
            $isCronExecution = true;
            error_log("Daily water report: cgi-fcgi mode with no referer/user agent - execution allowed (likely cron job)");
        } else {
            error_log("Daily water report: Cannot determine script file and conditions not met for auto-execution");
        }
    } elseif ($scriptFile && $thisFile && $scriptFile === $thisFile) {
        // This is the script being executed directly
        // On shared hosting (like xneelo), cron jobs may run via web wrapper
        $hasReferer = !empty($referer);
        $hasCronKey = isset($_GET['cron_key']) && $_GET['cron_key'] === 'daily_water_report_2024';
        
        // Allow execution if:
        // 1. No referer (direct access = cron job/web wrapper)
        // 2. Has special cron key parameter
        // Block only if it has a referer (browser navigation)
        if (!$hasReferer || $hasCronKey) {
            $isCronExecution = true;
            error_log("Daily water report: Web mode - execution allowed (script matches, no referer or has cron key)");
        } else {
            // Browser access - block it
            error_log("Daily water report: Web mode - execution BLOCKED (has referer: $referer)");
            header('Content-Type: text/plain');
            http_response_code(403);
            die("ERROR: This script cannot be accessed via web browser for security reasons.\n" .
                "This script is designed to run as a cron job only.\n");
        }
    } else {
        error_log("Daily water report: Script file mismatch - not executing (script: " . ($scriptFile ?: 'NULL') . ", this: $thisFile)");
    }
}

if ($isCronExecution) {
    
    // Set error reporting for cron job
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    // Ensure logs directory exists
    $logsDir = __DIR__ . '/../logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    
    // Set explicit error log path (fallback to default if directory creation fails)
    $errorLogPath = $logsDir . '/cron-error.log';
    if (is_dir($logsDir) && is_writable($logsDir)) {
        ini_set('error_log', $errorLogPath);
    }
    // If we can't write to custom log, PHP will use default error log

    // Set time limit for long-running script
    set_time_limit(300); // 5 minutes
    
    // Prevent any output buffering issues
    // In both modes, write to error_log and also echo so cron consoles capture it
    $outputFunction = function($msg) {
        error_log($msg);
        echo $msg;
    };
    
    // Log that cron job started
    $startMsg = "=== Daily Water Report Cron Job Started ===\n";
    $startMsg .= "PHP SAPI: " . php_sapi_name() . "\n";
    $startMsg .= "Script: " . __FILE__ . "\n";
    $startMsg .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $outputFunction($startMsg);
}

// Load required files with error handling
if (!file_exists(__DIR__ . '/../includes/functions.php')) {
    error_log("Fatal: functions.php not found in " . __DIR__);
    if ($isCronExecution) {
        die("Fatal error: Required files not found. Check error logs.\n");
    }
    throw new Exception("Required file functions.php not found");
}
require_once __DIR__ . '/../includes/functions.php';

if (!file_exists(__DIR__ . '/../includes/email.php')) {
    error_log("Fatal: email.php not found in " . __DIR__);
    if ($isCronExecution) {
        die("Fatal error: Required files not found. Check error logs.\n");
    }
    throw new Exception("Required file email.php not found");
}
require_once __DIR__ . '/../includes/email.php';

if (!file_exists(__DIR__ . '/../config/database.php')) {
    error_log("Fatal: database.php not found in " . __DIR__);
    if ($isCronExecution) {
        die("Fatal error: Required files not found. Check error logs.\n");
    }
    throw new Exception("Required file database.php not found");
}
require_once __DIR__ . '/../config/database.php';

// Only execute cron job logic if running directly
if ($isCronExecution) {
    error_log("Daily water report: Starting execution...");
    
    // Get today's date
    $reportDate = date('Y-m-d');
    error_log("Daily water report: Report date = $reportDate");

    // Get database connection
    error_log("Daily water report: Connecting to database...");
    try {
        $db = getDB();
        error_log("Daily water report: Database connection successful");
    } catch (Exception $e) {
        error_log("Daily water report: Database connection failed: " . $e->getMessage());
        die("Fatal error: Database connection failed. Check error logs.\n");
    }

    // Get all active email recipients
    error_log("Daily water report: Fetching email recipients...");
    try {
        $stmt = $db->query("
            SELECT email_address, name
            FROM " . TABLE_PREFIX . "daily_report_emails
            WHERE is_active = 1
        ");
        $recipients = $stmt->fetchAll();
        error_log("Daily water report: Found " . count($recipients) . " active recipients");
    } catch (Exception $e) {
        error_log("Daily water report: Failed to fetch recipients: " . $e->getMessage());
        die("Fatal error: Failed to fetch recipients. Check error logs.\n");
    }

    if (empty($recipients)) {
        error_log("Daily water report: No active email recipients found for date $reportDate");
        exit(0);
    }

    // Get all water availability data for today
    // Use stored coordinates from water_availability table, not current user profile coordinates
    // Include records with NULL user_id (imported data)
    error_log("Daily water report: Fetching water availability data for $reportDate...");
    try {
        $stmt = $db->prepare("
            SELECT 
                w.report_date,
                w.has_water,
                w.latitude,
                w.longitude,
                w.user_id,
                u.street_number,
                u.street_name,
                u.suburb,
                u.town,
                u.name,
                u.surname
            FROM " . TABLE_PREFIX . "water_availability w
            LEFT JOIN " . TABLE_PREFIX . "users u ON w.user_id = u.user_id
            WHERE w.report_date = ?
            AND w.latitude IS NOT NULL
            AND w.longitude IS NOT NULL
            ORDER BY COALESCE(u.town, ''), COALESCE(u.street_name, ''), COALESCE(u.street_number, '')
        ");
        $stmt->execute([$reportDate]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Daily water report: Found " . count($reports) . " water availability records");
    } catch (Exception $e) {
        error_log("Daily water report: Failed to fetch water availability data: " . $e->getMessage());
        die("Fatal error: Failed to fetch water availability data. Check error logs.\n");
    }

    if (empty($reports)) {
        error_log("Daily water report: No data found for date $reportDate");
        // Still send email to notify that no reports were received
        $reports = [];
    }

    // Build address strings and prepare data
    $dataPoints = [];
    $tableRows = [];

    foreach ($reports as $report) {
    $addressParts = [];
    if (!empty($report['street_number'])) $addressParts[] = $report['street_number'];
    if (!empty($report['street_name'])) $addressParts[] = $report['street_name'];
    if (!empty($report['suburb'])) $addressParts[] = $report['suburb'];
    if (!empty($report['town'])) $addressParts[] = $report['town'];
    $address = implode(', ', $addressParts);
    
    // For imported data (no user_id), show coordinates if no address components
    if (empty($address) && empty($report['user_id'])) {
        $address = sprintf('Location (%.6f, %.6f)', $report['latitude'], $report['longitude']);
    } elseif (empty($address)) {
        $address = 'Address not provided';
    }
    
    $dataPoints[] = [
        'lat' => (float)$report['latitude'],
        'lng' => (float)$report['longitude'],
        'has_water' => (int)$report['has_water'],
        'address' => $address
    ];
    
    $status = 'No';
    if ($report['has_water'] == 1) {
        $status = 'Yes';
    } elseif ($report['has_water'] == 2) {
        $status = 'Intermittent';
    }
    
    // Handle name for imported data (user_id is NULL)
    $name = 'Imported Data';
    if (!empty($report['user_id'])) {
        $name = trim(($report['name'] ?? '') . ' ' . ($report['surname'] ?? '')) ?: 'N/A';
    }
    
    $tableRows[] = [
        'address' => $address,
        'name' => $name,
        'has_water' => $status
    ];
    }

    // Generate map image as base64 embedded image
    error_log("Daily water report: Generating map with " . count($dataPoints) . " data points");
    try {
        $mapImageBase64 = generateMapImageBase64($dataPoints);
        error_log("Daily water report: Map image generated: " . (empty($mapImageBase64) ? "EMPTY" : strlen($mapImageBase64) . " chars"));
    } catch (Exception $e) {
        error_log("Daily water report: Failed to generate map image: " . $e->getMessage());
        $mapImageBase64 = '';
    }
    error_log("Daily water report: Table rows count: " . count($tableRows));
    error_log("Daily water report: Reports count: " . count($reports));

    // Generate email content
    error_log("Daily water report: Generating email content...");
    try {
        $emailSubject = 'Daily Water Availability Report - ' . date('F j, Y', strtotime($reportDate));
        $emailBody = generateDailyReportEmail($reportDate, $mapImageBase64, $tableRows, count($reports));
        error_log("Daily water report: Email body generated: " . strlen($emailBody) . " bytes");
    } catch (Exception $e) {
        error_log("Daily water report: Failed to generate email content: " . $e->getMessage());
        die("Fatal error: Failed to generate email content. Check error logs.\n");
    }

    // Send email to all recipients
    error_log("Daily water report: Starting to send emails to " . count($recipients) . " recipients...");
    $emailsSent = 0;
    $emailsFailed = 0;

    foreach ($recipients as $recipient) {
        $recipientEmail = $recipient['email_address'];
        $recipientName = $recipient['name'] ?: 'Recipient';
        
        error_log("Daily water report: Attempting to send email to $recipientEmail...");
        try {
            if (sendDailyReportEmail($recipientEmail, $recipientName, $emailSubject, $emailBody)) {
                $emailsSent++;
                error_log("Daily water report: SUCCESS - Email sent to $recipientEmail for date $reportDate");
            } else {
                $emailsFailed++;
                error_log("Daily water report: FAILED - Failed to send email to $recipientEmail for date $reportDate");
            }
        } catch (Exception $e) {
            $emailsFailed++;
            error_log("Daily water report: EXCEPTION - Error sending email to $recipientEmail: " . $e->getMessage());
        }
    }

    // Final summary
    $summary = "Daily water report: COMPLETED for $reportDate - Emails sent: $emailsSent, Failed: $emailsFailed, Recipients: " . count($recipients) . ", Reports: " . count($reports);
    error_log($summary);
    
    // Also output summary if in CLI mode
    if ($isCLI) {
        echo $summary . "\n";
    }
    
    error_log("=== Daily Water Report Cron Job Finished ===");
} else {
    // Script was included or blocked - log this for debugging
    error_log("Daily water report: Script loaded but NOT executing (isCronExecution=false, SAPI: " . php_sapi_name() . ")");
} // End of if ($isCronExecution) block

/**
 * Generate static map image using OpenStreetMap tiles and GD library
 * This creates the map image directly without relying on external services
 */
function generateMapImageWithGD($dataPoints) {
    $width = 800;
    $height = 600;
    $tileSize = 256;
    
    // Calculate bounds of data points
    if (empty($dataPoints)) {
        $minLat = -33.7 - 0.01;
        $maxLat = -33.7 + 0.01;
        $minLng = 26.7 - 0.01;
        $maxLng = 26.7 + 0.01;
        $zoom = 12;
    } else {
        $minLat = min(array_column($dataPoints, 'lat'));
        $maxLat = max(array_column($dataPoints, 'lat'));
        $minLng = min(array_column($dataPoints, 'lng'));
        $maxLng = max(array_column($dataPoints, 'lng'));
        
        // Add 10% padding to bounds
        $latPadding = ($maxLat - $minLat) * 0.1;
        $lngPadding = ($maxLng - $minLng) * 0.1;
        $minLat -= $latPadding;
        $maxLat += $latPadding;
        $minLng -= $lngPadding;
        $maxLng += $lngPadding;
        
        // Calculate zoom to fit bounds
        $zoom = calculateZoom($minLat, $maxLat, $minLng, $maxLng);
    }
    
    // Center on the bounds
    $centerLat = ($minLat + $maxLat) / 2;
    $centerLng = ($minLng + $maxLng) / 2;
    
    // Create image
    $image = imagecreatetruecolor($width, $height);
    
    // Allocate colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $green = imagecolorallocate($image, 76, 175, 80);   // Has water
    $red = imagecolorallocate($image, 211, 47, 47);      // No water
    $black = imagecolorallocate($image, 0, 0, 0);
    
    // Fill background
    imagefill($image, 0, 0, $white);
    
    // Function to convert lat/lng to tile coordinates
    $latToY = function($lat, $zoom) {
        $n = pow(2, $zoom);
        $latRad = deg2rad($lat);
        $y = (1 - log(tan($latRad) + 1/cos($latRad)) / M_PI) / 2 * $n;
        return $y;
    };
    
    $lngToX = function($lng, $zoom) {
        $n = pow(2, $zoom);
        $x = ($lng + 180) / 360 * $n;
        return $x;
    };
    
    // Get center tile coordinates
    $centerX = $lngToX($centerLng, $zoom);
    $centerY = $latToY($centerLat, $zoom);
    
    // Calculate bounds in tile coordinates
    $minTileX = $lngToX($minLng, $zoom);
    $maxTileX = $lngToX($maxLng, $zoom);
    $minTileY = $latToY($maxLat, $zoom); // Note: maxLat gives minTileY (Y increases southward)
    $maxTileY = $latToY($minLat, $zoom); // Note: minLat gives maxTileY
    
    // Calculate how many tiles we need to cover the bounds
    $tileSpanX = $maxTileX - $minTileX;
    $tileSpanY = $maxTileY - $minTileY;
    
    // Calculate starting tile to center the bounds in the image
    // We want the bounds to be centered, so calculate offset needed
    $tilesX = ceil($tileSpanX) + 4; // Extra tiles for padding
    $tilesY = ceil($tileSpanY) + 4;
    
    // Start from a tile that will center the bounds
    $startTileX = floor($minTileX) - 2;
    $startTileY = floor($minTileY) - 2;
    
    // Calculate bounds center in tile coordinates (this is what we're centering on)
    $boundsCenterTileX = ($minTileX + $maxTileX) / 2;
    $boundsCenterTileY = ($minTileY + $maxTileY) / 2;
    
    // Calculate offset to center bounds in image (used for both tiles and markers)
    $offsetX = ($width / 2) - ($boundsCenterTileX - $startTileX) * $tileSize;
    $offsetY = ($height / 2) - ($boundsCenterTileY - $startTileY) * $tileSize;
    
    // Download and composite OSM tiles
    $tileServer = 'https://tile.openstreetmap.org';
    $tilesLoaded = 0;
    
    error_log("Starting tile download for map generation (zoom: $zoom, tiles needed: " . ($tilesX * $tilesY) . ")");
    error_log("Bounds center: tileX=$boundsCenterTileX, tileY=$boundsCenterTileY, offsetX=$offsetX, offsetY=$offsetY");
    
    for ($ty = 0; $ty < $tilesY; $ty++) {
        for ($tx = 0; $tx < $tilesX; $tx++) {
            $tileX = $startTileX + $tx;
            $tileY = $startTileY + $ty;
            
            // Download tile with timeout and user agent
            $tileUrl = sprintf('%s/%d/%d/%d.png', $tileServer, $zoom, $tileX, $tileY);
            
            // Try using cURL first (more reliable than file_get_contents)
            $tileData = false;
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $tileUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_USERAGENT, 'On-Sea News Water Report Map Generator/1.0 (https://onseanews.co.za)');
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: image/png',
                    'Accept-Language: en-US,en;q=0.9',
                    'Connection: close'
                ]);
                $tileData = @curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($httpCode !== 200 || $tileData === false) {
                    if ($tilesLoaded == 0 && $tx == 0 && $ty == 0) {
                        error_log("cURL failed for tile $tileX/$tileY/$zoom: HTTP $httpCode" . ($curlError ? ", Error: $curlError" : ""));
                    }
                    $tileData = false;
                }
            }
            
            // Fallback to file_get_contents if cURL failed or not available
            if ($tileData === false) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'user_agent' => 'On-Sea News Water Report Map Generator/1.0 (https://onseanews.co.za)',
                        'method' => 'GET',
                        'header' => [
                            'Accept: image/png',
                            'Connection: close'
                        ]
                    ],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true
                    ]
                ]);
                
                $tileData = @file_get_contents($tileUrl, false, $context);
            }
            
            if ($tileData !== false && strlen($tileData) > 100) { // PNG files should be at least 100 bytes
                $tileImg = @imagecreatefromstring($tileData);
                if ($tileImg !== false) {
                    // Position tiles using the pre-calculated offset (centers bounds in image)
                    $destX = (int)(($tileX - $startTileX) * $tileSize + $offsetX);
                    $destY = (int)(($tileY - $startTileY) * $tileSize + $offsetY);
                    imagecopy($image, $tileImg, $destX, $destY, 0, 0, $tileSize, $tileSize);
                    imagedestroy($tileImg);
                    $tilesLoaded++;
                } else {
                    error_log("Failed to create image from tile data for tile $tileX/$tileY/$zoom (data length: " . strlen($tileData) . ")");
                }
            } else {
                // Only log first failure to reduce log spam
                if ($tilesLoaded == 0 && $tx == 0 && $ty == 0) {
                    $errorInfo = "Failed to download tile from $tileUrl";
                    if ($tileData !== false) {
                        $errorInfo .= " (received " . strlen($tileData) . " bytes, expected PNG)";
                    } else {
                        $errorInfo .= " (no data received)";
                    }
                    error_log($errorInfo);
                    error_log("This may be due to network restrictions, firewall blocking, or OpenStreetMap rate limiting");
                }
            }
            
            // Add small delay between tile requests to avoid rate limiting (only if we're actually downloading)
            if ($tilesLoaded > 0 && $tx < $tilesX - 1) {
                usleep(50000); // 50ms delay between successful downloads
            }
        }
    }
    
    error_log("Loaded $tilesLoaded tiles out of " . ($tilesX * $tilesY) . " needed");
    
    // If no tiles loaded, draw a simple background with gradient
    if ($tilesLoaded == 0) {
        error_log("Warning: No tiles loaded, using plain background with gradient");
        // Draw a gradient background (light blue to white)
        $lightBlue = imagecolorallocate($image, 230, 240, 250);
        $white = imagecolorallocate($image, 255, 255, 255);
        
        // Simple gradient effect
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = (int)(230 + (255 - 230) * $ratio);
            $g = (int)(240 + (255 - 240) * $ratio);
            $b = (int)(250 + (255 - 250) * $ratio);
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }
        
        // Add a subtle grid
        $gray = imagecolorallocate($image, 220, 220, 220);
        for ($i = 0; $i < $width; $i += 50) {
            imageline($image, $i, 0, $i, $height, $gray);
        }
        for ($i = 0; $i < $height; $i += 50) {
            imageline($image, 0, $i, $width, $i, $gray);
        }
        
        // Add text indicating map tiles unavailable
        $textColor = imagecolorallocate($image, 100, 100, 100);
        $fontSize = 5; // Built-in font size
        $text = "Map tiles unavailable - showing markers only";
        imagestring($image, $fontSize, 10, 10, $text, $textColor);
    }
    
    // Draw markers - use the same coordinate system as tiles
    // Calculate bounds center in tile coordinates (this is what we're centering on)
    $boundsCenterTileX = ($minTileX + $maxTileX) / 2;
    $boundsCenterTileY = ($minTileY + $maxTileY) / 2;
    
    // Calculate offset used for tile positioning (same as tiles use)
    $offsetX = ($width / 2) - ($boundsCenterTileX - $startTileX) * $tileSize;
    $offsetY = ($height / 2) - ($boundsCenterTileY - $startTileY) * $tileSize;
    
    $markersDrawn = 0;
    $markersSkipped = 0;
    
    error_log("Bounds: minLat=$minLat, maxLat=$maxLat, minLng=$minLng, maxLng=$maxLng");
    error_log("Bounds center: lat=$centerLat, lng=$centerLng, tileX=$boundsCenterTileX, tileY=$boundsCenterTileY");
    error_log("Tile positioning: startTileX=$startTileX, startTileY=$startTileY, offsetX=$offsetX, offsetY=$offsetY");
    
    foreach (array_slice($dataPoints, 0, 100) as $point) {
        // Convert lat/lng to tile coordinates
        $pointTileX = $lngToX($point['lng'], $zoom);
        $pointTileY = $latToY($point['lat'], $zoom);
        
        // Position marker using the EXACT same formula as tiles
        // Tiles are at: (tileX - startTileX) * tileSize + offsetX
        $markerX = (int)(($pointTileX - $startTileX) * $tileSize + $offsetX);
        $markerY = (int)(($pointTileY - $startTileY) * $tileSize + $offsetY);
        
        // Always draw markers, even if slightly outside bounds (they might be near edges)
        // Clamp to image bounds to ensure they're visible
        $markerX = max(0, min($width - 1, $markerX));
        $markerY = max(0, min($height - 1, $markerY));
        
        $color = $red; // Default to red
        if ($point['has_water'] == 1) {
            $color = $green;
        } elseif ($point['has_water'] == 2) {
            $color = imagecolorallocate($image, 243, 156, 18); // Orange #f39c12
        }
        
        // Draw solid circle marker (smaller, no white inner circle)
        imagefilledellipse($image, $markerX, $markerY, 6, 6, $color);
        $markersDrawn++;
    }
    
    error_log("Map markers: $markersDrawn drawn, $markersSkipped skipped (out of bounds)");
    
    // Output image to string
    ob_start();
    imagepng($image);
    $imageData = ob_get_contents();
    ob_end_clean();
    imagedestroy($image);
    
    return $imageData;
}

/**
 * Generate map image and return as base64 for email embedding
 * Base64 embedded images work well in Gmail
 * Uses GD library to generate map from OpenStreetMap tiles.
 */
function generateMapImageBase64($dataPoints) {
    try {
        // Check if GD library is available
        if (!function_exists('imagecreatetruecolor')) {
            error_log("GD library not available for map generation");
            return '';
        }
        
        error_log("Generating map image using GD library with " . count($dataPoints) . " data points");
        
        // Generate map image using GD library
        $imageData = generateMapImageWithGD($dataPoints);
        
        if ($imageData === false || empty($imageData)) {
            error_log("Failed to generate map image with GD library");
            return '';
        }
        
        // Validate that the response is an image
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false) {
            error_log("Generated data is not a valid image");
            return '';
        }
        
        // Convert to base64
        $base64 = base64_encode($imageData);
        error_log("Successfully generated base64 map image (" . strlen($base64) . " chars, " . strlen($imageData) . " bytes)");
        return 'data:image/png;base64,' . $base64;
        
    } catch (Exception $e) {
        error_log("Error generating map image: " . $e->getMessage());
        return '';
    }
}

/**
 * Calculate appropriate zoom level based on bounds
 */
function calculateZoom($minLat, $maxLat, $minLng, $maxLng) {
    $latDiff = abs($maxLat - $minLat);
    $lngDiff = abs($maxLng - $minLng);
    $maxDiff = max($latDiff, $lngDiff);
    
    if ($maxDiff > 1.0) return 8;
    if ($maxDiff > 0.5) return 9;
    if ($maxDiff > 0.25) return 10;
    if ($maxDiff > 0.1) return 11;
    if ($maxDiff > 0.05) return 12;
    if ($maxDiff > 0.025) return 13;
    if ($maxDiff > 0.01) return 14;
    return 15;
}

/**
 * Generate email HTML content
 */
function generateDailyReportEmail($reportDate, $mapImageBase64, $tableRows, $totalReports) {
    $dateFormatted = date('F j, Y', strtotime($reportDate));
    
    error_log("generateDailyReportEmail: reportDate=$reportDate, mapImageBase64=" . (empty($mapImageBase64) ? "EMPTY" : strlen($mapImageBase64) . " chars") . ", tableRows=" . count($tableRows) . ", totalReports=$totalReports");
    
    // Generate map image HTML with base64 embedded image
    if (!empty($mapImageBase64)) {
        // Ensure the base64 string is properly formatted
        $mapImageBase64 = trim($mapImageBase64);
        if (strpos($mapImageBase64, 'data:image') !== 0) {
            // If it doesn't start with data URI prefix, add it
            $mapImageBase64 = 'data:image/png;base64,' . $mapImageBase64;
        }
        // Don't escape the data URI - it needs to be raw for email clients
        $mapImageHtml = sprintf(
            '<img src="%s" alt="Water Availability Map" class="map-image" style="max-width: 800px; width: 100%%; height: auto; display: block; margin: 0 auto; border: 2px solid #ddd; border-radius: 8px;" />',
            $mapImageBase64
        );
        error_log("generateDailyReportEmail: Map image HTML generated (length: " . strlen($mapImageHtml) . " chars)");
    } else {
        $mapImageHtml = '<p style="color: #d32f2f; padding: 20px; text-align: center; background-color: #ffebee; border-radius: 8px;">Map image unavailable. Unable to generate map from OpenStreetMap.</p>';
        error_log("generateDailyReportEmail: WARNING - Map image is empty, using fallback message");
    }
    
    $tableHtml = '';
    if (empty($tableRows)) {
        $tableHtml = '<tr><td colspan="3" style="text-align: center; padding: 1rem; color: #666;">No water availability reports received for this date.</td></tr>';
        error_log("generateDailyReportEmail: WARNING - Table rows is empty, showing 'no reports' message");
    } else {
        error_log("generateDailyReportEmail: Generating table HTML for " . count($tableRows) . " rows");
        foreach ($tableRows as $row) {
            $statusColor = '#d32f2f'; // Default red
            if ($row['has_water'] === 'Yes') {
                $statusColor = '#4caf50'; // Green
            } elseif ($row['has_water'] === 'Intermittent') {
                $statusColor = '#f39c12'; // Orange
            }
            $tableHtml .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="color: %s; font-weight: 600;">%s</td></tr>',
                h($row['address']),
                h($row['name'] ?: 'N/A'),
                $statusColor,
                h($row['has_water'])
            );
        }
        error_log("generateDailyReportEmail: Table HTML generated (length: " . strlen($tableHtml) . " chars)");
    }
    
    $emailHtml = sprintf('
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 900px; margin: 0 auto; padding: 20px; }
            .header { background-color: #2c5f8d; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background-color: #f5f5f5; }
            .map-section { margin: 20px 0; text-align: center; }
            .map-image { max-width: 100%%; height: auto; border: 2px solid #ddd; border-radius: 8px; }
            .table-section { margin: 20px 0; }
            .data-table { width: 100%%; border-collapse: collapse; background-color: white; border-radius: 8px; overflow: hidden; }
            .data-table th { background-color: #2c5f8d; color: white; padding: 12px; text-align: left; font-weight: 600; }
            .data-table td { padding: 10px 12px; border-bottom: 1px solid #ddd; }
            .data-table tr:last-child td { border-bottom: none; }
            .data-table tr:hover { background-color: #f8f9fa; }
            .summary { background-color: #e8f4f8; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Daily Water Availability Report</h1>
                <p>%s</p>
            </div>
            <div class="content">
                <div class="summary">
                    <p><strong>Total Reports:</strong> %d</p>
                    <p><strong>Report Date:</strong> %s</p>
                </div>
                
                <div class="map-section">
                    <h2>Water Availability Map</h2>
                    %s
                    <p style="font-size: 0.9rem; color: #666; margin-top: 10px; text-align: center;">
                        <span style="color: #4caf50;">●</span> Has Water | 
                        <span style="color: #d32f2f;">●</span> No Water |
                        <span style="color: #f39c12;">●</span> Intermittent
                    </p>
                </div>
                
                <div class="table-section">
                    <h2>Address Details</h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Address</th>
                                <th>Name</th>
                                <th>Water Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            %s
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="footer">
                <p>&copy; %s %s. All rights reserved.</p>
                <p>This is an automated daily report.</p>
            </div>
        </div>
    </body>
    </html>
    ',
        $dateFormatted,
        $totalReports,
        $dateFormatted,
        $mapImageHtml, // Map image HTML (with fallback message if unavailable) - already contains full img tag
        $tableHtml,
        date('Y'),
        h(SITE_NAME)
    );
    
    error_log("generateDailyReportEmail: Final email HTML generated (length: " . strlen($emailHtml) . " chars)");
    return $emailHtml;
}

/**
 * Send daily report email
 */
function sendDailyReportEmail($to, $name, $subject, $body) {
    // Use sendEmail function (supports optional parameters but we'll use defaults)
    return sendEmail($to, $subject, $body);
}

