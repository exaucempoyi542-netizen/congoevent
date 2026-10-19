<?php
require_once __DIR__ . '/../includes/functions.php';
require_proprietaire();
ensure_reservation_payment_schema();

$userId = (int) current_user()['id'];
$statutFilter = $_GET['statut'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['res_id'], $_POST['action'])) {
    $rid = (int) $_POST['res_id'];
    $action = $_POST['action'];

    $stmt = db()->prepare(
        "SELECT r.*, r.utilisateur_id AS client_id, s.id AS salle_id FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE r.id = ? AND s.proprietaire_id = ? AND r.statut = 'en_attente'"
    );
    $stmt->execute([$rid, $userId]);
    $res = $stmt->fetch();

    if ($res) {
        if ($action === 'confirm') {
            $result = confirm_reservation_and_block($rid);
            if ($result['ok']) {
                flash('success', 'Réservation validée. Notification envoyée au client (app + Gmail).');
            } else {
                flash('error', $result['error'] ?? 'Impossible de confirmer.');
            }
        } elseif ($action === 'refuse') {
            $motif = trim($_POST['motif_refus'] ?? 'Non disponible.');
            $result = refuse_reservation_and_notify($rid, $motif);
            if ($result['ok']) {
                flash('success', 'Réservation refusée. Notification envoyée au client.');
            } else {
                flash('error', $result['error'] ?? 'Impossible de refuser.');
            }
        }
    }
    redirect('proprietaire/reservations.php' . ($statutFilter ? '?statut=' . urlencode($statutFilter) : ''));
}

$sql = "SELECT r.*, s.nom AS salle_nom, s.id AS salle_id, s.tarif_jour, s.devise,
               u.nom AS client_nom, u.email AS client_email, u.id AS client_id
        FROM reservations r
        JOIN salles s ON s.id = r.salle_id
        JOIN utilisateurs u ON u.id = r.utilisateur_id
        WHERE s.proprietaire_id = ?";
$params = [$userId];

if ($statutFilter !== '' && in_array($statutFilter, ['en_attente', 'confirmee', 'refusee', 'annulee'], true)) {
    $sql .= ' AND r.statut = ?';
    $params[] = $statutFilter;
}
$sql .= ' ORDER BY r.date_demande DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$countStmt = db()->prepare(
    "SELECT r.statut, COUNT(*) AS n
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     WHERE s.proprietaire_id = ?
     GROUP BY r.statut"
);
$countStmt->execute([$userId]);
$counts = ['total' => 0, 'en_attente' => 0, 'confirmee' => 0, 'refusee' => 0, 'annulee' => 0];
foreach ($countStmt->fetchAll() as $row) {
    $counts[$row['statut']] = (int) $row['n'];
    $counts['total'] += (int) $row['n'];
}

$pageTitle = 'Réservations';
$propPage = 'reservations';
require __DIR__ . '/../includes/proprietaire-header.php';
?>

<div class="res-summary">
  <article class="res-summary-card">
    <span>Total</span>
    <strong><?= (int) $counts['total'] ?></strong>
  </article>
  <article class="res-summary-card res-summary-warn">
    <span>En attente</span>
    <strong><?= (int) $counts['en_attente'] ?></strong>
  </article>
  <article class="res-summary-card res-summary-ok">
    <span>Confirmées</span>
    <strong><?= (int) $counts['confirmee'] ?></strong>
  </article>
</div>

<div class="dash-panel">
  <div class="dash-toolbar" style="margin-bottom:1rem;display:flex;gap:.5rem;flex-wrap:wrap">
    <a class="res-filter <?= $statutFilter === '' ? 'is-active' : '' ?>" href="<?= e(url('proprietaire/reservations.php')) ?>">Toutes</a>
    <a class="res-filter <?= $statutFilter === 'en_attente' ? 'is-active' : '' ?>" href="<?= e(url('proprietaire/reservations.php?statut=en_attente')) ?>">En attente <em><?= (int) $counts['en_attente'] ?></em></a>
    <a class="res-filter <?= $statutFilter === 'confirmee' ? 'is-active' : '' ?>" href="<?= e(url('proprietaire/reservations.php?statut=confirmee')) ?>">Confirmées <em><?= (int) $counts['confirmee'] ?></em></a>
  </div>

  <?php if (!$reservations): ?>
    <p class="dash-empty">Aucune réservation.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Salle</th>
            <th>Période</th>
            <th>Statut</th>
            <th>Paiement</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
            <?php
              $badgeMap = ['confirmee' => 'badge-success', 'refusee' => 'badge-danger', 'annulee' => 'badge-danger'];
              $badgeClass = $badgeMap[$r['statut']] ?? '';
              $modePay = ($r['mode_paiement'] ?? 'presentiel') === 'en_ligne' ? 'en_ligne' : 'presentiel';
              $statutPay = ($r['statut_paiement'] ?? 'en_attente') === 'paye' ? 'paye' : 'en_attente';
              $payInfo = reservation_payment_amount($r, $r);
            ?>
            <tr>
              <td>
                <strong><?= e($r['client_nom']) ?></strong><br>
                <span class="muted"><?= e($r['client_email']) ?></span>
              </td>
              <td><?= e($r['salle_nom']) ?></td>
              <td>
                <span class="muted"><?= e(format_datetime($r['date_debut'])) ?> → <?= e(format_datetime($r['date_fin'])) ?></span>
              </td>
              <td><span class="badge <?= $badgeClass ?>"><?= e(statut_label($r['statut'])) ?></span></td>
              <td>
                <strong><?= e(reservation_payment_mode_label($modePay)) ?></strong><br>
                <span class="muted"><?= e($payInfo['label']) ?></span><br>
                <?php if ($r['statut'] === 'confirmee' || $statutPay === 'paye'): ?>
                  <span class="badge <?= $statutPay === 'paye' ? 'badge-success' : 'badge-warning' ?>">
                    <?= e(reservation_payment_status_label($statutPay)) ?>
                  </span>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(url('proprietaire/messages.php?client_id=' . (int) $r['client_id'] . '&salle_id=' . (int) $r['salle_id'])) ?>">Chat</a>
                <?php if ($r['statut'] === 'en_attente'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="res_id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn btn-primary btn-sm" data-confirm="Confirmer cette réservation ?">Confirmer</button>
                  </form>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="res_id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="action" value="refuse">
                    <input type="hidden" name="motif_refus" value="Non disponible.">
                    <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Refuser cette demande ?">Refuser</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/proprietaire-footer.php'; ?>
