<?php
/**
 * Webhook SasPay — configurer l’URL dans le dashboard SasPay.
 * Events : transaction.success / transaction.failed
 */
require_once __DIR__ . '/includes/functions.php';
ensure_reservation_payment_schema();

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    http_response_code(200);
    echo 'OK';
    exit;
}

$raw = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
$timestamp = (string) ($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '0');
$eventHeader = (string) ($_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? '');

if (!saspay_verify_webhook_signature($raw, $signature, $timestamp)) {
    http_response_code(403);
    echo 'INVALID_SIGNATURE';
    exit;
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(200);
    echo 'BAD_JSON';
    exit;
}

$event = (string) ($payload['event'] ?? $eventHeader);
$data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
$paymentId = (string) ($data['id'] ?? '');
$status = strtoupper((string) ($data['status'] ?? ''));
$network = (string) ($data['network'] ?? 'saspay');

if ($paymentId === '') {
    http_response_code(200);
    echo 'NO_ID';
    exit;
}

if ($event === 'transaction.success' || in_array($status, ['SUCCESS', 'SUCCEEDED', 'PAID', 'COMPLETED'], true)) {
    // Toujours revérifier côté API
    $check = saspay_verify_payment($paymentId);
    $st = strtoupper((string) ($check['status'] ?? $status));
    if ($check['ok'] && in_array($st, ['SUCCESS', 'SUCCEEDED', 'PAID', 'COMPLETED'], true)) {
        $net = (string) (($check['data']['network'] ?? '') ?: $network);
        mark_reservation_paid_by_saspay($paymentId, $net);
        http_response_code(200);
        echo 'PAID';
        exit;
    }
}

http_response_code(200);
echo 'IGNORED:' . $event;
