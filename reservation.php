<?php
require_once __DIR__ . '/includes/functions.php';
require_client();
ensure_reservation_payment_schema();

$salleId = (int) ($_GET['salle_id'] ?? $_POST['salle_id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM salles WHERE id = ?');
$stmt->execute([$salleId]);
$salle = $stmt->fetch();

if (!$salle || !salle_is_public($salle)) {
    flash('error', 'Salle introuvable ou inactive.');
    redirect('salles.php');
}

$errors = [];
$titre = $type = $description = '';
$debut = $fin = '';
$modePaiement = 'presentiel';
$payModes = reservation_payment_modes();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre_evenement'] ?? '');
    $type = $_POST['type_evenement'] ?? 'conference';
    $debutRaw = $_POST['date_debut'] ?? '';
    $finRaw = $_POST['date_fin'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $modePaiement = $_POST['mode_paiement'] ?? 'presentiel';
    if (!isset($payModes[$modePaiement])) {
        $modePaiement = 'presentiel';
    }

    $allowedTypes = ['conference', 'concert', 'exposition', 'atelier', 'ceremonie', 'reunion', 'autre'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'autre';
    }

    if ($titre === '') {
        $errors[] = 'Le titre de l\'événement est requis.';
    }

    $debutDt = DateTime::createFromFormat('Y-m-d\TH:i', $debutRaw);
    $finDt = DateTime::createFromFormat('Y-m-d\TH:i', $finRaw);
    $debut = $debutDt ? $debutDt->format('Y-m-d H:i:s') : '';
    $fin = $finDt ? $finDt->format('Y-m-d H:i:s') : '';

    if (!$debutDt || !$finDt) {
        $errors[] = 'Dates invalides.';
    } elseif ($finDt <= $debutDt) {
        $errors[] = 'La date de fin doit être après la date de début.';
    } elseif ($debutDt < new DateTime('now')) {
        $errors[] = 'La date de début doit être dans le futur.';
    }

    if (!$errors && has_conflict($salleId, $debut, $fin)) {
        $errors[] = 'Cette salle a déjà une réservation confirmée sur ce créneau.';
    }

    if (!$errors) {
        $ins = db()->prepare(
            "INSERT INTO reservations
            (utilisateur_id, salle_id, titre_evenement, type_evenement, date_debut, date_fin, description, statut, mode_paiement, statut_paiement)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, 'en_attente')"
        );
        $ins->execute([
            current_user()['id'],
            $salleId,
            $titre,
            $type,
            $debut,
            $fin,
            $description ?: null,
            $modePaiement,
        ]);
        $reservationId = (int) db()->lastInsertId();
        notify_new_reservation_request($reservationId);
        flash('success', 'Demande de réservation envoyée. Elle est en attente de validation.');
        redirect('mes-reservations.php');
    }

    $debut = $debutRaw;
    $fin = $finRaw;
}

$pageTitle = 'Réserver';
$currentPage = 'salles';
require __DIR__ . '/includes/header.php';

$salleImg = salle_image($salle);
?>

<section class="book-page">
  <div class="container book-layout">
    <aside class="book-summary">
      <a class="detail-back" href="<?= e(url('salle.php?id=' . (int) $salle['id'])) ?>">← Retour à la salle</a>
      <div class="book-summary-media">
        <img src="<?= e($salleImg) ?>" alt="<?= e($salle['nom']) ?>" width="480" height="320" loading="lazy">
      </div>
      <h2><?= e($salle['nom']) ?></h2>
      <p class="muted"><?= e($salle['ville']) ?><?= !empty($salle['commune']) ? ' · ' . e($salle['commune']) : '' ?></p>
      <ul class="book-summary-list">
        <li><span>Catégorie</span><strong><?= e(categorie_label($salle['categorie'] ?? 'standard')) ?></strong></li>
        <li><span>Capacité</span><strong><?= (int) $salle['capacite'] ?> places</strong></li>
        <li><span>Tarif</span><strong><?= e(format_salle_tarif($salle)) ?> / jour</strong></li>
      </ul>
    </aside>

    <div class="book-form form-panel wide">
      <h1>Demande de réservation</h1>
      <p class="lead">Indiquez les informations de votre événement. La demande sera examinée avant confirmation.</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post">
        <input type="hidden" name="salle_id" value="<?= (int) $salle['id'] ?>">

        <div class="form-group">
          <label for="titre_evenement">Titre de l'événement</label>
          <input type="text" id="titre_evenement" name="titre_evenement" value="<?= e($titre) ?>" required placeholder="Ex. Conférence annuelle 2026">
        </div>

        <div class="form-group">
          <label for="type_evenement">Type d'événement</label>
          <select id="type_evenement" name="type_evenement" required>
            <?php
            $types = [
                'conference' => 'Conférence',
                'concert' => 'Concert',
                'exposition' => 'Exposition',
                'atelier' => 'Atelier',
                'ceremonie' => 'Cérémonie',
                'reunion' => 'Réunion',
                'autre' => 'Autre',
            ];
            foreach ($types as $val => $label):
            ?>
              <option value="<?= $val ?>" <?= $type === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="date_debut">Début</label>
            <input type="datetime-local" id="date_debut" name="date_debut" value="<?= e($debut) ?>" required>
          </div>
          <div class="form-group">
            <label for="date_fin">Fin</label>
            <input type="datetime-local" id="date_fin" name="date_fin" value="<?= e($fin) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="description">Description <span class="muted">(optionnel)</span></label>
          <textarea id="description" name="description" placeholder="Précisez le programme, le nombre d'invités, etc."><?= e($description) ?></textarea>
        </div>

        <fieldset class="pay-mode-fieldset">
          <legend>Mode de paiement</legend>
          <p class="muted pay-mode-hint">Après validation, vous pourrez payer sur place ou en ligne (simulation).</p>
          <div class="pay-modes">
            <?php foreach ($payModes as $key => $mode): ?>
              <label class="pay-mode">
                <input type="radio" name="mode_paiement" value="<?= e($key) ?>" required <?= $modePaiement === $key ? 'checked' : '' ?>>
                <span class="pay-mode-card">
                  <strong><?= e($mode['label']) ?></strong>
                  <small><?= e($mode['desc']) ?></small>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Envoyer la demande</button>
          <a class="btn btn-ghost" href="<?= e(url('salle.php?id=' . (int) $salle['id'])) ?>">Annuler</a>
        </div>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
