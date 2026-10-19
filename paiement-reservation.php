<?php
require_once __DIR__ . '/includes/functions.php';
require_client();
ensure_reservation_payment_schema();

$userId = (int) current_user()['id'];
$resId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT r.*,
            s.id AS salle_id, s.nom AS salle_nom, s.ville, s.commune, s.adresse,
            s.tarif_jour, s.devise, s.image, s.image2, s.categorie
     FROM reservations r
     JOIN salles s ON s.id = r.salle_id
     WHERE r.id = ? AND r.utilisateur_id = ?"
);
$stmt->execute([$resId, $userId]);
$reservation = $stmt->fetch();

if (!$reservation) {
    flash('error', 'Réservation introuvable.');
    redirect('mes-reservations.php');
}

if (($reservation['statut'] ?? '') !== 'confirmee') {
    flash('error', 'Le paiement en ligne est disponible uniquement pour une réservation confirmée.');
    redirect('mes-reservations.php');
}

if (($reservation['statut_paiement'] ?? '') === 'paye') {
    flash('success', 'Cette réservation est déjà payée.');
    redirect('mes-reservations.php');
}

$pay = reservation_payment_amount($reservation, $reservation);
$salleImg = salle_image([
    'id' => (int) ($reservation['salle_id'] ?? 0),
    'image' => $reservation['image'] ?? '',
]);
$adresse = format_salle_adresse([
    'adresse' => $reservation['adresse'] ?? '',
    'commune' => $reservation['commune'] ?? '',
    'ville' => $reservation['ville'] ?? '',
]);
$unitLabel = format_salle_tarif($reservation);
$sasReady = saspay_configured();
$defaultPhone = (string) (current_user()['telephone'] ?? '');
$tab = 'mobile';

$pageTitle = 'Paiement en ligne';
$clientPage = 'reservations';
require __DIR__ . '/includes/client-header.php';
?>

