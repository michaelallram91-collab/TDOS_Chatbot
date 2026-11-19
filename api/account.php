<?php
/**
 * API - Liefert (oder erzeugt) das Ausdruck-Konto eines Schülers.
 *
 * GET /api/account.php?student_id=abc123
 *   → { user_id, pin, student_id, completed_count, total_quests }
 *
 * Die User-ID und der PIN sind JEDERZEIT abfragbar (z. B. über den
 * Quests-Button oder am Ende der letzten Quest). Der Mitarbeiter an der
 * Ausdruck-Station nutzt sie zum Login.
 */

require __DIR__ . '/../lib/helpers.php';

$studentId = trim((string)($_GET['student_id'] ?? ''));
$studentId = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);

if ($studentId === '') {
    jsonError('student_id fehlt.', 400);
}

// Konto ist jederzeit abfragbar (unabhängig vom Quest-Fortschritt).
$account = getOrCreateAccount($studentId);

jsonResponse([
    'user_id'         => $account['user_id'],
    'pin'             => $account['pin'],
    'student_id'      => $account['student_id'],
    'completed_count' => count(getCompletedQuests($studentId)),
    'total_quests'    => count(loadQuests()),
]);
