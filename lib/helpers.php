<?php
/**
 * Gemeinsame Hilfsfunktionen
 */

function loadConfig(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
        if (!is_dir($config['upload_dir'])) {
            mkdir($config['upload_dir'], 0775, true);
        }
    }
    return $config;
}

function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400): void
{
    jsonResponse(['error' => $message], $status);
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function getContextInstructions(): string
{
    $config = loadConfig();
    $path = $config['context_file'];
    if (is_file($path)) {
        return file_get_contents($path);
    }
    return '';
}

/**
 * Lädt die dynamischen Quest-Definitionen.
 */
function loadQuests(): array
{
    static $quests = null;
    if ($quests === null) {
        $quests = require __DIR__ . '/../quests.php';
        if (!is_array($quests)) {
            $quests = [];
        }
    }
    return $quests;
}

/**
 * Lädt die TAN-Liste (Zeile => Code).
 */
function loadTans(): array
{
    static $tans = null;
    if ($tans === null) {
        $tans = require __DIR__ . '/../tans.php';
        if (!is_array($tans)) {
            $tans = [];
        }
    }
    return $tans;
}

/**
 * Wählt ZUFÄLLIG eine TAN-Zeile aus (bei jeder Anfrage neu).
 * So bekommt jeder Schüler – auch bei gleicher Antwort/Quest – eine
 * andere Zeile abgefragt.
 */
function randomTanRow(): int
{
    $tans = loadTans();
    $keys = array_keys($tans);
    if (count($keys) === 0) {
        return 1;
    }
    $idx = random_int(0, count($keys) - 1);
    return (int)$keys[$idx];
}

/**
 * Ermittelt deterministisch eine TAN-Zeile für (studentId + questId).
 * (Fallback, falls keine zufällige Auswahl gewünscht ist.)
 */
function tanRowFor(string $studentId, int $questId): int
{
    $tans = loadTans();
    $count = count($tans);
    if ($count === 0) {
        return 1;
    }
    $seed = abs(crc32($studentId . ':' . $questId)) % $count;
    $keys = array_keys($tans);
    return (int)$keys[$seed];
}

function normalizeText(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['a', 'o', 'u', 'ss'], $s);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

/**
 * Verkleinert und konvertiert ein hochgeladenes Bild auf eine handhabbare
 * Größe und ein einheitliches Format (JPEG), damit große Kamera-Fotos
 * (z. B. direkt am Smartphone aufgenommen) zuverlässig von der Vision-KI
 * ausgewertet werden können.
 *
 * Gibt den neuen MIME-Typ zurück (oder den Original-Typ, wenn GD fehlt
 * oder das Bild nicht verarbeitet werden konnte). Das Bild wird nur
 * dann verkleinert, wenn es größer als die Ziel-Maße ist.
 *
 * @param string $path      Dateipfad des gespeicherten Bildes (wird überschrieben)
 * @param string $mime      Aktueller MIME-Typ
 * @param int    $maxDim    Maximale Kantenlänge (Default 1600px)
 * @return string           Verwendeter MIME-Typ nach der Verarbeitung
 */
function normalizeUploadedImage(string $path, string $mime, int $maxDim = 1600): string
{
    if (!function_exists('imagecreatefromstring')) {
        // GD nicht verfügbar → Bild unverändert lassen.
        return $mime;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return $mime;
    }

    $img = @imagecreatefromstring($raw);
    if ($img === false) {
        return $mime;
    }

    $width  = imagesx($img);
    $height = imagesy($img);

    $newWidth  = $width;
    $newHeight = $height;
    if ($width > $maxDim || $height > $maxDim) {
        $scale = min($maxDim / $width, $maxDim / $height);
        $newWidth  = (int)round($width * $scale);
        $newHeight = (int)round($height * $scale);
    }

    // Wenn nichts verkleinert werden muss UND bereits JPEG, Original lassen.
    if ($newWidth === $width && $newHeight === $height && $mime === 'image/jpeg') {
        imagedestroy($img);
        return $mime;
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if ($dst === false) {
        imagedestroy($img);
        return $mime;
    }

    // Transparente Hintergründe mit Weiß füllen (wichtig bei PNG/GIF/WebP),
    // da JPEG keine Transparenz unterstützt.
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    if (!imagecopyresampled($dst, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
        imagedestroy($img);
        imagedestroy($dst);
        return $mime;
    }

    imagedestroy($img);

    $quality = 88;
    $ok = imagejpeg($dst, $path, $quality);
    imagedestroy($dst);

    if ($ok) {
        return 'image/jpeg';
    }

    return $mime;
}

/**
 * Unterordner eines Schülers im uploads-Verzeichnis.
 *
 * Der Ordner wird anhand der **User-ID** benannt (z. B. uploads/TD-4K7Q), damit
 * die Dateien eindeutig einer Person zugeordnet sind UND nachträgliche
 * File-Uploads (z. B. manuell durch einen Betreuer) direkt in den passenden
 * Ordner abgelegt werden können.
 *
 * Da die User-ID erst beim ersten Zugriff feststeht, wird das Konto bei Bedarf
 * hier angelegt (idempotent: gleiche student_id → gleiche User-ID).
 */
function studentUploadDir(string $studentId): string
{
    $config = loadConfig();
    // User-ID ermitteln (erzeugt das Konto, falls noch nicht vorhanden).
    $account = getOrCreateAccount($studentId);
    $folder = $account['user_id'];
    return rtrim($config['upload_dir'], '/\\') . '/' . $folder;
}

/**
 * Manifest-Datei für die Schüler->Bilder-Zuordnung.
 */
function imageManifestPath(): string
{
    return __DIR__ . '/../uploads/_manifest.json';
}

/**
 * Ordnet ein hochgeladenes Bild einem Schüler zu.
 * Die Datei liegt bereits im Schüler-Unterordner uploads/{studentId}/.
 */
function registerImageForStudent(string $studentId, string $filename): void
{
    $path = imageManifestPath();
    $manifest = [];
    if (is_file($path)) {
        $raw = file_get_contents($path);
        $manifest = json_decode($raw, true);
        if (!is_array($manifest)) {
            $manifest = [];
        }
    }

    if (!isset($manifest[$studentId])) {
        $manifest[$studentId] = [];
    }
    $manifest[$studentId][] = [
        'file' => $filename,
        'time' => time(),
    ];

    file_put_contents($path, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Liefert die Bilder eines bestimmten Schülers (oder aller, wenn $studentId leer).
 */
function getStudentImages(string $studentId = ''): array
{
    $config = loadConfig();
    $baseDir = rtrim($config['upload_dir'], '/\\');
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    $images = [];

    if ($studentId !== '') {
        // Eindeutige Zuordnung: nur der (anhand der User-ID benannte) Ordner wird gelesen.
        $dir = studentUploadDir($studentId);
        $folderName = basename($dir); // entspricht der User-ID
        if (is_dir($dir)) {
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $full = $dir . '/' . $file;
                if (!is_file($full)) {
                    continue;
                }
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    continue;
                }
                $images[] = [
                    'file' => $file,
                    'url'  => 'uploads/' . $folderName . '/' . $file,
                    'time' => filemtime($full),
                ];
            }
        }
        usort($images, fn($a, $b) => $a['time'] <=> $b['time']);
        return $images;
    }

    // Alle Bilder (Fallback: alle Schüler-Unterordner scannen)
    if (is_dir($baseDir)) {
        foreach (scandir($baseDir) as $sid) {
            if ($sid === '.' || $sid === '..' || $sid === '_manifest.json' || $sid === '_accounts.json' || $sid === '_completed.json') {
                continue;
            }
            $dir = $baseDir . '/' . $sid;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $full = $dir . '/' . $file;
                if (!is_file($full)) {
                    continue;
                }
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    continue;
                }
                $images[] = [
                    'file' => $file,
                    'url'  => 'uploads/' . $sid . '/' . $file,
                    'time' => filemtime($full),
                ];
            }
        }
    }

    usort($images, fn($a, $b) => $b['time'] <=> $a['time']);
    return $images;
}

/**
 * Datei, in der Ausdruck-Konten (User-ID + PIN) gespeichert werden.
 */
function accountsPath(): string
{
    return __DIR__ . '/../uploads/_accounts.json';
}

/**
 * Lädt alle Ausdruck-Konten als [userId => ['pin'=>..., 'student_id'=>..., ...]].
 */
function loadAccounts(): array
{
    $path = accountsPath();
    if (is_file($path)) {
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [];
}

/**
 * Liefert (oder erzeugt) das Ausdruck-Konto eines Schülers.
 *
 * Jedes Konto hat eine kurze, für Menschen lesbare User-ID sowie einen
 * 4-stelligen PIN. Beides wird dem Schüler am Ende angezeigt; der Mitarbeiter
 * an der Ausdruck-Station loggt sich damit ein.
 */
function getOrCreateAccount(string $studentId): array
{
    $accounts = loadAccounts();

    // Bestehendes Konto anhand der student_id finden.
    foreach ($accounts as $userId => $acc) {
        if (($acc['student_id'] ?? '') === $studentId) {
            // PIN sicher als String zurückgeben (führende Nullen erhalten).
            return [
                'user_id'    => $userId,
                'pin'        => str_pad((string)$acc['pin'], 4, '0', STR_PAD_LEFT),
                'student_id' => $studentId,
            ];
        }
    }

    // Neues Konto anlegen.
    $userId = generateUserId();
    while (isset($accounts[$userId])) {
        $userId = generateUserId();
    }

    $pin = random_int(0, 9999);
    $accounts[$userId] = [
        'student_id' => $studentId,
        'pin'        => $pin,
        'created'    => time(),
    ];

    file_put_contents(accountsPath(), json_encode($accounts, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);

    return [
        'user_id'    => $userId,
        'pin'        => str_pad((string)$pin, 4, '0', STR_PAD_LEFT),
        'student_id' => $studentId,
    ];
}

/**
 * Erzeugt eine kurze, gut ablesbare User-ID (z. B. "TD-4K7Q").
 */
function generateUserId(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // ohne verwechselbare Zeichen
    $code = '';
    for ($i = 0; $i < 4; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'TD-' . $code;
}

/**
 * Prüft, ob eine User-ID + PIN-Kombination gültig ist.
 * Liefert bei Erfolg das Konto (inkl. student_id), sonst null.
 */
function verifyAccount(string $userId, string $pin): ?array
{
    $accounts = loadAccounts();
    $key = strtoupper(trim($userId));
    if (!isset($accounts[$key])) {
        return null;
    }
    $acc = $accounts[$key];
    $expected = str_pad((string)($acc['pin'] ?? ''), 4, '0', STR_PAD_LEFT);
    $given = str_pad(trim($pin), 4, '0', STR_PAD_LEFT);
    if (hash_equals($expected, $given)) {
        return [
            'user_id'    => $key,
            'pin'        => $expected,
            'student_id' => (string)($acc['student_id'] ?? ''),
        ];
    }
    return null;
}

/**
 * Zeichnet die Erfüllung einer Quest SERVERSEITIG für einen Schüler auf.
 * Wird erst nach einer erfolgreichen TAN-Prüfung aufgerufen und ist damit die
 * einzige verlässliche Quelle dafür, dass ein Quest "erfüllt" ist.
 * Liefert true, wenn die Quest tatsächlich als neu-gelöst markiert wurde
 * (false, wenn sie bereits gelöst war).
 */
function recordQuestCompletion(string $studentId, int $questId): bool
{
    $path = __DIR__ . '/../uploads/_completed.json';
    $data = [];
    if (is_file($path)) {
        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    if (!isset($data[$studentId])) {
        $data[$studentId] = [];
    }
    $list = &$data[$studentId];
    $questId = (int)$questId;

    if (in_array($questId, $list, true)) {
        return false;
    }

    $list[] = $questId;
    $list = array_values(array_unique(array_map('intval', $list)));
    sort($list);

    file_put_contents($path, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    return true;
}

/**
 * Liefert die serverseitig erledigten Quest-IDs eines Schülers.
 */
function getCompletedQuests(string $studentId): array
{
    $path = __DIR__ . '/../uploads/_completed.json';
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data) || !isset($data[$studentId]) || !is_array($data[$studentId])) {
        return [];
    }
    return array_values(array_map('intval', $data[$studentId]));
}

/**
 * Setzt den SERVERSEITIGEN Fortschritt eines Schülers vollständig zurück.
 * Wird beim "Neustart" aufgerufen, damit nach einem Reload nicht sofort wieder
 * die alten, bereits gelösten Quests angezeigt werden.
 *
 * Entfernt:
 *  - Eintrag aus _completed.json (erledigte Quests)
 *  - Konto aus _accounts.json (User-ID + PIN)
 *  - Eintrag aus _manifest.json (Bilder-Zuordnung)
 *  - den Bilder-Ordner des Schülers
 */
function resetStudentData(string $studentId): void
{
    // 1) User-ID des Schülers VOR dem Entfernen auflösen, damit wir den
    //    (anhand der User-ID benannten) Bilder-Ordner noch finden können.
    $folderName = null;
    foreach (loadAccounts() as $userId => $acc) {
        if (($acc['student_id'] ?? '') === $studentId) {
            $folderName = $userId;
            break;
        }
    }

    // 2) _completed.json
    $completedPath = __DIR__ . '/../uploads/_completed.json';
    if (is_file($completedPath)) {
        $data = json_decode(file_get_contents($completedPath), true) ?: [];
        if (is_array($data) && isset($data[$studentId])) {
            unset($data[$studentId]);
            file_put_contents($completedPath, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
        }
    }

    // 3) _accounts.json – Konto des Schülers entfernen (User-ID + PIN)
    $accounts = loadAccounts();
    $changed = false;
    foreach ($accounts as $userId => $acc) {
        if (($acc['student_id'] ?? '') === $studentId) {
            unset($accounts[$userId]);
            $changed = true;
        }
    }
    if ($changed) {
        file_put_contents(accountsPath(), json_encode($accounts, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    }

    // 4) _manifest.json – Eintrag des Schülers entfernen
    $manifestPath = imageManifestPath();
    if (is_file($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
        if (is_array($manifest) && isset($manifest[$studentId])) {
            unset($manifest[$studentId]);
            file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
        }
    }

    // 5) Bilder-Ordner löschen (anhand der User-ID benannt).
    $config = loadConfig();
    $baseDir = rtrim($config['upload_dir'], '/\\');
    if ($folderName !== null) {
        deleteDirectoryRecursive($baseDir . '/' . $folderName);
    }
    // Fallback: alter student_id-Ordner (aus früheren Versionen)
    deleteDirectoryRecursive($baseDir . '/' . $studentId);
}

/**
 * Hilfsfunktion: löscht einen Ordner rekursiv.
 */
function deleteDirectoryRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $dir . '/' . $item;
        if (is_dir($full)) {
            deleteDirectoryRecursive($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($dir);
}

/**
 * Wandelt die Quest-Definitionen in einen Textblock für den KI-Kontext um.
 */
function buildQuestsContext(): string
{
    $quests = loadQuests();
    $lines = [];

    foreach ($quests as $q) {
        $id = $q['id'] ?? '?';
        $question = $q['question'] ?? $q['title'] ?? '';
        $lines[] = "Aufgabe {$id}: {$question}";

        if (!empty($q['imageBased'])) {
            $reqs = $q['imageRequirements'] ?? [];
            if (!$reqs && !empty($q['imageRequirement'])) {
                $reqs = [$q['imageRequirement']];
            }
            if ($reqs) {
                $lines[] = '  (Foto-Aufgabe – einer dieser Inhalte genügt):';
                foreach ($reqs as $r) {
                    $lines[] = "    - {$r}";
                }
            } else {
                $lines[] = '  (Foto-Aufgabe: ein passendes Foto)';
            }
            if (!empty($q['codeword'])) {
                $lines[] = "  (Codewort als Ersatz: {$q['codeword']})";
            }
        }

        if (!empty($q['imageUrl'])) {
            $lines[] = "  (Hinweis: Zu dieser Aufgabe gehört ein Rätsel-Bild mit dieser URL: {$q['imageUrl']} – beschreibe es bei Bedarf, ohne die Lösung zu verraten)";
        }

        $answers = $q['answers'] ?? [];
        if ($answers) {
            $lines[] = '  Lösung(er): [' . implode(' | ', $answers) . ']';
        }

        $hints = $q['hints'] ?? [];
        foreach ($hints as $i => $hint) {
            $lines[] = "  Hinweis " . ($i + 1) . ": {$hint}";
        }

        $lines[] = '';
    }

    return implode("\n", $lines);
}

/**
 * Liefert den vollständigen Kontext (statische Instruktionen + dynamische Quests).
 * Die Chat-Historie wird pro Anfrage übergeben.
 */
function buildContext(): string
{
    $static = getContextInstructions();
    $quests = buildQuestsContext();
    $dynamic = "AKTUELLE AUFGABEN (verbindlich, können sich ändern – nutze immer diese Liste):\n" . $quests;

    return $static . "\n\n" . $dynamic;
}

/**
 * Liefert alle erledigten Quests als [studentId => [questId, ...]].
 */
function getAllCompleted(): array
{
    $path = __DIR__ . '/../uploads/_completed.json';
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }
    $result = [];
    foreach ($data as $studentId => $list) {
        if (is_array($list)) {
            $result[(string)$studentId] = array_values(array_unique(array_map('intval', $list)));
        }
    }
    return $result;
}

/**
 * Liefert alle Ausdruck-Konten (User-ID => Konto inkl. student_id + pin).
 */
function getAllAccounts(): array
{
    return loadAccounts();
}

/**
 * Liefert die Anzahl der hochgeladenen Bilder je student_id.
 */
function getImageCounts(): array
{
    $path = __DIR__ . '/../uploads/_manifest.json';
    if (!is_file($path)) {
        return [];
    }
    $manifest = json_decode(file_get_contents($path), true);
    if (!is_array($manifest)) {
        return [];
    }
    $counts = [];
    foreach ($manifest as $studentId => $entries) {
        $counts[(string)$studentId] = is_array($entries) ? count($entries) : 0;
    }
    return $counts;
}

/**
 * Bündelt alle Schüler-Daten für das Dashboard:
 *  - user_id (aus dem Ausdruck-Konto, falls vorhanden)
 *  - student_id
 *  - completed  : Anzahl erledigter Quests
 *  - total      : Gesamtzahl der Quests
 *  - done       : bool (alles erledigt)
 *  - images     : Anzahl hochgeladener Bilder
 *  - created    : Zeitstempel des Kontos
 *
 * Liefert zusätzlich eine Liste ALLER bekannter student_ids (aus Konten,
 * Abschlussliste und Bild-Manifest – vereinigt).
 */
function getDashboardData(): array
{
    $accounts    = getAllAccounts();
    $completed   = getAllCompleted();
    $imageCounts = getImageCounts();
    $totalQuests = count(loadQuests());

    // Alle bekannten student_ids vereinigen.
    $studentIds = [];

    // Konten: userId => student_id UND umgekehrt student_id => userId
    $userIdByStudent = [];
    foreach ($accounts as $userId => $acc) {
        $sid = (string)($acc['student_id'] ?? '');
        if ($sid === '') {
            continue;
        }
        $studentIds[$sid] = true;
        $userIdByStudent[$sid] = $userId;
    }
    foreach ($completed as $sid => $list) {
        $studentIds[$sid] = true;
    }
    foreach ($imageCounts as $sid => $count) {
        $studentIds[$sid] = true;
    }

    $students = [];
    foreach (array_keys($studentIds) as $sid) {
        $doneIds = $completed[$sid] ?? [];
        $students[] = [
            'user_id'    => $userIdByStudent[$sid] ?? null,
            'student_id' => $sid,
            'completed'  => count($doneIds),
            'total'      => $totalQuests,
            'done'       => $totalQuests > 0 && count($doneIds) >= $totalQuests,
            'images'     => $imageCounts[$sid] ?? 0,
            'created'    => null,
        ];
    }

    // created-Zeitstempel aus den Konten ergänzen.
    $createdByStudent = [];
    foreach ($accounts as $userId => $acc) {
        $sid = (string)($acc['student_id'] ?? '');
        if ($sid !== '') {
            $createdByStudent[$sid] = (int)($acc['created'] ?? 0);
        }
    }
    foreach ($students as &$s) {
        $s['created'] = $createdByStudent[$s['student_id']] ?? null;
    }
    unset($s);

    // Sortieren: erst die noch nicht fertigen, dann nach Zeitstempel absteigend.
    usort($students, function ($a, $b) {
        if ($a['done'] !== $b['done']) {
            return $a['done'] ? 1 : -1; // offene zuerst
        }
        return ($b['created'] ?? 0) <=> ($a['created'] ?? 0);
    });

    // Pro Quest zählen, wie viele User sie erledigt haben.
    $perQuest = [];
    foreach (loadQuests() as $q) {
        $perQuest[(int)$q['id']] = 0;
    }
    foreach ($completed as $sid => $list) {
        foreach ($list as $questId) {
            $perQuest[(int)$questId] = ($perQuest[(int)$questId] ?? 0) + 1;
        }
    }

    return [
        'students'    => $students,
        'total_quests'=> $totalQuests,
        'per_quest'   => $perQuest,
    ];
}
