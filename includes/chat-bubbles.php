<?php
/** Affiche les bulles. Attendu : $thread, $userId */
foreach ($thread as $m):
    $mine = (int) $m['expediteur_id'] === $userId;
    $type = $m['type_msg'] ?? 'text';
    $mediaUrl = !empty($m['fichier']) ? chat_media_url($m['fichier']) : '';
    $caption = trim((string) ($m['contenu'] ?? ''));
    $defaultCaption = in_array($type, ['image', 'video', 'audio'], true)
        && in_array($caption, ['Image', 'Vidéo', 'Message vocal', 'Fichier'], true);

    // Anciens vocals MediaRecorder enregistrés à tort comme video/webm
    $fichierPath = (string) ($m['fichier'] ?? '');
    $isVoice = $type === 'audio'
        || ($type === 'video' && $mediaUrl !== '' && preg_match('/\.webm$/i', $fichierPath));

    $timeLabel = '—';
    $rawTime = (string) ($m['date_envoi'] ?? '');
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $rawTime)
        ?: DateTime::createFromFormat('Y-m-d\TH:i', $rawTime);
    if ($dt) {
        $timeLabel = $dt->format('H:i');
    }

    $bubbleType = $isVoice ? 'audio' : $type;
    ?>
  <div class="chat-bubble <?= $mine ? 'is-mine' : 'is-theirs' ?> chat-bubble--<?= e($bubbleType) ?>">
    <div class="chat-bubble-body">
      <?php if ($type === 'image' && $mediaUrl): ?>
        <a class="chat-media-link" href="<?= e($mediaUrl) ?>" target="_blank" rel="noopener">
          <img class="chat-media-img" src="<?= e($mediaUrl) ?>" alt="<?= e($caption ?: 'Image') ?>" loading="lazy">
        </a>
        <?php if ($caption !== '' && !$defaultCaption): ?>
          <p><?= nl2br(e($caption)) ?></p>
        <?php endif; ?>
      <?php elseif ($isVoice && $mediaUrl): ?>
        <div class="chat-voice" data-chat-voice>
          <button type="button" class="chat-voice-play" data-chat-voice-play aria-label="Lire le message vocal">
            <svg class="chat-voice-ico-play" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
            <svg class="chat-voice-ico-pause" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true" hidden><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
          </button>
          <div class="chat-voice-main">
            <div class="chat-voice-wave" data-chat-voice-wave aria-hidden="true">
              <?php
              $bars = [8, 14, 10, 18, 12, 20, 9, 16, 11, 19, 7, 15, 13, 17, 10, 21, 8, 14, 12, 18, 9, 16, 11, 15];
              foreach ($bars as $h):
              ?>
                <span style="--h: <?= (int) $h ?>"></span>
              <?php endforeach; ?>
            </div>
            <span class="chat-voice-time" data-chat-voice-time>0:00</span>
          </div>
          <span class="chat-voice-mic" aria-hidden="true" title="Message vocal">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 3a3 3 0 0 1 3 3v6a3 3 0 0 1-6 0V6a3 3 0 0 1 3-3Z"/><path d="M19 10v1a7 7 0 0 1-14 0v-1"/></svg>
          </span>
          <audio preload="metadata" src="<?= e($mediaUrl) ?>" data-chat-voice-audio></audio>
        </div>
        <?php if ($caption !== '' && !$defaultCaption && $caption !== 'Vidéo' && $caption !== 'Message vocal'): ?>
          <p><?= nl2br(e($caption)) ?></p>
        <?php endif; ?>
      <?php elseif ($type === 'video' && $mediaUrl): ?>
        <video class="chat-media-video" controls preload="metadata" src="<?= e($mediaUrl) ?>"></video>
        <?php if ($caption !== '' && !$defaultCaption): ?>
          <p><?= nl2br(e($caption)) ?></p>
        <?php endif; ?>
      <?php else: ?>
        <p><?= nl2br(e($caption)) ?></p>
      <?php endif; ?>
    </div>
    <time datetime="<?= e($rawTime) ?>"><?= e($timeLabel) ?></time>
  </div>
<?php endforeach; ?>
