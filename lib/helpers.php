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
