<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/water-questions.php';
requireLogin();

$user = getCurrentUser();
$userId = getCurrentUserId();
$message = '';
$error = '';
$editMode = isset($_GET['edit']) || isset($_POST['action']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $result = updateUserProfile($userId, $_POST);
        if ($result['success']) {
            $message = $result['message'];
            $editMode = false;
            // Reload user data
            $user = getCurrentUser();
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'save_water_info') {
        // Save water information responses
        $waterResponses = [];
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'water_q') === 0) {
                $questionId = str_replace('water_q', '', $key);
                // Handle checkbox arrays
                if (is_array($value)) {
                    $waterResponses[$questionId] = $value;
                } else {
                    $waterResponses[$questionId] = $value;
                }
            }
        }
        
        $result = saveWaterResponses($userId, $waterResponses);
        if ($result['success']) {
            $message = 'Water information saved successfully.';
        } else {
            $error = $result['message'] ?? 'Failed to save water information.';
        }
    }
}

// Get water questions and user's existing responses
$waterQuestions = getWaterQuestions('water_info', $userId);
$userWaterResponses = getUserWaterResponses($userId, 'water_info');

$pageTitle = 'My Profile';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>My Profile</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <!-- Profile Tabs -->
        <div class="profile-tabs">
            <button type="button" class="tab-button active" data-tab="personal">Personal Information</button>
            <button type="button" class="tab-button" data-tab="water">Water Information</button>
        </div>
        
        <!-- Personal Information Tab -->
        <div class="tab-content active" id="tab-personal">
            <?php if ($editMode): ?>
            <div class="profile-edit-form">
                <h2>Edit Profile Information</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" value="<?= h($user['username']) ?>" disabled>
                        <small>Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?= h($user['email']) ?>" disabled>
                        <small>Email cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="name">Name: <span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="<?= h($user['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="surname">Surname: <span class="required">*</span></label>
                        <input type="text" id="surname" name="surname" value="<?= h($user['surname']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone">Telephone: <span class="required">*</span></label>
                        <input type="tel" id="telephone" name="telephone" value="<?= h($user['telephone']) ?>" required>
                    </div>
                    
                    <div class="form-group" style="position: relative;">
                        <label>Address Search:</label>
                        <input type="text" id="address-search" placeholder="Start typing your address..." autocomplete="off">
                        <div id="address-autocomplete" class="address-autocomplete"></div>
                        <small>Start typing your address and select from the suggestions</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="street_number">Street Number:</label>
                        <input type="text" id="street_number" name="street_number" value="<?= h($user['street_number'] ?? '') ?>" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="street_name">Street Name: <span class="required">*</span></label>
                        <input type="text" id="street_name" name="street_name" value="<?= h($user['street_name'] ?? '') ?>" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="suburb">Suburb:</label>
                        <input type="text" id="suburb" name="suburb" value="<?= h($user['suburb'] ?? '') ?>" autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="town">Town: <span class="required">*</span></label>
                        <input type="text" id="town" name="town" value="<?= h($user['town'] ?? '') ?>" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?= baseUrl('/profile.php') ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="profile-info">
                <div style="margin-bottom: 1rem;">
                    <a href="?edit=1" class="btn btn-primary">Edit Profile</a>
                </div>
                
                <h2>Account Information</h2>
                <p><strong>Username:</strong> <?= h($user['username']) ?></p>
                <p><strong>Email:</strong> <?= h($user['email']) ?></p>
                <p><strong>Name:</strong> <?= h($user['name'] . ' ' . $user['surname']) ?></p>
                <p><strong>Telephone:</strong> <?= h($user['telephone']) ?></p>
                <p><strong>Address:</strong> 
                    <?php 
                    $addressParts = [];
                    if (!empty($user['street_number'])) $addressParts[] = $user['street_number'];
                    if (!empty($user['street_name'])) $addressParts[] = $user['street_name'];
                    if (!empty($user['suburb'])) $addressParts[] = $user['suburb'];
                    if (!empty($user['town'])) $addressParts[] = $user['town'];
                    echo h(implode(', ', $addressParts) ?: 'Not provided');
                    ?>
                </p>
                <?php if (!empty($user['latitude']) && !empty($user['longitude'])): ?>
                    <p><strong>Location:</strong> <?= number_format($user['latitude'], 6) ?>, <?= number_format($user['longitude'], 6) ?></p>
                <?php endif; ?>
                <p><strong>Member since:</strong> <?= formatDate($user['created_at'], 'F j, Y') ?></p>
            </div>
        <?php endif; ?>
        
        <div class="profile-roles">
            <h2>My Roles</h2>
            <?php
            $roles = getUserRoles($user['user_id']);
            if (empty($roles)): ?>
                <p>No roles assigned.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($roles as $role): ?>
                        <li><?= h($role['role_name']) ?> - <?= h($role['role_description']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        </div>
        
        <!-- Water Information Tab -->
        <div class="tab-content" id="tab-water">
            <div class="water-information-section">
            <h2>Water Information</h2>
            <p class="info-text">Complete this section if you want to report your water availability. You must accept the terms and conditions to submit water availability reports.</p>
            
            <form method="POST" action="" id="waterInfoForm">
                <input type="hidden" name="action" value="save_water_info">
                
                <div id="water-questions-container">
                    <?php foreach ($waterQuestions as $question): 
                        $userResponse = $userWaterResponses[$question['question_id']] ?? null;
                        $existingValue = $userResponse['response_value'] ?? '';
                    ?>
                        <div class="form-group water-question" 
                             data-question-id="<?= $question['question_id'] ?>"
                             <?php if ($question['depends_on_question_id']): ?>
                                 data-depends-on="<?= $question['depends_on_question_id'] ?>"
                                 data-depends-on-value="<?= h($question['depends_on_answer_value']) ?>"
                                 style="display: none;"
                             <?php endif; ?>>
                            <label for="water_q<?= $question['question_id'] ?>">
                                <?= h($question['question_text']) ?>
                                <?php if ($question['is_required']): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($question['question_type'] === 'dropdown'): ?>
                                <select id="water_q<?= $question['question_id'] ?>" 
                                        name="water_q<?= $question['question_id'] ?>" 
                                        class="water-response"
                                        data-question-id="<?= $question['question_id'] ?>"
                                        <?= $question['is_required'] ? 'required' : '' ?>>
                                    <option value="">Select an option</option>
                                    <?php foreach ($question['options'] as $option): ?>
                                        <option value="<?= h($option['option_value']) ?>" <?= ($existingValue == $option['option_value']) ? 'selected' : '' ?>>
                                            <?= h($option['option_text']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            
                            <?php elseif ($question['question_type'] === 'checkbox'): ?>
                                <div class="checkbox-group">
                                    <?php 
                                    $existingValues = $existingValue ? explode(',', $existingValue) : [];
                                    foreach ($question['options'] as $option): 
                                    ?>
                                        <label class="checkbox-label">
                                            <input type="checkbox" 
                                                   name="water_q<?= $question['question_id'] ?>[]" 
                                                   value="<?= h($option['option_value']) ?>"
                                                   class="water-response"
                                                   data-question-id="<?= $question['question_id'] ?>"
                                                   <?= in_array($option['option_value'], $existingValues) ? 'checked' : '' ?>
                                                   <?= $question['is_required'] && $option['display_order'] == 1 ? 'required' : '' ?>>
                                            <?= h($option['option_text']) ?>
                                            <?php if ($question['terms_link']): ?>
                                                <a href="<?= baseUrl($question['terms_link']) ?>" target="_blank">(View Terms)</a>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            
                            <?php elseif ($question['question_type'] === 'radio'): ?>
                                <div class="radio-group">
                                    <?php foreach ($question['options'] as $option): ?>
                                        <label class="radio-label">
                                            <input type="radio" 
                                                   name="water_q<?= $question['question_id'] ?>" 
                                                   value="<?= h($option['option_value']) ?>"
                                                   class="water-response"
                                                   data-question-id="<?= $question['question_id'] ?>"
                                                   <?= ($existingValue == $option['option_value']) ? 'checked' : '' ?>
                                                   <?= $question['is_required'] ? 'required' : '' ?>>
                                            <?= h($option['option_text']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($question['help_text']): ?>
                                <small><?= h($question['help_text']) ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Water Information</button>
                </div>
            </form>
            </div>
        </div>
    </div>
</div>

<script>
// Tab functionality
(function() {
    const tabs = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    let currentTab = 0;
    
    function showTab(index) {
        tabs.forEach((tab, i) => {
            if (i === index) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        tabContents.forEach((content, i) => {
            if (i === index) {
                content.classList.add('active');
            } else {
                content.classList.remove('active');
            }
        });
        
        currentTab = index;
    }
    
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', function() {
            showTab(index);
        });
    });
    
    // Check if URL hash indicates which tab to show
    function checkHashAndShowTab() {
        if (window.location.hash === '#water-tab') {
            showTab(1); // Show water information tab (index 1)
        }
    }
    
    // Check on page load (with a small delay to ensure DOM is ready)
    setTimeout(checkHashAndShowTab, 100);
    
    // Also check if hash changes (in case user navigates with back button)
    window.addEventListener('hashchange', checkHashAndShowTab);
})();

// Handle water question dependencies
(function() {
    function updateQuestionVisibility() {
        const answers = {};
        
        // Collect all current answers
        document.querySelectorAll('.water-response').forEach(function(input) {
            const questionId = input.dataset.questionId;
            if (input.type === 'checkbox') {
                if (!answers[questionId]) answers[questionId] = [];
                if (input.checked) {
                    answers[questionId].push(input.value);
                }
            } else if (input.checked || input.value) {
                answers[questionId] = input.value;
            }
        });
        
        // Show/hide dependent questions
        document.querySelectorAll('.water-question').forEach(function(questionDiv) {
            const dependsOn = questionDiv.dataset.dependsOn;
            const dependsOnValue = questionDiv.dataset.dependsOnValue;
            
            if (dependsOn && dependsOnValue) {
                const answer = answers[dependsOn];
                if (Array.isArray(answer)) {
                    questionDiv.style.display = answer.includes(dependsOnValue) ? 'block' : 'none';
                } else {
                    questionDiv.style.display = (answer == dependsOnValue) ? 'block' : 'none';
                }
            } else {
                questionDiv.style.display = 'block';
            }
        });
    }
    
    // Update visibility when answers change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('water-response')) {
            updateQuestionVisibility();
        }
    });
    
    // Initial visibility update
    updateQuestionVisibility();
})();
</script>

<?php if ($editMode): ?>
<script>
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
<?php endif; ?>

<?php 
$hideAdverts = false;
include 'includes/footer.php'; 
?>

