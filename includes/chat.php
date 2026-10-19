<?php
/**
 * Chat in-app client ↔ propriétaire (texte, image, vidéo, vocal).
 */

function ensure_chat_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo = db();
        if (!$pdo->query("SHOW TABLES LIKE 'conversations'")->fetchColumn()) {
            $pdo->exec("
                CREATE TABLE conversations (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  client_id INT UNSIGNED NOT NULL,
                  proprietaire_id INT UNSIGNED NOT NULL,
                  salle_id INT UNSIGNED NOT NULL DEFAULT 0,
                  date_creation DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  date_maj DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY uq_conv_parties (client_id, proprietaire_id, salle_id),
                  CONSTRAINT fk_conv_client FOREIGN KEY (client_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
                  CONSTRAINT fk_conv_owner FOREIGN KEY (proprietaire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        if (!$pdo->query("SHOW TABLES LIKE 'messages'")->fetchColumn()) {
            $pdo->exec("
                CREATE TABLE messages (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  conversation_id INT UNSIGNED NOT NULL,
                  expediteur_id INT UNSIGNED NOT NULL,
                  contenu TEXT NOT NULL,
                  type_msg VARCHAR(20) NOT NULL DEFAULT 'text',
                  fichier VARCHAR(255) DEFAULT NULL,
                  lu TINYINT(1) NOT NULL DEFAULT 0,
                  date_envoi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                  CONSTRAINT fk_msg_user FOREIGN KEY (expediteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
                  INDEX idx_msg_conv (conversation_id, date_envoi),
                  INDEX idx_msg_lu (conversation_id, lu, expediteur_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } else {
            if (!$pdo->query("SHOW COLUMNS FROM messages LIKE 'type_msg'")->fetch()) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN type_msg VARCHAR(20) NOT NULL DEFAULT 'text' AFTER contenu");
            }
            if (!$pdo->query("SHOW COLUMNS FROM messages LIKE 'fichier'")->fetch()) {
                $pdo->exec("ALTER TABLE messages ADD COLUMN fichier VARCHAR(255) DEFAULT NULL AFTER type_msg");
            }
        }
    } catch (Throwable $e) {
    }
}

/**
 * Ouvre ou crée une conversation client ↔ propriétaire (liée à une salle si fournie).
 */
function get_or_create_conversation(int $clientId, int $ownerId, ?int $salleId = null): ?array
{
    ensure_chat_schema();
    if ($clientId < 1 || $ownerId < 1 || $clientId === $ownerId) {
        return null;
    }

    $salleId = $salleId && $salleId > 0 ? $salleId : 0;

    $stmt = db()->prepare(
        'SELECT * FROM conversations WHERE client_id = ? AND proprietaire_id = ? AND salle_id = ? LIMIT 1'
    );
    $stmt->execute([$clientId, $ownerId, $salleId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    db()->prepare(
        'INSERT INTO conversations (client_id, proprietaire_id, salle_id) VALUES (?, ?, ?)'
    )->execute([$clientId, $ownerId, $salleId]);

    $id = (int) db()->lastInsertId();
    $stmt = db()->prepare('SELECT * FROM conversations WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function user_can_access_conversation(array $conv, int $userId): bool
{
    return (int) $conv['client_id'] === $userId || (int) $conv['proprietaire_id'] === $userId;
}

function fetch_conversation(int $id): ?array
{
    ensure_chat_schema();
    $stmt = db()->prepare(
        "SELECT c.*,
                cl.nom AS client_nom,
                pr.nom AS proprietaire_nom,
                s.nom AS salle_nom
         FROM conversations c
         JOIN utilisateurs cl ON cl.id = c.client_id
         JOIN utilisateurs pr ON pr.id = c.proprietaire_id
         LEFT JOIN salles s ON s.id = c.salle_id AND c.salle_id > 0
         WHERE c.id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_conversations_for_user(int $userId, string $role): array
{
    ensure_chat_schema();
    if ($role === 'proprietaire') {
        $sql = "SELECT c.*,
                       cl.nom AS peer_nom,
                       s.nom AS salle_nom,
                       (SELECT contenu FROM messages m WHERE m.conversation_id = c.id ORDER BY m.date_envoi DESC LIMIT 1) AS last_msg,
                       (SELECT type_msg FROM messages m WHERE m.conversation_id = c.id ORDER BY m.date_envoi DESC LIMIT 1) AS last_type,
                       (SELECT date_envoi FROM messages m WHERE m.conversation_id = c.id ORDER BY m.date_envoi DESC LIMIT 1) AS last_at,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.lu = 0 AND m.expediteur_id != ?) AS unread
                FROM conversations c
                JOIN utilisateurs cl ON cl.id = c.client_id
                LEFT JOIN salles s ON s.id = c.salle_id AND c.salle_id > 0
                WHERE c.proprietaire_id = ?
                ORDER BY COALESCE(c.date_maj, c.date_creation) DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute([$userId, $userId]);
    } else {
        $sql = "SELECT c.*,
                       pr.nom AS peer_nom,
                       s.nom AS salle_nom,
                       (SELECT contenu FROM messages m WHERE m.conversation_id = c.id ORDER BY m.date_envoi DESC LIMIT 1) AS last_msg,
                       (SELECT type_msg FROM messages m WHERE m.conversation_id = c.id ORDER BY m.date_envoi DESC LIMIT 1) AS last_type,
                       (SELECT date_envoi FROM messages m WHERE m.conversation_id = c.id ORDER BY m.date_envoi DESC LIMIT 1) AS last_at,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.lu = 0 AND m.expediteur_id != ?) AS unread
                FROM conversations c
                JOIN utilisateurs pr ON pr.id = c.proprietaire_id
                LEFT JOIN salles s ON s.id = c.salle_id AND c.salle_id > 0
                WHERE c.client_id = ?
                ORDER BY COALESCE(c.date_maj, c.date_creation) DESC";
        $stmt = db()->prepare($sql);
        $stmt->execute([$userId, $userId]);
    }
    return $stmt->fetchAll();
}

function fetch_messages(int $conversationId, int $limit = 200): array
{
    ensure_chat_schema();
    $stmt = db()->prepare(
        "SELECT m.*, u.nom AS expediteur_nom
         FROM messages m
         JOIN utilisateurs u ON u.id = m.expediteur_id
         WHERE m.conversation_id = ?
         ORDER BY m.date_envoi ASC
         LIMIT " . (int) $limit
    );
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll();
}

function mark_conversation_read(int $conversationId, int $readerId): void
{
    ensure_chat_schema();
    db()->prepare(
        'UPDATE messages SET lu = 1 WHERE conversation_id = ? AND expediteur_id != ? AND lu = 0'
    )->execute([$conversationId, $readerId]);
}

function chat_preview_label(?string $contenu, ?string $type): string
{
    $type = $type ?: 'text';
    $contenu = trim((string) $contenu);
    return match ($type) {
        'image' => $contenu !== '' ? '📷 ' . $contenu : '📷 Image',
        'video' => $contenu !== '' ? '🎬 ' . $contenu : '🎬 Vidéo',
        'audio' => $contenu !== '' ? '🎤 ' . $contenu : '🎤 Message vocal',
        default => $contenu !== '' ? $contenu : 'Nouveau message',
    };
}

/**
 * Upload média chat (image / vidéo / audio).
 * @return array{ok:bool,path:?string,type:?string,error:?string}
 */
function upload_chat_media(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'type' => null, 'error' => null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => null, 'type' => null, 'error' => 'Échec du téléversement.'];
    }

    $size = (int) ($file['size'] ?? 0);
    $finfoMime = null;
    if (class_exists('finfo') && is_uploaded_file($file['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $finfoMime = $finfo->file($file['tmp_name']);
    } elseif (!empty($file['type'])) {
        $finfoMime = $file['type'];
    }

    $images = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $videos = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];
    $audios = [
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
    ];

    $type = null;
    $ext = null;
    $max = 0;

    $origName = (string) ($file['name'] ?? '');
    $clientMime = strtolower((string) ($file['type'] ?? ''));
    $nameExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    // Vocals navigateur : MediaRecorder envoie audio/webm mais finfo lit souvent video/webm
    $isVoiceNote = str_starts_with($origName, 'vocal_')
        || str_starts_with($clientMime, 'audio/')
        || (($finfoMime === 'video/webm' || $finfoMime === 'application/octet-stream')
            && (str_starts_with($clientMime, 'audio/') || str_starts_with($origName, 'vocal_')));

    if ($finfoMime && isset($images[$finfoMime])) {
        $type = 'image';
        $ext = $images[$finfoMime];
        $max = 5 * 1024 * 1024;
    } elseif (
        $isVoiceNote
        || ($finfoMime && (isset($audios[$finfoMime]) || str_starts_with((string) $finfoMime, 'audio/')))
    ) {
        $type = 'audio';
        $ext = $audios[$finfoMime] ?? ($audios[$clientMime] ?? ($nameExt !== '' ? $nameExt : 'webm'));
        if ($ext === 'm4a' || $nameExt === 'm4a') {
            $ext = 'm4a';
        } elseif (!in_array($ext, ['webm', 'ogg', 'mp3', 'm4a', 'wav'], true)) {
            $ext = 'webm';
        }
        $max = 10 * 1024 * 1024;
    } elseif ($finfoMime && isset($videos[$finfoMime])) {
        $type = 'video';
        $ext = $videos[$finfoMime];
        $max = 25 * 1024 * 1024;
    } else {
        // Fallback extension for some browsers (voice notes)
        if (in_array($nameExt, ['webm', 'ogg', 'mp3', 'm4a', 'wav'], true)) {
            $type = 'audio';
            $ext = $nameExt === 'm4a' ? 'm4a' : $nameExt;
            $max = 10 * 1024 * 1024;
        }
    }

    if (!$type || !$ext) {
        return ['ok' => false, 'path' => null, 'type' => null, 'error' => 'Formats acceptés : image, vidéo (MP4/WEBM) ou audio.'];
    }

    if ($size > $max) {
        $label = $type === 'image' ? '5 Mo' : ($type === 'video' ? '25 Mo' : '10 Mo');
        return ['ok' => false, 'path' => null, 'type' => null, 'error' => "Fichier trop volumineux (max {$label})."];
    }

    $dir = __DIR__ . '/../assets/uploads/chat';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'path' => null, 'type' => null, 'error' => 'Impossible de créer le dossier uploads.'];
    }

    $name = 'chat_' . $type . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => false, 'path' => null, 'type' => null, 'error' => 'Impossible d\'enregistrer le fichier.'];
    }

    return ['ok' => true, 'path' => 'uploads/chat/' . $name, 'type' => $type, 'error' => null];
}

/**
 * @param array|null $file $_FILES['media'] ou null
 */
function send_chat_message(int $conversationId, int $senderId, string $contenu, ?array $file = null): array
{
    ensure_chat_schema();
    $contenu = trim($contenu);
    $type = 'text';
    $fichier = null;

    if ($file && (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
        $up = upload_chat_media($file);
        if (!$up['ok']) {
            return ['ok' => false, 'error' => $up['error'] ?? 'Upload impossible.'];
        }
        if ($up['path'] && $up['type']) {
            $type = $up['type'];
            $fichier = $up['path'];
        }
    }

    if ($type === 'text' && $contenu === '') {
        return ['ok' => false, 'error' => 'Écrivez un message ou joignez un fichier / vocal.'];
    }
    if (function_exists('mb_strlen') ? mb_strlen($contenu) > 4000 : strlen($contenu) > 4000) {
        return ['ok' => false, 'error' => 'Message trop long.'];
    }

    if ($type !== 'text' && $contenu === '') {
        $contenu = match ($type) {
            'image' => 'Image',
            'video' => 'Vidéo',
            'audio' => 'Message vocal',
            default => 'Fichier',
        };
    }

    $conv = fetch_conversation($conversationId);
    if (!$conv || !user_can_access_conversation($conv, $senderId)) {
        return ['ok' => false, 'error' => 'Conversation introuvable.'];
    }

    db()->prepare(
        'INSERT INTO messages (conversation_id, expediteur_id, contenu, type_msg, fichier) VALUES (?, ?, ?, ?, ?)'
    )->execute([$conversationId, $senderId, $contenu, $type, $fichier]);

    db()->prepare('UPDATE conversations SET date_maj = NOW() WHERE id = ?')->execute([$conversationId]);

    $recipientId = ((int) $conv['client_id'] === $senderId)
        ? (int) $conv['proprietaire_id']
        : (int) $conv['client_id'];

    $senderName = current_user()['nom'] ?? 'Utilisateur';
    $preview = chat_preview_label($contenu, $type);
    $preview = function_exists('mb_strimwidth')
        ? mb_strimwidth($preview, 0, 120, '…')
        : (strlen($preview) > 120 ? substr($preview, 0, 119) . '…' : $preview);

    $lien = ((int) $conv['proprietaire_id'] === $recipientId)
        ? 'proprietaire/messages.php?c=' . $conversationId
        : 'messages.php?c=' . $conversationId;

    create_notification(
        $recipientId,
        'Nouveau message — ' . $senderName,
        $preview,
        'chat',
        $lien
    );

    return ['ok' => true, 'error' => null];
}

function unread_chat_count(int $userId): int
{
    ensure_chat_schema();
    if ($userId < 1) {
        return 0;
    }
    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*) FROM messages m
             JOIN conversations c ON c.id = m.conversation_id
             WHERE m.lu = 0 AND m.expediteur_id != ?
               AND (c.client_id = ? OR c.proprietaire_id = ?)"
        );
        $stmt->execute([$userId, $userId, $userId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function chat_media_url(?string $relativePath): string
{
    if (!$relativePath) {
        return '';
    }
    if (str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
        return $relativePath;
    }
    return asset(ltrim(str_replace('\\', '/', $relativePath), '/'));
}
