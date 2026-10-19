<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect_after_login();
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email et mot de passe requis.';
    } else {
        $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['user'] = [
                'id'    => (int) $user['id'],
                'nom'   => $user['nom'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            flash('success', 'Bienvenue, ' . $user['nom'] . ' !');
            redirect_after_login();
        }
        $errors[] = 'Identifiants incorrects.';
    }
}

$pageTitle = 'Connexion';
$currentPage = 'login';
require __DIR__ . '/includes/header.php';
?>

<section class="auth-shell">
  <div class="container auth-shell-inner">
    <aside class="auth-aside" aria-hidden="true">
      <h2>Congo Events</h2>
      <p>Réservez et gérez vos salles pour conférences et événements culturels en RDC.</p>
    </aside>

    <div class="form-panel auth-card">
      <div class="auth-card-brand">
        <img src="<?= e(asset(APP_LOGO)) ?>?v=20261015v2" alt="<?= e(APP_NAME) ?>" width="160" height="64">
      </div>
      <h1>Connexion</h1>
      <p class="lead">Accédez à votre espace client, propriétaire ou administrateur.</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>

      <form method="post" novalidate>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email" placeholder="vous@email.com">
        </div>
        <div class="form-group">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
        </div>
        <div class="form-actions auth-actions">
          <button type="submit" class="btn btn-primary">Se connecter</button>
          <a href="<?= e(url('register.php')) ?>">Créer un compte</a>
        </div>
      </form>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
