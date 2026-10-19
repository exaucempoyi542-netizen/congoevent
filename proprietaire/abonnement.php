<?php
require_once __DIR__ . '/../includes/functions.php';
require_proprietaire();

$userId = (int) current_user()['id'];
$plans = subscription_plans();
$methods = payment_methods();
$errors = [];
$selectedPlan = $_GET['plan'] ?? ($_POST['plan'] ?? '');
if (!isset($plans[$selectedPlan])) {
    $selectedPlan = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'], $_POST['moyen_paiement'])) {
    $planKey = $_POST['plan'] ?? '';
    $moyen = $_POST['moyen_paiement'] ?? '';
    $reference = trim($_POST['reference'] ?? '');

    if (!isset($plans[$planKey])) {
        $errors[] = 'Plan invalide.';
        $selectedPlan = '';
    } elseif (!isset($methods[$moyen])) {
        $errors[] = 'Choisissez un moyen de paiement.';
        $selectedPlan = $planKey;
    } else {
        $plan = $plans[$planKey];
        $debut = date('Y-m-d H:i:s');
        $fin = date('Y-m-d H:i:s', strtotime('+' . (int) $plan['jours'] . ' days'));

        db()->prepare(
            "UPDATE abonnements SET statut = 'expire'
             WHERE utilisateur_id = ? AND statut = 'actif'"
        )->execute([$userId]);

        try {
            $col = db()->query("SHOW COLUMNS FROM abonnements LIKE 'moyen_paiement'")->fetch();
            if (!$col) {
                db()->exec("ALTER TABLE abonnements ADD COLUMN moyen_paiement VARCHAR(40) DEFAULT NULL AFTER statut");
            }
            $colRef = db()->query("SHOW COLUMNS FROM abonnements LIKE 'reference_paiement'")->fetch();
            if (!$colRef) {
                db()->exec("ALTER TABLE abonnements ADD COLUMN reference_paiement VARCHAR(120) DEFAULT NULL AFTER moyen_paiement");
            }
        } catch (Throwable $e) {
        }

        try {
            db()->prepare(
                "INSERT INTO abonnements (utilisateur_id, plan, montant, date_debut, date_fin, statut, moyen_paiement, reference_paiement)
                 VALUES (?, ?, ?, ?, ?, 'actif', ?, ?)"
            )->execute([
                $userId,
                $planKey,
                $plan['montant'],
                $debut,
                $fin,
                $moyen,
                $reference !== '' ? $reference : null,
            ]);
        } catch (Throwable $e) {
            db()->prepare(
                "INSERT INTO abonnements (utilisateur_id, plan, montant, date_debut, date_fin, statut)
                 VALUES (?, ?, ?, ?, ?, 'actif')"
            )->execute([$userId, $planKey, $plan['montant'], $debut, $fin]);
        }

        notify_subscription_activated($userId, $planKey, (float) $plan['montant']);

        flash(
            'success',
            'Vous venez de souscrire un abonnement ' . plan_abonnement_phrase($planKey) . ' de '
            . format_usd((float) $plan['montant'])
            . ' sur Congo Events. Vous pouvez actuellement mettre vos salles en ligne. Merci pour votre confiance.'
        );
        redirect('proprietaire/abonnement.php');
    }
}

$active = get_active_subscription();
$history = db()->prepare(
    'SELECT * FROM abonnements WHERE utilisateur_id = ? ORDER BY date_creation DESC LIMIT 10'
);
$history->execute([$userId]);
$historyRows = $history->fetchAll();

$pageTitle = 'Abonnement';
$propPage = 'abonnement';
require __DIR__ . '/../includes/proprietaire-header.php';

$planMeta = [
    'mensuel' => [
        'eyebrow' => 'Essentiel',
        'period' => 'Par mois',
        'perMonth' => null,
    ],
    'trimestriel' => [
        'eyebrow' => 'Recommandé',
        'period' => 'Par 3 mois',
        'perMonth' => '5 $ / mois',
    ],
    'annuel' => [
        'eyebrow' => 'Premium',
        'period' => 'Par année',
        'perMonth' => '5 $ / mois',
    ],
];
?>