<div class="checkout">
  <header class="checkout-top">
    <div class="checkout-top-title">
      <span class="checkout-shield" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/><path d="M9.5 12.5 11 14l3.5-3.5"/></svg>
      </span>
      <div>
        <h1>Paiement en ligne</h1>
        <p>Finalisez votre réservation en toute sécurité</p>
      </div>
    </div>
    <ol class="checkout-steps" aria-label="Étapes">
      <li class="is-done"><span>1</span> Informations</li>
      <li class="is-current" aria-current="step"><span>2</span> Paiement</li>
      <li><span>3</span> Confirmation</li>
    </ol>
  </header>

  <?php if (!$sasReady): ?>
    <div class="alert alert-error">
      Le paiement en ligne n’est pas encore disponible. Contactez l’administrateur.
    </div>
  <?php endif; ?>

  <div id="checkout-api-error" class="alert alert-error" hidden></div>
  <div id="checkout-api-info" class="alert alert-success" hidden></div>

  <div class="checkout-grid">
    <aside class="checkout-summary">
      <div class="checkout-summary-media">
        <img src="<?= e($salleImg) ?>" alt="<?= e($reservation['salle_nom'] ?? 'Salle') ?>" width="640" height="360" loading="lazy">
      </div>
      <span class="checkout-type"><?= e(type_evenement_label($reservation['type_evenement'] ?? 'autre')) ?></span>
      <h2><?= e($reservation['titre_evenement']) ?></h2>
      <ul class="checkout-facts">
        <li>
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
          <span><?= e(format_datetime($reservation['date_debut'])) ?> → <?= e(format_datetime($reservation['date_fin'])) ?></span>
        </li>
        <li>
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
          <span><?= e($reservation['salle_nom']) ?><?= $adresse !== '' ? ' · ' . e($adresse) : '' ?></span>
        </li>
      </ul>

      <div class="checkout-details">
        <h3>Détails de la réservation</h3>
        <div class="checkout-detail-row">
          <span>Salle</span>
          <strong><?= e($reservation['salle_nom']) ?></strong>
        </div>
        <div class="checkout-detail-row">
          <span>Durée</span>
          <strong><?= (int) $pay['days'] ?> jour(s)</strong>
        </div>
        <div class="checkout-detail-row">
          <span>Tarif / jour</span>
          <strong><?= e($unitLabel) ?></strong>
        </div>
      </div>

      <div class="checkout-recap">
        <h3>Récapitulatif</h3>
        <div class="checkout-recap-row">
          <span>Sous-total</span>
          <strong><?= e($pay['label']) ?></strong>
        </div>
        <div class="checkout-recap-total">
          <span>Total à payer</span>
          <strong><?= e($pay['label']) ?></strong>
        </div>
      </div>

      <div class="checkout-secure-note">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L15.5 10"/></svg>
        <div>
          <strong>Paiement 100 % sécurisé</strong>
          <p>Vos données sont protégées. Mobile Money et carte bancaire acceptés.</p>
        </div>
      </div>
    </aside>

    <section class="checkout-pay">
      <h2>
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        Choisissez votre moyen de paiement
      </h2>

      <form class="checkout-form" id="saspay-form" onsubmit="return false;">
        <input type="hidden" name="reservation_id" value="<?= (int) $reservation['id'] ?>">
        <input type="hidden" name="pay_tab" value="<?= e($tab) ?>" data-pay-tab-input>

        <div class="checkout-tabs" role="tablist">
          <button type="button" class="checkout-tab" data-pay-tab="carte" role="tab">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
            Carte bancaire
          </button>
          <button type="button" class="checkout-tab is-active" data-pay-tab="mobile" role="tab" aria-selected="true">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>
            Mobile Money
          </button>
          <button type="button" class="checkout-tab" data-pay-tab="tous" role="tab">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 10 12 3l9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V10Z"/><path d="M9 21v-7h6v7"/></svg>
            Tous
          </button>
        </div>

        <div class="checkout-pane is-active" data-pay-pane="saspay">
          <div class="checkout-brands" aria-hidden="true">
            <span class="checkout-brand checkout-brand-visa">VISA</span>
            <span class="checkout-brand checkout-brand-mc">Mastercard</span>
            <span class="checkout-brand">M-Pesa</span>
            <span class="checkout-brand">Orange</span>
            <span class="checkout-brand">Airtel</span>
          </div>

          <div class="checkout-fields" data-softpay-fields>
            <label class="checkout-field">
              <span>Opérateur</span>
              <span class="checkout-input">
                <select name="network" id="saspay-network">
                  <option value="orange_cd">Orange Money</option>
                  <option value="vodacom_cd">M-Pesa (Vodacom)</option>
                  <option value="airtel_cd">Airtel Money</option>
                  <option value="card">Carte bancaire</option>
                </select>
              </span>
            </label>
            <label class="checkout-field" data-phone-field>
              <span>Numéro Mobile Money</span>
              <span class="checkout-input">
                <input type="tel" name="phone" id="saspay-phone" value="<?= e($defaultPhone) ?>" placeholder="+243 8XX XXX XXX" autocomplete="tel">
              </span>
            </label>
          </div>

          <p class="checkout-pane-lead">
            Validez pour confirmer le paiement. Vous pourrez aussi être dirigé vers une page sécurisée selon l’opérateur.
          </p>
        </div>

        <button type="button" class="checkout-submit" id="saspay-btn" <?= $sasReady ? '' : 'disabled' ?>>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
          <span>Payer maintenant — <?= e($pay['label']) ?></span>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
        <p class="checkout-foot">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/></svg>
          Paiement sécurisé
        </p>
        <p class="checkout-back">
          <a href="<?= e(url('mes-reservations.php')) ?>">← Retour à mes réservations</a>
        </p>
      </form>
    </section>
  </div>
</div>

