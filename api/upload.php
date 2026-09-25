<?php
/**
 * API - Bild-Upload.
 * POST (multipart/form-data): file, ggf. message
 * Speichert das Bild lokal und sendet es anschließend (optional) an die KI
 * zur Bildeinordnung. Die Antwort der KI wird mit zurückgegeben.
 */

require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/AiClient.php';

$config = loadConfig();

if (!isset($_FILES['file'])) {
    jsonError('Kein Bild empfangen.', 400);
}

$file = $_FILES['file'];

// Detaillierte Upload-Fehlerbehandlung
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'Datei zu groß (Server-Limit überschritten).',
        UPLOAD_ERR_FORM_SIZE  => 'Datei zu groß (Formular-Limit überschritten).',
        UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE    => 'Es wurde keine Datei hochgeladen.',
    ];
    $msg = $errMap[$file['error']] ?? ('Upload-Fehler (Code ' . $file['error'] . ').');
    jsonError($msg, 400);
}

if ($file['size'] > $config['max_upload']) {
    jsonError('Datei zu groß (max. ' . round($config['max_upload'] / 1024 / 1024, 1) . ' MB).', 400);
}

$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $config['allowed_types'], true)) {
    // HEIC/iOS-Hinweis gezielt ausgeben
    if (stripos($mime, 'heic') !== false || stripos($mime, 'heif') !== false) {
        jsonError('HEIC-Format wird nicht unterstützt. Bitte als JPG/PNG aufnehmen oder umwandeln.', 400);
    }
    jsonError('Ungültiger Dateityp (' . $mime . '). Erlaubt: JPG, PNG, GIF, WebP.', 400);
}

$extMap = [
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$ext = $extMap[$mime] ?? 'jpg';

// Schüler-/Browser-ID zur Zuordnung der Bilder
$studentId = trim((string)($_POST['student_id'] ?? ''));
$studentId = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);
if ($studentId === '') {
    $studentId = 'default';
}

$name = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = rtrim($config['upload_dir'], '/\\') . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonError('Bild konnte nicht gespeichert werden.', 500);
}

// Bild dem Schüler zuordnen (Manifest im uploads-Ordner)
registerImageForStudent($studentId, $name);

$publicUrl = 'uploads/' . $name;

// Bild mit der KI auswerten (Vision): Erkennt, ob das geforderte Ziel
// (z. B. "High five mit dem Direktor") auf dem Bild zu sehen ist.
$aiReply = null;
$message = trim((string)($_POST['message'] ?? ''));
$history = json_decode((string)($_POST['history'] ?? ''), true) ?: [];

// Die aktuelle Foto-Quest (imageBased) mit ihrer Bildanforderung ermitteln
$imageQuest = null;
foreach (loadQuests() as $q) {
    if (!empty($q['imageBased'])) {
        $imageQuest = $q;
        break;
    }
}

$client = new AiClient();
$imgData = [
    'data' => base64_encode(file_get_contents($dest)),
    'mime' => $mime,
];

$apiMessages = [];
$apiMessages[] = ['role' => 'system', 'content' => buildContext()];
foreach ($history as $msg) {
    $role = ($msg['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    if (($msg['content'] ?? '') !== '') {
        $apiMessages[] = ['role' => $role, 'content' => (string)$msg['content']];
    }
}

// Gezielte Auswertung: Ist das gesuchte Ziel auf dem Bild zu sehen?
$aiPrompt = $message;
if (empty($aiPrompt)) {
    if ($imageQuest && !empty($imageQuest['imageRequirement'])) {
        $aiPrompt = 'Ich habe ein Foto hochgeladen. Prüfe bitte, ob darauf Folgendes zu sehen ist: '
            . $imageQuest['imageRequirement']
            . '. Antworte klar mit JA (wenn es erkennbar ist) oder NEIN (wenn es fehlt), und gib kurz Feedback.';
    } else {
        $aiPrompt = 'Ich habe dieses Bild hochgeladen. Bitte beschreibe kurz, was darauf zu sehen ist.';
    }
}

$apiMessages[] = ['role' => 'user', 'content' => $aiPrompt];

$res = $client->chat($apiMessages, [$imgData]);
if (!isset($res['error'])) {
    $aiReply = $res['content'];
}

// Auswertung: Hat die KI das gesuchte Ziel auf dem Bild erkannt?
// Wir prüfen heuristisch auf eine positive Antwort (JA-basiert).
$accepted = false;
if ($aiReply !== null) {
    $lower = mb_strtolower($aiReply, 'UTF-8');
    // Positive Signale
    $positive = ['ja', 'ja!', 'yes', 'stimmt', 'richtig', 'passt', 'erkennbar', 'zu sehen', 'super', 'perfekt', 'genau'];
    $negative = ['nein', ' no ', ' no.', ' no!', 'fehlt', 'nicht zu sehen', 'nicht erkennbar', 'leider nicht', 'nein,', 'auf dem bild ist kein'];

    $hasPositive = false;
    $hasNegative = false;
    foreach ($positive as $p) {
        if (strpos($lower, $p) !== false) { $hasPositive = true; break; }
    }
    foreach ($negative as $n) {
        if (strpos($lower, $n) !== false) { $hasNegative = true; break; }
    }

    if ($hasPositive && !$hasNegative) {
        $accepted = true;
    }
}

jsonResponse([
    'url'        => $publicUrl,
    'file'       => $name,
    'student_id' => $studentId,
    'ai'         => $aiReply,
    'accepted'   => $accepted,
    'mime'       => $mime,
]);
