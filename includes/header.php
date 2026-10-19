<?php
$currentPage = $currentPage ?? '';
$user = current_user();
$flash = get_flash();

$displayName = $user['nom'] ?? '';
$initials = '';
if ($user) {
    foreach (preg_split('/\s+/', trim($displayName)) as $part) {
        if ($part === '') continue;
        $ch = function_exists('mb_substr')
            ? mb_strtoupper(mb_substr($part, 0, 1))
            : strtoupper(substr($part, 0, 1));
        $initials .= $ch;
        if (strlen($initials) >= 2) break;
    }
    if ($initials === '') {
        $initials = 'U';
    }
}
$isHome = ($currentPage === 'home');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? APP_NAME) ?> — <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=20261016pay">
</head>
<body class="site site-dash <?= $isHome ? 'page-home' : '' ?>">
  <header class="site-header site-header-home21 <?= $isHome ? 'header-home' : '' ?>">
    <div class="container header-row home21-header-row">
      <a class="logo logo-dash" href="<?= e(url('index.php')) ?>">
        <img class="logo-img logo-img-full" src="<?= e(asset(APP_LOGO)) ?>?v=20261015v2" alt="<?= e(APP_NAME) ?>" width="180" height="72">
      </a>

      <nav class="home21-nav desktop-only" aria-label="Navigation principale">
        <a href="<?= e(url('index.php')) ?>" class="home21-nav-item <?= $currentPage === 'home' ? 'is-active' : '' ?>">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z"/></svg>
          <span>Accueil</span>
        </a>
        <a href="<?= e(url('salles.php')) ?>" class="home21-nav-item <?= $currentPage === 'salles' ? 'is-active' : '' ?>">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 21V10l8-5 8 5v11"/><path d="M9 21v-7h6v7"/><path d="M2 21h20"/></svg>
          <span>Salles</span>
        </a>
        <a href="<?= e(url('categories.php')) ?>" class="home21-nav-item <?= $currentPage === 'categories' ? 'is-active' : '' ?>">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.2"/><rect x="14" y="3" width="7" height="7" rx="1.2"/><rect x="3" y="14" width="7" height="7" rx="1.2"/><rect x="14" y="14" width="7" height="7" rx="1.2"/></svg>
          <span>Catégories</span>
        </a>
        <?php if ($user): ?>
          <?php if (is_client()): ?>
            <a href="<?= e(url('client/index.php')) ?>" class="home21-nav-item <?= $currentPage === 'client' ? 'is-active' : '' ?>">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
              <span>Mon espace</span>
            </a>
            <a href="<?= e(url('mes-reservations.php')) ?>" class="home21-nav-item <?= $currentPage === 'mes-reservations' ? 'is-active' : '' ?>">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
              <span>Réservations</span>
            </a>
          <?php endif; ?>

          <?php if (is_proprietaire()): ?>
            <a href="<?= e(url('proprietaire/index.php')) ?>" class="home21-nav-item <?= $currentPage === 'proprietaire' ? 'is-active' : '' ?>">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
              <span>Mon espace</span>
            </a>
            <a href="<?= e(url('proprietaire/messages.php')) ?>" class="home21-nav-item <?= ($propPage ?? '') === 'messages' ? 'is-active' : '' ?>">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              <span>Chat</span>
            </a>
          <?php endif; ?>
          <?php if (is_admin()): ?>
            <a href="<?= e(url('admin/index.php')) ?>" class="home21-nav-item <?= $currentPage === 'admin' ? 'is-active' : '' ?>">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V7l8-4Z"/></svg>
              <span>Admin</span>
            </a>
          <?php endif; ?>
        <?php endif; ?>
      </nav>

      <div class="header-actions home21-actions desktop-only">
        <?php if ($user): ?>
          <?php $notifCount = unread_notifications_count((int) $user['id']); ?>
          <a class="notif-bell <?= $currentPage === 'notifications' ? 'is-active' : '' ?>" href="<?= e(url('notifications.php')) ?>" title="Notifications" aria-label="Notifications">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0"/></svg>
            <?php if ($notifCount > 0): ?><span class="notif-badge"><?= $notifCount > 9 ? '9+' : (int) $notifCount ?></span><?php endif; ?>
          </a>
          <span class="user-label" title="<?= e($user['nom']) ?>"><?= e($user['nom']) ?></span>
          <a class="btn home21-btn" href="<?= e(url('logout.php')) ?>">Déconnexion</a>
        <?php else: ?>
          <a class="btn home21-btn" href="<?= e(url('login.php')) ?>">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            Se connecter
          </a>
          <a class="btn home21-btn home21-btn-fill" href="<?= e(url('register.php')) ?>">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
            S'inscrire
          </a>
        <?php endif; ?>
      </div>

      <button class="nav-burger mobile-only" type="button" aria-label="Ouvrir le menu" aria-expanded="false" data-nav-toggle>
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <div class="nav-overlay" data-nav-overlay hidden></div>
  <aside class="mobile-drawer" id="site-menu" data-nav aria-hidden="true">
    <div class="mobile-drawer-top">
      <a class="logo" href="<?= e(url('index.php')) ?>">
        <img class="logo-img logo-img-full" src="<?= e(asset(APP_LOGO)) ?>?v=20261015v2" alt="<?= e(APP_NAME) ?>" width="160" height="64">
      </a>
      <button type="button" class="drawer-close" aria-label="Fermer" data-nav-close>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
    </div>

    <nav class="mobile-drawer-nav mobile-drawer-nav-icons">
      <a href="<?= e(url('index.php')) ?>" class="<?= $currentPage === 'home' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z"/></svg>
        Accueil
      </a>
      <a href="<?= e(url('salles.php')) ?>" class="<?= $currentPage === 'salles' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18"/><path d="M5 21V8l7-4 7 4v13"/><path d="M9 21v-6h6v6"/></svg>
        Salles
      </a>
      <a href="<?= e(url('categories.php')) ?>" class="<?= $currentPage === 'categories' ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Catégories
      </a>
      <?php if ($user): ?>
        <?php if (is_client()): ?>
          <a href="<?= e(url('client/index.php')) ?>" class="<?= $currentPage === 'client' ? 'is-active' : '' ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            Mon espace
          </a>
          <a href="<?= e(url('mes-reservations.php')) ?>" class="<?= $currentPage === 'mes-reservations' ? 'is-active' : '' ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
            Mes réservations
          </a>
          <a href="<?= e(url('messages.php')) ?>" class="<?= $currentPage === 'messages' ? 'is-active' : '' ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Chat
          </a>
        <?php endif; ?>

        <?php if (is_proprietaire()): ?>
          <a href="<?= e(url('proprietaire/index.php')) ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            Mon espace
          </a>
          <a href="<?= e(url('proprietaire/messages.php')) ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Chat
          </a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
          <a href="<?= e(url('admin/index.php')) ?>">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V7l8-4Z"/></svg>
            Admin
          </a>
        <?php endif; ?>
        <a href="<?= e(url('notifications.php')) ?>">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5"/><path d="M9.5 17a2.5 2.5 0 0 0 5 0"/></svg>
          Notifications
        </a>
      <?php endif; ?>
    </nav>

    <div class="mobile-drawer-foot">
      <?php if ($user): ?>
        <div class="mobile-user">
          <div class="mobile-avatar"><?= e($initials) ?></div>
          <div>
            <strong><?= e($displayName) ?></strong>
            <span><?= e(role_label($user['role'] ?? 'client')) ?></span>
          </div>
        </div>
        <a class="mobile-logout" href="<?= e(url('logout.php')) ?>">Déconnexion</a>
      <?php else: ?>
        <a class="btn home21-btn" href="<?= e(url('login.php')) ?>">se connecter</a>
        <a class="btn home21-btn" href="<?= e(url('register.php')) ?>">s'inscrire</a>
      <?php endif; ?>
    </div>
  </aside>

  <?php if ($flash): ?>
    <div class="container flash-slot">
      <div class="alert alert-<?= e($flash['type']) ?>" role="alert" data-auto-dismiss>
        <?= e($flash['message']) ?>
      </div>
    </div>
  <?php endif; ?>

  <main>
