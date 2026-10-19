<?php
require_once __DIR__ . '/includes/functions.php';
require_login();

if (!is_client()) {
    if (is_proprietaire()) {
        redirect('proprietaire/messages.php' . (isset($_GET['c']) ? '?c=' . (int) $_GET['c'] : ''));
    }
    flash('error', 'Le chat est réservé aux clients et propriétaires.');
    redirect('index.php');
}

$userId = (int) current_user()['id'];
ensure_chat_schema();

if (isset($_GET['salle_id'])) {
    $salleId = (int) $_GET['salle_id'];
    $stmt = db()->prepare('SELECT id, proprietaire_id, nom FROM salles WHERE id = ? AND statut = \'active\'');
    $stmt->execute([$salleId]);
    $salle = $stmt->fetch();
    if (!$salle || empty($salle['proprietaire_id'])) {
        flash('error', 'Impossible de contacter le propriétaire de cette salle.');
        redirect('salles.php');
    }
    $conv = get_or_create_conversation($userId, (int) $salle['proprietaire_id'], $salleId);
    if (!$conv) {
        flash('error', 'Impossible d’ouvrir la conversation.');
        redirect('salles.php');
    }
    redirect('messages.php?c=' . (int) $conv['id']);
}

$activeId = (int) ($_GET['c'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $cid = (int) ($_POST['conversation_id'] ?? 0);
    $body = trim($_POST['contenu'] ?? '');
    $file = $_FILES['media'] ?? null;
    $result = send_chat_message($cid, $userId, $body, $file);
    if ($result['ok']) {
        redirect('messages.php?c=' . $cid);
    }
    flash('error', $result['error'] ?? 'Envoi impossible.');
    redirect('messages.php?c=' . $cid);
}

$conversations = list_conversations_for_user($userId, 'client');
$active = $activeId > 0 ? fetch_conversation($activeId) : null;
if ($active && !user_can_access_conversation($active, $userId)) {
    $active = null;
    $activeId = 0;
}
$thread = [];
if ($active) {
    mark_conversation_read((int) $active['id'], $userId);
    $thread = fetch_messages((int) $active['id']);
}

$peerName = $active['proprietaire_nom'] ?? '';
$peerInitials = '';
foreach (preg_split('/\s+/', trim($peerName)) as $part) {
    if ($part === '') continue;
    $ch = function_exists('mb_substr') ? mb_strtoupper(mb_substr($part, 0, 1)) : strtoupper(substr($part, 0, 1));
    $peerInitials .= $ch;
    if (strlen($peerInitials) >= 2) break;
}
if ($peerInitials === '') {
    $peerInitials = 'PR';
}

$pageTitle = 'Chat';
$clientPage = 'messages';
require __DIR__ . '/includes/client-header.php';
?>

<div class="chat-app <?= $active ? 'has-active' : '' ?>">
  <aside class="chat-sidebar">
    <div class="chat-sidebar-head">
      <h2>Discussions</h2>
      <p>Échanges avec les propriétaires</p>
    </div>
    <div class="chat-list">
      <?php if (!$conversations): ?>
        <div class="chat-empty">
          <p>Aucune discussion.</p>
          <a class="btn btn-primary btn-sm" href="<?= e(url('salles.php')) ?>">Voir les salles</a>
        </div>
      <?php else: ?>
        <?php foreach ($conversations as $c): ?>
          <?php
            $name = (string) ($c['peer_nom'] ?? '');
            $ini = '';
            foreach (preg_split('/\s+/', trim($name)) as $part) {
                if ($part === '') continue;
                $ch = function_exists('mb_substr') ? mb_strtoupper(mb_substr($part, 0, 1)) : strtoupper(substr($part, 0, 1));
                $ini .= $ch;
                if (strlen($ini) >= 2) break;
            }
            if ($ini === '') {
                $ini = '?';
            }
          ?>
          <a class="chat-list-item <?= (int) $c['id'] === $activeId ? 'is-active' : '' ?>" href="<?= e(url('messages.php?c=' . (int) $c['id'])) ?>">
            <span class="chat-avatar" aria-hidden="true"><?= e($ini) ?></span>
            <span class="chat-list-body">
              <span class="chat-list-top">
                <strong><?= e($name) ?></strong>
                <?php if ((int) ($c['unread'] ?? 0) > 0): ?>
                  <em class="chat-unread"><?= (int) $c['unread'] ?></em>
                <?php endif; ?>
              </span>
              <?php if (!empty($c['salle_nom'])): ?>
                <span class="chat-meta"><?= e($c['salle_nom']) ?></span>
              <?php endif; ?>
              <span class="chat-preview"><?= e(excerpt(chat_preview_label($c['last_msg'] ?? '', $c['last_type'] ?? 'text'), 55)) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </aside>

  <section class="chat-thread">
    <?php if (!$active): ?>
      <div class="chat-placeholder">
        <div class="chat-placeholder-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <h3>Vos messages</h3>
        <p>Sélectionnez une conversation ou contactez un propriétaire depuis une salle.</p>
        <a class="btn btn-primary" href="<?= e(url('salles.php')) ?>">Parcourir les salles</a>
      </div>
    <?php else: ?>
      <header class="chat-thread-head">
        <a class="chat-back" href="<?= e(url('messages.php')) ?>" title="Retour aux discussions" aria-label="Retour aux discussions">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <span class="chat-avatar chat-avatar-lg" aria-hidden="true"><?= e($peerInitials) ?></span>
        <div class="chat-thread-meta">
          <strong><?= e($peerName) ?></strong>
          <?php if (!empty($active['salle_nom'])): ?>
            <span><?= e($active['salle_nom']) ?></span>
          <?php else: ?>
            <span>Propriétaire</span>
          <?php endif; ?>
        </div>
      </header>
      <div class="chat-messages" data-chat-scroll>
        <?php if (!$thread): ?>
          <div class="chat-day-hint">Début de la conversation — dites bonjour</div>
        <?php endif; ?>
        <?php require __DIR__ . '/includes/chat-bubbles.php'; ?>
      </div>
      <?php
      $composePlaceholder = 'Écrire un message…';
      require __DIR__ . '/includes/chat-compose.php';
      ?>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/includes/client-footer.php'; ?>
