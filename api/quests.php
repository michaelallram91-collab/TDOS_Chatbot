<?php
/**
 * API - Liefert die dynamischen Quests an das Frontend.
 * GET /api/quests.php
 */

require __DIR__ . '/../lib/helpers.php';

$quests = loadQuests();

// Antwortfelder, die das Frontend benötigt; Lösungen werden NICHT mitgeliefert
$safe = [];
foreach ($quests as $q) {
    $safe[] = [
        'id'           => $q['id'] ?? null,
        'title'        => $q['title'] ?? '',
        'description'  => $q['description'] ?? '',
        'imageBased'   => !empty($q['imageBased']),
        'imageRequirement' => $q['imageRequirement'] ?? null,
        'imageUrl'     => $q['imageUrl'] ?? null,
        'hintCount'    => count($q['hints'] ?? []),
    ];
}

// Auch die Lösungen/Fragen intern mitgeben? Nein – Antworten werden serverseitig
// über die KI oder über das Print-Quest-Handling geprüft. Für die lokale
// Erkennung im Frontend liefern wir die Antworten NUR anonymisiert mit (Hash),
// damit der Browser die Lösung nicht auslesen kann, aber die KI sie kennt.
// Für einfache Schulrallye ist direkte Prüfung im Frontend ausreichend:
// Wir liefern die Antworten NICHT aus und prüfen stattdessen Server-seitig.
jsonResponse(['quests' => $safe]);
