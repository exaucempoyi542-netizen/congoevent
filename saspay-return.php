<?php
/**
 * Retour client après paiement SasPay.
 */
require_once __DIR__ . '/includes/functions.php';
require_client();
ensure_reservation_payment_schema();

$userId = (int) current_user()['id'];
$rid = (int) ($_GET['rid'] ?? 0);
$paymentId = trim((string) ($_GET['payment_id'] ?? $_GET['id'] ?? ''));

$stmt = null;
$res = null;

if ($rid > 0) {
    $stmt = db()->prepare('SELECT * FROM reservations WHERE id = ? AND utilisateur_id = ? LIMIT 1');
    $stmt->execute([$rid, $userId]);
    $res = $stmt->fetch();
}

if (!$res && $paymentId !== '') {
    $stmt = db()->prepare(
        'SELECT * FROM reservations
         WHERE utilisateur_id = ? AND (saspay_payment_id = ? OR reference_paiement = ?)
         LIMIT 1'
    );
    $stmt->execute([$userId, $paymentId, $paymentId]);
    $res = $stmt->fetch();
}

if (!$res) {
    flash('error', 'Réservation introuvable après paiement.');
    redirect('mes-reservations.php');
}

if (($res['statut_paiement'] ?? '') === 'paye') {
    flash('success', 'Paiement confirmé. Merci !');
    redirect('mes-reservations.php');
}

$pid = $paymentId !== '' ? $paymentId : (string) ($res['saspay_payment_id'] ?? $res['reference_paiement'] ?? '');
if ($pid === '') {
    flash('warning', 'Paiement en cours de confirmation. Actualisez Mes réservations dans un instant.');
    redirect('mes-reservations.php');
}

$check = saspay_verify_payment($pid);
$status = strtoupper((string) ($check['status'] ?? ''));
$network = (string) (($check['data']['network'] ?? '') ?: 'saspay');

if ($check['ok'] && in_array($status, ['SUCCESS', 'SUCCEEDED', 'PAID', 'COMPLETED'], true)) {
    mark_reservation_paid_by_saspay($pid, $network);
    flash('success', 'Paiement réussi. Référence : ' . $pid);
    redirect('mes-reservations.php');
}

if (in_array($status, ['PENDING', 'PROCESSING', 'INITIATED'], true)) {
    flash('warning', 'Paiement en attente de confirmation opérateur. Actualisez Mes réservations bientôt.');
    redirect('mes-reservations.php');
}

flash('error', 'Paiement non abouti' . ($status !== '' ? ' (' . $status . ')' : '') . '. Vous pouvez réessayer.');
redirect('paiement-reservation.php?id=' . (int) $res['id']);
