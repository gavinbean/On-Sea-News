<?php
/**
 * API endpoint for top banner adverts
 * Returns active business adverts with banner images for display in header
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$today = date('Y-m-d');

try {
    // Get active business adverts that are currently valid
    // For 'basic' type: always show if active
    // For 'timed' and 'events': check date range
    $stmt = $db->prepare("
        SELECT 
            a.advert_id,
            a.banner_image,
            a.display_image,
            a.advert_type,
            a.event_title,
            a.event_date,
            b.business_id,
            b.business_name,
            b.description,
            b.telephone,
            b.email,
            b.website,
            b.address
        FROM " . TABLE_PREFIX . "business_adverts a
        JOIN " . TABLE_PREFIX . "businesses b ON a.business_id = b.business_id
        WHERE a.is_active = 1
        AND a.approval_status = 'approved'
        AND b.is_approved = 1
        AND (
            a.advert_type = 'basic'
            OR (
                a.advert_type IN ('timed', 'events')
                AND (
                    -- If no dates provided, always active
                    (a.start_date IS NULL AND a.end_date IS NULL)
                    OR
                    -- If only start date provided, check if today >= start_date
                    (a.start_date IS NOT NULL AND a.end_date IS NULL AND a.start_date <= ?)
                    OR
                    -- If only end date provided, check if today <= end_date
                    (a.start_date IS NULL AND a.end_date IS NOT NULL AND a.end_date >= ?)
                    OR
                    -- If both dates provided, check if today is within range
                    (a.start_date IS NOT NULL AND a.end_date IS NOT NULL AND a.start_date <= ? AND a.end_date >= ?)
                )
            )
        )
        ORDER BY 
            CASE a.advert_type
                WHEN 'events' THEN 1
                WHEN 'timed' THEN 2
                WHEN 'basic' THEN 3
            END,
            a.event_date ASC,
            a.created_at DESC
    ");
    // Execute with today's date for date checks (will be used multiple times in query)
    $stmt->execute([$today, $today, $today, $today]);
    $allBanners = $stmt->fetchAll();
    
    // Normalize paths: replace old 'uploads/adverts/' with 'uploads/graphics/'
    foreach ($allBanners as &$banner) {
        if (!empty($banner['banner_image'])) {
            $banner['banner_image'] = str_replace('uploads/adverts/', 'uploads/graphics/', $banner['banner_image']);
        }
        if (!empty($banner['display_image'])) {
            $banner['display_image'] = str_replace('uploads/adverts/', 'uploads/graphics/', $banner['display_image']);
        }
    }
    unset($banner);
    
    // Spread out adverts from the same business
    // Group by business_id
    $businessGroups = [];
    foreach ($allBanners as $banner) {
        $businessId = $banner['business_id'];
        if (!isset($businessGroups[$businessId])) {
            $businessGroups[$businessId] = [];
        }
        $businessGroups[$businessId][] = $banner;
    }
    
    // Interleave banners from different businesses
    $banners = [];
    $maxPerBusiness = [];
    foreach ($businessGroups as $businessId => $businessBanners) {
        $maxPerBusiness[$businessId] = count($businessBanners);
    }
    
    $maxIterations = max($maxPerBusiness) ?: 1;
    for ($i = 0; $i < $maxIterations; $i++) {
        foreach ($businessGroups as $businessId => $businessBanners) {
            if (isset($businessBanners[$i])) {
                $banners[] = $businessBanners[$i];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'banners' => $banners
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error loading banners: ' . $e->getMessage()
    ]);
}
