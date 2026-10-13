<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . '/app/Models/DatabaseModel.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/ApiController.php';

use App\Controllers\AuthController;
use App\Controllers\ApiController;

ApiController::process();

$auth = AuthController::handleLogin();
$login_error = $auth['error'];
$captcha_text = $auth['captcha_text'];

// Render View
require_once __DIR__ . '/app/Views/layouts/header.php';

if (empty($_SESSION['logged_in'])) {
    require_once __DIR__ . '/app/Views/login.php';
} else {
    require_once __DIR__ . '/app/Views/dashboard.php';
}

require_once __DIR__ . '/app/Views/layouts/footer.php';