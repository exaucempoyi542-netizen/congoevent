<?php
/** Formulaire chat : texte + fichier + vocal. Attendu : $active (conversation), $composePlaceholder */
$composePlaceholder = $composePlaceholder ?? 'Écrire un message…';
?>
<form method="post" class="chat-compose" enctype="multipart/form-data" data-chat-compose>
  <input type="hidden" name="send_message" value="1">
  <input type="hidden" name="conversation_id" value="<?= (int) $active['id'] ?>">

  <div class="chat-compose-bar">
    <label class="chat-icon-btn" title="Joindre image ou vidéo">
      <input type="file" name="media" accept="image/*,video/mp4,video/webm,video/quicktime" hidden data-chat-file>
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M21.44 11.05l-8.49 8.49a5.5 5.5 0 0 1-7.78-7.78l8.49-8.49a3.5 3.5 0 0 1 4.95 4.95l-8.5 8.49a1.5 1.5 0 0 1-2.12-2.12l7.78-7.78"/></svg>
    </label>

    <button type="button" class="chat-icon-btn" data-chat-mic title="Message vocal">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9"><path d="M12 3a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V6a3 3 0 0 1 3-3Z"/><path d="M19 10v1a7 7 0 0 1-14 0v-1"/><path d="M12 18v3"/><path d="M8 21h8"/></svg>
      <span class="sr-only" data-chat-mic-label>Message vocal</span>
    </button>

    <div class="chat-compose-input-wrap">
      <textarea name="contenu" rows="1" placeholder="<?= e($composePlaceholder) ?>" maxlength="4000" data-chat-text></textarea>
      <span class="chat-file-name" data-chat-file-name hidden></span>
      <span class="chat-rec-status" data-chat-rec-status hidden>Enregistrement…</span>
    </div>

    <button type="submit" class="chat-send-fab" title="Envoyer" aria-label="Envoyer">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/></svg>
    </button>
  </div>
</form>
