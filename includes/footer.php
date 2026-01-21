    </main>
    
    <footer class="main-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>. All rights reserved.</p>
            <p><a href="<?= baseUrl('/terms.php') ?>">Terms and Conditions</a></p>
        </div>
    </footer>
    
    <!-- Google Ads Bottom Banner (Visible to All Users) -->
    <div class="google-ads-banner" id="google-ads-banner">
        <!-- Mobile: 1 ad -->
        <div class="google-ad-item mobile-ad">
            <ins class="adsbygoogle"
                 style="display:inline-block;width:400px;height:60px"
                 data-ad-client="ca-pub-9986887492350930"
                 data-ad-slot="9601907202"></ins>
        </div>
        <!-- Desktop: 3 ads side by side -->
        <div class="google-ad-item desktop-ad-1">
            <ins class="adsbygoogle"
                 style="display:inline-block;width:400px;height:60px"
                 data-ad-client="ca-pub-9986887492350930"
                 data-ad-slot="9601907202"></ins>
        </div>
        <div class="google-ad-item desktop-ad-2">
            <ins class="adsbygoogle"
                 style="display:inline-block;width:400px;height:60px"
                 data-ad-client="ca-pub-9986887492350930"
                 data-ad-slot="9601907202"></ins>
        </div>
        <div class="google-ad-item desktop-ad-3">
            <ins class="adsbygoogle"
                 style="display:inline-block;width:400px;height:60px"
                 data-ad-client="ca-pub-9986887492350930"
                 data-ad-slot="9601907202"></ins>
        </div>
    </div>
    <script>
        // Push ads to Google AdSense
        (adsbygoogle = window.adsbygoogle || []).push({});
        (adsbygoogle = window.adsbygoogle || []).push({});
        (adsbygoogle = window.adsbygoogle || []).push({});
        (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
    
    <script>
        // Check if ads have loaded and adjust banner visibility
        function checkAndHideAdBanner() {
            const adBanner = document.getElementById('google-ads-banner');
            if (!adBanner) return;
            
            const adElements = adBanner.querySelectorAll('.adsbygoogle');
            let hasLoadedAd = false;
            let stillLoading = false;
            
            adElements.forEach(function(adElement) {
                const adStatus = adElement.getAttribute('data-adsbygoogle-status');
                const isAdReady = adStatus === 'done';
                const isAdLoading = adStatus === 'loading';
                const adHeight = adElement.offsetHeight;
                const adWidth = adElement.offsetWidth;
                const adIframe = adElement.querySelector('iframe');
                const hasIframeContent = adIframe && adIframe.offsetHeight > 0 && adIframe.offsetWidth > 0;
                
                if (isAdLoading || !adStatus) {
                    stillLoading = true;
                }
                
                if (isAdReady && (hasIframeContent || (adHeight >= 20 && adWidth >= 20))) {
                    hasLoadedAd = true;
                }
            });
            
            // Always show banner initially, only hide if ads have definitely failed after reasonable time
                // Set padding based on screen size
                if (window.innerWidth <= 768) {
                    document.body.style.paddingBottom = '68px'; // Mobile: match top banner (60px + 8px padding)
                    // Add iOS safe area if needed
                    const safeArea = getComputedStyle(document.documentElement).getPropertyValue('env(safe-area-inset-bottom)') || '0px';
                    if (safeArea !== '0px') {
                        document.body.style.paddingBottom = 'calc(68px + ' + safeArea + ')';
                    }
                } else {
                    document.body.style.paddingBottom = '76px'; // Desktop: 60px ad + 16px padding
                }
            
            // If ads are still loading, keep banner visible
            if (stillLoading) {
                adBanner.style.display = 'flex';
                document.body.classList.add('ad-banner-visible');
                document.body.classList.remove('no-ad-banner');
                return;
            }
            
            // If at least one ad has loaded, show banner
            if (hasLoadedAd) {
                adBanner.style.display = 'flex';
                document.body.classList.add('ad-banner-visible');
                document.body.classList.remove('no-ad-banner');
            } else {
                // Ads finished loading but none have content - wait a bit longer before hiding
                // This gives ads more time to render, especially on slow connections
            }
        }
        
        // Show banner immediately and set padding
        const adBanner = document.getElementById('google-ads-banner');
        if (adBanner) {
            adBanner.style.display = 'flex';
            document.body.classList.add('ad-banner-visible');
            if (window.innerWidth <= 768) {
                document.body.style.paddingBottom = '68px'; // Match top banner (60px + 8px padding)
                const safeArea = getComputedStyle(document.documentElement).getPropertyValue('env(safe-area-inset-bottom)') || '0px';
                if (safeArea !== '0px') {
                    document.body.style.paddingBottom = 'calc(68px + ' + safeArea + ')';
                }
            } else {
                document.body.style.paddingBottom = '76px';
            }
        }
        
        // Run checks after page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(checkAndHideAdBanner, 3000);
            });
        } else {
            setTimeout(checkAndHideAdBanner, 3000);
        }
        
        setTimeout(checkAndHideAdBanner, 5000);
        setTimeout(checkAndHideAdBanner, 10000);
        setTimeout(checkAndHideAdBanner, 20000); // Longer wait for slow connections
        
        window.addEventListener('load', checkAndHideAdBanner);
        window.addEventListener('resize', checkAndHideAdBanner);
        window.addEventListener('orientationchange', function() {
            setTimeout(checkAndHideAdBanner, 500);
        });
        
        // Use MutationObserver to watch for ad content changes
        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(function(mutations) {
                checkAndHideAdBanner();
            });
            
            const adBanner = document.getElementById('google-ads-banner');
            if (adBanner) {
                observer.observe(adBanner, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style', 'class', 'data-adsbygoogle-status']
                });
            }
        }
    </script>
    
    <script src="<?= baseUrl('/js/main.js') ?>?v=<?= filemtime(__DIR__ . '/../js/main.js') ?>"></script>
