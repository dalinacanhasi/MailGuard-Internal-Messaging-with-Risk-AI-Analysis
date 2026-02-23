<?php
declare(strict_types=1);

/**
 * GLOBAL APP CONFIG
 * Works with XAMPP (Windows), Composer, PHPMailer, Python
 */

/* =========================================================
   PHP SESSION SECURITY
   ========================================================= */
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');


session_start();

/* =========================================================
   DATABASE CONFIG (XAMPP defaults)
   ========================================================= */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mail_dashboard');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default = empty password

/* =========================================================
   APPLICATION
   ========================================================= */
define('APP_NAME', 'MailGuard Dashboard');

/* =========================================================
   SMTP CONFIG (PHPMailer)
   ⚠️ YOU MUST CHANGE THESE
   ========================================================= */
// Example for Gmail SMTP (recommended)
// If using Gmail, create an App Password in Google Account

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');      // CHANGE THIS
define('SMTP_PASS', 'your_app_password_here');    // CHANGE THIS
define('SMTP_SECURE', 'tls');                     // 'tls' or 'ssl'

/*
Alternative (SendGrid, Mailgun, etc.)
Just change the values above.
*/

/* =========================================================
   PYTHON "BRAIN" CONFIG (WINDOWS)
   ========================================================= */
// On Windows it is usually "python", NOT "python3"
define('PYTHON_BIN', 'C:\Users\dalinac\AppData\Local\Programs\Python\Python313\python.exe');

// Absolute path to your analyzer
define('PYTHON_BRAIN', __DIR__ . '/brain/analyzer_cli.py');


// Only enable secure cookies if HTTPS is actually on
if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
) {
    ini_set('session.cookie_secure', '1');
}

/* =========================================================
   ERROR REPORTING (DEV MODE)
   ========================================================= */
// Turn ON during development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// In production, set display_errors to 0