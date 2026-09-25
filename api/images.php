<?php
/**
 * API - Liste der hochgeladenen Bilder (optional gefiltert nach Schüler).
 * GET /api/images.php?student_id=abc123  → nur Bilder dieses Schülers
 * GET /api/images.php                     → alle Bilder
 */

require __DIR__ . '/../lib/helpers.php';

$studentId = trim((string)($_GET['student_id'] ?? ''));
$studentId = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);

jsonResponse(['images' => getStudentImages($studentId)]);