<div class="abo-page">
  <section class="abo-hero">
    <div class="abo-hero-copy">
      <span class="abo-kicker">Abonnement</span>
      <h1>Publiez vos salles en toute confiance</h1>
      <p>
        Choisissez une formule, activez-la en quelques secondes et rendez vos espaces
        visibles sur Congo Events.
      </p>
      <ul class="abo-hero-points">
        <li>Visibilité dans le catalogue</li>
        <li>Demandes de réservation</li>
        <li>Activation immédiate</li>
      </ul>
    </div>

    <?php if ($active): ?>
      <aside class="abo-status-card is-active">
        <span class="abo-status-dot" aria-hidden="true"></span>
        <span class="abo-kicker abo-kicker-light">Abonnement actif</span>
        <strong><?= e(subscription_plans()[$active['plan']]['label'] ?? $active['plan']) ?></strong>
        <span class="abo-status-price"><?= e(format_usd(subscription_amount_for_display($active['plan'], (float) $active['montant']))) ?></span>
        <p>Valable jusqu’au <strong><?= e(format_datetime($active['date_fin'])) ?></strong></p>
        <a class="btn btn-sm abo-status-btn" href="<?= e(url('proprietaire/abonnement.php?plan=' . urlencode($active['plan']))) ?>">
          Renouveler
        </a>
      </aside>
    <?php else: ?>
      <aside class="abo-status-card is-empty">
        <span class="abo-kicker">Statut</span>
        <strong>Aucun abonnement actif</strong>
        <p>Vos salles ne sont pas visibles dans le catalogue tant qu’aucun plan n’est actif.</p>
      </aside>
    <?php endif; ?>
  </section>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <?php if ($selectedPlan === ''): ?>
    <section class="abo-section">
      <header class="abo-section-head">
        <span class="abo-kicker">Formules</span>
        <h2>Choisissez votre plan</h2>
        <p>Tarifs clairs, sans engagement complexe — renouvelez quand vous voulez.</p>
      </header>

      <div class="abo-plan-grid">
        <?php foreach ($plans as $key => $plan): ?>
          <?php $meta = $planMeta[$key] ?? ['eyebrow' => 'Plan', 'period' => '', 'perMonth' => null]; ?>
          <article class="abo-plan <?= $key === 'trimestriel' ? 'is-featured' : '' ?> <?= $key === 'annuel' ? 'is-premium' : '' ?>">
            <?php if ($key === 'trimestriel'): ?>
              <span class="abo-plan-badge">Le plus choisi</span>
            <?php endif; ?>

            <div class="abo-plan-top">
              <span class="abo-plan-eyebrow"><?= e($meta['eyebrow']) ?></span>
              <h3><?= e($plan['label']) ?></h3>
            </div>

            <div class="abo-plan-price">
              <strong><?= e(format_usd((float) $plan['montant'])) ?></strong>
              <span><?= e($meta['period']) ?></span>
              <?php if (!empty($meta['perMonth'])): ?>
                <em><?= e($meta['perMonth']) ?></em>
              <?php endif; ?>
            </div>

            <ul class="abo-plan-features">
              <li class="is-ok">Diffusion de vos salles</li>
              <li class="is-ok">Demandes de réservation</li>
              <li class="is-ok"><?= e($plan['desc']) ?></li>
              <li class="<?= $key === 'mensuel' ? 'is-off' : 'is-ok' ?>">Priorité d’exposition</li>
              <li class="<?= $key === 'annuel' ? 'is-ok' : 'is-off' ?>">Présence continue toute l’année</li>
            </ul>

            <a class="btn abo-plan-cta <?= $key === 'trimestriel' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('proprietaire/abonnement.php?plan=' . urlencode($key))) ?>">
              <?= $active ? 'Renouveler' : 'Choisir ce plan' ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php else: ?>
    <?php
    $plan = $plans[$selectedPlan];
    $meta = $planMeta[$selectedPlan] ?? ['eyebrow' => 'Plan', 'period' => '', 'perMonth' => null];
    ?>
    <section class="abo-pay-wrap">
      <aside class="abo-pay-summary">
        <span class="abo-kicker"><?= e($meta['eyebrow']) ?></span>
        <h2><?= e($plan['label']) ?></h2>
        <div class="abo-pay-amount"><?= e(format_usd((float) $plan['montant'])) ?></div>
        <p><?= e($plan['desc']) ?></p>
        <ul class="abo-plan-features abo-plan-features-compact">
          <li class="is-ok">Activation immédiate</li>
          <li class="is-ok">Carte ou Mobile Money</li>
          <li class="is-ok">Renouvellement à tout moment</li>
        </ul>
      </aside>

      <div class="dash-panel abo-pay-panel">
        <div class="dash-panel-head">
          <h2>Paiement</h2>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('proprietaire/abonnement.php')) ?>">Changer de plan</a>
        </div>

        <div class="abo-pay-body">
          <p class="abo-pay-lead">
            Confirmez <strong><?= e($plan['label']) ?></strong> pour
            <strong><?= e(format_usd((float) $plan['montant'])) ?></strong>.
          </p>

          <form method="post" class="pay-form">
            <input type="hidden" name="plan" value="<?= e($selectedPlan) ?>">

            <div class="pay-methods">
              <?php foreach ($methods as $key => $method): ?>
                <label class="pay-method">
                  <input type="radio" name="moyen_paiement" value="<?= e($key) ?>" required <?= $key === 'mpesa' ? 'checked' : '' ?>>
                  <span class="pay-method-card">
                    <strong><?= e($method['label']) ?></strong>
                    <small><?= e($method['desc']) ?></small>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="form-group abo-ref-field">
              <label for="reference">Référence / n° de transaction <span class="muted">(optionnel)</span></label>
              <input type="text" id="reference" name="reference" placeholder="Ex. n° M-Pesa, 4 derniers chiffres…">
            </div>

            <div class="form-actions abo-pay-actions">
              <button type="submit" class="btn btn-primary" data-confirm="Confirmer le paiement de <?= e(format_usd((float) $plan['montant'])) ?> ?">
                Payer <?= e(format_usd((float) $plan['montant'])) ?>
              </button>
              <a class="btn btn-ghost" href="<?= e(url('proprietaire/abonnement.php')) ?>">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($historyRows): ?>
    <section class="dash-panel abo-history-panel">
      <div class="dash-panel-head">
        <div>
          <span class="abo-kicker">Historique</span>
          <h2>Abonnements récents</h2>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Plan</th>
              <th>Montant</th>
              <th>Paiement</th>
              <th>Période</th>
              <th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($historyRows as $h): ?>
              <tr>
                <td><strong><?= e(subscription_plans()[$h['plan']]['label'] ?? $h['plan']) ?></strong></td>
                <td><?= e(format_usd(subscription_amount_for_display($h['plan'], (float) $h['montant']))) ?></td>
                <td><?= !empty($h['moyen_paiement']) ? e(payment_method_label($h['moyen_paiement'])) : '—' ?></td>
                <td><?= e(format_datetime($h['date_debut'])) ?> → <?= e(format_datetime($h['date_fin'])) ?></td>
                <td>
                  <span class="badge <?= ($h['statut'] ?? '') === 'actif' ? 'badge-success' : 'badge-muted' ?>">
                    <?= e(abonnement_statut_label($h['statut'])) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/proprietaire-footer.php'; ?>
