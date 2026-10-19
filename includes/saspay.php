<?php
/**
 * Intégration SasPay (API REST v1).
 * @see https://docs.saspay.me
 */

function saspay_configured(): bool
{
    $key = SASPAY_API_KEY;
    return $key !== '' && (str_starts_with($key, 'sk_test_') || str_starts_with($key, 'sk_live_') || strlen($key) > 10);
}

/** URL absolue (return_url / webhook). */
function absolute_url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if (APP_PUBLIC_URL !== '') {
        return rtrim(APP_PUBLIC_URL, '/') . '/' . $path;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = rtrim(APP_URL, '/');

    return $scheme . '://' . $host . $base . '/' . $path;
}

function saspay_currency(string $devise): string
{
    return strtoupper($devise) === 'USD' ? 'USD' : 'CDF';
}

/** Montant au format décimal string attendu par SasPay. */
function saspay_amount_string(float $amount): string
{
    return number_format(max(0, $amount), 2, '.', '');
}

/**
 * @return array{ok:bool,http:int,data:?array,raw:string,error:?string}
 */
function saspay_request(string $method, string $endpoint, ?array $payload = null, ?string $idempotencyKey = null): array
{
    $url = 'https://api.saspay.me/api/v1/' . ltrim($endpoint, '/');
    $headers = [
        'Authorization: Bearer ' . SASPAY_API_KEY,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if ($idempotencyKey) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $json = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'http' => 0, 'data' => null, 'raw' => '', 'error' => 'cURL PHP requis pour SasPay.'];
    }

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
    ];
    if ($json !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
        $opts[CURLOPT_POSTFIELDS] = $json;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'http' => $http, 'data' => null, 'raw' => '', 'error' => $cerr ?: 'Erreur réseau SasPay.'];
    }

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'http' => $http, 'data' => null, 'raw' => (string) $raw, 'error' => 'Réponse SasPay invalide.'];
    }

    return ['ok' => true, 'http' => $http, 'data' => $data, 'raw' => (string) $raw, 'error' => null];
}

function saspay_split_name(string $fullName): array
{
    $fullName = trim(preg_replace('/\s+/', ' ', $fullName) ?? '');
    if ($fullName === '') {
        return ['first' => 'Client', 'last' => 'CongoEvents'];
    }
    $parts = explode(' ', $fullName, 2);
    return ['first' => $parts[0], 'last' => $parts[1] ?? $parts[0]];
}

/**
 * Crée une session checkout hébergée SasPay.
 * @return array{ok:bool,checkout_url:?string,payment_id:?string,error:?string}
 */
function saspay_create_checkout(array $opts): array
{
    if (!saspay_configured()) {
        return [
            'ok' => false,
            'checkout_url' => null,
            'payment_id' => null,
            'error' => 'SasPay n’est pas configuré. Ajoutez SASPAY_API_KEY (sk_test_… ou sk_live_…) dans config/database.php.',
        ];
    }

    $payload = [
        'amount' => (string) ($opts['amount'] ?? '0.00'),
        'currency' => (string) ($opts['currency'] ?? 'CDF'),
        'description' => (string) ($opts['description'] ?? 'Paiement Congo Events'),
        'customer_email' => (string) ($opts['customer_email'] ?? ''),
        'customer_name' => (string) ($opts['customer_name'] ?? 'Client'),
        'return_url' => (string) ($opts['return_url'] ?? absolute_url('saspay-return.php')),
    ];

    if (!empty($opts['metadata'])) {
        $payload['metadata'] = $opts['metadata'];
    }

    $res = saspay_request('POST', 'checkout-sessions/', $payload, $opts['idempotency_key'] ?? null);
    if (!$res['ok'] || !is_array($res['data'])) {
        return ['ok' => false, 'checkout_url' => null, 'payment_id' => null, 'error' => $res['error'] ?? 'Échec SasPay.'];
    }

    $body = $res['data'];
    $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
    $checkoutUrl = $data['checkout_url'] ?? null;
    $id = $data['id'] ?? null;

    if (($body['success'] ?? false) || $res['http'] === 201 || (is_string($checkoutUrl) && $checkoutUrl !== '')) {
        if (is_string($checkoutUrl) && $checkoutUrl !== '') {
            return [
                'ok' => true,
                'checkout_url' => $checkoutUrl,
                'payment_id' => is_string($id) ? $id : null,
                'error' => null,
            ];
        }
    }

    $msg = (string) ($body['message'] ?? $body['error'] ?? $data['message'] ?? 'Création checkout refusée.');
    if (isset($body['errors']) && is_array($body['errors'])) {
        $msg .= ' ' . json_encode($body['errors'], JSON_UNESCAPED_UNICODE);
    }

    return ['ok' => false, 'checkout_url' => null, 'payment_id' => $id, 'error' => $msg];
}

/**
 * Softpay Mobile Money RDC.
 * @return array{ok:bool,checkout_url:?string,payment_id:?string,status:?string,instructions:?string,error:?string}
 */
