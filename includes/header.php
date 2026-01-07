<?php
if (!isset($pageTitle)) {
    $pageTitle = SITE_NAME;
}
startSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2c5f8d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= h(SITE_NAME) ?>">
    <title><?= h($pageTitle) ?> - <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= baseUrl('/css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="192x192" href="<?= baseUrl('/images/icons/icon-192x192.png') ?>?v=<?= filemtime(__DIR__ . '/../images/icons/icon-192x192.png') ?>">
    <link rel="shortcut icon" href="<?= baseUrl('/images/icons/icon-192x192.png') ?>?v=<?= filemtime(__DIR__ . '/../images/icons/icon-192x192.png') ?>">
    <link rel="manifest" href="<?= baseUrl('/manifest.php') ?>?v=<?= filemtime(__DIR__ . '/../manifest.php') ?>">
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="152x152" href="<?= baseUrl('/images/icons/icon-152x152.png') ?>?v=<?= filemtime(__DIR__ . '/../images/icons/icon-152x152.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= baseUrl('/images/icons/icon-192x192.png') ?>?v=<?= filemtime(__DIR__ . '/../images/icons/icon-192x192.png') ?>">
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXX"
            crossorigin="anonymous"></script>
    <script>
        // Set advertisement rotation interval for main.js
        window.ADVERT_ROTATION_INTERVAL = <?= defined('ADVERT_ROTATION_INTERVAL') ? ADVERT_ROTATION_INTERVAL : 5000 ?>;
    </script>
</head>
<body>
    <header class="main-header">
        <div class="container">
            <div class="header-top">
                <div class="logo">
                    <a href="<?= baseUrl('/index.php') ?>" title="Go to Home">
                        <img src="<?= baseUrl('/images/logo/150ppi/Asset%2021.png') ?>" alt="On-Sea News" class="logo-img" style="height: 52px; width: auto; display: block;">
                    </a>
                </div>
                
                <div class="header-icons">
                    <!-- Add to Home Screen Button (only shown on mobile browsers, hidden when running as PWA) -->
                    <div class="add-to-home-screen" id="addToHomeScreen" style="display: none;">
                        <button class="add-to-home-btn" id="addToHomeBtn" aria-label="Add to Home Screen" title="Add to Home Screen">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <div class="add-to-home-instructions" id="addToHomeInstructions" style="display: none;">
                            <div class="instructions-content">
                                <button class="instructions-close" onclick="closeAddToHomeInstructions()">&times;</button>
                                <h3>Add to Home Screen</h3>
                                <div id="ios-instructions" style="display: none;">
                                    <p><strong>iOS (Safari):</strong></p>
                                    <ol>
                                        <li>Tap the <strong>Share</strong> button <i class="fas fa-share"></i> at the bottom</li>
                                        <li>Scroll down and tap <strong>"Add to Home Screen"</strong></li>
                                        <li>Tap <strong>"Add"</strong> in the top right</li>
                                    </ol>
                                </div>
                                <div id="android-instructions" style="display: none;">
                                    <p><strong>Android (Chrome):</strong></p>
                                    <ol>
                                        <li>Tap the <strong>Menu</strong> button <i class="fas fa-ellipsis-v"></i> (three dots)</li>
                                        <li>Tap <strong>"Add to Home screen"</strong> or <strong>"Install app"</strong></li>
                                        <li>Tap <strong>"Add"</strong> or <strong>"Install"</strong></li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-icon-container">
                        <div class="profile-icon-wrapper">
                            <button class="profile-icon-btn" id="profileIconBtn" aria-label="User menu" title="<?= isLoggedIn() ? 'Logout' : 'Log In' ?>">
                                <i class="fas fa-user-circle"></i>
                            </button>
                            <div class="profile-dropdown" id="profileDropdown">
                                <?php if (isLoggedIn()): ?>
                                    <a href="<?= baseUrl('/profile.php') ?>" class="profile-dropdown-item">
                                        <i class="fas fa-user"></i> Profile
                                    </a>
                                    <a href="<?= baseUrl('/logout.php') ?>" class="profile-dropdown-item">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                <?php else: ?>
                                    <a href="<?= baseUrl('/login.php') ?>" class="profile-dropdown-item">
                                        <i class="fas fa-sign-in-alt"></i> Login
                                    </a>
                                    <a href="<?= baseUrl('/register.php') ?>" class="profile-dropdown-item">
                                        <i class="fas fa-user-plus"></i> Register
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <nav class="main-nav">
                <button class="hamburger" id="hamburger" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                
                <ul class="nav-menu" id="navMenu">
                    <li><a href="<?= baseUrl('/index.php') ?>">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?= baseUrl('/water-availability.php') ?>">Water Status</a></li>
                    <?php endif; ?>
                    <li><a href="<?= baseUrl('/businesses.php') ?>">Businesses</a></li>
                    <li><a href="<?= baseUrl('/contact.php') ?>">Contact Us</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?= baseUrl('/my-businesses.php') ?>">My Businesses</a></li>
                        <?php if (hasAnyRole(['ADMIN', 'PUBLISHER'])): ?>
                            <li><a href="<?= baseUrl('/admin/news.php') ?>">News</a></li>
                        <?php endif; ?>
                        <?php if (hasAnyRole(['ADMIN', 'ADVERTISER'])): ?>
                            <li><a href="<?= baseUrl('/advertiser/dashboard.php') ?>">Advertisements</a></li>
                        <?php endif; ?>
                        <?php if (hasRole('ANALYTICS')): ?>
                            <li><a href="<?= baseUrl('/water-analytics.php') ?>">Water Analytics</a></li>
                        <?php endif; ?>
                        <?php if (hasRole('ADMIN')): ?>
                            <li><a href="<?= baseUrl('/admin/dashboard.php') ?>">Admin</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <?php
            // Water availability status bar - only show to registered water users
            if (isLoggedIn()) {
                $db = getDB();
                $userId = getCurrentUserId();
                
                // Check if user has accepted water terms
                $stmt = $db->prepare("
                    SELECT q.question_id 
                    FROM " . TABLE_PREFIX . "water_questions q
                    WHERE q.question_text LIKE '%terms and conditions%' 
                    AND q.page_tag = 'water_info'
                    AND q.is_active = 1
                    LIMIT 1
                ");
                $stmt->execute();
                $termsQuestion = $stmt->fetch();
                
                $isWaterRegistered = false;
                if ($termsQuestion) {
                    $stmt = $db->prepare("
                        SELECT response_value 
                        FROM " . TABLE_PREFIX . "water_user_responses
                        WHERE user_id = ? AND question_id = ?
                    ");
                    $stmt->execute([$userId, $termsQuestion['question_id']]);
                    $termsResponse = $stmt->fetch();
                    $isWaterRegistered = ($termsResponse && $termsResponse['response_value'] === 'agreed');
                }
                
                if ($isWaterRegistered) {
                    // Get today's water availability reports
                    $today = date('Y-m-d');
                    // Count yes (1), intermittent (2), and no (0) responses separately
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count
                        FROM " . TABLE_PREFIX . "water_availability
                        WHERE report_date = ? AND has_water = 1
                    ");
                    $stmt->execute([$today]);
                    $hasWaterResult = $stmt->fetch(PDO::FETCH_ASSOC);
                    $hasWaterCount = (int)($hasWaterResult['count'] ?? 0);
                    
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count
                        FROM " . TABLE_PREFIX . "water_availability
                        WHERE report_date = ? AND has_water = 2
                    ");
                    $stmt->execute([$today]);
                    $intermittentResult = $stmt->fetch(PDO::FETCH_ASSOC);
                    $intermittentCount = (int)($intermittentResult['count'] ?? 0);
                    
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count
                        FROM " . TABLE_PREFIX . "water_availability
                        WHERE report_date = ? AND has_water = 0
                    ");
                    $stmt->execute([$today]);
                    $noWaterResult = $stmt->fetch(PDO::FETCH_ASSOC);
                    $noWaterCount = (int)($noWaterResult['count'] ?? 0);
                    // Show all three dots with their respective counts
                    // Always show all dots, even if count is zero
                    ?>
                    <a href="<?= baseUrl('/water-availability.php') ?>" class="water-status-bar">
                        <span class="water-status-label">Today's logged water availability:</span>
                        <div class="water-status-indicator">
                            <span class="water-status-item">
                                <span class="water-dot water-dot-green" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; background-color: #27ae60;" title="Has water"></span>
                                <span class="water-status-count"><?= $hasWaterCount ?></span>
                            </span>
                            <span class="water-status-item">
                                <span class="water-dot water-dot-orange" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; background-color: #f39c12;" title="Intermittent water"></span>
                                <span class="water-status-count"><?= $intermittentCount ?></span>
                            </span>
                            <span class="water-status-item">
                                <span class="water-dot water-dot-red" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; background-color: #e74c3c;" title="No water"></span>
                                <span class="water-status-count"><?= $noWaterCount ?></span>
                            </span>
                        </div>
                    </a>
                    <?php
                }
            }
            ?>
        </div>
    </header>
    
    <style>
    .water-status-bar {
        display: flex !important;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 1rem;
        background-color: var(--bg-color);
        border-top: 1px solid var(--border-color);
        font-size: 0.875rem;
        flex-wrap: wrap;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
        transition: background-color 0.2s ease;
        width: 100%;
    }
    
    .water-status-bar:hover {
        background-color: var(--hover-bg-color, rgba(0, 0, 0, 0.05));
    }
    
    .water-status-label {
        font-weight: 500;
        color: var(--text-color);
        white-space: nowrap;
    }
    
    .water-status-indicator {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .water-status-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .water-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }
    
    .water-dot-green {
        background-color: #27ae60;
    }
    
    .water-dot-red {
        background-color: #e74c3c;
    }
    
    .water-dot-orange {
        background-color: #f39c12;
    }
    
    .water-status-count {
        font-weight: 600;
        color: var(--text-color);
    }
    
    .water-status-none {
        color: #666;
        font-style: italic;
    }
    
    /* Explicitly show on desktop */
    @media (min-width: 769px) {
        .water-status-bar {
            display: flex !important;
        }
    }
    
    @media (max-width: 768px) {
        .water-status-bar {
            padding: 0.5rem;
            font-size: 0.8rem;
        }
        
        .water-status-label {
            font-size: 0.8rem;
        }
        
        .water-dot {
            width: 10px;
            height: 10px;
        }
    }
    </style>
    
    <script>
    // PWA Detection and Add to Home Screen functionality
    (function() {
        // Detect if running as PWA (standalone mode)
        function isRunningAsPWA() {
            // Check for standalone mode (iOS)
            if (window.navigator.standalone === true) {
                return true;
            }
            // Check for display-mode: standalone (Android/Chrome)
            if (window.matchMedia('(display-mode: standalone)').matches) {
                return true;
            }
            // Check if launched from home screen (Android)
            if (window.matchMedia('(display-mode: fullscreen)').matches) {
                return true;
            }
            return false;
        }
        
        // Detect device type
        function detectDevice() {
            const userAgent = navigator.userAgent || navigator.vendor || window.opera;
            
            // iOS detection
            if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
                return 'ios';
            }
            
            // Android detection
            if (/android/i.test(userAgent)) {
                return 'android';
            }
            
            return 'unknown';
        }
        
        // Check if on mobile device
        function isMobileDevice() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }
        
        // Show/hide Add to Home Screen button
        function updateAddToHomeButton() {
            const addToHomeContainer = document.getElementById('addToHomeScreen');
            if (!addToHomeContainer) return;
            
            // Hide if running as PWA or not on mobile
            if (isRunningAsPWA() || !isMobileDevice()) {
                addToHomeContainer.style.display = 'none';
            } else {
                addToHomeContainer.style.display = 'block';
            }
        }
        
        // Show instructions based on device
        function showAddToHomeInstructions() {
            const instructions = document.getElementById('addToHomeInstructions');
            const iosInstructions = document.getElementById('ios-instructions');
            const androidInstructions = document.getElementById('android-instructions');
            const device = detectDevice();
            
            if (!instructions) return;
            
            // Show appropriate instructions
            if (device === 'ios') {
                iosInstructions.style.display = 'block';
                androidInstructions.style.display = 'none';
            } else if (device === 'android') {
                iosInstructions.style.display = 'none';
                androidInstructions.style.display = 'block';
            } else {
                // Show both if unknown
                iosInstructions.style.display = 'block';
                androidInstructions.style.display = 'block';
            }
            
            instructions.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddToHomeInstructions() {
            const instructions = document.getElementById('addToHomeInstructions');
            if (instructions) {
                instructions.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
        
        // Make functions globally available
        window.closeAddToHomeInstructions = closeAddToHomeInstructions;
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateAddToHomeButton();
            
            const addToHomeBtn = document.getElementById('addToHomeBtn');
            if (addToHomeBtn) {
                addToHomeBtn.addEventListener('click', showAddToHomeInstructions);
            }
            
            // Close instructions when clicking outside
            const instructions = document.getElementById('addToHomeInstructions');
            if (instructions) {
                instructions.addEventListener('click', function(e) {
                    if (e.target === instructions) {
                        closeAddToHomeInstructions();
                    }
                });
            }
            
            // Re-check on orientation change or resize
            window.addEventListener('orientationchange', updateAddToHomeButton);
            window.addEventListener('resize', updateAddToHomeButton);
        });
        
        // Also check immediately (in case DOMContentLoaded already fired)
        if (document.readyState === 'loading') {
            // DOMContentLoaded will handle it
        } else {
            // DOM already loaded
            updateAddToHomeButton();
        }
    })();
    </script>
    
    <main class="main-content">

