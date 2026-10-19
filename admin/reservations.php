<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_reservation_payment_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $motif = trim($_POST['motif_refus'] ?? '');

    $stmt = db()->prepare('SELECT * FROM reservations WHERE id = ?');
    $stmt->execute([$id]);
    $res = $stmt->fetch();

    if (!$res) {
        flash('error', 'Réservation introuvable.');
        redirect('admin/reservations.php');
    }

    if ($action === 'confirmer') {
        $result = confirm_reservation_and_block($id);
        if ($result['ok']) {
            flash('success', 'Réservation validée. Salle bloquée. Notification Gmail envoyée.');
        } else {
            flash('error', $result['error'] ?? 'Impossible de confirmer.');
        }
    } elseif ($action === 'refuser') {
        if ($motif === '') {
            flash('error', 'Indiquez un motif de refus.');
        } else {
            $result = refuse_reservation_and_notify($id, $motif);
            if ($result['ok']) {
                flash('success', 'Réservation refusée. Notification envoyée.');
            } else {
                flash('error', $result['error'] ?? 'Impossible de refuser.');
            }
        }
    } elseif ($action === 'annuler') {
        db()->prepare("UPDATE reservations SET statut = 'annulee' WHERE id = ?")->execute([$id]);
        flash('success', 'Réservation annulée.');
    }

    redirect('admin/reservations.php');
}

$filtre = $_GET['statut'] ?? '';
$sql = "SELECT r.*, s.nom AS salle_nom, s.ville, s.tarif_jour, s.devise, u.nom AS user_nom, u.email
        FROM reservations r
        JOIN salles s ON s.id = r.salle_id
        JOIN utilisateurs u ON u.id = r.utilisateur_id";
$params = [];
if (in_array($filtre, ['en_attente', 'confirmee', 'refusee', 'annulee'], true)) {
    $sql .= ' WHERE r.statut = ?';
    $params[] = $filtre;
}
$sql .= ' ORDER BY FIELD(r.statut, \'en_attente\', \'confirmee\', \'refusee\', \'annulee\'), r.date_demande DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$pageTitle = 'Réservations';
$adminPage = 'reservations';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="dash-panel">
  <div class="dash-toolbar">
    <form class="filters" method="get" style="margin:0;display:flex;gap:.65rem;flex:1;flex-wrap:wrap">
      <select name="statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        <?php foreach (['en_attente', 'confirmee', 'refusee', 'annulee'] as $s): ?>
          <option value="<?= $s ?>" <?= $filtre === $s ? 'selected' : '' ?>><?= e(statut_label($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Événement</th>
          <th>Organisateur</th>
          <th>Salle / Période</th>
          <th>Statut</th>
          <th>Paiement</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$reservations): ?>
          <tr><td colspan="6" class="muted">Aucune réservation.</td></tr>
        <?php endif; ?>
        <?php foreach ($reservations as $r): ?>
          <?php
            $modePay = ($r['mode_paiement'] ?? 'presentiel') === 'en_ligne' ? 'en_ligne' : 'presentiel';
            $statutPay = ($r['statut_paiement'] ?? 'en_attente') === 'paye' ? 'paye' : 'en_attente';
            $payInfo = reservation_payment_amount($r, $r);
          ?>
          <tr>
            <td>
              <strong><?= e($r['titre_evenement']) ?></strong><br>
              <span class="muted"><?= e(type_evenement_label($r['type_evenement'])) ?></span>
            </td>
            <td>
              <?= e($r['user_nom']) ?><br>
              <span class="muted"><?= e($r['email']) ?></span>
            </td>
            <td>
              <?= e($r['salle_nom']) ?> (<?= e($r['ville']) ?>)<br>
              <span class="muted"><?= e(format_datetime($r['date_debut'])) ?> → <?= e(format_datetime($r['date_fin'])) ?></span>
            </td>
            <td><span class="badge"><?= e(statut_label($r['statut'])) ?></span></td>
            <td>
              <?= e(reservation_payment_mode_label($modePay)) ?><br>
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
              <?php if ($r['statut'] === 'en_attente'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <input type="hidden" name="action" value="confirmer">
                  <button class="btn btn-primary btn-sm" type="submit" data-confirm="Confirmer cette réservation ?">Confirmer</button>
                </form>
                <form method="post" style="display:inline-flex;gap:.35rem;align-items:center;flex-wrap:wrap">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <input type="hidden" name="action" value="refuser">
                  <input type="text" name="motif_refus" placeholder="Motif" style="width:110px;padding:.35rem;min-height:2.1rem" required>
                  <button class="btn btn-danger btn-sm" type="submit">Refuser</button>
                </form>
              <?php elseif ($r['statut'] === 'confirmee'): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                  <input type="hidden" name="action" value="annuler">
                  <button class="btn btn-ghost btn-sm" type="submit" data-confirm="Annuler cette réservation confirmée ?">Annuler</button>
                </form>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
