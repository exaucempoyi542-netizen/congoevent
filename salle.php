<?php
require_once __DIR__ . '/includes/functions.php';
ensure_salle_features_schema();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM salles WHERE id = ?');
$stmt->execute([$id]);
$salle = $stmt->fetch();

if (!$salle || !salle_is_public($salle)) {
    flash('error', 'Salle introuvable ou non disponible.');
    redirect('salles.php');
}

$gallery = salle_images($salle);
$videoUrl = salle_video_url($salle);
$siteWeb = trim((string) ($salle['site_web'] ?? ''));
if ($siteWeb !== '') {
    $siteWeb = normalize_site_url($siteWeb) ?? $siteWeb;
}

$catKey = strtolower((string) ($salle['categorie'] ?? 'standard'));
$equipements = array_values(array_filter(array_map('trim', preg_split('/[,;|]+/', (string) ($salle['equipements'] ?? '')) ?: [])));

$pageTitle = $salle['nom'];
$currentPage = 'salles';
require __DIR__ . '/includes/header.php';
?>

<section class="detail-hero">
  <div class="detail-hero-slider" data-hero-slider aria-hidden="true">
    <?php foreach ($gallery as $i => $imgUrl): ?>
      <div
        class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>"
        style="background-image: url('<?= e($imgUrl) ?>')"
        role="img"
        aria-label="<?= e($salle['nom']) ?> — photo <?= $i + 1 ?>"
      ></div>
    <?php endforeach; ?>
  </div>
  <div class="detail-hero-overlay" aria-hidden="true"></div>
  <div class="container detail-hero-content">
    <a class="detail-back" href="<?= e(url('salles.php')) ?>">← Retour au catalogue</a>
    <span class="detail-hero-cat"><?= e(categorie_label($catKey)) ?></span>
    <h1><?= e($salle['nom']) ?></h1>
    <p><?= e($salle['ville']) ?><?= !empty($salle['commune']) ? ' · ' . e($salle['commune']) : '' ?> · RDC</p>
  </div>
  <?php if (count($gallery) > 1): ?>
    <div class="detail-hero-dots" data-hero-dots>
      <?php foreach ($gallery as $i => $_): ?>
        <button type="button" class="hero-dot<?= $i === 0 ? ' is-active' : '' ?>" aria-label="Photo <?= $i + 1 ?>" data-hero-dot="<?= $i ?>"></button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="container detail-layout">
  <div class="detail-main">
    <div class="detail-specs">
      <div class="detail-spec">
        <span>Capacité</span>
        <strong><?= (int) $salle['capacite'] ?> places</strong>
      </div>
      <div class="detail-spec">
        <span>Catégorie</span>
        <strong><?= e(categorie_label($catKey)) ?></strong>
      </div>
      <div class="detail-spec">
        <span>Tarif</span>
        <strong><?= e(format_salle_tarif($salle)) ?> <small>/ jour</small></strong>
      </div>
      <?php if (!empty($salle['adresse'])): ?>
        <div class="detail-spec detail-spec-wide">
          <span>Adresse</span>
          <strong><?= e($salle['adresse']) ?></strong>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($salle['description'])): ?>
      <div class="detail-block">
        <h2>Description</h2>
        <div class="detail-desc"><?= nl2br(e($salle['description'])) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($equipements): ?>
      <div class="detail-block">
        <h2>Équipements</h2>
        <ul class="detail-tags">
          <?php foreach ($equipements as $eq): ?>
            <li><?= e($eq) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($videoUrl !== ''): ?>
      <div class="detail-block">
        <h2>Vidéo</h2>
        <div class="detail-video">
          <video controls playsinline>
            <source src="<?= e($videoUrl) ?>">
          </video>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <aside class="detail-side">
    <div class="detail-book">
      <p class="detail-book-label">Tarif journalier</p>
      <p class="detail-book-price"><?= e(format_salle_tarif($salle)) ?></p>
      <p class="detail-book-note">Déposez une demande ; le propriétaire ou l’administration confirmera la disponibilité.</p>

      <div class="detail-side-actions">
        <?php if (is_logged_in() && is_client()): ?>
          <a class="btn btn-solid" href="<?= e(url('reservation.php?salle_id=' . (int) $salle['id'])) ?>">Demander une réservation</a>
          <?php if (!empty($salle['proprietaire_id'])): ?>
            <a class="btn btn-ghost" href="<?= e(url('messages.php?salle_id=' . (int) $salle['id'])) ?>">Contacter le propriétaire</a>
          <?php endif; ?>
        <?php elseif (is_logged_in() && is_proprietaire()): ?>
          <p class="muted">Utilisez un compte client pour réserver une salle.</p>
          <a class="btn btn-ghost" href="<?= e(url('proprietaire/index.php')) ?>">Mon espace propriétaire</a>
        <?php elseif (is_logged_in() && is_admin()): ?>
          <p class="muted">Gérez les réservations depuis l’espace admin.</p>
          <a class="btn btn-ghost" href="<?= e(url('admin/reservations.php')) ?>">Voir les réservations</a>
        <?php else: ?>
          <a class="btn btn-solid" href="<?= e(url('login.php')) ?>">Se connecter pour réserver</a>
          <a class="btn btn-ghost" href="<?= e(url('register.php')) ?>">Créer un compte client</a>
        <?php endif; ?>
        <?php if ($siteWeb !== ''): ?>
          <a class="btn btn-ghost" href="<?= e($siteWeb) ?>" target="_blank" rel="noopener noreferrer">Site web de la salle</a>
        <?php endif; ?>
      </div>
    </div>
  </aside>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
