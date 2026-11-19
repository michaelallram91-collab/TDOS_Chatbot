<?php
/**
 * API - Setzt den serverseitigen Fortschritt eines Schülers zurück.
 *
 * POST { student_id }
 *   → { ok: true }
 *
 * Wird beim "Neustart" aus dem Frontend aufgerufen, damit nach einem Reload
 * nicht die alten, bereits gelösten Quests wieder angezeigt werden.
 */

require __DIR__ . '/../lib/helpers.php';

$data = readJsonBody();

$studentId = trim((string)($data['student_id'] ?? ''));
$studentId = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);

if ($studentId === '') {
    jsonError('student_id fehlt.', 400);
}

resetStudentData($studentId);

jsonResponse(['ok' => true]);
