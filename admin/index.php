<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_subscription_schema();

$stats = [
    'salles' => (int) db()->query("SELECT COUNT(*) FROM salles WHERE statut = 'active'")->fetchColumn(),
    'attente' => (int) db()->query("SELECT COUNT(*) FROM reservations WHERE statut = 'en_attente'")->fetchColumn(),
    'confirmees' => (int) db()->query("SELECT COUNT(*) FROM reservations WHERE statut = 'confirmee'")->fetchColumn(),
    'users' => (int) db()->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn(),
    'abos' => (int) db()->query("SELECT COUNT(*) FROM abonnements WHERE statut = 'actif' AND date_fin >= NOW()")->fetchColumn(),
];

$monthCounts = array_fill(1, 12, 0);
try {
    $rows = db()->query(
        "SELECT MONTH(date_demande) AS m, COUNT(*) AS n
         FROM reservations
         WHERE YEAR(date_demande) = YEAR(CURDATE())
         GROUP BY MONTH(date_demande)"
    )->fetchAll();
    foreach ($rows as $row) {
        $m = (int) $row['m'];
        if ($m >= 1 && $m <= 12) {
            $monthCounts[$m] = (int) $row['n'];
        }
    }
} catch (Throwable $e) {
    // silent
}
$maxMonth = max(1, max($monthCounts));

$salles = db()->query(
    "SELECT id, nom, ville, commune, capacite
     FROM salles
     WHERE statut = 'active'
     ORDER BY nom
     LIMIT 6"
)->fetchAll();

$recent = db()->query(
    "SELECT r.*, s.nom AS salle_nom, u.nom AS user_nom
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     JOIN utilisateurs u ON u.id = r.utilisateur_id
     ORDER BY r.date_demande DESC
     LIMIT 6"
)->fetchAll();

$upcoming = db()->query(
    "SELECT r.titre_evenement, r.date_debut, s.nom AS salle_nom, u.nom AS user_nom
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     JOIN utilisateurs u ON u.id = r.utilisateur_id
     WHERE r.statut = 'confirmee' AND r.date_debut >= NOW()
     ORDER BY r.date_debut ASC
     LIMIT 5"
)->fetchAll();

$mois = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];

$pageTitle = 'Tableau de bord';
$adminPage = 'dashboard';
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="dash-home-kpis">
  <article class="dash-kpi">
    <div class="dash-kpi-label">Salles actives</div>
    <div class="dash-kpi-value"><?= $stats['salles'] ?></div>
    <div class="dash-kpi-meta">Catalogue public</div>
  </article>
  <a class="dash-kpi" href="<?= e(url('admin/reservations.php?statut=en_attente')) ?>">
    <div class="dash-kpi-label">En attente</div>
    <div class="dash-kpi-value"><?= $stats['attente'] ?></div>
    <?php if ($stats['attente'] > 0): ?>
      <span class="dash-kpi-badge">À traiter</span>
    <?php else: ?>
      <div class="dash-kpi-meta">Aucune demande</div>
    <?php endif; ?>
  </a>
  <article class="dash-kpi">
    <div class="dash-kpi-label">Confirmées</div>
    <div class="dash-kpi-value"><?= $stats['confirmees'] ?></div>
    <div class="dash-kpi-meta">Réservations validées</div>
  </article>
  <article class="dash-kpi is-dark">
    <div class="dash-kpi-label">Utilisateurs</div>
    <div class="dash-kpi-value"><?= $stats['users'] ?></div>
    <div class="dash-kpi-meta"><?= $stats['abos'] ?> abo<?= $stats['abos'] > 1 ? 's' : '' ?> actif<?= $stats['abos'] > 1 ? 's' : '' ?></div>
  </article>
</div>

<div class="dash-home-grid">
  <div class="dash-panel">
    <div class="dash-panel-head">
      <h2>Réservations par mois</h2>
      <span class="muted" style="font-size:.82rem;font-weight:600"><?= date('Y') ?></span>
    </div>
    <div class="dash-chart">
      <p class="dash-chart-legend"><i></i> Demandes reçues</p>
      <div class="dash-bars" aria-hidden="true">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <?php
            $n = $monthCounts[$m];
            $h = (int) round(($n / $maxMonth) * 100);
            if ($n > 0 && $h < 8) {
                $h = 8;
            }
          ?>
          <div class="dash-bar-col" title="<?= $n ?> demande<?= $n > 1 ? 's' : '' ?>">
            <div class="dash-bar" style="height: <?= $n ? $h : 3 ?>%"></div>
            <span class="dash-bar-label"><?= $mois[$m - 1] ?></span>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="dash-widget">
      <div class="dash-widget-head">
        <h2>Dernières réservations</h2>
        <a href="<?= e(url('admin/reservations.php')) ?>">Voir</a>
      </div>
      <?php if (!$recent): ?>
        <p class="dash-widget-empty">Aucune réservation.</p>
      <?php else: ?>
        <table class="dash-widget-table">
          <thead>
            <tr>
              <th>Événement</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td>
                  <strong><?= e($r['titre_evenement']) ?></strong>
                  <span><?= e($r['user_nom']) ?> · <?= e($r['salle_nom']) ?></span>
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
        <a href="<?= e(url('admin/reservations.php?statut=confirmee')) ?>">Voir</a>
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

<div class="dash-panel" style="margin-top:1.15rem">
  <div class="dash-panel-head">
    <h2>Salles actives</h2>
    <a class="btn btn-primary btn-sm" href="<?= e(url('admin/salles.php')) ?>">Gérer</a>
  </div>
  <?php if (!$salles): ?>
    <p class="dash-empty">Aucune salle enregistrée.</p>
  <?php else: ?>
    <ul class="dash-list">
      <?php foreach ($salles as $s): ?>
        <li class="dash-list-item">
          <div class="dash-list-main">
            <strong><?= e($s['nom']) ?></strong>
            <span><?= e($s['ville']) ?><?= $s['commune'] ? ' · ' . e($s['commune']) : '' ?> · <?= (int) $s['capacite'] ?> places</span>
          </div>
          <a class="dash-more" href="<?= e(url('admin/salles.php?edit=' . (int) $s['id'])) ?>" aria-label="Modifier">⋯</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
