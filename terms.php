<?php
require_once 'includes/functions.php';

$pageTitle = 'Terms and Conditions';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-page">
        <h1>Terms and Conditions</h1>
        
        <div class="terms-content">
            <h2>1. Acceptance of Terms</h2>
            <p>By accessing and using this website, you accept and agree to be bound by the terms and provision of this agreement.</p>
            
            <h2>2. User Accounts</h2>
            <p>Users are responsible for maintaining the confidentiality of their account credentials. You agree to notify us immediately of any unauthorized use of your account.</p>
            
            <h2>3. User Responsibilities</h2>
            <p>Users are responsible for:</p>
            <ul>
                <li>Providing accurate and complete information during registration</li>
                <li>Maintaining the security of their account</li>
                <li>All activities that occur under their account</li>
                <li>Complying with all applicable laws and regulations</li>
            </ul>
            
            <h2>4. Content and Publications</h2>
            <p>Published content on this website is the responsibility of the author. The website administrators reserve the right to review, edit, or remove content that violates these terms.</p>
            
            <h2>5. Advertisements</h2>
            <p>Advertisers are responsible for the content of their advertisements. Payment for advertisements must be made in advance. Advertisements are valid for calendar months as specified.</p>
            
            <h2>6. Water Availability Data</h2>
            <p>Water availability information is provided by community members and is for informational purposes only. The accuracy of this information cannot be guaranteed.</p>
            
            <h2>7. Privacy</h2>
            <p>We are committed to protecting your privacy. Your personal information will be used in accordance with our privacy policy and will not be shared with third parties without your consent.</p>
            
            <h2>8. Limitation of Liability</h2>
            <p>The website and its content are provided "as is" without warranties of any kind. We shall not be liable for any damages arising from the use of this website.</p>
            
            <h2>9. Changes to Terms</h2>
            <p>We reserve the right to modify these terms at any time. Continued use of the website after changes constitutes acceptance of the modified terms.</p>
            
            <h2>10. Contact</h2>
            <p>For questions about these terms, please contact the website administrators.</p>
        </div>
        
        <div class="terms-actions">
            <a href="<?= baseUrl('/register.php') ?>" class="btn btn-primary">Return to Registration</a>
            <a href="<?= baseUrl('/index.php') ?>" class="btn btn-secondary">Return to Home</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

