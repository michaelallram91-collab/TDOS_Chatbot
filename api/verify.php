<?php
/**
 * API - Prüft eine Antwort gegen die Quests (serverseitig).
 * POST { message, image?: bool }
 * Gibt zurück: { matched: true, questId, title } oder { matched: false }
 *
 * Lösungswörter bleiben so nicht im Browser sichtbar.
 */

require __DIR__ . '/../lib/helpers.php';

$data = readJsonBody();
$message = trim((string)($data['message'] ?? ''));
$hasImage = !empty($data['image']);

$quests = loadQuests();
$messageNorm = normalizeText($message);

$matched = null;

foreach ($quests as $q) {
    // Nicht-textuelle Foto-Aufgaben: Bild-Upload markiert hier nicht automatisch;
    // die inhaltliche Prüfung übernimmt die KI. Codewort wird wie Text geprüft.
    if (!empty($q['imageBased'])) {
        // Prüfe Codewort als Text
        $codeword = $q['codeword'] ?? null;
        if ($codeword !== null && $codeword !== '' && strpos($messageNorm, normalizeText($codeword)) !== false) {
            $matched = $q;
            break;
        }
        continue; // Bild-Inhalt wird von der KI im chat.php/upload.php bestätigt
    }

    $answers = $q['answers'] ?? [];
    foreach ($answers as $ans) {
        $a = normalizeText((string)$ans);
        if ($a === '') {
            continue;
        }
        if ($messageNorm === $a || strpos($messageNorm, $a) !== false) {
            $matched = $q;
            break 2;
        }
    }
}

if ($matched) {
    jsonResponse([
        'matched' => true,
        'questId' => $matched['id'],
        'title'   => $matched['title'] ?? '',
    ]);
}

jsonResponse(['matched' => false]);

function normalizeText(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['a', 'o', 'u', 'ss'], $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}
