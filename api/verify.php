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

    // Die Zeile kommt vom Frontend mit (wurde in Schritt 1 zufällig gewählt).
    $row = isset($data['row']) ? (int)$data['row'] : 0;
    if ($row <= 0) {
        jsonResponse(['tanOk' => false]);
    }

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

/**
 * Prüft, ob eine normalisierte Antwort EXAKT einer Lösung entspricht.
 *
 * Wir vergleichen auf Wort-Ebene, damit Teil-Antworten NICHT als richtig
 * durchgehen (z. B. "DV7" darf NICHT "DVS7" treffen) und damit ein harmloser
 * Satz nicht zufällig eine Lösung "enthält".
 *
 * Regel: Die Antwort gilt als richtig, wenn
 *   - die gesamte Eingabe exakt der Lösung entspricht, ODER
 *   - die Lösung als EIGENSTÄNDIGES Wort (getrennt durch Leerzeichen,
 *     Satzzeichen oder Wortgrenzen) in der Eingabe vorkommt.
 */
function answerMatches(string $inputNorm, string $answerNorm): bool
{
    if ($inputNorm === '') {
        return false;
    }

    // Exakte Übereinstimmung
    if ($inputNorm === $answerNorm) {
        return true;
    }

    // Als eigenständiges Wort vorhanden (nicht als Teil eines längeren Wortes).
    // Wir suchen mit Wortgrenzen. Da Antworten auch Ziffern enthalten können
    // (z. B. "DVS7"), nutzen wir eine Lookahead/Lookbehind-Konstruktion.
    if (preg_match('/\b' . preg_quote($answerNorm, '/') . '\b/u', $inputNorm)) {
        return true;
    }

    return false;
}

$matched = null;

foreach ($quests as $q) {
    // Foto-Aufgaben: Codewort wird wie Text geprüft (Bildinhalt übernimmt die KI)
    if (!empty($q['imageBased'])) {
        $codeword = $q['codeword'] ?? null;
        if ($codeword !== null && $codeword !== '') {
            $cw = normalizeText($codeword);
            if (answerMatches($messageNorm, $cw)) {
                $matched = $q;
                break;
            }
        }
        continue;
    }

    $answers = $q['answers'] ?? [];
    foreach ($answers as $ans) {
        $a = normalizeText((string)$ans);
        if ($a === '') {
            continue;
        }
        if (answerMatches($messageNorm, $a)) {
            $matched = $q;
            break 2;
        }
    }
}

if ($matched) {
    // Zufällige Zeile pro Anfrage → jeder Schüler bekommt (auch bei gleicher
    // Antwort) eine andere Zeile abgefragt.
    $row = randomTanRow();
    jsonResponse([
        'matched'    => true,
        'requireTan' => true,
        'questId'    => (int)$matched['id'],
        'title'      => $matched['title'] ?? '',
        'row'        => $row,
    ]);
}

jsonResponse(['matched' => false]);
