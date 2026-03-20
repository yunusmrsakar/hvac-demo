<?php
/**
 * CoolBreeze HVAC – API: Track a Booking
 * Method: GET
 * Param:  ref  (booking reference, e.g. CB-X4F2-8371)
 * Returns: JSON booking data or { success, error }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$ref = trim($_GET['ref'] ?? '');

if ($ref === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No reference number provided.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    'SELECT id, reference, service_type, customer_name, phone, email,
            preferred_date, time_slot, address, notes, status, admin_notes,
            created_at, updated_at
     FROM bookings
     WHERE reference = ?'
);
$stmt->execute([$ref]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'No booking found with that reference number.']);
    exit;
}

echo json_encode(['success' => true, 'booking' => $booking]);