<script>
// Load admin notification counts
<?php if (hasRole('ADMIN')): ?>
function loadAdminNotificationCounts() {
    fetch('<?= baseUrl('/api/admin-pending-counts.php') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update pending businesses
                const pendingBusinessesBadge = document.getElementById('pendingBusinessesBadge');
                const pendingBusinessesIcon = document.getElementById('pendingBusinessesIcon');
                if (pendingBusinessesBadge && pendingBusinessesIcon) {
                    pendingBusinessesBadge.textContent = data.pendingBusinesses;
                    if (data.pendingBusinesses > 0) {
                        pendingBusinessesIcon.style.display = 'flex';
                    } else {
                        pendingBusinessesIcon.style.display = 'none';
                    }
                }
                
                // Update new contact messages
                const newContactMessagesBadge = document.getElementById('newContactMessagesBadge');
                const newContactMessagesIcon = document.getElementById('newContactMessagesIcon');
                if (newContactMessagesBadge && newContactMessagesIcon) {
                    newContactMessagesBadge.textContent = data.newContactMessages;
                    if (data.newContactMessages > 0) {
                        newContactMessagesIcon.style.display = 'flex';
                    } else {
                        newContactMessagesIcon.style.display = 'none';
                    }
                }
                
                // Update pending adverts
                const pendingAdvertsBadge = document.getElementById('pendingAdvertsBadge');
                const pendingAdvertsIcon = document.getElementById('pendingAdvertsIcon');
                if (pendingAdvertsBadge && pendingAdvertsIcon) {
                    pendingAdvertsBadge.textContent = data.pendingAdverts;
                    if (data.pendingAdverts > 0) {
                        pendingAdvertsIcon.style.display = 'flex';
                    } else {
                        pendingAdvertsIcon.style.display = 'none';
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error loading admin notification counts:', error);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    loadAdminNotificationCounts();
    // Refresh counts every 30 seconds
    setInterval(loadAdminNotificationCounts, 30000);
});
<?php endif; ?>

// Load top banners for admin
<?php if (hasRole('ADMIN')): ?>
document.addEventListener('DOMContentLoaded', function() {
    loadTopBanners();
});

let allBanners = [];
let currentBannerIndex = 0;
let bannerRotationInterval = null;

function loadTopBanners() {
    const container = document.getElementById('topBanners');
    if (!container) return;
    
    fetch('<?= baseUrl('/api/top-banners.php') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.banners && data.banners.length > 0) {
                allBanners = data.banners;
                currentBannerIndex = 0;
                
                // Clear any existing rotation
                if (bannerRotationInterval) {
                    clearInterval(bannerRotationInterval);
                }
                
                // Display initial banners
                displayBanners();
                
                // Start rotation if there are more banners than can be displayed
                const isMobile = window.innerWidth <= 768;
                const bannersToShow = isMobile ? 1 : 3;
                
                if (allBanners.length > bannersToShow) {
                    bannerRotationInterval = setInterval(function() {
                        rotateBanners();
                    }, 10000); // 10 seconds
                }
            } else {
                // Hide container if no banners
                const bannerContainer = document.getElementById('topBannerContainer');
                if (bannerContainer) {
                    bannerContainer.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error loading top banners:', error);
            const bannerContainer = document.getElementById('topBannerContainer');
            if (bannerContainer) {
                bannerContainer.style.display = 'none';
            }
        });
}

function displayBanners() {
    const container = document.getElementById('topBanners');
    if (!container || allBanners.length === 0) return;
    
    const isMobile = window.innerWidth <= 768;
    const bannersToShow = isMobile ? 1 : 3;
    
    container.innerHTML = '';
    
    // Show the appropriate number of banners starting from current index
    for (let i = 0; i < bannersToShow && i < allBanners.length; i++) {
        const bannerIndex = (currentBannerIndex + i) % allBanners.length;
        const banner = allBanners[bannerIndex];
        
        const bannerItem = document.createElement('div');
        bannerItem.className = 'top-banner-item';
        bannerItem.style.cursor = 'pointer';
        bannerItem.onclick = function() {
            showAdvertModal(banner);
        };
        
        const img = document.createElement('img');
        img.src = '<?= baseUrl('/') ?>' + banner.banner_image;
        img.alt = banner.business_name || 'Banner ' + (bannerIndex + 1);
        img.className = 'top-banner-image';
        img.onerror = function() {
            this.style.display = 'none';
        };
        
        bannerItem.appendChild(img);
        container.appendChild(bannerItem);
    }
}

function rotateBanners() {
    if (allBanners.length === 0) return;
    
    const isMobile = window.innerWidth <= 768;
    const bannersToShow = isMobile ? 1 : 3;
    
    // Move to next set of banners
    currentBannerIndex = (currentBannerIndex + bannersToShow) % allBanners.length;
    
    // Update display
    displayBanners();
}

// Handle window resize to restart rotation with correct number of banners
let resizeTimeout;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(function() {
        if (allBanners.length > 0) {
            // Clear existing rotation
            if (bannerRotationInterval) {
                clearInterval(bannerRotationInterval);
            }
            
            // Reset to start
            currentBannerIndex = 0;
            displayBanners();
            
            // Restart rotation if needed
            const isMobile = window.innerWidth <= 768;
            const bannersToShow = isMobile ? 1 : 3;
            
            if (allBanners.length > bannersToShow) {
                bannerRotationInterval = setInterval(function() {
                    rotateBanners();
                }, 10000); // 10 seconds
            }
        }
    }, 250);
});

