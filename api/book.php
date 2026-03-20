<?php
/**
 * CoolBreeze HVAC – API: Submit a Booking
 * Method: POST
 * Body:   JSON
 * Returns: JSON { success, reference, name } or { success, error }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

// ── Parse JSON body ──────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

// ── Validation ───────────────────────────────────────────────────────────────
$required = ['service_type', 'customer_name', 'phone', 'email', 'preferred_date', 'time_slot', 'address'];
$errors   = [];

foreach ($required as $field) {
    if (empty(trim((string)($data[$field] ?? '')))) {
        $errors[] = $field;
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing required fields: ' . implode(', ', $errors)]);
    exit;
}

// Basic e-mail check
if (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
    exit;
}

// ── Sanitise ─────────────────────────────────────────────────────────────────
$serviceType   = trim($data['service_type']);
$customerName  = trim($data['customer_name']);
$phone         = trim($data['phone']);
$email         = trim($data['email']);
$preferredDate = trim($data['preferred_date']);
$timeSlot      = trim($data['time_slot']);
$address       = trim($data['address']);
$notes         = trim($data['notes'] ?? '');

// ── Generate unique reference: CB-XXXX-DDDD ──────────────────────────────────
function generateReference(): string
{
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $alpha  = '';
    for ($i = 0; $i < 4; $i++) {
        $alpha .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $digits = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    return 'CB-' . $alpha . '-' . $digits;
}

$db = getDB();

// Ensure uniqueness (extremely unlikely collision, but handle it anyway)
$maxAttempts = 10;
$reference   = '';
for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
    $candidate = generateReference();
    $stmt = $db->prepare('SELECT id FROM bookings WHERE reference = ?');
    $stmt->execute([$candidate]);
    if (!$stmt->fetch()) {
        $reference = $candidate;
        break;
    }
}

if ($reference === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not generate a unique reference. Please try again.']);
    exit;
}

// ── Insert into DB ────────────────────────────────────────────────────────────
try {
    $stmt = $db->prepare(
        'INSERT INTO bookings
            (reference, service_type, customer_name, phone, email, preferred_date, time_slot, address, notes, status)
         VALUES
            (:reference, :service_type, :customer_name, :phone, :email, :preferred_date, :time_slot, :address, :notes, :status)'
    );

    $stmt->execute([
        ':reference'      => $reference,
        ':service_type'   => $serviceType,
        ':customer_name'  => $customerName,
        ':phone'          => $phone,
        ':email'          => $email,
        ':preferred_date' => $preferredDate,
        ':time_slot'      => $timeSlot,
        ':address'        => $address,
        ':notes'          => $notes,
        ':status'         => 'pending',
    ]);

    echo json_encode([
        'success'   => true,
        'reference' => $reference,
        'name'      => $customerName,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