<script>
(() => {
  const form = document.getElementById('saspay-form');
  const btn = document.getElementById('saspay-btn');
  const errBox = document.getElementById('checkout-api-error');
  const infoBox = document.getElementById('checkout-api-info');
  const tabInput = form?.querySelector('[data-pay-tab-input]');
  const networkEl = document.getElementById('saspay-network');
  const phoneEl = document.getElementById('saspay-phone');
  const phoneField = form?.querySelector('[data-phone-field]');
  if (!form || !btn) return;

  const ready = <?= $sasReady ? 'true' : 'false' ?>;
  const initUrl = <?= json_encode(url('saspay-init.php')) ?>;
  const returnBase = <?= json_encode(url('saspay-return.php?rid=' . (int) $reservation['id'])) ?>;

  const syncNetworkUi = () => {
    const isCard = networkEl && networkEl.value === 'card';
    if (phoneField) phoneField.hidden = !!isCard;
  };

  const applyTab = (name) => {
    if (tabInput) tabInput.value = name;
    form.querySelectorAll('[data-pay-tab]').forEach((b) => {
      const on = b.getAttribute('data-pay-tab') === name;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (networkEl) {
      if (name === 'carte') networkEl.value = 'card';
      else if (name === 'mobile' && networkEl.value === 'card') networkEl.value = 'orange_cd';
    }
    syncNetworkUi();
  };

  form.querySelectorAll('[data-pay-tab]').forEach((tabBtn) => {
    tabBtn.addEventListener('click', () => applyTab(tabBtn.getAttribute('data-pay-tab')));
  });
  networkEl?.addEventListener('change', syncNetworkUi);
  syncNetworkUi();

  const showError = (msg) => {
    if (infoBox) infoBox.hidden = true;
    if (!errBox) return;
    errBox.hidden = false;
    errBox.textContent = msg;
  };

  const showInfo = (msg) => {
    if (errBox) errBox.hidden = true;
    if (!infoBox) return;
    infoBox.hidden = false;
    infoBox.textContent = msg;
  };

  btn.addEventListener('click', async () => {
    if (!ready) {
      showError('Le paiement en ligne n’est pas encore disponible.');
      return;
    }
    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';
    btn.disabled = true;
    const label = btn.querySelector('span');
    const original = label ? label.textContent : '';
    if (label) label.textContent = 'Traitement en cours…';
    if (errBox) errBox.hidden = true;
    if (infoBox) infoBox.hidden = true;

    const network = networkEl ? networkEl.value : 'orange_cd';
    const phone = phoneEl ? phoneEl.value.trim() : '';
    const tab = tabInput ? tabInput.value : 'mobile';
    // Page hébergée SasPay pour « Tous » ; softpay pour Mobile / Carte
    const mode = tab === 'tous' ? 'checkout' : 'softpay';

    try {
      const res = await fetch(initUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          reservation_id: <?= (int) $reservation['id'] ?>,
          mode,
          network: mode === 'softpay' ? network : '',
          phone: mode === 'softpay' ? phone : '',
        }),
        credentials: 'same-origin',
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.ok) {
        throw new Error(data.error || 'Échec du paiement.');
      }

      if (data.checkout_url) {
        if (label) label.textContent = 'Redirection sécurisée…';
        window.location.href = data.checkout_url;
        return;
      }

      if (data.instructions) {
        showInfo(data.instructions);
      } else {
        showInfo('Paiement initié. Confirmez sur votre téléphone si demandé, puis revenez ici.');
      }
      if (label) label.textContent = 'Vérification…';
      const pid = data.payment_id ? ('&payment_id=' + encodeURIComponent(data.payment_id)) : '';
      setTimeout(() => {
        window.location.href = returnBase + pid;
      }, 2500);
    } catch (e) {
      showError(e.message || 'Erreur réseau.');
      btn.dataset.busy = '0';
      btn.disabled = false;
      if (label) label.textContent = original;
    }
  });
})();
</script>

<?php require __DIR__ . '/includes/client-footer.php'; ?>
