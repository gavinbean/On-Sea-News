<?php
require_once __DIR__ . '/../includes/functions.php';
requireRole('ADMIN');

$db = getDB();
$message = '';
$error = '';

$configFile = __DIR__ . '/../config/geocoding.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $provider = $_POST['provider'] ?? 'nominatim';
    $apiKey = trim($_POST['google_api_key'] ?? '');
    $useGoogleForHouseNumbers = isset($_POST['use_google_for_house_numbers']) ? 1 : 0;
    
    // Validate provider
    if (!in_array($provider, ['nominatim', 'google'])) {
        $error = 'Invalid geocoding provider selected.';
    } else {
        // Create or update config file
        $configContent = "<?php
/**
 * Geocoding Configuration
 * Configure which geocoding service to use
 */

// Geocoding provider: 'nominatim' or 'google'
define('GEOCODING_PROVIDER', '" . addslashes($provider) . "');

// Google Maps API Key (required if using Google Maps)
// Get your API key from: https://console.cloud.google.com/google/maps-apis
// Make sure to enable \"Geocoding API\" in your Google Cloud Console
define('GOOGLE_MAPS_API_KEY', '" . addslashes($apiKey) . "');

// Use Google Maps for addresses with street numbers (more accurate)
// If true, will use Google Maps when street_number is provided, Nominatim otherwise
define('USE_GOOGLE_FOR_HOUSE_NUMBERS', " . ($useGoogleForHouseNumbers ? 'true' : 'false') . ");
";
        
        if (file_put_contents($configFile, $configContent) !== false) {
            $message = 'Geocoding settings saved successfully.';
        } else {
            $error = 'Failed to save configuration file. Please check file permissions.';
        }
    }
}

// Read current settings
$currentProvider = 'nominatim';
$currentApiKey = '';
$currentUseGoogleForHouseNumbers = false;

if (file_exists($configFile)) {
    require_once $configFile;
    $currentProvider = defined('GEOCODING_PROVIDER') ? GEOCODING_PROVIDER : 'nominatim';
    $currentApiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
    $currentUseGoogleForHouseNumbers = defined('USE_GOOGLE_FOR_HOUSE_NUMBERS') ? USE_GOOGLE_FOR_HOUSE_NUMBERS : false;
}

