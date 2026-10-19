<?php
require_once __DIR__ . '/includes/functions.php';
ensure_salle_features_schema();

$cat = strtolower(trim($_GET['cat'] ?? ''));
$categories = salle_categories();
if ($cat !== '' && !isset($categories[$cat])) {
    $cat = '';
}

$sql = 'SELECT * FROM salles WHERE ' . public_salles_sql();
$params = [];
if ($cat !== '') {
    $sql .= ' AND categorie = ?';
    $params[] = $cat;
}
$sql .= ' ORDER BY FIELD(categorie, \'vvip\', \'vip\', \'standard\'), ville, nom';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$salles = $stmt->fetchAll();

$counts = [];
foreach (array_keys($categories) as $key) {
    $counts[$key] = 0;
}
$countStmt = db()->query(
    'SELECT categorie, COUNT(*) AS n FROM salles WHERE ' . public_salles_sql() . ' GROUP BY categorie'
);
foreach ($countStmt->fetchAll() as $row) {
    $key = $row['categorie'] ?? 'standard';
    if (isset($counts[$key])) {
        $counts[$key] = (int) $row['n'];
    }
}

$pageTitle = $cat !== '' ? ('Catégorie ' . categorie_label($cat)) : 'Catégories';
$currentPage = 'categories';
require __DIR__ . '/includes/header.php';
?>

<section class="salle-page">
  <div class="container">
    <header class="salle-page-head">
      <div>
        <p class="salle-eyebrow">Standing</p>
        <h1><?= $cat !== '' ? e(categorie_label($cat)) : 'Catégories de salles' ?></h1>
        <p>
          <?php if ($cat !== ''): ?>
            <?= e($categories[$cat]['desc'] ?? 'Salles de cette catégorie.') ?>
          <?php else: ?>
            Filtrez les espaces selon le standing : VVIP, VIP ou Standard.
          <?php endif; ?>
        </p>
      </div>
    </header>

    <?php if ($cat === ''): ?>
      <div class="cat-grid">
        <?php foreach ($categories as $key => $info): ?>
          <a class="cat-card cat-card--<?= e($key) ?>" href="<?= e(url('categories.php?cat=' . urlencode($key))) ?>">
            <span class="cat-card-label"><?= e($info['label']) ?></span>
            <strong class="cat-card-count"><?= (int) ($counts[$key] ?? 0) ?></strong>
            <span class="cat-card-meta"><?= e($info['desc']) ?></span>
            <span class="cat-card-cta">Voir le catalogue →</span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="salle-toolbar">
        <div class="salle-cat-chips">
          <a class="chip" href="<?= e(url('categories.php')) ?>">← Toutes</a>
          <?php foreach ($categories as $key => $info): ?>
            <a class="chip <?= $cat === $key ? 'is-active' : '' ?>" href="<?= e(url('categories.php?cat=' . urlencode($key))) ?>">
              <?= e($info['label']) ?> (<?= (int) ($counts[$key] ?? 0) ?>)
            </a>
          <?php endforeach; ?>
        </div>
        <p class="salle-count">
          <strong><?= count($salles) ?></strong>
          salle<?= count($salles) > 1 ? 's' : '' ?>
        </p>
      </div>

      <?php if (!$salles): ?>
        <div class="salle-empty">
          <div class="salle-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V10l8-5 8 5v11"/><path d="M9 21v-7h6v7"/></svg>
          </div>
          <h2>Aucune salle dans cette catégorie</h2>
          <p>Revenez plus tard ou explorez une autre catégorie.</p>
          <a class="btn btn-primary" href="<?= e(url('categories.php')) ?>">Voir toutes les catégories</a>
        </div>
      <?php else: ?>
        <div class="venue-grid">
          <?php foreach ($salles as $salle): ?>
            <?php require __DIR__ . '/includes/room-card.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
