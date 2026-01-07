<?php
/**
 * Daily Water Availability Report
 * 
 * This script should be run daily via cron job or scheduled task
 * Example cron: 0 23 * * * /usr/bin/php /path/to/cron/daily-water-report.php
 * (Runs at 11 PM daily)
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../config/database.php';

// Get today's date
$reportDate = date('Y-m-d');

// Get all active email recipients
$db = getDB();
$stmt = $db->query("
    SELECT email_address, name
    FROM " . TABLE_PREFIX . "daily_report_emails
    WHERE is_active = 1
");
$recipients = $stmt->fetchAll();

if (empty($recipients)) {
    error_log("Daily water report: No active email recipients found for date $reportDate");
    exit(0);
}

// Get all water availability data for today
// Use stored coordinates from water_availability table, not current user profile coordinates
$stmt = $db->prepare("
    SELECT 
        w.report_date,
        w.has_water,
        w.latitude,
        w.longitude,
        u.street_number,
        u.street_name,
        u.suburb,
        u.town,
        u.name,
        u.surname
    FROM " . TABLE_PREFIX . "water_availability w
    JOIN " . TABLE_PREFIX . "users u ON w.user_id = u.user_id
    WHERE w.report_date = ?
    AND w.latitude IS NOT NULL
    AND w.longitude IS NOT NULL
    ORDER BY u.town, u.street_name, u.street_number
");
$stmt->execute([$reportDate]);
$reports = $stmt->fetchAll();

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
    $address = implode(', ', $addressParts) ?: 'Address not provided';
    
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
    
    $tableRows[] = [
        'address' => $address,
        'name' => trim(($report['name'] ?? '') . ' ' . ($report['surname'] ?? '')),
        'has_water' => $status
    ];
}

// Generate map image as base64 embedded image
$mapImageBase64 = generateMapImageBase64($dataPoints);

// Generate email content
$emailSubject = 'Daily Water Availability Report - ' . date('F j, Y', strtotime($reportDate));
$emailBody = generateDailyReportEmail($reportDate, $mapImageBase64, $tableRows, count($reports));

// Send email to all recipients
$emailsSent = 0;
$emailsFailed = 0;

foreach ($recipients as $recipient) {
    $recipientEmail = $recipient['email_address'];
    $recipientName = $recipient['name'] ?: 'Recipient';
    
    if (sendDailyReportEmail($recipientEmail, $recipientName, $emailSubject, $emailBody)) {
        $emailsSent++;
        error_log("Daily water report: Email sent to $recipientEmail for date $reportDate");
    } else {
        $emailsFailed++;
        error_log("Daily water report: Failed to send email to $recipientEmail for date $reportDate");
    }
}

error_log("Daily water report completed for $reportDate: $emailsSent sent, $emailsFailed failed");

/**
 * Generate static map image using OpenStreetMap tiles and GD library
 * This creates the map image directly without relying on external services
 */
