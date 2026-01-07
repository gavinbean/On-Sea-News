<?php
require_once 'includes/functions.php';
require_once 'includes/captcha.php';

startSession();
$captchaCode = generateCaptcha();
renderCaptchaImage($captchaCode);



