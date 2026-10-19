<?php
require_once __DIR__ . '/includes/functions.php';
ensure_salle_features_schema();

$ville = trim($_GET['ville'] ?? '');
$q = trim($_GET['q'] ?? '');
$categorie = strtolower(trim($_GET['categorie'] ?? ''));
if ($categorie !== '' && !isset(salle_categories()[$categorie])) {
    $categorie = '';
}

$sql = 'SELECT * FROM salles WHERE ' . public_salles_sql();
$params = [];

if ($ville !== '') {
    $sql .= ' AND ville = ?';
    $params[] = $ville;
}
if ($categorie !== '') {
    $sql .= ' AND categorie = ?';
    $params[] = $categorie;
}
if ($q !== '') {
    $sql .= ' AND (nom LIKE ? OR description LIKE ? OR commune LIKE ? OR ville LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY FIELD(categorie, \'vvip\', \'vip\', \'standard\'), ville, nom';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$salles = $stmt->fetchAll();
$total = count($salles);

$villes = db()->query(
    'SELECT DISTINCT ville FROM salles WHERE ' . public_salles_sql() . ' ORDER BY ville'
)->fetchAll(PDO::FETCH_COLUMN);

$hasFilters = ($q !== '' || $ville !== '' || $categorie !== '');

$pageTitle = 'Salles';
$currentPage = 'salles';
require __DIR__ . '/includes/header.php';
?>

<section class="salle-page">
  <div class="container">
    <form class="salle-filters" method="get" role="search">
      <div class="salle-filters-main">
        <label class="salle-search">
          <span class="sr-only">Rechercher</span>
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
          <input type="search" name="q" placeholder="Rechercher une salle, une ville…" value="<?= e($q) ?>">
        </label>
        <select name="ville" aria-label="Ville">
          <option value="">Toutes les villes</option>
          <?php foreach ($villes as $v): ?>
            <option value="<?= e($v) ?>" <?= $ville === $v ? 'selected' : '' ?>><?= e($v) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="categorie" aria-label="Catégorie">
          <option value="">Toutes les catégories</option>
          <?php foreach (salle_categories() as $key => $info): ?>
            <option value="<?= e($key) ?>" <?= $categorie === $key ? 'selected' : '' ?>><?= e($info['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filtrer</button>
      </div>
    </form>

    <div class="salle-toolbar">
      <div class="salle-cat-chips">
        <?php
          $chipBase = [];
          if ($q !== '') {
              $chipBase['q'] = $q;
          }
          if ($ville !== '') {
              $chipBase['ville'] = $ville;
          }
          $allCatHref = 'salles.php' . ($chipBase ? '?' . http_build_query($chipBase) : '');
        ?>
        <a class="chip <?= $categorie === '' ? 'is-active' : '' ?>" href="<?= e(url($allCatHref)) ?>">Toutes</a>
        <?php foreach (salle_categories() as $key => $info): ?>
          <?php
            $qs = $chipBase;
            $qs['categorie'] = $key;
            $href = 'salles.php?' . http_build_query($qs);
          ?>
          <a class="chip <?= $categorie === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>"><?= e($info['label']) ?></a>
        <?php endforeach; ?>
      </div>
      <p class="salle-count">
        <strong><?= (int) $total ?></strong>
        salle<?= $total > 1 ? 's' : '' ?>
        <?php if ($hasFilters): ?> trouvée<?= $total > 1 ? 's' : '' ?><?php endif; ?>
      </p>
    </div>

    <?php if (!$salles): ?>
      <div class="salle-empty">
        <div class="salle-empty-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V10l8-5 8 5v11"/><path d="M9 21v-7h6v7"/><path d="M2 21h20"/></svg>
        </div>
        <h2>Aucune salle trouvée</h2>
        <p>Essayez d’élargir votre recherche ou de retirer certains filtres.</p>
        <?php if ($hasFilters): ?>
          <a class="btn btn-primary" href="<?= e(url('salles.php')) ?>">Réinitialiser les filtres</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="venue-grid">
        <?php foreach ($salles as $salle): ?>
          <?php require __DIR__ . '/includes/room-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
