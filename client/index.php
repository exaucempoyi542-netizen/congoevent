<?php
require_once __DIR__ . '/../includes/functions.php';
require_client();

$userId = (int) current_user()['id'];

$stmt = db()->prepare(
    "SELECT r.*, s.nom AS salle_nom, s.ville
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     WHERE r.utilisateur_id = ?
     ORDER BY r.date_demande DESC"
);
$stmt->execute([$userId]);
$reservations = $stmt->fetchAll();

$counts = ['total' => count($reservations), 'en_attente' => 0, 'confirmee' => 0, 'refusee' => 0, 'annulee' => 0];
foreach ($reservations as $row) {
    if (isset($counts[$row['statut']])) {
        $counts[$row['statut']]++;
    }
}

$recent = array_slice($reservations, 0, 5);
$upcoming = [];
foreach ($reservations as $row) {
    if ($row['statut'] === 'confirmee' && strtotime((string) $row['date_debut']) >= time()) {
        $upcoming[] = $row;
    }
}
$upcoming = array_slice($upcoming, 0, 5);

$monthCounts = array_fill(1, 12, 0);
foreach ($reservations as $row) {
    $ts = strtotime((string) ($row['date_demande'] ?? ''));
    if ($ts && (int) date('Y', $ts) === (int) date('Y')) {
        $m = (int) date('n', $ts);
        $monthCounts[$m]++;
    }
}
$maxMonth = max(1, max($monthCounts));
$mois = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];

$pageTitle = 'Mon espace';
$clientPage = 'dashboard';
require __DIR__ . '/../includes/client-header.php';
?>

<div class="dash-home-kpis">
  <article class="dash-kpi">
    <div class="dash-kpi-label">Total</div>
    <div class="dash-kpi-value"><?= (int) $counts['total'] ?></div>
    <div class="dash-kpi-meta">Toutes demandes</div>
  </article>
  <article class="dash-kpi">
    <div class="dash-kpi-label">En attente</div>
    <div class="dash-kpi-value"><?= (int) $counts['en_attente'] ?></div>
    <?php if ($counts['en_attente'] > 0): ?>
      <span class="dash-kpi-badge">En cours</span>
    <?php else: ?>
      <div class="dash-kpi-meta">Rien en attente</div>
    <?php endif; ?>
  </article>
  <article class="dash-kpi">
    <div class="dash-kpi-label">Confirmées</div>
    <div class="dash-kpi-value"><?= (int) $counts['confirmee'] ?></div>
    <div class="dash-kpi-meta">Événements validés</div>
  </article>
  <article class="dash-kpi is-dark">
    <div class="dash-kpi-label">Autres</div>
    <div class="dash-kpi-value"><?= (int) $counts['refusee'] + (int) $counts['annulee'] ?></div>
    <div class="dash-kpi-meta">Refusées / annulées</div>
  </article>
</div>

<div class="dash-home-grid">
  <div class="dash-panel">
    <div class="dash-panel-head">
      <h2>Mes demandes par mois</h2>
      <span class="muted" style="font-size:.82rem;font-weight:600"><?= date('Y') ?></span>
    </div>
    <div class="dash-chart">
      <p class="dash-chart-legend"><i></i> Réservations</p>
      <div class="dash-bars" aria-hidden="true">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <?php
            $n = $monthCounts[$m];
            $h = (int) round(($n / $maxMonth) * 100);
            if ($n > 0 && $h < 8) {
                $h = 8;
            }
          ?>
          <div class="dash-bar-col" title="<?= $n ?>">
            <div class="dash-bar" style="height: <?= $n ? $h : 3 ?>%"></div>
            <span class="dash-bar-label"><?= $mois[$m - 1] ?></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>
    <div class="dash-actions-row">
      <a class="btn btn-primary" href="<?= e(url('salles.php')) ?>">Réserver une salle</a>
      <a class="btn btn-ghost" href="<?= e(url('mes-reservations.php')) ?>">Voir mes réservations</a>
    </div>
  </div>

  <div>
    <div class="dash-widget">
      <div class="dash-widget-head">
        <h2>Dernières demandes</h2>
        <a href="<?= e(url('mes-reservations.php')) ?>">Voir</a>
      </div>
      <?php if (!$recent): ?>
        <p class="dash-widget-empty">Aucune réservation pour le moment.</p>
      <?php else: ?>
        <table class="dash-widget-table">
          <thead>
            <tr>
              <th>Événement</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td>
                  <strong><?= e($r['titre_evenement']) ?></strong>
                  <span><?= e($r['salle_nom']) ?> · <?= e($r['ville']) ?></span>
                </td>
                <td><?= e(statut_label($r['statut'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="dash-widget">
      <div class="dash-widget-head">
        <h2>Prochains événements</h2>
        <a href="<?= e(url('mes-reservations.php')) ?>">Voir</a>
      </div>
      <?php if (!$upcoming): ?>
        <p class="dash-widget-empty">Aucun événement confirmé à venir.</p>
      <?php else: ?>
        <table class="dash-widget-table">
          <thead>
            <tr>
              <th>Événement</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($upcoming as $u): ?>
              <tr>
                <td>
                  <strong><?= e($u['titre_evenement']) ?></strong>
                  <span><?= e($u['salle_nom']) ?></span>
                </td>
                <td><?= e(format_datetime($u['date_debut'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/client-footer.php'; ?>
