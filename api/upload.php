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

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonError('Kein Bild empfangen oder Upload-Fehler.', 400);
}

$file = $_FILES['file'];

if ($file['size'] > $config['max_upload']) {
    jsonError('Datei zu groß (max. ' . round($config['max_upload'] / 1024 / 1024, 1) . ' MB).', 400);
}

$mime = mime_content_type($file['tmp_name']);
if (!in_array($mime, $config['allowed_types'], true)) {
    jsonError('Ungültiger Dateityp. Erlaubt: JPG, PNG, GIF, WebP.', 400);
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

// Bild wurde gespeichert – weitere Verarbeitung abhängig vom Provider.
// DeepSeek unterstützt keine Vision-Eingaben über die Public API, daher
// KEIN Bild an die KI senden (sonst verwirrende Antwort). Stattdessen wird
// die Foto-Aufgabe über Bild-Upload selbst oder über das Codewort erfüllt.
$aiReply = null;
$message = trim((string)($_POST['message'] ?? ''));

if ($config['provider'] !== 'deepseek') {
    // Nur für Provider mit echter Vision-Unterstützung das Bild an die KI senden.
    $history = json_decode((string)($_POST['history'] ?? ''), true) ?: [];

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

    $apiMessages[] = [
        'role' => 'user',
        'content' => $message !== ''
            ? $message
            : 'Ich habe dieses Bild hochgeladen. Bitte ordne es einer Aufgabe zu.',
    ];

    $res = $client->chat($apiMessages, [$imgData]);
    if (!isset($res['error'])) {
        $aiReply = $res['content'];
    }
}

jsonResponse([
    'url'        => $publicUrl,
    'file'       => $name,
    'student_id' => $studentId,
    'ai'         => $aiReply,
    'mime'       => $mime,
]);