function saspay_softpay(array $opts): array
{
    if (!saspay_configured()) {
        return [
            'ok' => false,
            'checkout_url' => null,
            'payment_id' => null,
            'status' => null,
            'instructions' => null,
            'error' => 'SasPay n’est pas configuré.',
        ];
    }

    $payload = [
        'amount' => (string) ($opts['amount'] ?? '0.00'),
        'currency' => (string) ($opts['currency'] ?? 'CDF'),
        'country' => (string) ($opts['country'] ?? 'CD'),
        'network' => (string) ($opts['network'] ?? 'orange_cd'),
        'description' => (string) ($opts['description'] ?? 'Paiement Congo Events'),
        'customer' => [
            'email' => (string) ($opts['email'] ?? ''),
            'first_name' => (string) ($opts['first_name'] ?? 'Client'),
            'last_name' => (string) ($opts['last_name'] ?? 'CongoEvents'),
            'phone' => (string) ($opts['phone'] ?? ''),
        ],
    ];

    if (!empty($opts['return_url'])) {
        $payload['return_url'] = $opts['return_url'];
    }

    $res = saspay_request('POST', 'payments/softpay/', $payload, $opts['idempotency_key'] ?? null);
    if (!$res['ok'] || !is_array($res['data'])) {
        return [
            'ok' => false,
            'checkout_url' => null,
            'payment_id' => null,
            'status' => null,
            'instructions' => null,
            'error' => $res['error'] ?? 'Échec softpay SasPay.',
        ];
    }

    $body = $res['data'];
    $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
    $id = $data['id'] ?? null;
    $status = $data['status'] ?? null;
    $checkoutUrl = $data['checkout_url'] ?? '';
    $instructions = $data['instructions'] ?? ($data['message'] ?? '');

    if ($res['http'] >= 200 && $res['http'] < 300 && $id) {
        return [
            'ok' => true,
            'checkout_url' => is_string($checkoutUrl) && $checkoutUrl !== '' ? $checkoutUrl : null,
            'payment_id' => (string) $id,
            'status' => is_string($status) ? $status : 'PENDING',
            'instructions' => is_string($instructions) ? $instructions : null,
            'error' => null,
        ];
    }

    $msg = (string) ($body['message'] ?? $data['message'] ?? 'Softpay refusé.');
    return [
        'ok' => false,
        'checkout_url' => null,
        'payment_id' => $id ? (string) $id : null,
        'status' => null,
        'instructions' => null,
        'error' => $msg,
    ];
}

/** Vérifie un paiement chez SasPay. */
function saspay_verify_payment(string $paymentId): array
{
    $res = saspay_request('GET', 'payments/' . rawurlencode($paymentId) . '/verify/');
    if (!$res['ok'] || !is_array($res['data'])) {
        return ['ok' => false, 'status' => null, 'data' => null, 'error' => $res['error'] ?? 'Vérification impossible.'];
    }
    $body = $res['data'];
    $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
    $status = strtoupper((string) ($data['status'] ?? ''));

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => null];
}

function saspay_verify_webhook_signature(string $rawBody, string $signature, string $timestamp): bool
{
    if (SASPAY_WEBHOOK_SECRET === '') {
        return true; // secret non configuré : on s’appuie sur verify API
    }
    if (abs(time() - (int) $timestamp) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, SASPAY_WEBHOOK_SECRET);
    return hash_equals($expected, $signature);
}

function mark_reservation_paid_by_saspay(string $paymentId, string $network = 'saspay'): bool
{
    ensure_reservation_payment_schema();

    $stmt = db()->prepare(
        'SELECT r.*, s.tarif_jour, s.devise
         FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE r.reference_paiement = ? OR r.saspay_payment_id = ? OR r.cinetpay_trans_id = ?
         LIMIT 1'
    );
    $stmt->execute([$paymentId, $paymentId, $paymentId]);
    $res = $stmt->fetch();
    if (!$res) {
        return false;
    }
    if (($res['statut_paiement'] ?? '') === 'paye') {
        return true;
    }
    if (($res['statut'] ?? '') !== 'confirmee') {
        return false;
    }

    $pay = reservation_payment_amount($res, $res);
    $moyen = 'saspay';
    $n = strtolower($network);
    if (str_contains($n, 'orange')) {
        $moyen = 'orange_money';
    } elseif (str_contains($n, 'vodacom') || str_contains($n, 'mpesa')) {
        $moyen = 'mpesa';
    } elseif (str_contains($n, 'airtel')) {
        $moyen = 'airtel_money';
    } elseif (str_contains($n, 'card')) {
        $moyen = 'carte';
    }

    db()->prepare(
        "UPDATE reservations
         SET mode_paiement = 'en_ligne',
             statut_paiement = 'paye',
             moyen_paiement = ?,
             reference_paiement = ?,
             saspay_payment_id = ?,
             date_paiement = NOW(),
             montant_paiement = ?
         WHERE id = ?"
    )->execute([$moyen, $paymentId, $paymentId, $pay['amount'], (int) $res['id']]);

    return true;
}

