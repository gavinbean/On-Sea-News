// Main JavaScript for On-Sea News Community Website


// Initialize when DOM is ready
function initMenuHandlers() {
    // Hamburger menu toggle
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');
    
    if (hamburger && navMenu) {
        function positionMenu() {
            if (window.innerWidth <= 768) {
                const hamburgerRect = hamburger.getBoundingClientRect();
                // Align to left edge of viewport (accounting for container padding)
                const leftPos = 20; // Match container padding
                const topPos = hamburgerRect.bottom + 8;
                
                // Force left alignment
                navMenu.style.setProperty('position', 'fixed', 'important');
                navMenu.style.setProperty('left', leftPos + 'px', 'important');
                navMenu.style.setProperty('top', topPos + 'px', 'important');
                navMenu.style.setProperty('right', 'auto', 'important');
                navMenu.style.setProperty('margin-left', '0', 'important');
                navMenu.style.setProperty('margin-right', 'auto', 'important');
                
            } else {
                navMenu.style.removeProperty('position');
                navMenu.style.removeProperty('left');
                navMenu.style.removeProperty('top');
                navMenu.style.removeProperty('right');
            }
        }
        
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navMenu.classList.toggle('active');
            if (navMenu.classList.contains('active')) {
                // Use requestAnimationFrame to ensure DOM is updated
                requestAnimationFrame(function() {
                    positionMenu();
                });
            } else {
                navMenu.style.removeProperty('position');
                navMenu.style.removeProperty('left');
                navMenu.style.removeProperty('top');
                navMenu.style.removeProperty('right');
            }
        });
        
        // Update menu position on window resize
        window.addEventListener('resize', function() {
            if (navMenu.classList.contains('active')) {
                positionMenu();
            }
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                navMenu.classList.remove('active');
                navMenu.style.removeProperty('position');
                navMenu.style.removeProperty('left');
                navMenu.style.removeProperty('top');
                navMenu.style.removeProperty('right');
            }
        });
    }
    
    // Profile icon dropdown toggle
    const profileIconBtn = document.getElementById('profileIconBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    
    if (profileIconBtn && profileDropdown) {
        profileIconBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileIconBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
    
    // Water menu - JavaScript solution for desktop hover and mobile click
    const waterMenuItems = document.querySelectorAll('.water-menu-item');
    
    if (waterMenuItems.length > 0) {
        waterMenuItems.forEach(function(menuItem) {
            const toggle = menuItem.querySelector('.water-menu-toggle');
            const submenu = menuItem.querySelector('.water-submenu');
            
            if (submenu) {
                let hideTimeout;
                
                // Only apply on desktop (width > 768px)
                function isDesktop() {
                    return window.innerWidth > 768;
                }
                
                // Desktop hover handling - only attach if desktop
                if (isDesktop()) {
                    // Show submenu on mouseenter
                    menuItem.addEventListener('mouseenter', function() {
                        if (window.innerWidth > 768) {
                            clearTimeout(hideTimeout);
                            submenu.style.display = 'block';
                            submenu.style.visibility = 'visible';
                            submenu.style.opacity = '1';
                            submenu.style.pointerEvents = 'auto';
                        }
                    });
                    
                    // Hide submenu on mouseleave with delay
                    menuItem.addEventListener('mouseleave', function(e) {
                        if (window.innerWidth > 768) {
                            // Check if mouse is moving to submenu
                            const relatedTarget = e.relatedTarget;
                            if (relatedTarget && (submenu.contains(relatedTarget) || submenu === relatedTarget)) {
                                return; // Don't hide if moving to submenu
                            }
                            
                            hideTimeout = setTimeout(function() {
                                submenu.style.display = 'none';
                                submenu.style.visibility = 'hidden';
                                submenu.style.opacity = '0';
                                submenu.style.pointerEvents = 'none';
                            }, 300); // 300ms delay to allow mouse movement
                        }
                    });
                    
                    // Keep submenu visible when hovering over it
                    submenu.addEventListener('mouseenter', function() {
                        if (window.innerWidth > 768) {
                            clearTimeout(hideTimeout);
                            submenu.style.display = 'block';
                            submenu.style.visibility = 'visible';
                            submenu.style.opacity = '1';
                            submenu.style.pointerEvents = 'auto';
                        }
                    });
                    
                    // Hide when leaving submenu
                    submenu.addEventListener('mouseleave', function() {
                        if (window.innerWidth > 768) {
                            hideTimeout = setTimeout(function() {
                                submenu.style.display = 'none';
                                submenu.style.visibility = 'hidden';
                                submenu.style.opacity = '0';
                                submenu.style.pointerEvents = 'none';
                            }, 300);
                        }
                    });
                }
                
                // Mobile click handling - support both click and touch events
                if (toggle) {
                    // Handle click event
                    toggle.addEventListener('click', function(e) {
                        if (window.innerWidth <= 768) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            const isActive = menuItem.classList.contains('active');
                            
                            handleMobileMenuToggle(menuItem, submenu, waterMenuItems);
                        }
                    });
                    
                    // Handle touch events for mobile
                    toggle.addEventListener('touchend', function(e) {
                        if (window.innerWidth <= 768) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            const isActive = menuItem.classList.contains('active');
                            
                            handleMobileMenuToggle(menuItem, submenu, waterMenuItems);
                        }
                    });
                }
                
                // Mobile menu toggle handler function
                function handleMobileMenuToggle(menuItem, submenu, waterMenuItems) {
                    const isActive = menuItem.classList.contains('active');
                    
                    // Close all other water menus
                    waterMenuItems.forEach(function(item) {
                        if (item !== menuItem) {
                            item.classList.remove('active');
                            // Clear any inline styles that might interfere
                            const otherSubmenu = item.querySelector('.water-submenu');
                            if (otherSubmenu) {
                                otherSubmenu.style.removeProperty('display');
                                otherSubmenu.style.removeProperty('visibility');
                                otherSubmenu.style.removeProperty('opacity');
                                otherSubmenu.style.removeProperty('pointer-events');
                            }
                        }
                    });
                    
                    // Toggle this menu
                    if (isActive) {
                        menuItem.classList.remove('active');
                        // Clear inline styles to let CSS handle it
                        submenu.style.removeProperty('display');
                        submenu.style.removeProperty('visibility');
                        submenu.style.removeProperty('opacity');
                        submenu.style.removeProperty('pointer-events');
                    } else {
                        menuItem.classList.add('active');
                        // Force inline styles to override any CSS
                        submenu.style.display = 'block';
                        submenu.style.visibility = 'visible';
                        submenu.style.opacity = '1';
                        submenu.style.pointerEvents = 'auto';
                        submenu.style.position = 'static';
                    }
                }
            }
        });
        
        // Close water menu when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                let clickedInsideWaterMenu = false;
                waterMenuItems.forEach(function(menuItem) {
                    if (menuItem.contains(e.target)) {
                        clickedInsideWaterMenu = true;
                    }
                });
                
                if (!clickedInsideWaterMenu) {
                    waterMenuItems.forEach(function(menuItem) {
                        menuItem.classList.remove('active');
                    });
                }
            }
        });
    }
    
    // Load business menu
    loadBusinessMenu();
    
    // Load advertisements
    loadAdvertisements();
}

