<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Accueil';
$currentPage = 'home';

$salles = [];
$dbReady = true;
$catCounts = ['vvip' => 0, 'vip' => 0, 'standard' => 0];
$villes = [];

try {
    $salles = db()->query(
        'SELECT * FROM salles WHERE ' . public_salles_sql() . ' ORDER BY capacite DESC, nom LIMIT 6'
    )->fetchAll();

    $countStmt = db()->query(
        'SELECT categorie, COUNT(*) AS n FROM salles WHERE ' . public_salles_sql() . ' GROUP BY categorie'
    );
    foreach ($countStmt->fetchAll() as $row) {
        $key = $row['categorie'] ?? '';
        if (isset($catCounts[$key])) {
            $catCounts[$key] = (int) $row['n'];
        }
    }

    $villes = db()->query(
        'SELECT DISTINCT ville FROM salles WHERE ' . public_salles_sql() . ' ORDER BY ville'
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $dbReady = false;
}

require __DIR__ . '/includes/header.php';

$heroSlides = [
    asset('img/dash-hero-1.jpg'),
    asset('img/dash-hero-2.jpg'),
    asset('img/dash-hero-3.jpg'),
];
$categories = salle_categories();
?>

<section class="lp-hero lp-hero-discover">
  <div class="lp-hero-slider" data-hero-slider aria-hidden="true">
    <?php foreach ($heroSlides as $i => $imgUrl): ?>
      <div
        class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>"
        style="background-image: url('<?= e($imgUrl) ?>')"
      ></div>
    <?php endforeach; ?>
  </div>
  <div class="lp-hero-overlay" aria-hidden="true"></div>

  <div class="container lp-hero-center">
    <p class="lp-kicker lp-fade">Trouvez votre salle</p>
    <h1 class="lp-brand lp-fade lp-fade-delay-1">Congo Events</h1>
    <p class="lp-lead lp-fade lp-fade-delay-2">
      Découvrez les meilleures salles, conférences et expériences pour vos événements en RDC.
    </p>

    <form class="lp-search lp-fade lp-fade-delay-3" method="get" action="<?= e(url('salles.php')) ?>" role="search">
      <label class="lp-search-field">
        <span>Recherche</span>
        <input type="search" name="q" placeholder="Nom de salle…">
      </label>
      <label class="lp-search-field">
        <span>Ville</span>
        <select name="ville">
          <option value="">Toutes</option>
          <?php foreach ($villes as $v): ?>
            <option value="<?= e($v) ?>"><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="lp-search-field">
        <span>Catégorie</span>
        <select name="categorie">
          <option value="">Toutes</option>
          <?php foreach ($categories as $key => $info): ?>
            <option value="<?= e($key) ?>"><?= e($info['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="lp-search-btn">Rechercher</button>
    </form>

    <div class="lp-quick lp-fade lp-fade-delay-3">
      <a class="lp-quick-btn" href="<?= e(url('salles.php')) ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 21V10l8-5 8 5v11"/><path d="M9 21v-7h6v7"/></svg>
        Toutes les salles
      </a>
      <a class="lp-quick-btn" href="<?= e(url('categories.php')) ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Catégories
      </a>
      <a class="lp-quick-btn" href="<?= e(url('register.php?type=proprietaire')) ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        Publier une salle
      </a>
    </div>
  </div>

  <div class="lp-hero-dots" data-hero-dots>
    <?php foreach ($heroSlides as $i => $_): ?>
      <button type="button" class="hero-dot<?= $i === 0 ? ' is-active' : '' ?>" aria-label="Photo <?= $i + 1 ?>" data-hero-dot="<?= $i ?>"></button>
    <?php endforeach; ?>
  </div>
</section>

<section class="lp-cats">
  <div class="container">
    <div class="lp-section-head">
      <h2>Des salles pour chaque standing</h2>
      <p>Choisissez le niveau adapté à votre événement.</p>
    </div>
    <div class="lp-cats-grid">
      <a class="lp-cat" href="<?= e(url('categories.php?cat=vvip')) ?>">
        <span class="lp-cat-tag">VVIP</span>
        <strong>Prestige</strong>
        <span><?= (int) $catCounts['vvip'] ?> salle<?= $catCounts['vvip'] > 1 ? 's' : '' ?></span>
      </a>
      <a class="lp-cat" href="<?= e(url('categories.php?cat=vip')) ?>">
        <span class="lp-cat-tag">VIP</span>
        <strong>Premium</strong>
        <span><?= (int) $catCounts['vip'] ?> salle<?= $catCounts['vip'] > 1 ? 's' : '' ?></span>
      </a>
      <a class="lp-cat" href="<?= e(url('categories.php?cat=standard')) ?>">
        <span class="lp-cat-tag">Standard</span>
        <strong>Polyvalent</strong>
        <span><?= (int) $catCounts['standard'] ?> salle<?= $catCounts['standard'] > 1 ? 's' : '' ?></span>
      </a>
    </div>
  </div>
</section>

<section class="section lp-section">
  <div class="container">
    <div class="lp-head">
      <div>
        <h2>Salles à découvrir</h2>
        <p class="lp-head-sub">Espaces sélectionnés pour vos prochains événements.</p>
      </div>
      <a class="lp-link" href="<?= e(url('salles.php')) ?>">Catalogue complet →</a>
    </div>

    <?php if (!$dbReady): ?>
      <div class="alert alert-info">
        La base de données n'est pas encore installée.
        <a href="<?= e(url('install.php')) ?>">Lancer l'installation</a>
      </div>
    <?php elseif (!$salles): ?>
      <p class="empty">Aucune salle disponible pour le moment.</p>
    <?php else: ?>
      <div class="venue-grid">
        <?php foreach ($salles as $salle): ?>
          <?php require __DIR__ . '/includes/room-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="lp-how">
  <div class="container">
    <div class="lp-section-head lp-section-head-center">
      <h2>Comment réserver</h2>
      <p>Trois étapes simples pour confirmer votre salle.</p>
    </div>
    <ol class="lp-steps">
      <li>
        <span class="lp-step-num" aria-hidden="true">01</span>
        <div>
          <strong>Choisissez</strong>
          <p>Comparez salles, capacité, catégorie et tarif.</p>
        </div>
      </li>
      <li>
        <span class="lp-step-num" aria-hidden="true">02</span>
        <div>
          <strong>Demandez</strong>
          <p>Indiquez les dates et le type d’événement.</p>
        </div>
      </li>
      <li>
        <span class="lp-step-num" aria-hidden="true">03</span>
        <div>
          <strong>Confirmez</strong>
          <p>Recevez la validation dans votre espace.</p>
        </div>
      </li>
    </ol>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
