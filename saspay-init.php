<?php
/**
 * Initialise un paiement SasPay (JSON).
 */
require_once __DIR__ . '/includes/functions.php';
require_client();
ensure_reservation_payment_schema();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Méthode non autorisée.']);
    exit;
}

if (!saspay_configured()) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'SasPay n’est pas configuré. Ajoutez SASPAY_API_KEY dans config/database.php (clé sk_test_… depuis app.saspay.me).',
    ]);
    exit;
}

$input = $_POST;
$raw = file_get_contents('php://input');
if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
    }
}

$resId = (int) ($input['reservation_id'] ?? 0);
$mode = (string) ($input['mode'] ?? 'checkout'); // checkout | softpay
$network = (string) ($input['network'] ?? '');
$phone = (string) ($input['phone'] ?? '');
$userId = (int) current_user()['id'];

if ($mode !== 'softpay') {
    $mode = 'checkout';
}

$result = start_reservation_saspay($resId, $userId, $mode, $network, $phone);

if (!$result['ok']) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $result['error'] ?? 'Échec SasPay.',
        'payment_id' => $result['payment_id'] ?? null,
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'checkout_url' => $result['checkout_url'],
    'payment_id' => $result['payment_id'],
    'status' => $result['status'],
    'instructions' => $result['instructions'],
    'return_url' => absolute_url('saspay-return.php?rid=' . $resId),
]);