function generateMapImageWithGD($dataPoints) {
    if (empty($dataPoints)) {
        $centerLat = -33.7;
        $centerLng = 26.7;
        $zoom = 12;
    } else {
        $minLat = min(array_column($dataPoints, 'lat'));
        $maxLat = max(array_column($dataPoints, 'lat'));
        $minLng = min(array_column($dataPoints, 'lng'));
        $maxLng = max(array_column($dataPoints, 'lng'));
        
        $centerLat = ($minLat + $maxLat) / 2;
        $centerLng = ($minLng + $maxLng) / 2;
        $zoom = calculateZoom($minLat, $maxLat, $minLng, $maxLng);
    }
    
    $width = 800;
    $height = 600;
    
    // Create image
    $image = imagecreatetruecolor($width, $height);
    
    // Allocate colors
    $white = imagecolorallocate($image, 255, 255, 255);
    $green = imagecolorallocate($image, 76, 175, 80);   // Has water
    $red = imagecolorallocate($image, 211, 47, 47);      // No water
    $black = imagecolorallocate($image, 0, 0, 0);
    
    // Fill background
    imagefill($image, 0, 0, $white);
    
    // Function to convert lat/lng to pixel coordinates
    $latToY = function($lat, $zoom) use ($height) {
        $n = pow(2, $zoom);
        $latRad = deg2rad($lat);
        $y = (1 - log(tan($latRad) + 1/cos($latRad)) / M_PI) / 2 * $n;
        return $y;
    };
    
    $lngToX = function($lng, $zoom) use ($width) {
        $n = pow(2, $zoom);
        $x = ($lng + 180) / 360 * $n;
        return $x;
    };
    
    // Get center tile coordinates
    $centerX = $lngToX($centerLng, $zoom);
    $centerY = $latToY($centerLat, $zoom);
    
    // Calculate tile bounds
    $tileSize = 256;
    $tilesX = ceil($width / $tileSize) + 2;
    $tilesY = ceil($height / $tileSize) + 2;
    
    $startTileX = floor($centerX - ($width / 2) / $tileSize);
    $startTileY = floor($centerY - ($height / 2) / $tileSize);
    
    // Download and composite OSM tiles
    $tileServer = 'https://tile.openstreetmap.org';
    $tilesLoaded = 0;
    
    for ($ty = 0; $ty < $tilesY; $ty++) {
        for ($tx = 0; $tx < $tilesX; $tx++) {
            $tileX = $startTileX + $tx;
            $tileY = $startTileY + $ty;
            
            // Download tile
            $tileUrl = sprintf('%s/%d/%d/%d.png', $tileServer, $zoom, $tileX, $tileY);
            $tileData = @file_get_contents($tileUrl);
            
            if ($tileData !== false) {
                $tileImg = @imagecreatefromstring($tileData);
                if ($tileImg !== false) {
                    $destX = $tx * $tileSize - ($centerX - floor($centerX)) * $tileSize + ($width / 2) - ($tileSize / 2);
                    $destY = $ty * $tileSize - ($centerY - floor($centerY)) * $tileSize + ($height / 2) - ($tileSize / 2);
                    imagecopy($image, $tileImg, $destX, $destY, 0, 0, $tileSize, $tileSize);
                    imagedestroy($tileImg);
                    $tilesLoaded++;
                }
            }
        }
    }
    
    // Draw markers
    foreach (array_slice($dataPoints, 0, 100) as $point) {
        $markerX = $lngToX($point['lng'], $zoom) - $centerX + ($width / 2);
        $markerY = $latToY($point['lat'], $zoom) - $centerY + ($height / 2);
        
        if ($markerX >= 0 && $markerX < $width && $markerY >= 0 && $markerY < $height) {
            $color = $red; // Default to red
            if ($point['has_water'] == 1) {
                $color = $green;
            } elseif ($point['has_water'] == 2) {
                $color = imagecolorallocate($image, 243, 156, 18); // Orange #f39c12
            }
            // Draw circle marker
            imagefilledellipse($image, $markerX, $markerY, 12, 12, $color);
            imagefilledellipse($image, $markerX, $markerY, 8, 8, $white);
        }
    }
    
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
    
    // Generate map image HTML with base64 embedded image
    if (!empty($mapImageBase64)) {
        $mapImageHtml = sprintf(
            '<img src="%s" alt="Water Availability Map" class="map-image" style="max-width: 800px; width: 100%%; display: block; margin: 0 auto;" />',
            $mapImageBase64 // Don't escape base64 data URI
        );
    } else {
        $mapImageHtml = '<p style="color: #d32f2f; padding: 20px; text-align: center; background-color: #ffebee; border-radius: 8px;">Map image unavailable. Unable to generate map from OpenStreetMap.</p>';
    }
    
    $tableHtml = '';
    if (empty($tableRows)) {
        $tableHtml = '<tr><td colspan="3" style="text-align: center; padding: 1rem; color: #666;">No water availability reports received for this date.</td></tr>';
    } else {
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
    }
    
    return sprintf('
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
                    <img src="%s" alt="Water Availability Map" class="map-image" style="max-width: 800px; width: 100%%; display: block; margin: 0 auto;" />
                    <p style="font-size: 0.9rem; color: #666; margin-top: 10px; text-align: center;">
                        <span style="color: #4caf50;">●</span> Has Water | 
                        <span style="color: #d32f2f;">●</span> No Water
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
        $mapImageHtml, // Map image HTML (with fallback message if unavailable)
        $tableHtml,
        date('Y'),
        h(SITE_NAME)
    );
}

/**
 * Send daily report email
 */
function sendDailyReportEmail($to, $name, $subject, $body) {
    // Use sendEmail function (supports optional parameters but we'll use defaults)
    return sendEmail($to, $subject, $body);
}

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

