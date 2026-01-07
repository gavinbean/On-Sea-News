<?php
require_once 'includes/functions.php';

$pageTitle = 'Water Data Terms and Conditions';
include 'includes/header.php';
?>

<div class="container">
    <div class="content-area">
        <div class="terms-content">
            <h1>Water Data Terms and Conditions</h1>
            
            <h2>Data Collection and Use</h2>
            <p>By submitting water information through this website, you agree to the following terms:</p>
            
            <h2>Privacy and Confidentiality</h2>
            <ul>
                <li><strong>No Personal Information Disclosure:</strong> No personal questions answered or personal information will be publicly linked to any individual.</li>
                <li><strong>Aggregated Data Only:</strong> Water availability data will be displayed in aggregated form on public maps and reports.</li>
                <li><strong>Anonymous Display:</strong> Your specific answers and personal details will never be displayed publicly.</li>
            </ul>
            
            <h2>Data Usage</h2>
            <ul>
                <li>Water data collected will be used to provide community-wide water availability information.</li>
                <li>Data may be used for statistical analysis and community planning purposes.</li>
                <li>Individual responses are kept confidential and secure.</li>
            </ul>
            
            <h2>Your Rights</h2>
            <ul>
                <li>You can update or change your water information at any time by logging into your account.</li>
                <li>You can request deletion of your water data by contacting the administrator.</li>
                <li>All data is timestamped and linked to your account for your reference.</li>
            </ul>
            
            <h2>Affidavit Submission</h2>
            <p>If you indicated willingness to submit an affidavit, you may be contacted separately regarding this process. Submission of an affidavit is voluntary and separate from this data collection.</p>
            
            <h2>Agreement</h2>
            <p>By checking the agreement box, you confirm that:</p>
            <ul>
                <li>You understand that your personal information will not be publicly displayed.</li>
                <li>You consent to the collection and use of your water data as described above.</li>
                <li>You have read and understood these terms and conditions.</li>
            </ul>
            
            <div class="terms-actions">
                <a href="<?= baseUrl('/register.php') ?>" class="btn btn-primary">Back to Registration</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

