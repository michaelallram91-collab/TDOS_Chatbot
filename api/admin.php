<?php
/**
 * API - Dashboard-Daten (nur für angemeldete Admins).
 *
 * GET /api/admin.php
 *   → { students, total_quests, per_quest }
 *
 * Geschützt über die Admin-Session (gesetzt in admin.php).
 */

require __DIR__ . '/../lib/helpers.php';

session_start();

if (empty($_SESSION['admin_logged_in'])) {
    jsonError('Nicht angemeldet.', 401);
}

jsonResponse(getDashboardData());