/**
 * Démarre un paiement SasPay pour une réservation.
 * @return array{ok:bool,checkout_url:?string,payment_id:?string,status:?string,instructions:?string,error:?string}
 */
function start_reservation_saspay(int $reservationId, int $userId, string $mode = 'checkout', string $network = '', string $phone = ''): array
{
    ensure_reservation_payment_schema();

    $stmt = db()->prepare(
        "SELECT r.*, s.tarif_jour, s.devise, s.nom AS salle_nom,
                u.nom AS client_nom, u.email AS client_email, u.telephone AS client_tel
         FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         JOIN utilisateurs u ON u.id = r.utilisateur_id
         WHERE r.id = ? AND r.utilisateur_id = ?"
    );
    $stmt->execute([$reservationId, $userId]);
    $res = $stmt->fetch();
    if (!$res) {
        return ['ok' => false, 'checkout_url' => null, 'payment_id' => null, 'status' => null, 'instructions' => null, 'error' => 'Réservation introuvable.'];
    }
    if (($res['statut'] ?? '') !== 'confirmee') {
        return ['ok' => false, 'checkout_url' => null, 'payment_id' => null, 'status' => null, 'instructions' => null, 'error' => 'Réservation non confirmée.'];
    }
    if (($res['statut_paiement'] ?? '') === 'paye') {
        return ['ok' => false, 'checkout_url' => null, 'payment_id' => null, 'status' => null, 'instructions' => null, 'error' => 'Déjà payée.'];
    }

    $pay = reservation_payment_amount($res, $res);
    $currency = saspay_currency($pay['devise']);
    $amount = saspay_amount_string($pay['amount']);
    $names = saspay_split_name((string) ($res['client_nom'] ?? 'Client'));
    $idem = 'res-' . $reservationId . '-' . bin2hex(random_bytes(8));
    $returnUrl = absolute_url('saspay-return.php?rid=' . $reservationId);

    if ($mode === 'softpay') {
        $allowed = ['orange_cd', 'airtel_cd', 'vodacom_cd', 'card'];
        if (!in_array($network, $allowed, true)) {
            $network = 'orange_cd';
        }
        $phone = trim($phone) !== '' ? $phone : (string) ($res['client_tel'] ?? '');
        if ($phone === '' && $network !== 'card') {
            return ['ok' => false, 'checkout_url' => null, 'payment_id' => null, 'status' => null, 'instructions' => null, 'error' => 'Indiquez un numéro de téléphone Mobile Money.'];
        }
        if ($network === 'card') {
            $currency = 'USD';
            // Si tarif en FC, on envoie quand même le montant tel quel ; SasPay convertit si besoin
        }

        $soft = saspay_softpay([
            'amount' => $amount,
            'currency' => $currency,
            'country' => 'CD',
            'network' => $network,
            'description' => 'Reservation ' . ($res['salle_nom'] ?? '') . ' #' . $reservationId,
            'email' => (string) ($res['client_email'] ?? ''),
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'phone' => $phone !== '' ? $phone : '+243000000000',
            'return_url' => $returnUrl,
            'idempotency_key' => $idem,
        ]);

        if ($soft['ok'] && !empty($soft['payment_id'])) {
            db()->prepare(
                "UPDATE reservations
                 SET mode_paiement = 'en_ligne',
                     moyen_paiement = 'saspay',
                     reference_paiement = ?,
                     saspay_payment_id = ?,
                     montant_paiement = ?
                 WHERE id = ?"
            )->execute([$soft['payment_id'], $soft['payment_id'], $pay['amount'], $reservationId]);
        }

        return $soft;
    }

    // Checkout hébergé (par défaut)
    $session = saspay_create_checkout([
        'amount' => $amount,
        'currency' => $currency,
        'description' => 'Reservation #' . $reservationId . ' — ' . ($res['salle_nom'] ?? ''),
        'customer_email' => (string) ($res['client_email'] ?? ''),
        'customer_name' => (string) ($res['client_nom'] ?? 'Client'),
        'return_url' => $returnUrl,
        'idempotency_key' => $idem,
        'metadata' => ['reservation_id' => (string) $reservationId],
    ]);

    if ($session['ok'] && !empty($session['payment_id'])) {
        db()->prepare(
            "UPDATE reservations
             SET mode_paiement = 'en_ligne',
                 moyen_paiement = 'saspay',
                 reference_paiement = ?,
                 saspay_payment_id = ?,
                 montant_paiement = ?
             WHERE id = ?"
        )->execute([$session['payment_id'], $session['payment_id'], $pay['amount'], $reservationId]);
    }

    return [
        'ok' => $session['ok'],
        'checkout_url' => $session['checkout_url'],
        'payment_id' => $session['payment_id'],
        'status' => $session['ok'] ? 'PENDING' : null,
        'instructions' => null,
        'error' => $session['error'],
    ];
}
