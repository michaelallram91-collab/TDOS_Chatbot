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
 * Manifest-Datei für die Schüler->Bilder-Zuordnung.
 */
function imageManifestPath(): string
{
    return __DIR__ . '/../uploads/_manifest.json';
}

/**
 * Ordnet ein hochgeladenes Bild einem Schüler zu.
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

    file_put_contents($path, json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

/**
 * Liefert die Bilder eines bestimmten Schülers (oder aller, wenn $studentId leer).
 */
function getStudentImages(string $studentId = ''): array
{
    $config = loadConfig();
    $dir = rtrim($config['upload_dir'], '/\\');
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    $images = [];
    $manifestPath = imageManifestPath();

    if ($studentId !== '' && is_file($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
        if (isset($manifest[$studentId])) {
            foreach ($manifest[$studentId] as $entry) {
                $file = $entry['file'] ?? '';
                if ($file === '' || !is_file($dir . '/' . $file)) {
                    continue;
                }
                $images[] = [
                    'file' => $file,
                    'url'  => 'uploads/' . $file,
                    'time' => filemtime($dir . '/' . $file),
                ];
            }
        }
        return $images;
    }

    // Alle Bilder (Fallback: Directory-Scan)
    if (is_dir($dir)) {
        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..' || $file === '_manifest.json') {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed, true)) {
                $images[] = [
                    'file' => $file,
                    'url'  => 'uploads/' . $file,
                    'time' => filemtime($dir . '/' . $file),
                ];
            }
        }
    }

    usort($images, fn($a, $b) => $b['time'] <=> $a['time']);
    return $images;
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
            $req = $q['imageRequirement'] ?? 'ein passendes Foto';
            $lines[] = "  (Foto-Aufgabe: {$req})";
            if (!empty($q['codeword'])) {
                $lines[] = "  (Codewort als Ersatz: {$q['codeword']})";
            }
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