function showAdvertModal(advert) {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.className = 'advert-modal-overlay';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;';
    
    // Create modal content
    const modalContent = document.createElement('div');
    modalContent.className = 'advert-modal-content';
    modalContent.style.cssText = 'background: white; border-radius: 8px; padding: 20px; max-width: 90%; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';
    
    // Close button
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('type', 'button');
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.9); border: 2px solid #ddd; border-radius: 50%; font-size: 2rem; cursor: pointer; color: #666; width: 44px; height: 44px; line-height: 40px; text-align: center; z-index: 10001; padding: 0; min-width: 44px; min-height: 44px;';
    closeBtn.onclick = function() {
        document.body.removeChild(modal);
        document.body.style.overflow = '';
    };
    
    // Build modal content
    let content = '<div style="max-width: 800px; margin: 0 auto;">';
    
    // Display image
    if (advert.display_image) {
        content += '<div style="margin-bottom: 20px; text-align: center;">';
        content += '<img src="<?= baseUrl('/') ?>' + escapeHtml(advert.display_image) + '" alt="' + escapeHtml(advert.business_name || 'Advertisement') + '" style="max-width: 100%; height: auto; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
        content += '</div>';
    }
    
    // Business name
    if (advert.business_name) {
        content += '<h2 style="margin-top: 0; margin-bottom: 15px; color: var(--primary-color);">' + escapeHtml(advert.business_name) + '</h2>';
    }
    
    // Event info (if events type)
    if (advert.advert_type === 'events' && advert.event_title) {
        content += '<div style="background: #f5f5f5; padding: 15px; border-radius: 4px; margin-bottom: 15px;">';
        content += '<h3 style="margin: 0 0 10px 0; color: var(--primary-color);">' + escapeHtml(advert.event_title) + '</h3>';
        if (advert.event_date) {
            const eventDate = new Date(advert.event_date + 'T00:00:00');
            content += '<p style="margin: 0; color: #666;"><strong>Event Date:</strong> ' + eventDate.toLocaleDateString('en-ZA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) + '</p>';
        }
        content += '</div>';
    }
    
    // Description
    if (advert.description) {
        content += '<div style="margin-bottom: 15px;">';
        content += '<p style="line-height: 1.6; color: #333;">' + escapeHtml(advert.description) + '</p>';
        content += '</div>';
    }
    
    // Contact information
    content += '<div style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 20px;">';
    if (advert.telephone) {
        content += '<p style="margin: 5px 0;"><strong>Telephone:</strong> <a href="tel:' + escapeHtml(advert.telephone) + '">' + escapeHtml(advert.telephone) + '</a></p>';
    }
    if (advert.email) {
        content += '<p style="margin: 5px 0;"><strong>Email:</strong> <a href="mailto:' + escapeHtml(advert.email) + '">' + escapeHtml(advert.email) + '</a></p>';
    }
    if (advert.website) {
        content += '<p style="margin: 5px 0;"><strong>Website:</strong> <a href="' + escapeHtml(advert.website) + '" target="_blank" rel="noopener">' + escapeHtml(advert.website) + '</a></p>';
    }
    if (advert.address) {
        content += '<p style="margin: 5px 0;"><strong>Address:</strong> ' + escapeHtml(advert.address) + '</p>';
    }
    content += '</div>';
    
    // Follow checkbox (only if logged in and has business_id)
    <?php if (isLoggedIn()): ?>
    if (advert.business_id) {
        content += '<div style="border-top: 1px solid #ddd; padding-top: 15px; margin-top: 20px;">';
        content += '<label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">';
        content += '<input type="checkbox" id="follow-business-' + advert.business_id + '" style="margin-top: 3px; cursor: pointer;" onchange="toggleBusinessFollow(' + advert.business_id + ', this.checked)">';
        content += '<div>';
        content += '<strong>Follow</strong>';
        content += '<p style="margin: 5px 0 0 0; font-size: 0.9rem; color: #666;">By ticking Follow I agree to having emails sent to me when adverts are changed</p>';
        content += '</div>';
        content += '</label>';
        content += '</div>';
    }
    <?php endif; ?>
    
    // Business link
    if (advert.business_id) {
        content += '<div style="margin-top: 20px; text-align: center;">';
        content += '<a href="<?= baseUrl('/business-view.php?id=') ?>' + advert.business_id + '" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 4px;">View Business</a>';
        content += '</div>';
    }
    
    content += '</div>';
    
    modalContent.innerHTML = content;
    modalContent.appendChild(closeBtn);
    modal.appendChild(modalContent);
    
    // Load follow status if logged in
    <?php if (isLoggedIn()): ?>
    if (advert.business_id) {
        checkFollowStatus(advert.business_id);
    }
    <?php endif; ?>
    
    // Close on backdrop click
    modal.onclick = function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
            document.body.style.overflow = '';
        }
    };
    
    // Close on escape key
    function handleEscape(e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            document.body.removeChild(modal);
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleEscape);
        }
    }
    document.addEventListener('keydown', handleEscape);
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
    document.body.appendChild(modal);
}

