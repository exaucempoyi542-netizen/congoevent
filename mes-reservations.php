<?php
require_once __DIR__ . '/includes/functions.php';
require_client();
ensure_reservation_payment_schema();

$userId = current_user()['id'];

if (isset($_POST['annuler_id'])) {
    $rid = (int) $_POST['annuler_id'];
    $stmt = db()->prepare(
        "SELECT * FROM reservations WHERE id = ? AND utilisateur_id = ? AND statut IN ('en_attente','confirmee')"
    );
    $stmt->execute([$rid, $userId]);
    $res = $stmt->fetch();
    if ($res) {
        $upd = db()->prepare("UPDATE reservations SET statut = 'annulee' WHERE id = ?");
        $upd->execute([$rid]);
        flash('success', 'Réservation annulée.');
    } else {
        flash('error', 'Impossible d\'annuler cette réservation.');
    }
    redirect('mes-reservations.php');
}

$stmt = db()->prepare(
    "SELECT r.*, s.nom AS salle_nom, s.ville, s.id AS salle_id, s.proprietaire_id, s.tarif_jour, s.devise, s.adresse, s.commune
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     WHERE r.utilisateur_id = ?
     ORDER BY r.date_demande DESC"
);
$stmt->execute([$userId]);
$reservations = $stmt->fetchAll();

$counts = ['total' => count($reservations), 'en_attente' => 0, 'confirmee' => 0, 'refusee' => 0, 'annulee' => 0];
foreach ($reservations as $resRow) {
    if (isset($counts[$resRow['statut']])) {
        $counts[$resRow['statut']]++;
    }
}

$pageTitle = 'Mes réservations';
$clientPage = 'reservations';
require __DIR__ . '/includes/client-header.php';
?>

<div class="dash-home-kpis" style="margin-bottom:1.15rem">
  <article class="dash-kpi">
    <div class="dash-kpi-label">Total</div>
    <div class="dash-kpi-value"><?= (int) $counts['total'] ?></div>
  </article>
  <article class="dash-kpi">
    <div class="dash-kpi-label">En attente</div>
    <div class="dash-kpi-value"><?= (int) $counts['en_attente'] ?></div>
  </article>
  <article class="dash-kpi">
    <div class="dash-kpi-label">Confirmées</div>
    <div class="dash-kpi-value"><?= (int) $counts['confirmee'] ?></div>
  </article>
  <article class="dash-kpi is-dark">
    <div class="dash-kpi-label">Autres</div>
    <div class="dash-kpi-value"><?= (int) $counts['refusee'] + (int) $counts['annulee'] ?></div>
  </article>
</div>

<?php if (!$reservations): ?>
  <div class="dash-panel">
    <div class="res-empty res-empty-public" style="border:0;box-shadow:none">
      <div class="res-empty-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
      </div>
      <h3>Aucune réservation</h3>
      <p>Vous n'avez encore aucune demande. Parcourez le catalogue pour réserver une salle.</p>
      <a class="btn btn-primary" href="<?= e(url('salles.php')) ?>">Parcourir les salles</a>
    </div>
  </div>
<?php else: ?>
  <div class="res-cards res-cards-public">
    <?php foreach ($reservations as $r): ?>
      <?php
      $badgeClass = match ($r['statut']) {
          'confirmee' => 'badge-success',
          'en_attente' => 'badge-warning',
          'refusee' => 'badge-danger',
          default => 'badge-muted',
      };
      ?>
      <?php
        $payInfo = reservation_payment_amount($r, $r);
        $modePay = ($r['mode_paiement'] ?? 'presentiel') === 'en_ligne' ? 'en_ligne' : 'presentiel';
        $statutPay = ($r['statut_paiement'] ?? 'en_attente') === 'paye' ? 'paye' : 'en_attente';
        $canPayOnline = $r['statut'] === 'confirmee' && $statutPay !== 'paye';
      ?>
      <article class="res-card res-card--<?= e($r['statut']) ?>">
        <div class="res-card-top">
          <div class="res-card-event">
            <span class="res-type"><?= e(type_evenement_label($r['type_evenement'])) ?></span>
            <h3><?= e($r['titre_evenement']) ?></h3>
          </div>
          <span class="badge <?= $badgeClass ?>"><?= e(statut_label($r['statut'])) ?></span>
        </div>

        <div class="res-card-grid">
          <div class="res-card-block">
            <span class="res-label">Salle</span>
            <strong class="res-value"><?= e($r['salle_nom']) ?></strong>
            <small class="res-subvalue"><?= e($r['ville']) ?></small>
          </div>
          <div class="res-card-block res-card-block-wide">
            <span class="res-label">Période</span>
            <div class="res-period">
              <time><?= e(format_datetime($r['date_debut'])) ?></time>
              <span class="res-period-arrow">→</span>
              <time><?= e(format_datetime($r['date_fin'])) ?></time>
            </div>
          </div>
        </div>

        <div class="res-pay-box <?= $statutPay === 'paye' ? 'is-paid' : '' ?>">
          <div class="res-pay-row">
            <span class="res-label">Paiement</span>
            <strong><?= e(reservation_payment_mode_label($modePay)) ?></strong>
          </div>
          <div class="res-pay-row">
            <span class="res-label">Montant</span>
            <strong><?= e($payInfo['label']) ?></strong>
            <small class="res-subvalue"><?= (int) $payInfo['days'] ?> jour(s)</small>
          </div>
          <div class="res-pay-row">
            <span class="res-label">Statut</span>
            <span class="badge <?= $statutPay === 'paye' ? 'badge-success' : 'badge-warning' ?>">
              <?= e(reservation_payment_status_label($statutPay)) ?>
            </span>
          </div>
          <?php if ($statutPay === 'paye' && !empty($r['reference_paiement'])): ?>
            <div class="res-pay-row">
              <span class="res-label">Référence</span>
              <strong class="res-pay-ref"><?= e($r['reference_paiement']) ?></strong>
            </div>
          <?php endif; ?>
          <?php if ($r['statut'] === 'confirmee' && $statutPay !== 'paye' && $modePay === 'presentiel'): ?>
            <p class="res-pay-hint">Paiement sur place à l’adresse de la salle, ou réglez en ligne ci-dessous.</p>
          <?php endif; ?>
        </div>

        <?php if ($r['statut'] === 'refusee' && $r['motif_refus']): ?>
          <p class="res-refusal"><strong>Motif :</strong> <?= e($r['motif_refus']) ?></p>
        <?php endif; ?>

        <div class="res-card-actions">
          <?php if ($canPayOnline): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(url('paiement-reservation.php?id=' . (int) $r['id'])) ?>">
              Payer en ligne
            </a>
          <?php endif; ?>
          <?php if (!empty($r['proprietaire_id'])): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('messages.php?salle_id=' . (int) $r['salle_id'])) ?>">Contacter le propriétaire</a>
          <?php endif; ?>
          <?php if (in_array($r['statut'], ['en_attente', 'confirmee'], true)): ?>
            <form method="post">
              <input type="hidden" name="annuler_id" value="<?= (int) $r['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" data-confirm="Annuler cette réservation ?">Annuler</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/client-footer.php'; ?>
