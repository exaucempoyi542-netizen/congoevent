<?php
require_once __DIR__ . '/../includes/functions.php';
require_proprietaire();
ensure_salle_devise_schema();

$userId = (int) current_user()['id'];
$hasAbo = has_active_subscription();
$edit = null;
$errors = [];

if (isset($_GET['edit'])) {
    $stmt = db()->prepare('SELECT * FROM salles WHERE id = ? AND proprietaire_id = ?');
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $edit = $stmt->fetch() ?: null;
}

if (isset($_POST['toggle_id'])) {
    $tid = (int) $_POST['toggle_id'];
    $check = db()->prepare('SELECT id FROM salles WHERE id = ? AND proprietaire_id = ?');
    $check->execute([$tid, $userId]);
    if ($check->fetch()) {
        db()->prepare(
            "UPDATE salles SET statut = IF(statut = 'active', 'inactive', 'active') WHERE id = ?"
        )->execute([$tid]);
        flash('success', 'Statut de la salle mis à jour.');
    }
    redirect('proprietaire/salles.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_salle'])) {
    if (!$hasAbo) {
        $errors[] = 'Un abonnement actif est requis pour publier une salle.';
    }

    $id = (int) ($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $capacite = (int) ($_POST['capacite'] ?? 0);
    $ville = trim($_POST['ville'] ?? '');
    $commune = trim($_POST['commune'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $equipements = trim($_POST['equipements'] ?? '');
    $tarif = (float) str_replace([' ', ','], ['', '.'], $_POST['tarif_jour'] ?? '0');
    $devise = strtoupper(trim($_POST['devise'] ?? 'FC')) === 'USD' ? 'USD' : 'FC';
    $categorie = $_POST['categorie'] ?? 'standard';
    if (!isset(salle_categories()[$categorie])) {
        $categorie = 'standard';
    }
    $siteWebRaw = trim($_POST['site_web'] ?? '');
    $siteWeb = $siteWebRaw !== '' ? normalize_site_url($siteWebRaw) : null;
    if ($siteWebRaw !== '' && $siteWeb === null) {
        $errors[] = 'Lien du site web invalide (ex. https://exemple.com).';
    }

    $currentImage = trim($_POST['image_actuelle'] ?? '');
    $currentImage2 = trim($_POST['image2_actuelle'] ?? '');
    $currentVideo = trim($_POST['video_actuelle'] ?? '');

    if ($id > 0) {
        $own = db()->prepare('SELECT id FROM salles WHERE id = ? AND proprietaire_id = ?');
        $own->execute([$id, $userId]);
        if (!$own->fetch()) {
            $errors[] = 'Salle introuvable.';
        }
    }

    if ($nom === '' || $ville === '' || $capacite < 1) {
        $errors[] = 'Nom, ville et capacité (> 0) sont obligatoires.';
    }

    $imagePath = $currentImage !== '' ? $currentImage : null;
    $upload = upload_salle_photo($_FILES['photo'] ?? [], $currentImage !== '' ? $currentImage : null);
    if (!$upload['ok']) {
        $errors[] = $upload['error'];
    } elseif ($upload['path']) {
        $imagePath = $upload['path'];
    }

    $image2Path = $currentImage2 !== '' ? $currentImage2 : null;
    $upload2 = upload_salle_photo($_FILES['photo2'] ?? [], $currentImage2 !== '' ? $currentImage2 : null);
    if (!$upload2['ok']) {
        $errors[] = $upload2['error'];
    } elseif ($upload2['path']) {
        $image2Path = $upload2['path'];
    }

    $videoPath = $currentVideo !== '' ? $currentVideo : null;
    $uploadVid = upload_salle_video($_FILES['video'] ?? [], $currentVideo !== '' ? $currentVideo : null);
    if (!$uploadVid['ok']) {
        $errors[] = $uploadVid['error'];
    } elseif ($uploadVid['path']) {
        $videoPath = $uploadVid['path'];
    }

    if (!$errors) {
        ensure_salle_features_schema();
        if ($id > 0) {
            $sql = "UPDATE salles SET nom=?, description=?, capacite=?, ville=?, commune=?, adresse=?, equipements=?, tarif_jour=?, devise=?, categorie=?, image=?, image2=?, video=?, site_web=? WHERE id=? AND proprietaire_id=?";
            db()->prepare($sql)->execute([
                $nom, $description ?: null, $capacite, $ville,
                $commune ?: null, $adresse ?: null, $equipements ?: null, $tarif, $devise, $categorie,
                $imagePath, $image2Path, $videoPath, $siteWeb, $id, $userId
            ]);
            flash('success', 'Salle mise à jour.');
        } else {
            $sql = "INSERT INTO salles (nom, description, capacite, ville, commune, adresse, equipements, tarif_jour, devise, categorie, image, image2, video, site_web, statut, proprietaire_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)";
            db()->prepare($sql)->execute([
                $nom, $description ?: null, $capacite, $ville,
                $commune ?: null, $adresse ?: null, $equipements ?: null, $tarif, $devise, $categorie,
                $imagePath, $image2Path, $videoPath, $siteWeb, $userId
            ]);
            flash('success', 'Salle publiée dans le catalogue.');
        }
        redirect('proprietaire/salles.php');
    }

    $edit = [
        'id' => $id,
        'nom' => $nom,
        'description' => $description,
        'capacite' => $capacite,
        'ville' => $ville,
        'commune' => $commune,
        'adresse' => $adresse,
        'equipements' => $equipements,
        'tarif_jour' => $tarif,
        'devise' => $devise,
        'categorie' => $categorie,
        'image' => $imagePath,
        'image2' => $image2Path,
        'video' => $videoPath,
        'site_web' => $siteWebRaw,
    ];
}

$stmt = db()->prepare('SELECT * FROM salles WHERE proprietaire_id = ? ORDER BY statut, ville, nom');
$stmt->execute([$userId]);
$salles = $stmt->fetchAll();

$pageTitle = 'Mes salles';
$propPage = 'salles';
require __DIR__ . '/../includes/proprietaire-header.php';

ensure_salle_features_schema();
$previewSrc = !empty($edit['image']) ? media_url($edit['image']) : '';
$previewSrc2 = !empty($edit['image2']) ? media_url($edit['image2']) : '';
$previewVideo = !empty($edit['video']) ? media_url($edit['video']) : '';
?>

<?php if (!$hasAbo): ?>
  <div class="alert alert-warning" style="margin-bottom:1.25rem">
    Abonnement requis pour ajouter des salles au catalogue.
    <a href="<?= e(url('proprietaire/abonnement.php')) ?>">Souscrire →</a>
  </div>
<?php endif; ?>

<?php if ($hasAbo): ?>
<div class="dash-panel" style="margin-bottom:1.25rem">
  <div class="dash-panel-head">
    <h2><?= $edit ? 'Modifier la salle' : 'Nouvelle salle' ?></h2>
  </div>
  <div class="form-panel wide">
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="save_salle" value="1">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <input type="hidden" name="image_actuelle" value="<?= e($edit['image'] ?? '') ?>">
      <input type="hidden" name="image2_actuelle" value="<?= e($edit['image2'] ?? '') ?>">
      <input type="hidden" name="video_actuelle" value="<?= e($edit['video'] ?? '') ?>">

      <div class="form-group">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom" value="<?= e($edit['nom'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description"><?= e($edit['description'] ?? '') ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="categorie">Catégorie</label>
          <select id="categorie" name="categorie" required>
            <?php $catEdit = $edit['categorie'] ?? 'standard'; ?>
            <?php foreach (salle_categories() as $key => $cat): ?>
              <option value="<?= e($key) ?>" <?= $catEdit === $key ? 'selected' : '' ?>><?= e($cat['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="capacite">Capacité</label>
          <input type="number" id="capacite" name="capacite" min="1" value="<?= e((string) ($edit['capacite'] ?? '50')) ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="devise">Devise</label>
          <select id="devise" name="devise" required>
            <?php $deviseEdit = ($edit['devise'] ?? 'FC') === 'USD' ? 'USD' : 'FC'; ?>
            <option value="FC" <?= $deviseEdit === 'FC' ? 'selected' : '' ?>>Franc congolais (FC)</option>
            <option value="USD" <?= $deviseEdit === 'USD' ? 'selected' : '' ?>>Dollar américain (USD)</option>
          </select>
        </div>
        <div class="form-group">
          <label for="tarif_jour">Tarif / jour</label>
          <input type="number" id="tarif_jour" name="tarif_jour" min="0" step="any" value="<?= e((string) ($edit['tarif_jour'] ?? '0')) ?>" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="ville">Ville</label>
          <input type="text" id="ville" name="ville" value="<?= e($edit['ville'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label for="commune">Commune</label>
          <input type="text" id="commune" name="commune" value="<?= e($edit['commune'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label for="adresse">Adresse</label>
        <input type="text" id="adresse" name="adresse" value="<?= e($edit['adresse'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="equipements">Équipements</label>
        <input type="text" id="equipements" name="equipements" value="<?= e($edit['equipements'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="site_web">Lien du site web (réel)</label>
        <input type="url" id="site_web" name="site_web" value="<?= e($edit['site_web'] ?? '') ?>" placeholder="https://www.exemple.com">
        <small class="muted">URL publique de la salle ou de votre établissement.</small>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="photo">Photo 1</label>
          <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
          <?php if ($previewSrc !== ''): ?>
            <img src="<?= e($previewSrc) ?>" alt="Aperçu 1" class="photo-preview">
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label for="photo2">Photo 2</label>
          <input type="file" id="photo2" name="photo2" accept="image/jpeg,image/png,image/webp">
          <?php if ($previewSrc2 !== ''): ?>
            <img src="<?= e($previewSrc2) ?>" alt="Aperçu 2" class="photo-preview">
          <?php endif; ?>
        </div>
      </div>
      <div class="form-group">
        <label for="video">Vidéo (MP4/WEBM, max 20 Mo)</label>
        <input type="file" id="video" name="video" accept="video/mp4,video/webm,video/quicktime">
        <?php if ($previewVideo !== ''): ?>
          <video class="photo-preview" src="<?= e($previewVideo) ?>" controls style="max-width:100%;margin-top:.5rem"></video>
        <?php endif; ?>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $edit ? 'Enregistrer' : 'Publier' ?></button>
        <?php if ($edit): ?>
          <a class="btn btn-ghost" href="<?= e(url('proprietaire/salles.php')) ?>">Annuler</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="dash-panel">
  <div class="dash-panel-head">
    <h2>Mes salles (<?= count($salles) ?>)</h2>
  </div>
  <?php if (!$salles): ?>
    <p class="dash-empty">Aucune salle publiée.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nom</th>
            <th>Catégorie</th>
            <th>Localisation</th>
            <th>Capacité</th>
            <th>Tarif</th>
            <th>Visibilité</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($salles as $s): ?>
            <?php $visible = $hasAbo && $s['statut'] === 'active'; ?>
            <tr>
              <td><strong><?= e($s['nom']) ?></strong></td>
              <td><span class="badge"><?= e(categorie_label($s['categorie'] ?? 'standard')) ?></span></td>
              <td><?= e($s['ville']) ?><?= $s['commune'] ? ' · ' . e($s['commune']) : '' ?></td>
              <td><?= (int) $s['capacite'] ?></td>
              <td><?= e(format_salle_tarif($s)) ?></td>
              <td><span class="badge"><?= $visible ? 'Catalogue' : 'Masquée' ?></span></td>
              <td><span class="badge"><?= e(statut_label($s['statut'])) ?></span></td>
              <td class="actions">
                <?php if ($hasAbo): ?>
                  <a class="btn btn-ghost btn-sm" href="<?= e(url('proprietaire/salles.php?edit=' . (int) $s['id'])) ?>">Modifier</a>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="toggle_id" value="<?= (int) $s['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm"><?= $s['statut'] === 'active' ? 'Désactiver' : 'Activer' ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/proprietaire-footer.php'; ?>