<?php if (isLoggedIn()): ?>
function checkFollowStatus(businessId) {
    fetch('<?= baseUrl('/api/business-follow.php') ?>?business_id=' + businessId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const checkbox = document.getElementById('follow-business-' + businessId);
                if (checkbox) {
                    checkbox.checked = data.following;
                }
            }
        })
        .catch(error => {
            console.error('Error checking follow status:', error);
        });
}

function toggleBusinessFollow(businessId, isFollowing) {
    const action = isFollowing ? 'follow' : 'unfollow';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('business_id', businessId);
    
    fetch('<?= baseUrl('/api/business-follow.php') ?>', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Optionally show a message
                console.log(data.message);
            } else {
                // Revert checkbox on error
                const checkbox = document.getElementById('follow-business-' + businessId);
                if (checkbox) {
                    checkbox.checked = !isFollowing;
                }
                alert('Error: ' + (data.error || 'Failed to update follow status'));
            }
        })
        .catch(error => {
            console.error('Error toggling follow:', error);
            // Revert checkbox on error
            const checkbox = document.getElementById('follow-business-' + businessId);
            if (checkbox) {
                checkbox.checked = !isFollowing;
            }
            alert('Error updating follow status. Please try again.');
        });
}
<?php endif; ?>

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
<?php endif; ?>
</script>
    <?php if (isset($additionalScripts)): ?>
        <?php foreach ($additionalScripts as $script): ?>
            <script src="<?= h($script) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

