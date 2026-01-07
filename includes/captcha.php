<?php
/**
 * Simple CAPTCHA Implementation
 * For production, consider using Google reCAPTCHA
 */

function generateCaptcha() {
    startSession();
    $captcha = rand(1000, 9999);
    $_SESSION['captcha_code'] = $captcha;
    $_SESSION['captcha_time'] = time();
    return $captcha;
}

function verifyCaptcha($input) {
    startSession();
    
    // CAPTCHA expires after 10 minutes
    if (!isset($_SESSION['captcha_code']) || !isset($_SESSION['captcha_time'])) {
        return false;
    }
    
    if (time() - $_SESSION['captcha_time'] > 600) {
        unset($_SESSION['captcha_code'], $_SESSION['captcha_time']);
        return false;
    }
    
    $isValid = isset($_SESSION['captcha_code']) && (int)$_SESSION['captcha_code'] === (int)$input;
    
    // Clear CAPTCHA after verification
    unset($_SESSION['captcha_code'], $_SESSION['captcha_time']);
    
    return $isValid;
}

function renderCaptchaImage($captchaCode) {
    // Create a simple image with the CAPTCHA code
    $width = 120;
    $height = 40;
    $image = imagecreate($width, $height);
    
    // Colors
    $bg = imagecolorallocate($image, 255, 255, 255);
    $text_color = imagecolorallocate($image, 0, 0, 0);
    $line_color = imagecolorallocate($image, 200, 200, 200);
    
    // Add some random lines for security
    for ($i = 0; $i < 5; $i++) {
        imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $line_color);
    }
    
    // Add the text
    imagestring($image, 5, 30, 12, $captchaCode, $text_color);
    
    // Output image
    header('Content-Type: image/png');
    imagepng($image);
    imagedestroy($image);
}



