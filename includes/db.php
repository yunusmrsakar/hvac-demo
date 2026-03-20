<?php
/**
 * CoolBreeze HVAC – Database Initialisation (SQLite via PDO)
 *
 * NOTE: The data/ directory must be writable by the web-server process.
 *   chmod 775 data/
 * The SQLite file (hvac.db) will be created automatically on first run.
 */

require_once __DIR__ . '/config.php';

/** @var PDO|null Singleton instance */
$_db_instance = null;

/**
 * Returns a PDO singleton connected to the SQLite database.
 * Creates the schema on first run.
 */
function getDB(): PDO
{
    global $_db_instance;

    if ($_db_instance !== null) {
        return $_db_instance;
    }

    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode=WAL;');
        $pdo->exec('PRAGMA foreign_keys=ON;');

        // Create schema if it does not exist yet
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS bookings (
                id            INTEGER  PRIMARY KEY AUTOINCREMENT,
                reference     TEXT     UNIQUE NOT NULL,
                service_type  TEXT     NOT NULL,
                customer_name TEXT     NOT NULL,
                phone         TEXT     NOT NULL,
                email         TEXT     NOT NULL,
                preferred_date TEXT    NOT NULL,
                time_slot     TEXT     NOT NULL,
                address       TEXT     NOT NULL,
                notes         TEXT,
                status        TEXT     DEFAULT 'pending',
                admin_notes   TEXT,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $_db_instance = $pdo;
        return $pdo;

    } catch (PDOException $e) {
        // Surface the error clearly during development
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
    }
}
