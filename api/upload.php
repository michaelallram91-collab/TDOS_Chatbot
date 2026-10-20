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

// Die passende Foto-Quest ermitteln. Der Schüler kann auch mehrere
// Foto-Quests haben; wir nehmen die erste, die noch nicht erledigt ist.
// Das Frontend übermittelt optional die Quest-ID, damit eindeutig klar ist,
// welche Aufgabe gemeint ist.
$requestedQuestId = isset($_POST['quest_id']) ? (int)$_POST['quest_id'] : 0;

$imageQuest = null;
foreach (loadQuests() as $q) {
    if (empty($q['imageBased'])) {
        continue;
    }
    if ($requestedQuestId > 0 && (int)$q['id'] === $requestedQuestId) {
        $imageQuest = $q;
        break;
    }
    if ($imageQuest === null) {
        $imageQuest = $q;
    }
}

// Die möglichen Bildinhalte ermitteln (eine ODER-Liste).
$imageRequirements = [];
if ($imageQuest) {
    $reqs = $imageQuest['imageRequirements'] ?? [];
    if (!$reqs && !empty($imageQuest['imageRequirement'])) {
        $reqs = [$imageQuest['imageRequirement']];
    }
    $imageRequirements = array_values(array_filter(array_map('trim', $reqs), fn($r) => $r !== ''));
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

// Gezielte Auswertung: Ist EINER der erlaubten Inhalte auf dem Bild zu sehen?
// Wichtig: Wir fordern eine EINDEUTIGE, strukturierte Antwort an, damit die
// Auswertung nicht von freier Formulierung abhängt.
$aiPrompt = $message;
if (empty($aiPrompt) && $imageQuest) {
    $listText = '';
    foreach ($imageRequirements as $i => $r) {
        $listText .= ($i + 1) . ') ' . $r . "\n";
    }
    $aiPrompt =
        "Ich habe ein Foto zu der aktuellen Foto-Aufgabe hochgeladen.\n"
        . "Auf dem Foto darf EINER der folgenden Inhalte zu sehen sein (es genügt ein Treffer):\n"
        . $listText
        . "Prüfe das Bild genau und antworte NUR mit einem einzigen Wort:\n"
        . '- "JA", wenn mindestens eine der genannten Optionen klar erkennbar ist.\n'
        . '- "NEIN", wenn keine der Optionen erkennbar ist.\n'
        . 'Schreibe danach in einer neuen Zeile höchstens einen kurzen Satz Feedback.';
} elseif (empty($aiPrompt)) {
    $aiPrompt = 'Ich habe dieses Bild hochgeladen. Bitte beschreibe kurz, was darauf zu sehen ist.';
}

$apiMessages[] = ['role' => 'user', 'content' => $aiPrompt];

$res = $client->chat($apiMessages, [$imgData]);
if (!isset($res['error'])) {
    $aiReply = $res['content'];
}

// Auswertung: Wir suchen gezielt nach einem führenden JA/NEIN (strukturierte
// Antwort). So vermeiden wir Fehlinterpretationen von Fließtext.
// Für accepted Bild-Aufgaben wird (wie bei Text-Aufgaben) eine zufällige
// TAN-Zeile gewählt, damit auch Foto-Quests über die TAN-Prüfung laufen.
$tanRow = 0;

$accepted = false;
if ($aiReply !== null) {
    $firstLine = trim(explode("\n", trim($aiReply))[0] ?? '');
    $firstLower = normalizeText($firstLine);

    // Strukturierte Antwort: erster Token ist "ja" oder "nein"
    if (preg_match('/^\s*(ja|yes|nein|no)\b/i', $firstLower, $m)) {
        $accepted = strtolower($m[1]) === 'ja' || strtolower($m[1]) === 'yes';
    } else {
        // Fallback: reine Signalwort-Prüfung auf der gesamten Antwort.
        $lower = mb_strtolower($aiReply, 'UTF-8');
        $positive = ['ja', 'yes', 'richtig', 'passt', 'erkennbar', 'zu sehen', 'genau'];
        $negative = ['nein', 'no', 'fehlt', 'nicht zu sehen', 'nicht erkennbar', 'leider nicht', 'kein'];

        $hasNegative = false;
        foreach ($negative as $n) {
            if (strpos($lower, $n) !== false) { $hasNegative = true; break; }
        }
        if (!$hasNegative) {
            $hasPositive = false;
            foreach ($positive as $p) {
                if (strpos($lower, $p) !== false) { $hasPositive = true; break; }
            }
            $accepted = $hasPositive;
        }
    }
}

if ($imageQuest && $accepted) {
    $tanRow = randomTanRow();
}

jsonResponse([
    'url'         => $publicUrl,
    'file'        => $name,
    'student_id'  => $studentId,
    'ai'          => $aiReply,
    'accepted'    => $imageQuest ? $accepted : false,
    'quest_id'    => $imageQuest ? (int)$imageQuest['id'] : null,
    'require_tan' => (bool)$imageQuest,
    'row'         => $tanRow,
    'mime'        => $mime,
]);
