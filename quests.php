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
 *  - imageRequirements : Liste von Dingen, die auf dem Bild zu sehen sein dürfen.
 *                     EIN Treffer reicht (ODER-Verknüpfung). z. B. eine Handflächen-Geste
 *                     ODER ein Foto mit einem Buddy. Jeder Eintrag ist eine Alternative.
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
        // Hier legst du fest, WELCHE Fotos als erfüllt gelten.
        // Jeder Eintrag ist eine EIGENE, akzeptierte Möglichkeit (ODER-Verknüpfung).
        'imageRequirements' => [
            'Ein High five zwischen einem Schüler und dem Direktor',
            'Eine Handflächen-Geste (jemand zeigt gut sichtbar die flache Handfläche in die Kamera)',
            'Ein Foto, auf dem der Schüler zusammen mit einem Buddy zu sehen ist',
        ],
        'codeword'    => 'DIRECTOR',
        'hints'       => [
            'Der Direktor kennt das Codewort für diese Aufgabe.',
            'Du darfst den Direktor auch die Handfläche zeigen (kein Körperkontakt nötig).',
            'Alternativ geht auch ein Foto zusammen mit einem Buddy.',
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
        // WICHTIG: Hier muss eine DIREKTE Bild-URL stehen, die direkt die
        // Bilddatei liefert (endet z. B. auf .jpg/.png) und KEINE Webseite ist.
        //
        // NICHT verwenden: Pixabay-Download-Links wie
        //   https://pixabay.com/de/images/download/x-10455132_1920.jpg
        // (das ist eine HTML-Download-Seite und wird NICHT als Bild angezeigt).
        //
        // Alternativ: Lege dein Rätsel-Bild z. B. als assets/raetsel-ort.jpg
        // im Projekt ab und trage den relativen Pfad ein.
        'imageUrl'    => 'https://pixabay.com/get/g4254881af1e97707f14a2c8e259ee08b54086d9f4e1f38af4f2480f9164dced0501eba97d9213f8aeea4240b7e3d1c92_1920.jpg',
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
