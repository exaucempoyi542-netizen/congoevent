<?php
require_once __DIR__ . '/../includes/functions.php';
require_proprietaire();

$userId = (int) current_user()['id'];
ensure_subscription_schema();

$abo = get_active_subscription();
$hasOwnerCol = salle_column_exists('proprietaire_id');

$stats = [
    'salles' => 0,
    'attente' => 0,
    'confirmees' => 0,
    'total' => 0,
];
$recent = [];
$upcoming = [];
$salles = [];
$monthCounts = array_fill(1, 12, 0);

if ($hasOwnerCol) {
    $stmtSalles = db()->prepare('SELECT COUNT(*) FROM salles WHERE proprietaire_id = ?');
    $stmtSalles->execute([$userId]);
    $stats['salles'] = (int) $stmtSalles->fetchColumn();

    $stmtList = db()->prepare('SELECT id, nom, ville, statut FROM salles WHERE proprietaire_id = ? ORDER BY nom LIMIT 6');
    $stmtList->execute([$userId]);
    $salles = $stmtList->fetchAll();

    $stmtAttente = db()->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE s.proprietaire_id = ? AND r.statut = 'en_attente'"
    );
    $stmtAttente->execute([$userId]);
    $stats['attente'] = (int) $stmtAttente->fetchColumn();

    $stmtConf = db()->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE s.proprietaire_id = ? AND r.statut = 'confirmee'"
    );
    $stmtConf->execute([$userId]);
    $stats['confirmees'] = (int) $stmtConf->fetchColumn();

    $stmtTotal = db()->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE s.proprietaire_id = ?"
    );
    $stmtTotal->execute([$userId]);
    $stats['total'] = (int) $stmtTotal->fetchColumn();

    $stmtRecent = db()->prepare(
        "SELECT r.*, s.nom AS salle_nom, u.nom AS client_nom
         FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         JOIN utilisateurs u ON u.id = r.utilisateur_id
         WHERE s.proprietaire_id = ?
         ORDER BY r.date_demande DESC
         LIMIT 5"
    );
    $stmtRecent->execute([$userId]);
    $recent = $stmtRecent->fetchAll();

    $stmtUp = db()->prepare(
        "SELECT r.titre_evenement, r.date_debut, s.nom AS salle_nom, u.nom AS client_nom
         FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         JOIN utilisateurs u ON u.id = r.utilisateur_id
         WHERE s.proprietaire_id = ? AND r.statut = 'confirmee' AND r.date_debut >= NOW()
         ORDER BY r.date_debut ASC
         LIMIT 5"
    );
    $stmtUp->execute([$userId]);
    $upcoming = $stmtUp->fetchAll();

    $stmtMonths = db()->prepare(
        "SELECT MONTH(r.date_demande) AS m, COUNT(*) AS n
         FROM reservations r
         JOIN salles s ON s.id = r.salle_id
         WHERE s.proprietaire_id = ? AND YEAR(r.date_demande) = YEAR(CURDATE())
         GROUP BY MONTH(r.date_demande)"
    );
    $stmtMonths->execute([$userId]);
    foreach ($stmtMonths->fetchAll() as $row) {
        $m = (int) $row['m'];
        if ($m >= 1 && $m <= 12) {
            $monthCounts[$m] = (int) $row['n'];
        }
    }
}

$maxMonth = max(1, max($monthCounts));
$mois = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];

$pageTitle = 'Tableau de bord';
$propPage = 'dashboard';
require __DIR__ . '/../includes/proprietaire-header.php';
?>

<?php if (!$abo): ?>
  <div class="alert alert-warning" style="margin-bottom:1.15rem">
    Vous n'avez pas d'abonnement actif. Vos salles ne seront pas visibles dans le catalogue.
    <a href="<?= e(url('proprietaire/abonnement.php')) ?>">Souscrire →</a>
  </div>
<?php else: ?>
  <div class="alert alert-info" style="margin-bottom:1.15rem">
    Abonnement <strong><?= e(subscription_plans()[$abo['plan']]['label'] ?? $abo['plan']) ?></strong>
    actif jusqu'au <?= e(format_datetime($abo['date_fin'])) ?>.
  </div>
<?php endif; ?>

<div class="dash-home-kpis">
  <article class="dash-kpi">
    <div class="dash-kpi-label">Mes salles</div>
    <div class="dash-kpi-value"><?= (int) $stats['salles'] ?></div>
    <div class="dash-kpi-meta">Espaces publiés</div>
  </article>
  <a class="dash-kpi" href="<?= e(url('proprietaire/reservations.php')) ?>">
    <div class="dash-kpi-label">En attente</div>
    <div class="dash-kpi-value"><?= (int) $stats['attente'] ?></div>
    <?php if ($stats['attente'] > 0): ?>
      <span class="dash-kpi-badge">À valider</span>
    <?php else: ?>
      <div class="dash-kpi-meta">Aucune demande</div>
    <?php endif; ?>
  </a>
  <article class="dash-kpi">
    <div class="dash-kpi-label">Confirmées</div>
    <div class="dash-kpi-value"><?= (int) $stats['confirmees'] ?></div>
    <div class="dash-kpi-meta">Événements validés</div>
  </article>
  <article class="dash-kpi is-dark">
    <div class="dash-kpi-label">Total demandes</div>
    <div class="dash-kpi-value"><?= (int) $stats['total'] ?></div>
    <div class="dash-kpi-meta">Sur toutes vos salles</div>
  </article>
</div>

<div class="dash-home-grid">
  <div class="dash-panel">
    <div class="dash-panel-head">
      <h2>Demandes par mois</h2>
      <span class="muted" style="font-size:.82rem;font-weight:600"><?= date('Y') ?></span>
    </div>
    <div class="dash-chart">
      <p class="dash-chart-legend"><i></i> Réservations reçues</p>
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
      <?php if (!$abo): ?>
        <a class="btn btn-primary" href="<?= e(url('proprietaire/abonnement.php')) ?>">Souscrire un abonnement</a>
      <?php endif; ?>
      <a class="btn <?= $abo ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('proprietaire/salles.php')) ?>">Gérer mes salles</a>
      <a class="btn btn-ghost" href="<?= e(url('proprietaire/reservations.php')) ?>">Réservations</a>
    </div>
  </div>

  <div>
    <div class="dash-widget">
      <div class="dash-widget-head">
        <h2>Dernières demandes</h2>
        <a href="<?= e(url('proprietaire/reservations.php')) ?>">Voir</a>
      </div>
      <?php if (!$recent): ?>
        <p class="dash-widget-empty">Aucune demande pour le moment.</p>
      <?php else: ?>
        <table class="dash-widget-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td>
                  <strong><?= e($r['titre_evenement']) ?></strong>
                  <span><?= e($r['client_nom']) ?> · <?= e($r['salle_nom']) ?></span>
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
        <a href="<?= e(url('proprietaire/reservations.php')) ?>">Voir</a>
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
                  <span><?= e($u['client_nom']) ?></span>
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

<?php require __DIR__ . '/../includes/proprietaire-footer.php'; ?>
