<?php
/**
 * API - Prüft eine Antwort gegen die Quests (serverseitig).
 *
 * Zwei-Stufen-Ablauf:
 *  1) POST { message, student_id, hasImage }
 *     → wenn eine richtige Lösung erkannt wird:
 *       { matched: true, requireTan: true, questId, row }
 *     → sonst { matched: false }
 *
 *  2) POST { tan, questId, student_id, row }
 *     → prüft den TAN-Code der in Schritt 1 genannten Zeile
 *       { tanOk: true } oder { tanOk: false }
 *
 * Lösungswörter und TANs bleiben so nicht im Browser sichtbar.
 */

require __DIR__ . '/../lib/helpers.php';

$data = readJsonBody();

/* ---------- Schritt 2: TAN prüfen ---------- */
if (isset($data['tan']) && isset($data['questId'])) {
    $tan = normalizeText((string)$data['tan']);
    $questId = (int)$data['questId'];
    $studentId = (string)($data['student_id'] ?? '');

    $row = isset($data['row']) ? (int)$data['row'] : tanRowFor($studentId, $questId);
    $tans = loadTans();

    $expected = isset($tans[$row]) ? normalizeText((string)$tans[$row]) : '';

    if ($expected !== '' && $tan === $expected) {
        jsonResponse(['tanOk' => true]);
    }
    jsonResponse(['tanOk' => false]);
}

/* ---------- Schritt 1: Antwort prüfen ---------- */
$message = trim((string)($data['message'] ?? ''));
$hasImage = !empty($data['hasImage']);
$studentId = (string)($data['student_id'] ?? '');

$quests = loadQuests();
$messageNorm = normalizeText($message);

$matched = null;

foreach ($quests as $q) {
    // Foto-Aufgaben: Codewort wird wie Text geprüft (Bildinhalt übernimmt die KI)
    if (!empty($q['imageBased'])) {
        $codeword = $q['codeword'] ?? null;
        if ($codeword !== null && $codeword !== '' && strpos($messageNorm, normalizeText($codeword)) !== false) {
            $matched = $q;
            break;
        }
        continue;
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
    $row = tanRowFor($studentId, (int)$matched['id']);
    jsonResponse([
        'matched'    => true,
        'requireTan' => true,
        'questId'    => (int)$matched['id'],
        'title'      => $matched['title'] ?? '',
        'row'        => $row,
    ]);
}

jsonResponse(['matched' => false]);
