<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/water-questions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = registerUser($_POST);
    if ($result['success']) {
        // Save water responses if user was created
        if (isset($result['user_id']) && isset($_POST['water_responses'])) {
            $waterResponses = json_decode($_POST['water_responses'], true);
            if ($waterResponses) {
                saveWaterResponses($result['user_id'], $waterResponses);
            }
        }
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Get water questions for display
$waterQuestions = getWaterQuestions('water_info');

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('/index.php');
}

// Generate CAPTCHA
$captchaCode = generateCaptcha();

$pageTitle = 'Register';
include 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <h1>Register</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= h($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="username">Username: <span class="required">*</span></label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="email">Email: <span class="required">*</span></label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="name">Name: <span class="required">*</span></label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="surname">Surname: <span class="required">*</span></label>
                <input type="text" id="surname" name="surname" required>
            </div>
            
            <div class="form-group">
                <label for="telephone">Telephone: <span class="required">*</span></label>
                <input type="tel" id="telephone" name="telephone" required>
            </div>
            
            <div class="form-group" style="position: relative;">
                <label>Address: <span class="required">*</span></label>
                <input type="text" id="address-search" placeholder="Start typing your address..." autocomplete="off">
                <div id="address-autocomplete" class="address-autocomplete"></div>
                <small>Start typing your address and select from the suggestions</small>
            </div>
            
            <div class="form-group">
                <label for="street_number">Street Number:</label>
                <input type="text" id="street_number" name="street_number" autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="street_name">Street Name: <span class="required">*</span></label>
                <input type="text" id="street_name" name="street_name" required autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="suburb">Suburb:</label>
                <input type="text" id="suburb" name="suburb" autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="town">Town: <span class="required">*</span></label>
                <input type="text" id="town" name="town" required autocomplete="off">
            </div>
            
            <div class="form-group">
                <label for="password">Password: <span class="required">*</span></label>
                <input type="password" id="password" name="password" required minlength="8">
                <small>Minimum 8 characters</small>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirm Password: <span class="required">*</span></label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
            </div>
            
            <div class="form-group">
                <label for="captcha">CAPTCHA Code: <span class="required">*</span></label>
                <div class="captcha-container">
                    <img src="captcha-image.php" alt="CAPTCHA" id="captchaImage">
                    <button type="button" onclick="refreshCaptcha()" class="btn-captcha-refresh">Refresh</button>
                </div>
                <input type="text" id="captcha" name="captcha" required maxlength="4" pattern="[0-9]{4}">
            </div>
            
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="terms_accepted" name="terms_accepted" value="1" required>
                        I accept the <a href="<?= baseUrl('/terms.php') ?>" target="_blank">Terms and Conditions</a> <span class="required">*</span>
                    </label>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Register</button>
                </div>
        </form>
        
        <div class="auth-links">
            <a href="<?= baseUrl('/login.php') ?>">Already have an account? Login</a>
        </div>
    </div>
</div>

<script>
function refreshCaptcha() {
    document.getElementById('captchaImage').src = '<?= baseUrl('/captcha-image.php') ?>?' + new Date().getTime();
    document.getElementById('captcha').value = '';
}

// Address autocomplete functionality
(function() {
    const addressSearch = document.getElementById('address-search');
    const autocompleteDiv = document.getElementById('address-autocomplete');
    const streetNumberInput = document.getElementById('street_number');
    const streetNameInput = document.getElementById('street_name');
    const suburbInput = document.getElementById('suburb');
    const townInput = document.getElementById('town');
    const apiUrl = '<?= baseUrl('/api/address-autocomplete.php') ?>';
    let autocompleteTimeout = null;
    let selectedIndex = -1;
    let suggestions = [];

    console.log('Address autocomplete initialized');
    console.log('API URL:', apiUrl);
    console.log('Address search element:', addressSearch);
    console.log('Autocomplete div element:', autocompleteDiv);

    if (!addressSearch || !autocompleteDiv) {
        console.error('Missing required elements:', {addressSearch, autocompleteDiv});
        return;
    }

    // Hide autocomplete when clicking outside (but allow time for clicks on items)
    let hideTimeout = null;
    let isInputFocused = false;
    
    addressSearch.addEventListener('focus', function() {
        isInputFocused = true;
        clearTimeout(hideTimeout);
        // If there's a query and suggestions, show the dropdown
        const query = this.value.trim();
        if (query.length >= 3 && suggestions.length > 0) {
            autocompleteDiv.style.display = 'block';
        }
    });
    
    addressSearch.addEventListener('blur', function() {
        isInputFocused = false;
        // Delay hiding to allow clicks on suggestions
        hideTimeout = setTimeout(function() {
            if (!autocompleteDiv.matches(':hover')) {
                autocompleteDiv.style.display = 'none';
            }
        }, 200);
    });
    
    document.addEventListener('click', function(e) {
        const isClickInside = addressSearch.contains(e.target) || autocompleteDiv.contains(e.target);
        if (!isClickInside && !isInputFocused) {
            hideTimeout = setTimeout(function() {
                autocompleteDiv.style.display = 'none';
            }, 150);
        } else {
            clearTimeout(hideTimeout);
        }
    });
    
    // Keep autocomplete visible when hovering over it
    autocompleteDiv.addEventListener('mouseenter', function() {
        clearTimeout(hideTimeout);
    });
    
    autocompleteDiv.addEventListener('mouseleave', function() {
        // Only hide if input is not focused
        if (!isInputFocused) {
            hideTimeout = setTimeout(function() {
                autocompleteDiv.style.display = 'none';
            }, 200);
        }
    });

    addressSearch.addEventListener('input', function() {
        const query = this.value.trim();
        console.log('Input event, query:', query, 'length:', query.length);
        
        // Clear any hide timeouts when user is typing
        clearTimeout(hideTimeout);
        clearTimeout(autocompleteTimeout);
        
        if (query.length < 3) {
            console.log('Query too short, hiding autocomplete');
            autocompleteDiv.style.display = 'none';
            suggestions = []; // Clear suggestions
            return;
        }

        // Debounce API calls
        autocompleteTimeout = setTimeout(function() {
            console.log('Calling fetchAddressSuggestions with:', query);
            fetchAddressSuggestions(query);
        }, 300);
    });

    addressSearch.addEventListener('keydown', function(e) {
        if (autocompleteDiv.style.display === 'none' || suggestions.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, suggestions.length - 1);
            updateSelection();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            updateSelection();
        } else if (e.key === 'Enter' && selectedIndex >= 0) {
            e.preventDefault();
            selectSuggestion(suggestions[selectedIndex]);
        } else if (e.key === 'Escape') {
            autocompleteDiv.style.display = 'none';
        }
    });

    function fetchAddressSuggestions(query) {
        const url = apiUrl + '?q=' + encodeURIComponent(query);
        console.log('Fetching address suggestions from:', url);
        fetch(url)
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response content-type:', response.headers.get('content-type'));
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text.substring(0, 200));
                        throw new Error('Response is not JSON');
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('API Response:', data);
                if (data.success && data.suggestions && data.suggestions.length > 0) {
                    suggestions = data.suggestions;
                    console.log('Found', suggestions.length, 'suggestions');
                    displaySuggestions(data.suggestions);
                } else {
                    console.log('No suggestions found or empty response');
                    autocompleteDiv.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching address suggestions:', error);
                autocompleteDiv.style.display = 'none';
            });
    }

    function displaySuggestions(suggestions) {
        // Clear any hide timeouts when displaying suggestions
        clearTimeout(hideTimeout);
        
        if (suggestions.length === 0) {
            console.log('No suggestions to display');
            autocompleteDiv.style.display = 'none';
            return;
        }

        console.log('Displaying', suggestions.length, 'suggestions');
        autocompleteDiv.innerHTML = '';
        suggestions.forEach((suggestion, index) => {
            const item = document.createElement('div');
            item.className = 'autocomplete-item';
            item.textContent = suggestion.display_name || 'Unknown address';
            item.addEventListener('mousedown', function(e) {
                e.preventDefault(); // Prevent input from losing focus
                selectSuggestion(suggestion);
            });
            
            item.addEventListener('mouseenter', function() {
                selectedIndex = index;
                updateSelection();
            });
            autocompleteDiv.appendChild(item);
        });
        
        autocompleteDiv.style.display = 'block';
        console.log('Autocomplete div display set to block, z-index:', window.getComputedStyle(autocompleteDiv).zIndex);
        selectedIndex = -1;
    }

    function updateSelection() {
        const items = autocompleteDiv.querySelectorAll('.autocomplete-item');
        items.forEach((item, index) => {
            if (index === selectedIndex) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
        });
    }

    function selectSuggestion(suggestion) {
        // Clear any hide timeouts
        clearTimeout(hideTimeout);
        
        // Extract street number from search query if present and not returned by API
        let extractedStreetNumber = '';
        const searchQuery = addressSearch.value.trim();
        if (searchQuery) {
            // Try to extract street number from query (e.g., "47 Main Street" -> "47")
            const numberMatch = searchQuery.match(/^(\d+)\s+/);
            if (numberMatch) {
                extractedStreetNumber = numberMatch[1];
            }
        }
        
        // Populate separate address fields
        // Use API street_number if available, otherwise use extracted from query
        if (streetNumberInput) {
            streetNumberInput.value = suggestion.street_number || extractedStreetNumber || '';
        }
        if (streetNameInput) streetNameInput.value = suggestion.street_name || '';
        if (suburbInput) suburbInput.value = suggestion.suburb || '';
        if (townInput) townInput.value = suggestion.town || '';
        
        // Clear the search field
        addressSearch.value = '';
        autocompleteDiv.style.display = 'none';
        selectedIndex = -1;
        suggestions = [];
        
        // Focus on street name if empty, otherwise town
        if (streetNameInput && !streetNameInput.value) {
            streetNameInput.focus();
        } else if (townInput && !townInput.value) {
            townInput.focus();
        }
    }
})();

</script>

<?php include 'includes/footer.php'; ?>