$pageTitle = 'Geocoding Settings';
include __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <h1>Geocoding Settings</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= h($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <div class="settings-info">
            <h2>Geocoding Provider Options</h2>
            
            <div class="provider-option">
                <h3>Nominatim (OpenStreetMap)</h3>
                <ul>
                    <li><strong>Cost:</strong> Free, no API key required</li>
                    <li><strong>Accuracy:</strong> Good for street-level, may lack house number data in some areas</li>
                    <li><strong>Rate Limits:</strong> 1 request per second (respected automatically)</li>
                    <li><strong>Best for:</strong> General use, unlimited free requests</li>
                </ul>
            </div>
            
            <div class="provider-option">
                <h3>Google Maps Geocoding API</h3>
                <ul>
                    <li><strong>Cost:</strong> Free tier: $200/month credit (~40,000 requests/month)</li>
                    <li><strong>Accuracy:</strong> Excellent, includes house number data</li>
                    <li><strong>Rate Limits:</strong> Higher limits than Nominatim</li>
                    <li><strong>Best for:</strong> When you need precise house-level geocoding</li>
                    <li><strong>Setup Required:</strong> 
                        <ol>
                            <li>Get API key from <a href="https://console.cloud.google.com/google/maps-apis" target="_blank">Google Cloud Console</a></li>
                            <li>Enable "Geocoding API" in your project</li>
                            <li>Set up billing (you won't be charged if you stay within $200/month)</li>
                            <li>Enter API key below</li>
                        </ol>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="settings-form">
            <h2>Configure Geocoding</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="form-group">
                    <label for="provider">Geocoding Provider: <span class="required">*</span></label>
                    <select id="provider" name="provider" required onchange="toggleGoogleSettings()">
                        <option value="nominatim" <?= $currentProvider === 'nominatim' ? 'selected' : '' ?>>Nominatim (OpenStreetMap) - Free</option>
                        <option value="google" <?= $currentProvider === 'google' ? 'selected' : '' ?>>Google Maps - Free tier available</option>
                    </select>
                </div>
                
                <div class="form-group" id="google-settings">
                    <label for="google_api_key">Google Maps API Key:</label>
                    <input type="text" id="google_api_key" name="google_api_key" value="<?= h($currentApiKey) ?>" placeholder="Enter your Google Maps API key">
                    <small>
                        Get your API key from <a href="https://console.cloud.google.com/google/maps-apis" target="_blank">Google Cloud Console</a>.
                        Make sure to enable "Geocoding API" in your project.
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="use_google_for_house_numbers" value="1" <?= $currentUseGoogleForHouseNumbers ? 'checked' : '' ?>>
                        Use Google Maps for addresses with street numbers (hybrid mode)
                    </label>
                    <small>
                        If checked, addresses with street numbers will use Google Maps (if API key is set), 
                        while addresses without street numbers will use Nominatim. This gives you the best of both worlds.
                    </small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
        
        <div class="test-geocoding">
            <h2>Test Geocoding</h2>
            <form method="POST" action="" id="testForm">
                <input type="hidden" name="action" value="test_geocoding">
                
                <div class="form-group">
                    <label for="test_street_number">Street Number:</label>
                    <input type="text" id="test_street_number" name="test_street_number" placeholder="47">
                </div>
                
                <div class="form-group">
                    <label for="test_street_name">Street Name: <span class="required">*</span></label>
                    <input type="text" id="test_street_name" name="test_street_name" required placeholder="Main Street">
                </div>
                
                <div class="form-group">
                    <label for="test_town">Town: <span class="required">*</span></label>
                    <input type="text" id="test_town" name="test_town" required placeholder="Bushman's River Mouth">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-secondary">Test Geocoding</button>
                </div>
            </form>
            
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_geocoding'):
                require_once __DIR__ . '/../includes/geocoding.php';
                
                $testResult = validateAndGeocodeAddress([
                    'street_number' => $_POST['test_street_number'] ?? '',
                    'street_name' => $_POST['test_street_name'] ?? '',
                    'town' => $_POST['test_town'] ?? ''
                ]);
            ?>
                <div class="test-results">
                    <h3>Test Results</h3>
                    <?php if ($testResult['success']): ?>
                        <div class="alert alert-success">
                            <strong>Success!</strong><br>
                            <strong>Coordinates:</strong> <?= number_format($testResult['latitude'], 6) ?>, <?= number_format($testResult['longitude'], 6) ?><br>
                            <strong>Formatted Address:</strong> <?= h($testResult['formatted_address']) ?><br>
                            <?php if (isset($testResult['approximate']) && $testResult['approximate']): ?>
                                <span class="approximate-warning">⚠️ Approximate location</span><br>
                            <?php endif; ?>
                            <?php if (isset($testResult['has_house_number'])): ?>
                                <strong>House Number in Result:</strong> <?= $testResult['has_house_number'] ? 'Yes ✓' : 'No ✗' ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-error">
                            <strong>Failed:</strong> <?= h($testResult['message'] ?? 'Unknown error') ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleGoogleSettings() {
    const provider = document.getElementById('provider').value;
    const googleSettings = document.getElementById('google-settings');
    if (provider === 'google') {
        googleSettings.style.display = 'block';
        document.getElementById('google_api_key').required = true;
    } else {
        googleSettings.style.display = 'block'; // Still show for hybrid mode
        document.getElementById('google_api_key').required = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleGoogleSettings);
</script>

<style>
.settings-info {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.provider-option {
    margin-bottom: 2rem;
    padding: 1rem;
    background-color: var(--bg-color);
    border-radius: 4px;
}

.provider-option h3 {
    margin-top: 0;
    color: var(--primary-color);
}

.provider-option ul {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.provider-option ol {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.settings-form,
.test-geocoding {
    background-color: var(--white);
    padding: 2rem;
    border-radius: 8px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.test-results {
    margin-top: 1.5rem;
    padding: 1rem;
    background-color: var(--bg-color);
    border-radius: 4px;
}
</style>

<?php 
$hideAdverts = true;
include __DIR__ . '/../includes/footer.php'; 
?>


