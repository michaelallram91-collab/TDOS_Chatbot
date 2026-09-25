<?php
/**
 * Quests – Dynamische Quest-Definitionen.
 *
 * Hier kannst du die Quests beliebig ändern, löschen oder ergänzen.
 * Jedes Quest hat:
 *  - id            : eindeutige Nummer
 *  - title         : Kurztitel (Popup)
 *  - description   : Aufgabenstellung (für Popup + Kontext)
 *  - question      : vollständige Fragestellung (für den KI-Kontext)
 *  - answers       : Liste der richtigen Antworten (Schreibweisen, wird normalisiert verglichen)
 *  - hints         : abgestufte Hinweise (die KI gibt sie nur nach und nach preis)
 *  - imageBased    : true, wenn ein Foto hochgeladen werden muss
 *  - imageRequirement : was auf dem Bild zu sehen sein muss (z. B. "High five mit dem Direktor")
 *  - codeword      : optionales Codewort als Ersatz, falls kein Bild möglich ist
 *  - imageUrl      : optional eine Bild-URL, die im Quest-Popup angezeigt wird
 *                     (z. B. ein Rätsel-Bild, das den gesuchten Ort zeigt)
 */

return [
    [
        'id'          => 1,
        'title'       => 'Finde den größten Raum',
        'description' => 'Antwort gesucht: Name des Raums',
        'question'    => 'Aufgabe 1: Finde den größten Raum im Gebäude.',
        'answers'     => ['DVS7'],
        'hints'       => [
            'Frage den Direktor.',
            'Frage einen Buddy.',
            'Gehe in den 2. Stock und frage jemanden.',
        ],
        'imageBased'  => false,
    ],
    [
        'id'          => 2,
        'title'       => 'High five mit dem Direktor',
        'description' => 'Lade ein Foto hoch oder nenne das Codewort',
        'question'    => 'Aufgabe 2: Mach ein Foto, auf dem du dem Direktor ein High five gibst, und lade es hier hoch.',
        'answers'     => ['DIRECTOR'],
        'imageBased'  => true,
        'imageRequirement' => 'High five mit dem Direktor (ein Foto, auf dem der Schüler dem Direktor ein High five gibt)',
        'codeword'    => 'DIRECTOR',
        'hints'       => [
            'Der Direktor kennt das Codewort für diese Aufgabe.',
            'Frage einen Buddy.',
        ],
    ],
    [
        'id'          => 3,
        'title'       => 'Wirtschafts-/Verkaufspsychologie',
        'description' => 'Abkürzung mit 3 Buchstaben gesucht',
        'question'    => 'Aufgabe 3: Wie heißt das Fach, in dem es um Wirtschafts- und Verkaufspsychologie geht?',
        'answers'     => ['WOP'],
        'hints'       => [
            'Frage einen Buddy.',
            'Es ist eine Abkürzung mit 3 Buchstaben gesucht.',
            'Es beginnt mit einem W.',
        ],
        'imageBased'  => false,
    ],
    [
        'id'          => 4,
        'title'       => 'Suche den Ort',
        'description' => 'Löse das Bild-Rätsel',
        'question'    => 'Aufgabe 4: Suche den gezeigten Ort.',
        'imageUrl'    => 'https://pixabay.com/de/images/download/x-10455132_1920.jpg',
        'answers'     => ['AULA'],
        'hints'       => [
            'Frage einen Buddy.',
        ],
        'imageBased'  => false,
    ],
    [
        'id'          => 5,
        'title'       => 'Ort, wo Bilder gedruckt werden',
        'description' => 'Antwort gesucht: Name des Raums',
        'question'    => 'Aufgabe 5: Finde den Ort, wo Bilder direkt ausgedruckt werden.',
        'answers'     => ['Sprechzimmer'],
        'hints'       => [
            'Im Erdgeschoss.',
        ],
        'imageBased'  => false,
    ],
];