// Run initialization when DOM is ready
(function() {
    try {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    initMenuHandlers();
                } catch (e) {
                    console.error('Error initializing menu handlers:', e);
                }
            });
        } else {
            // DOM is already ready
            try {
                initMenuHandlers();
            } catch (e) {
                console.error('Error initializing menu handlers:', e);
                // Try again after a short delay in case elements aren't ready yet
                setTimeout(function() {
                    try {
                        initMenuHandlers();
                    } catch (e2) {
                        console.error('Error initializing menu handlers (retry):', e2);
                    }
                }, 100);
            }
        }
    } catch (e) {
        console.error('Error setting up menu handlers:', e);
    }
})();

// Load business menu
function loadBusinessMenu() {
    const menuContainer = document.getElementById('businessMenuContainer');
    const menu = document.getElementById('businessMenu');
    
    if (!menuContainer || !menu) return;
    
    fetch('/api/business-menu.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.categories) {
                data.categories.forEach(category => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = '#';
                    a.textContent = category.category_name;
                    
                    if (category.businesses && category.businesses.length > 0) {
                        const submenu = document.createElement('ul');
                        submenu.className = 'business-submenu';
                        
                        category.businesses.forEach(business => {
                            const subLi = document.createElement('li');
                            const subA = document.createElement('a');
                            subA.href = `/business-view.php?id=${business.business_id}`;
                            subA.textContent = business.business_name;
                            subLi.appendChild(subA);
                            submenu.appendChild(subLi);
                        });
                        
                        li.appendChild(a);
                        li.appendChild(submenu);
                    } else {
                        li.appendChild(a);
                    }
                    
                    menu.appendChild(li);
                });
                
                menuContainer.classList.add('active');
            }
        })
        .catch(error => {
            console.error('Error loading business menu:', error);
        });
}

// Load and rotate advertisements
let currentAdIndex = 0;
let advertisements = [];

function loadAdvertisements() {
    const sidebar = document.getElementById('advertSidebar');
    if (!sidebar) return;
    
    fetch('/api/advertisements.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.advertisements && data.advertisements.length > 0) {
                advertisements = data.advertisements;
                displayAdvertisement(0);
                
                // Rotate advertisements (default 5000ms if not set)
                const rotationInterval = window.ADVERT_ROTATION_INTERVAL || 5000;
                setInterval(rotateAdvertisement, rotationInterval);
            } else {
                sidebar.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading advertisements:', error);
            sidebar.style.display = 'none';
        });
}

function displayAdvertisement(index) {
    const sidebar = document.getElementById('advertSidebar');
    if (!sidebar || !advertisements[index]) return;
    
    const ad = advertisements[index];
    const adHtml = `
        <div class="advert-item">
            <a href="/advert-click.php?id=${ad.advert_id}&url=${encodeURIComponent(ad.advert_url || '#')}" target="_blank">
                <img src="/${ad.advert_image}" alt="${ad.advert_title || 'Advertisement'}">
            </a>
        </div>
    `;
    
    sidebar.innerHTML = adHtml;
}

function rotateAdvertisement() {
    if (advertisements.length === 0) return;
    
    currentAdIndex = (currentAdIndex + 1) % advertisements.length;
    displayAdvertisement(currentAdIndex);
}


