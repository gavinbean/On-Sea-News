<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

// Helper functions for generating report (must be defined before use)
/**
 * Generate static map image using OpenStreetMap tiles and GD library
 * This creates the map image directly without relying on external services
 */
function generateMapImageWithGD($dataPoints) {
    if (empty($dataPoints)) {
        $centerLat = -33.7;
        $centerLng = 26.7;
        $zoom = 13;
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
    $tilesFailed = 0;
    
    // Calculate pixel coordinates of center point
    $centerPixelX = $lngToX($centerLng, $zoom) * $tileSize;
    $centerPixelY = $latToY($centerLat, $zoom) * $tileSize;
    
    // Calculate offset to center the map
    $offsetX = ($width / 2) - $centerPixelX;
    $offsetY = ($height / 2) - $centerPixelY;
    
    // Function to download tile using cURL (more reliable than file_get_contents)
    $downloadTile = function($url) {
        if (!function_exists('curl_init')) {
            // Fallback to file_get_contents if cURL not available
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: On-Sea News Daily Report/1.0\r\n",
                    'timeout' => 10,
                    'follow_location' => true
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);
            return @file_get_contents($url, false, $context);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'On-Sea News Daily Report/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($data === false || $httpCode !== 200) {
            error_log("Failed to download tile: $url - HTTP $httpCode - Error: $error");
            return false;
        }
        
        return $data;
    };
    
    for ($ty = 0; $ty < $tilesY; $ty++) {
        for ($tx = 0; $tx < $tilesX; $tx++) {
            $tileX = $startTileX + $tx;
            $tileY = $startTileY + $ty;
            
            // Validate tile coordinates
            $maxTile = pow(2, $zoom);
            if ($tileX < 0 || $tileX >= $maxTile || $tileY < 0 || $tileY >= $maxTile) {
                continue;
            }
            
            // Download tile
            $tileUrl = sprintf('%s/%d/%d/%d.png', $tileServer, $zoom, $tileX, $tileY);
            $tileData = $downloadTile($tileUrl);
            
            if ($tileData !== false && strlen($tileData) > 0) {
                $tileImg = @imagecreatefromstring($tileData);
                if ($tileImg !== false) {
                    // Calculate destination position
                    $tilePixelX = $tileX * $tileSize;
                    $tilePixelY = $tileY * $tileSize;
                    $destX = $tilePixelX + $offsetX;
                    $destY = $tilePixelY + $offsetY;
                    
                    // Only draw if tile is visible in image bounds
                    if ($destX > -$tileSize && $destX < $width && $destY > -$tileSize && $destY < $height) {
                        imagecopy($image, $tileImg, $destX, $destY, 0, 0, $tileSize, $tileSize);
                        $tilesLoaded++;
                    }
                    imagedestroy($tileImg);
                } else {
                    $tilesFailed++;
                    error_log("Failed to create image from tile data: $tileUrl");
                }
            } else {
                $tilesFailed++;
            }
        }
    }
    
    error_log("Map generation: Loaded $tilesLoaded tiles, Failed $tilesFailed tiles at zoom level $zoom");
    
    // Draw markers using the same coordinate system as tiles (solid red/green)
    foreach (array_slice($dataPoints, 0, 100) as $point) {
        $pointPixelX = $lngToX($point['lng'], $zoom) * $tileSize;
        $pointPixelY = $latToY($point['lat'], $zoom) * $tileSize;
        $markerX = $pointPixelX + $offsetX;
        $markerY = $pointPixelY + $offsetY;
        
        if ($markerX >= 0 && $markerX < $width && $markerY >= 0 && $markerY < $height) {
            $color = $red; // Default to red
            if ($point['has_water'] == 1) {
                $color = $green;
            } elseif ($point['has_water'] == 2) {
                $color = imagecolorallocate($image, 243, 156, 18); // Orange #f39c12
            }
            // Draw solid circle marker (no white center)
            imagefilledellipse($image, $markerX, $markerY, 12, 12, $color);
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
 * Generate map image and return as base64 data URI for embedding in email.
 * This avoids external loads and works in Gmail (similar to the Joes minifigures site).
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
        
        // Convert to base64 data URI
        // Note: For email compatibility, we need to keep the base64 continuous (no line breaks)
        // but we'll ensure the HTML structure doesn't create extremely long lines
        $base64 = base64_encode($imageData);
        error_log("Successfully generated base64 map image (" . strlen($base64) . " chars, " . strlen($imageData) . " bytes)");
        return 'data:image/png;base64,' . $base64;
        
    } catch (Exception $e) {
        error_log("Error generating map image: " . $e->getMessage());
        return '';
    }
}

function calculateZoom($minLat, $maxLat, $minLng, $maxLng) {
    $latDiff = abs($maxLat - $minLat);
    $lngDiff = abs($maxLng - $minLng);
    $maxDiff = max($latDiff, $lngDiff);
    
    // Avoid division by zero and set a tiny span for single-point cases
    if ($maxDiff <= 0) {
        return 16;
    }
    
    // Add a small padding factor so points are not flush to the edge
    $paddingFactor = 1.5; // 50% padding
    $span = $maxDiff * $paddingFactor;
    
    // Approximate zoom based on world degrees covered
    // 360 degrees at zoom 0; each zoom level doubles resolution
    $zoom = floor(log(360 / $span, 2));
    
    // Clamp to sensible bounds for street-level detail
    if ($zoom < 8) $zoom = 8;
    if ($zoom > 17) $zoom = 17;
    
    return $zoom;
}

function generateDailyReportEmail($reportDate, $mapImageBase64, $tableRows, $totalReports, $inlineCid = false) {
    $dateFormatted = date('F j, Y', strtotime($reportDate));
    
    // Generate map image HTML with base64 embedded image
    if (!empty($mapImageBase64)) {
        // Ensure base64 string doesn't have any whitespace or newlines that could break the email
        $mapImageBase64 = trim($mapImageBase64);
        if ($inlineCid) {
            // Use CID for inline image when sending multipart/related
            // Try both formats for maximum compatibility
            $mapImageHtml = '<img src="cid:mapimage" alt="Water Availability Map" class="map-image" style="max-width: 800px; width: 100%; display: block; margin: 0 auto;" />';
        } else {
            // Inline data URI (base64) fallback
            $mapImageHtml = sprintf(
                '<img src="%s" alt="Water Availability Map" class="map-image" style="max-width: 800px; width: 100%%; display: block; margin: 0 auto;" />',
                $mapImageBase64 // Don't escape base64 data URI
            );
        }
    } else {
        $mapImageHtml = '<p style="color: #d32f2f; padding: 20px; text-align: center; background-color: #ffebee; border-radius: 8px;">Map image unavailable. Unable to generate map from OpenStreetMap.</p>';
    }
    
    $tableHtml = '';
    if (empty($tableRows)) {
        $tableHtml = '<tr><td colspan="2" style="text-align: center; padding: 1rem; color: #666;">No water availability reports received for this date.</td></tr>';
    } else {
        foreach ($tableRows as $row) {
            $statusColor = '#d32f2f'; // Default red
            if ($row['has_water'] === 'Yes') {
                $statusColor = '#4caf50'; // Green
            } elseif ($row['has_water'] === 'Intermittent') {
                $statusColor = '#f39c12'; // Orange
            }
            $tableHtml .= sprintf(
                '<tr><td>%s</td><td style="color: %s; font-weight: 600;">%s</td></tr>',
                h($row['address']),
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
                    %s
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $email = trim($_POST['email'] ?? '');
            $name = trim($_POST['name'] ?? '');
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO " . TABLE_PREFIX . "daily_report_emails (email_address, name)
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$email, $name ?: null]);
                    $message = 'Email address added successfully.';
                } catch (PDOException $e) {
                    $error = 'Email address already exists.';
                }
            }
        } elseif ($_POST['action'] === 'toggle') {
            $emailId = (int)($_POST['email_id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);
            
            $stmt = $db->prepare("
                UPDATE " . TABLE_PREFIX . "daily_report_emails 
                SET is_active = ?
                WHERE email_id = ?
            ");
            $stmt->execute([$isActive, $emailId]);
            $message = 'Email status updated successfully.';
        } elseif ($_POST['action'] === 'delete') {
            $emailId = (int)($_POST['email_id'] ?? 0);
            
            $stmt = $db->prepare("DELETE FROM " . TABLE_PREFIX . "daily_report_emails WHERE email_id = ?");
            $stmt->execute([$emailId]);
            $message = 'Email address deleted successfully.';
        } elseif ($_POST['action'] === 'send_manual') {
            // Send daily report manually
            try {
                require_once __DIR__ . '/../includes/email.php';
                
                $reportDate = $_POST['report_date'] ?? date('Y-m-d');
                
                // Get all active email recipients
                $stmt = $db->query("
                    SELECT email_address, name
                    FROM " . TABLE_PREFIX . "daily_report_emails
                    WHERE is_active = 1
                ");
                $recipients = $stmt->fetchAll();
                
                if (empty($recipients)) {
                    $error = 'No active email recipients found.';
                } else {
                // Get all water availability data for the specified date
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
                $emailBody = generateDailyReportEmail($reportDate, $mapImageBase64, $tableRows, count($reports), true);
                
                // Validate email body
                $emailBodyLength = strlen($emailBody);
                $base64Length = strlen($mapImageBase64);
                error_log("Email body length: $emailBodyLength bytes");
                error_log("Base64 image length: $base64Length chars");
                
                // Check for potential issues
                if ($emailBodyLength > 1000000) { // 1MB
                    error_log("WARNING: Email body is very large ($emailBodyLength bytes), may be rejected by mail server");
                }
                
                // Send email to all recipients using multipart/related with CID inline image
                $emailsSent = 0;
                $emailsFailed = 0;
                
                error_log("Attempting to send daily report email to " . count($recipients) . " recipients");
                error_log("Email subject: $emailSubject");
                
                // Prepare inline image data
                $mapImageData = null;
                if (!empty($mapImageBase64) && str_starts_with($mapImageBase64, 'data:image/png;base64,')) {
                    $mapImageData = base64_decode(str_replace('data:image/png;base64,', '', $mapImageBase64));
                    if ($mapImageData === false) {
                        error_log("Failed to decode base64 map image for inline embedding");
                        $mapImageData = null;
                    }
                }
                
                // Build multipart/related email
                $boundary = '==Multipart_Boundary_x' . md5((string)microtime(true)) . 'x';
                
                foreach ($recipients as $recipient) {
                    $recipientEmail = $recipient['email_address'];
                    $recipientName = $recipient['name'] ?: 'Recipient';
                    
                    error_log("Sending email to: $recipientEmail");
                    
                    // Validate email address
                    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                        error_log("Invalid email address: $recipientEmail");
                        $emailsFailed++;
                        continue;
                    }
                    
                    // Use a simpler approach: base64 data URI for better iOS compatibility
                    // iOS Mail handles base64 data URIs better than CID references
                    if ($mapImageData !== null && strlen($mapImageData) < 1000000) { // Only use inline if under 1MB
                        // Replace CID reference with base64 data URI in email body
                        $emailBodyWithImage = str_replace(
                            'src="cid:mapimage"',
                            'src="data:image/png;base64,' . base64_encode($mapImageData) . '"',
                            $emailBody
                        );
                        
                        $headers = "MIME-Version: 1.0\r\n";
                        $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM_ADDRESS . ">\r\n";
                        $headers .= "Sender: " . EMAIL_FROM_ADDRESS . "\r\n";
                        $headers .= "Reply-To: " . EMAIL_FROM_ADDRESS . "\r\n";
                        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
                        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                        $headers .= "Content-Transfer-Encoding: quoted-printable\r\n";
                        
                        $body = quoted_printable_encode($emailBodyWithImage);
                    } else {
                        // Fallback to multipart/related for large images
                        $headers = "MIME-Version: 1.0\r\n";
                        $headers .= "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM_ADDRESS . ">\r\n";
                        $headers .= "Sender: " . EMAIL_FROM_ADDRESS . "\r\n";
                        $headers .= "Reply-To: " . EMAIL_FROM_ADDRESS . "\r\n";
                        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
                        $headers .= "Content-Type: multipart/related; type=\"text/html\"; boundary=\"{$boundary}\"\r\n";
                        
                        $body = "--{$boundary}\r\n";
                        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
                        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
                        $body .= quoted_printable_encode($emailBody) . "\r\n\r\n";
                        
                        if ($mapImageData !== null) {
                            $body .= "--{$boundary}\r\n";
                            $body .= "Content-Type: image/png\r\n";
                            $body .= "Content-Transfer-Encoding: base64\r\n";
                            $body .= "Content-Disposition: inline\r\n";
                            $body .= "Content-ID: <mapimage>\r\n\r\n";
                            $body .= chunk_split(base64_encode($mapImageData), 76, "\r\n");
                        }
                        
                        $body .= "--{$boundary}--\r\n";
                    }
                    
                    // Use envelope sender for deliverability
                    $mailParams = '-f' . EMAIL_FROM_ADDRESS;
                    $emailResult = mail($recipientEmail, $emailSubject, $body, $headers, $mailParams);
                    
                    if ($emailResult) {
                        $emailsSent++;
                        error_log("Email sent successfully to: $recipientEmail");
                    } else {
                        $emailsFailed++;
                        $lastError = error_get_last();
                        error_log("Email failed to send to: $recipientEmail. Error: " . ($lastError['message'] ?? 'Unknown error'));
                    }
                }
                
                if ($emailsSent > 0) {
                    $message = "Daily report sent successfully! $emailsSent email(s) sent" . ($emailsFailed > 0 ? ", $emailsFailed failed" : "") . ". Report date: " . date('F j, Y', strtotime($reportDate)) . " (" . count($reports) . " reports).";
                    error_log("Daily report email summary: $emailsSent sent, $emailsFailed failed");
                } else {
                    $error = "Failed to send emails. $emailsFailed email(s) failed.";
                    error_log("Daily report email failed: All $emailsFailed emails failed to send");
                }
                }
                
                // Clear any output buffer
                ob_end_clean();
            } catch (Exception $e) {
                ob_end_clean();
                $error = 'Error sending report: ' . $e->getMessage();
                error_log("Daily report manual send error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            } catch (Error $e) {
                ob_end_clean();
                $error = 'Fatal error sending report: ' . $e->getMessage();
                error_log("Daily report manual send fatal error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            }
        }
    }
}


// Get all email addresses
$stmt = $db->query("
    SELECT * FROM " . TABLE_PREFIX . "daily_report_emails
    ORDER BY created_at DESC
");
$emails = $stmt->fetchAll();

$pageTitle = 'Manage Daily Report Emails';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Manage Daily Report Emails</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="email-form-section">
            <h2>Add Email Address</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="email">Email Address: <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="name">Name (optional):</label>
                    <input type="text" id="name" name="name" placeholder="Recipient name">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Email</button>
                </div>
            </form>
        </div>
        
        <div class="emails-list-section">
            <h2>Email Recipients (<?= count($emails) ?>)</h2>
            <?php if (empty($emails)): ?>
                <p>No email addresses configured yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Email Address</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emails as $email): ?>
                            <tr>
                                <td><?= h($email['email_address']) ?></td>
                                <td><?= h($email['name'] ?: 'N/A') ?></td>
                                <td>
                                    <?php if ($email['is_active']): ?>
                                        <span style="color: green; font-weight: 600;">Active</span>
                                    <?php else: ?>
                                        <span style="color: red; font-weight: 600;">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($email['created_at'], 'Y-m-d') ?></td>
                                <td>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="email_id" value="<?= $email['email_id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= $email['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= $email['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                            <?= $email['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this email address?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="email_id" value="<?= $email['email_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <div class="manual-send-section" style="margin-top: 2rem; padding: 1.5rem; background-color: #e8f4f8; border-radius: 8px; border: 2px solid #2c5f8d;">
            <h3>Send Report Manually</h3>
            <p>You can manually send the daily water availability report to all active recipients.</p>
            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to send the daily report to all active recipients?');">
                <input type="hidden" name="action" value="send_manual">
                <div class="form-group" style="display: flex; gap: 1rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label for="report_date">Report Date:</label>
                        <input type="date" id="report_date" name="report_date" value="<?= date('Y-m-d') ?>" required>
                        <small style="display: block; color: #666; margin-top: 0.25rem;">Select the date for the report (defaults to today)</small>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                            <i class="fas fa-paper-plane"></i> Send Manually
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="info-section" style="margin-top: 2rem; padding: 1.5rem; background-color: #f5f5f5; border-radius: 8px;">
            <h3>About Daily Reports</h3>
            <p>Daily water availability reports are automatically sent at the end of each day to all active email addresses listed above.</p>
            <p><strong>Note:</strong> To enable automatic sending, you need to set up a cron job or scheduled task to run <code>cron/daily-water-report.php</code> daily.</p>
        </div>
    </div>
</div>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>

