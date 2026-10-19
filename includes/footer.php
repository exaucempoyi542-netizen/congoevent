</main>

  <footer class="site-footer site-footer-dash">
    <div class="container footer-grid">
      <div class="footer-brand">
        <div class="logo">
          <img class="logo-img logo-img-full logo-img-footer" src="<?= e(asset(APP_LOGO)) ?>?v=20261015v2" alt="<?= e(APP_NAME) ?>" width="200" height="80">
        </div>
        <p>La plateforme professionnelle de réservation de salles pour conférences et événements culturels en République Démocratique du Congo.</p>
      </div>
      <div>
        <h4>Navigation</h4>
        <a href="<?= e(url('salles.php')) ?>">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V8l7-4 7 4v13"/></svg>
          Catalogue des salles
        </a>
        <a href="<?= e(url('register.php')) ?>">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          Créer un compte
        </a>
        <a href="<?= e(url('login.php')) ?>">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5M15 12H3"/></svg>
          Connexion
        </a>
        <a href="<?= e(url('register.php?type=proprietaire')) ?>">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Publier ma salle
        </a>
      </div>
      <div>
        <h4>Couverture</h4>
        <p>Kinshasa · Lubumbashi<br>Kisangani · Matadi</p>
      </div>
    </div>
    <div class="container footer-bottom">
      <span>&copy; <?= date('Y') ?> Congo Events</span>
      <span>Événements culturels &amp; conférences</span>
    </div>
  </footer>

  <script src="<?= e(asset('js/app.js')) ?>?v=20261016slider"></script>
</body>
</html>
