<?php
/**
 * CoolBreeze HVAC – Application Configuration
 */

// Database path (SQLite file)
define('DB_PATH', __DIR__ . '/../data/hvac.db');

// Admin credentials
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123');

// Application name
define('APP_NAME', 'CoolBreeze HVAC');

// Base URL helper (no trailing slash)
define('BASE_URL', rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/'));
