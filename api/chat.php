<?php
/**
 * API - Chat mit der KI.
 * POST { message, history }
 * history: Array von {role, content}
 * Die statische Kontextdatei wird dem Verlauf vorangestellt.
 */

require __DIR__ . '/../lib/helpers.php';
require __DIR__ . '/../lib/AiClient.php';

$data = readJsonBody();
$message = trim((string)($data['message'] ?? ''));
$history = $data['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}

$systemContext = buildContext();

// Nachrichten für die API aufbauen
$apiMessages = [];
$apiMessages[] = ['role' => 'system', 'content' => $systemContext];

foreach ($history as $msg) {
    $role = ($msg['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
    $content = (string)($msg['content'] ?? '');
    if ($content !== '') {
        $apiMessages[] = ['role' => $role, 'content' => $content];
    }
}

$apiMessages[] = ['role' => 'user', 'content' => $message];

$client = new AiClient();
$result = $client->chat($apiMessages);

if (isset($result['error'])) {
    jsonError($result['error'], 502);
}

jsonResponse(['content' => $result['content']]);
