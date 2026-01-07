// Main JavaScript for On-Sea News Community Website

document.addEventListener('DOMContentLoaded', function() {
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
                
                console.log('Menu positioned at left:', leftPos, 'top:', topPos);
            } else {
                navMenu.style.removeProperty('position');
                navMenu.style.removeProperty('left');
                navMenu.style.removeProperty('top');
                navMenu.style.removeProperty('right');
            }
        }
        
        hamburger.addEventListener('click', function(e) {
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
    
    // Load business menu
    loadBusinessMenu();
    
    // Load advertisements
    loadAdvertisements();
});

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


