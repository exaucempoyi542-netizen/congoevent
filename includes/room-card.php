<?php
$sid = (int) $salle['id'];
$img = salle_image($salle);
$cap = (int) $salle['capacite'];
$catKey = strtolower((string) ($salle['categorie'] ?? 'standard'));
$catLabel = categorie_label($catKey);

$ville = trim((string) ($salle['ville'] ?? ''));
$commune = trim((string) ($salle['commune'] ?? ''));
$lieu = $ville;
if ($commune !== '' && strcasecmp($commune, $ville) !== 0) {
    $lieu .= ' · ' . $commune;
}
?>
<article class="venue-card">
  <a class="venue-media" href="<?= e(url('salle.php?id=' . $sid)) ?>">
    <img src="<?= e($img) ?>" alt="<?= e($salle['nom']) ?>" loading="lazy" width="640" height="420">
    <span class="venue-badge venue-badge--<?= e($catKey) ?>"><?= e($catLabel) ?></span>
  </a>

  <div class="venue-body">
    <h3><a href="<?= e(url('salle.php?id=' . $sid)) ?>"><?= e($salle['nom']) ?></a></h3>

    <ul class="venue-meta">
      <?php if ($lieu !== ''): ?>
        <li>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
          <span><?= e($lieu) ?></span>
        </li>
      <?php endif; ?>
      <li>
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span><?= $cap ?> places</span>
      </li>
    </ul>

    <div class="venue-foot">
      <div class="venue-price">
        <strong><?= e(format_salle_tarif($salle)) ?></strong>
        <span>par jour</span>
      </div>
      <a class="venue-cta" href="<?= e(url('salle.php?id=' . $sid)) ?>">Voir détails</a>
    </div>
  </div>
</article>
