<?php
/**
 * API - Liefert (oder erzeugt) das Ausdruck-Konto eines Schülers.
 *
 * GET /api/account.php?student_id=abc123
 *   → { user_id, pin, student_id }
 *
 * Die User-ID und der PIN werden dem Schüler am Ende der letzten Quest
 * angezeigt. Der Mitarbeiter an der Ausdruck-Station nutzt sie zum Login.
 *
 * Aus Sicherheitsgründen wird das Konto erst dann erstellt/freigegeben, wenn
 * ALLE Quests erfüllt sind (Prüfung über die serverseitige Abschlussliste).
 */

require __DIR__ . '/../lib/helpers.php';

$studentId = trim((string)($_GET['student_id'] ?? ''));
$studentId = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);

if ($studentId === '') {
    jsonError('student_id fehlt.', 400);
}

$quests = loadQuests();
$total = count($quests);
$completed = getCompletedQuests($studentId);

// Konto nur freigeben, wenn wirklich alle Quests gelöst sind.
if ($total > 0 && count($completed) < $total) {
    jsonError('Noch nicht alle Aufgaben gelöst.', 403);
}

$account = getOrCreateAccount($studentId);

jsonResponse([
    'user_id'    => $account['user_id'],
    'pin'        => $account['pin'],
    'student_id' => $account['student_id'],
]);
