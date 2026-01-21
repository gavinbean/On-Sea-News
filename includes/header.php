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
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= h(SITE_NAME) ?>">
    <title><?= h($pageTitle) ?> - <?= h(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= baseUrl('/css/style.css') ?>?v=<?= filemtime(__DIR__ . '/../css/style.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="192x192" href="<?= baseUrl('/images/icons/icon-192x192.png') ?>?v=<?= filemtime(__DIR__ . '/../images/icons/icon-192x192.png') ?>">
    <link rel="shortcut icon" href="<?= baseUrl('/images/icons/icon-192x192.png') ?>?v=<?= filemtime(__DIR__ . '/../images/icons/icon-192x192.png') ?>">
    <link rel="manifest" href="<?= baseUrl('/manifest.php') ?>?v=<?= filemtime(__DIR__ . '/../manifest.php') ?>">
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="152x152" href="<?= baseUrl('/images/icons/icon-152x152.png') ?>?v=<?= filemtime(__DIR__ . '/../images/icons/icon-152x152.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= baseUrl('/images/icons/icon-192x192.png') ?>?v=<?= filemtime(__DIR__ . '/../images/icons/icon-192x192.png') ?>">
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9986887492350930"
            crossorigin="anonymous"></script>
    <script>
        // Set advertisement rotation interval for main.js
        window.ADVERT_ROTATION_INTERVAL = <?= defined('ADVERT_ROTATION_INTERVAL') ? ADVERT_ROTATION_INTERVAL : 5000 ?>;
    </script>
</head>
<body>
    <?php if (hasRole('ADMIN')): ?>
    <!-- Top Banner Area (Admin Only) -->
    <div class="top-banner-container" id="topBannerContainer">
        <div class="container">
            <div class="top-banners" id="topBanners">
                <!-- Banners will be loaded dynamically via JavaScript -->
            </div>
        </div>
    </div>
    <?php endif; ?>
    <header class="main-header">
        <div class="container">
            <div class="header-top">
                <button type="button" class="hamburger" id="hamburger" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                
                <div class="logo">
                    <a href="<?= baseUrl('/index.php') ?>" title="Go to Home">
                        <img src="<?= baseUrl('/images/logo/150ppi/Asset%2021.png') ?>" alt="On-Sea News" class="logo-img" style="height: 52px; width: auto; display: block;">
                    </a>
                </div>
                
                <?php if (hasRole('ADMIN')): ?>
                <!-- Admin Notification Icons (Center) -->
                <div class="admin-notifications" id="adminNotifications">
                    <a href="<?= baseUrl('/admin/approve-businesses.php') ?>" class="admin-notification-icon" id="pendingBusinessesIcon" title="Pending Business Reviews" style="display: none;">
                        <i class="fas fa-building"></i>
                        <span class="notification-badge" id="pendingBusinessesBadge">0</span>
                    </a>
                    <a href="<?= baseUrl('/admin/contact-queries.php?status=new') ?>" class="admin-notification-icon" id="newContactMessagesIcon" title="New Contact Messages" style="display: none;">
                        <i class="fas fa-envelope"></i>
                        <span class="notification-badge" id="newContactMessagesBadge">0</span>
                    </a>
                    <a href="<?= baseUrl('/admin/approve-adverts.php') ?>" class="admin-notification-icon" id="pendingAdvertsIcon" title="Pending Advert Reviews" style="display: none;">
                        <i class="fas fa-image"></i>
                        <span class="notification-badge" id="pendingAdvertsBadge">0</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="header-icons">
                    <!-- Events Calendar Icon (admin only) -->
                    <?php if (hasRole('ADMIN')): ?>
                        <button class="calendar-icon-btn" id="eventsCalendarBtn" aria-label="Events Calendar" title="Events Calendar" style="background: none; border: none; color: var(--primary-color); font-size: 1.5rem; cursor: pointer; padding: 0.5rem; margin-right: 0.5rem;">
                            <i class="fas fa-calendar-alt"></i>
                        </button>
                    <?php endif; ?>
                    
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
                            <button type="button" class="profile-icon-btn" id="profileIconBtn" aria-label="User menu" title="<?= isLoggedIn() ? 'Logout' : 'Log In' ?>">
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
                <ul class="nav-menu" id="navMenu">
                    <li><a href="<?= baseUrl('/index.php') ?>">Home</a></li>
            <?php if (isLoggedIn()): ?>
                <li class="water-menu-item">
                    <a href="#" class="water-menu-toggle">Water <i class="fas fa-chevron-down"></i></a>
                    <ul class="water-submenu">
                        <li><a href="<?= baseUrl('/water-availability.php') ?>">Water Status</a></li>
                        <li><a href="<?= baseUrl('/log-water-deliveries.php') ?>">Log Water Deliveries</a></li>
                        <li><a href="<?= baseUrl('/report-tankers.php') ?>">Report Tankers</a></li>
                        <?php if (hasRole('ANALYTICS')): ?>
                            <li><a href="<?= baseUrl('/water-analytics.php') ?>">Water Analytics</a></li>
                        <?php endif; ?>
                        <?php if (hasAnyRole(['ADMIN', 'ANALYTICS'])): ?>
                            <li><a href="<?= baseUrl('/water-engineers-map.php') ?>">Water Engineers Map</a></li>
                            <li><a href="<?= baseUrl('/admin/manage-water-truck-permits.php') ?>">Water Truck Permits</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                        <?php if (hasAnyRole(['ADMIN', 'ELECTRICITY_ADMIN'])): ?>
                            <li><a href="<?= baseUrl('/electricity-availability.php') ?>">Electricity Issues</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li><a href="<?= baseUrl('/businesses.php') ?>">Businesses</a></li>
                    <li><a href="<?= baseUrl('/contact.php') ?>">Contact Us</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="<?= baseUrl('/submit-news.php') ?>">Submit News</a></li>
                        <?php if (hasAnyRole(['ADMIN', 'PUBLISHER'])): ?>
                            <li><a href="<?= baseUrl('/admin/news.php') ?>">News</a></li>
                        <?php endif; ?>
                        <?php if (hasAnyRole(['ADMIN', 'ADVERTISER'])): ?>
                            <li><a href="<?= baseUrl('/advertiser/dashboard.php') ?>">Advertisements</a></li>
                        <?php endif; ?>
                        <?php if (hasAnyRole(['ADMIN', 'USER_ADMIN'])): ?>
                            <li><a href="<?= baseUrl('/admin/dashboard.php') ?>">Admin</a></li>
                        <?php endif; ?>
                        <?php if (hasRole('ADMIN')): ?>
                            <li><a href="<?= baseUrl('/admin/manage-adverts.php') ?>">Manage Adverts</a></li>
                        <?php endif; ?>
                        <?php if (hasAnyRole(['ADMIN', 'ELECTRICITY_ADMIN'])): ?>
                            <li><a href="<?= baseUrl('/admin/electricity-issues.php') ?>">Manage Electricity Issues</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    <li><a href="<?= baseUrl('/faq.php') ?>">FAQ</a></li>
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
        // Local flag key for "app already installed/used"
        const INSTALLED_FLAG_KEY = 'onsea_app_installed';
        
        function hasInstalledFlag() {
            try {
                return window.localStorage && localStorage.getItem(INSTALLED_FLAG_KEY) === '1';
            } catch (e) {
                return false;
            }
        }
        
        function setInstalledFlag() {
            try {
                if (window.localStorage) {
                    localStorage.setItem(INSTALLED_FLAG_KEY, '1');
                }
            } catch (e) {
                // ignore storage errors
            }
        }
        
        // Detect if running as PWA (standalone mode) right now
        function isRunningAsPWA() {
            // Check for standalone mode (iOS)
            if (window.navigator.standalone === true) {
                setInstalledFlag();
                return true;
            }
            // Check for display-mode: standalone (Android/Chrome)
            if (window.matchMedia('(display-mode: standalone)').matches) {
                setInstalledFlag();
                return true;
            }
            // Check if launched from home screen (Android)
            if (window.matchMedia('(display-mode: fullscreen)').matches) {
                setInstalledFlag();
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
            
            // Hide if:
            //  - we detect it is running as PWA now, OR
            //  - user is not on mobile, OR
            //  - we've previously detected the app as installed/used
            if (isRunningAsPWA() || !isMobileDevice() || hasInstalledFlag()) {
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
            
            // Listen for appinstalled event (mostly Android/Chrome)
            window.addEventListener('appinstalled', function() {
                setInstalledFlag();
                updateAddToHomeButton();
            });
            
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
            
            // Listen for appinstalled event even if DOM was already loaded
            window.addEventListener('appinstalled', function() {
                setInstalledFlag();
                updateAddToHomeButton();
            });
        }
    })();
    </script>
    
    <!-- Events Calendar Modal -->
    <?php if (hasRole('ADMIN')): ?>
    <div id="eventsCalendarModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
        <div style="max-width: 900px; margin: 2rem auto; background: white; border-radius: 8px; padding: 2rem; position: relative;">
            <button id="closeCalendarModal" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 2rem; cursor: pointer; color: #666;">&times;</button>
            <h2 style="margin-top: 0;">Events Calendar</h2>
            <div style="margin-bottom: 1rem;">
                <input type="text" id="calendarSearch" placeholder="Search events..." style="width: 100%; padding: 0.5rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 4px;">
            </div>
            <div id="calendarContainer"></div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarBtn = document.getElementById('eventsCalendarBtn');
        const modal = document.getElementById('eventsCalendarModal');
        const closeBtn = document.getElementById('closeCalendarModal');
        
        if (calendarBtn && modal) {
            calendarBtn.addEventListener('click', function() {
                modal.style.display = 'block';
                loadEventsCalendar();
            });
        }
        
        if (closeBtn && modal) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
        
        // Close on outside click
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    });
    
    
    let currentCalendarYear = new Date().getFullYear();
    let currentCalendarMonth = new Date().getMonth();
    
    function renderMonth(year, month, eventsByDate) {
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        
        const monthYearEl = document.getElementById('calendar-month-year');
        if (monthYearEl) {
            monthYearEl.innerHTML = `
                <button onclick="changeCalendarMonth(-1)" style="background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 0 1rem;">&lt;</button>
                ${monthNames[month]} ${year}
                <button onclick="changeCalendarMonth(1)" style="background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 0 1rem;">&gt;</button>
            `;
        }
        
        const daysContainer = document.getElementById('calendar-days');
        if (!daysContainer) return;
        daysContainer.innerHTML = '';
        
        // Empty cells for days before month starts
        for (let i = 0; i < firstDay; i++) {
            const cell = document.createElement('div');
            cell.style.padding = '0.5rem';
            daysContainer.appendChild(cell);
        }
        
        // Days of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const cell = document.createElement('div');
            cell.style.padding = '0.5rem';
            cell.style.border = '1px solid var(--border-color)';
            cell.style.borderRadius = '4px';
            cell.style.minHeight = '60px';
            cell.style.cursor = 'pointer';
            
            const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const dayEvents = eventsByDate[dateStr] || [];
            
            if (dayEvents.length > 0) {
                cell.style.backgroundColor = '#d4edda';
                cell.style.borderColor = '#28a745';
            }
            
            cell.innerHTML = '<div style="font-weight: bold; margin-bottom: 0.25rem;">' + day + '</div>';
            
            dayEvents.forEach(event => {
                const eventLink = document.createElement('a');
                eventLink.href = '#';
                eventLink.textContent = event.event_title;
                eventLink.style.display = 'block';
                eventLink.style.fontSize = '0.85rem';
                eventLink.style.color = '#155724';
                eventLink.style.marginTop = '0.25rem';
                eventLink.style.textDecoration = 'underline';
                eventLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    showAdvertModal(event);
                });
                cell.appendChild(eventLink);
            });
            
            daysContainer.appendChild(cell);
        }
    }
    
    window.changeCalendarMonth = function(direction) {
        currentCalendarMonth += direction;
        if (currentCalendarMonth < 0) {
            currentCalendarMonth = 11;
            currentCalendarYear--;
        } else if (currentCalendarMonth > 11) {
            currentCalendarMonth = 0;
            currentCalendarYear++;
        }
        loadEventsCalendar();
    };
    
    // Store events globally for calendar navigation
    let globalEventsData = [];
    
    function loadEventsCalendar() {
        fetch('<?= baseUrl('/api/business-adverts.php?action=events') ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    globalEventsData = data.events;
                    renderCalendar(data.events);
                } else {
                    console.error('Failed to load events:', data.error);
                    document.getElementById('calendarContainer').innerHTML = '<p>No events found.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading events:', error);
                document.getElementById('calendarContainer').innerHTML = '<p>Error loading events. Please try again.</p>';
            });
    }
    
    function renderCalendar(events) {
        const container = document.getElementById('calendarContainer');
        if (!container) return;
        
        // Group events by date
        const eventsByDate = {};
        events.forEach(event => {
            const date = event.event_date;
            if (!eventsByDate[date]) {
                eventsByDate[date] = [];
            }
            eventsByDate[date].push(event);
        });
        
        // Generate calendar HTML
        let html = '<div id="calendar-month-year" style="text-align: center; margin-bottom: 1rem; font-size: 1.2rem; font-weight: bold;"></div>';
        html += '<div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; margin-bottom: 1rem;">';
        html += '<div style="text-align: center; font-weight: bold; padding: 0.5rem;">Sun</div>';
        html += '<div style="text-align: center; font-weight: bold; padding: 0.5rem;">Mon</div>';
        html += '<div style="text-align: center; font-weight: bold; padding: 0.5rem;">Tue</div>';
        html += '<div style="text-align: center; font-weight: bold; padding: 0.5rem;">Wed</div>';
        html += '<div style="text-align: center; font-weight: bold; padding: 0.5rem;">Thu</div>';
        html += '<div style="text-align: center; font-weight: bold; padding: 0.5rem;">Fri</div>';
        html += '<div style="text-align: center; font-weight: bold; padding: 0.5rem;">Sat</div>';
        html += '</div>';
        
        html += '<div id="calendar-days" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem;"></div>';
        
        container.innerHTML = html;
        
        // Render calendar with current month/year
        renderMonth(currentCalendarYear, currentCalendarMonth, eventsByDate);
    }
    
    function showAdvertModal(event) {
        // Remove any existing modal first
        const existingModal = document.getElementById('advert-modal-overlay');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Create modal for displaying advert
        const modal = document.createElement('div');
        modal.id = 'advert-modal-overlay';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001; display: flex; align-items: center; justify-content: center;';
        
        const modalContent = document.createElement('div');
        modalContent.style.cssText = 'max-width: 600px; background: white; border-radius: 8px; padding: 2rem; position: relative; max-height: 90vh; overflow-y: auto;';
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 2rem; cursor: pointer; color: #666; padding: 0; width: 2rem; height: 2rem; line-height: 2rem; text-align: center;';
        closeBtn.addEventListener('click', function() {
            modal.remove();
        });
        
        modalContent.appendChild(closeBtn);
        
        const title = document.createElement('h3');
        title.textContent = event.event_title || 'Event';
        title.style.marginTop = '0';
        modalContent.appendChild(title);
        
        const eventDate = document.createElement('p');
        eventDate.innerHTML = '<strong>Event Date:</strong> ' + (event.event_date || 'N/A');
        modalContent.appendChild(eventDate);
        
        const businessName = document.createElement('p');
        businessName.innerHTML = '<strong>Business:</strong> ' + (event.business_name || 'N/A');
        modalContent.appendChild(businessName);
        
        if (event.banner_image) {
            const bannerImg = document.createElement('img');
            bannerImg.src = '<?= baseUrl('/') ?>' + event.banner_image;
            bannerImg.style.cssText = 'max-width: 100%; margin-top: 1rem; display: block;';
            bannerImg.onerror = function() {
                this.style.display = 'none';
            };
            modalContent.appendChild(bannerImg);
        }
        
        if (event.display_image) {
            const displayImg = document.createElement('img');
            displayImg.src = '<?= baseUrl('/') ?>' + event.display_image;
            displayImg.style.cssText = 'max-width: 100%; margin-top: 1rem; display: block;';
            displayImg.onerror = function() {
                this.style.display = 'none';
            };
            modalContent.appendChild(displayImg);
        }
        
        modal.appendChild(modalContent);
        
        // Close modal when clicking outside (on the backdrop)
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
        
        // Close modal with Escape key
        const escapeHandler = function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                modal.remove();
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);
        
        document.body.appendChild(modal);
    }
    </script>
    <?php endif; ?>
    
    <main class="main-content">

