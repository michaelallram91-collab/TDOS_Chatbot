<?php
/**
 * AI-Client: Unterstützt DeepSeek (OpenAI-kompatibel) und FreeGPT (g4f).
 */

class AiClient
{
    private array $config;

    public function __construct()
    {
        $this->config = loadConfig();
    }

    /**
     * Sendet eine Chat-Anfrage an den konfigurierten Provider.
     *
     * @param array $messages  Array von [{role, content}]
     * @param array|null $images  Optionale Bild-Bytes (für Vision) als [['data'=>base64,'mime'=>'image/jpeg']]
     */
    public function chat(array $messages, ?array $images = null): array
    {
        $provider = $this->config['provider'] ?? 'freegpt';

        if ($provider === 'deepseek') {
            return $this->chatDeepSeek($messages, $images);
        }

        return $this->chatFreeGpt($messages, $images);
    }

    private function chatFreeGpt(array $messages, ?array $images = null): array
    {
        $cfg = $this->config['freegpt'];
        $url = rtrim($cfg['base_url'], '/') . '/v1/chat/completions';

        $body = [
            'model'    => $cfg['model'] ?? 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.7,
            'stream'   => false,
        ];

        // FreeGPT/g4f Vision-Unterstützung (falls vorhanden)
        if ($images && isset($images[0]['data'])) {
            $content = [
                ['type' => 'text', 'text' => end($messages)['content']],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $images[0]['mime'] . ';base64,' . $images[0]['data']]],
            ];
            $body['messages'][count($body['messages']) - 1]['content'] = $content;
        }

        return $this->post($url, $body, []);
    }

    private function chatDeepSeek(array $messages, ?array $images = null): array
    {
        $cfg = $this->config['deepseek'];
        $useVision = !empty($images);

        $model = $useVision
            ? ($cfg['vision_model'] ?? 'deepseek-vl')
            : ($cfg['model'] ?? 'deepseek-chat');

        $url = rtrim($cfg['base_url'], '/') . '/chat/completions';

        $body = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'stream'      => false,
        ];

        if ($useVision) {
            $last = $messages[count($messages) - 1];
            $content = [['type' => 'text', 'text' => $last['content']]];
            foreach ($images as $img) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'data:' . $img['mime'] . ';base64,' . $img['data']],
                ];
            }
            $body['messages'][count($body['messages']) - 1]['content'] = $content;
        }

        $headers = [
            'Authorization: Bearer ' . $cfg['api_key'],
        ];

        return $this->post($url, $body, $headers);
    }

    private function post(string $url, array $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => array_merge([
                'Content-Type: application/json',
            ], $headers),
            CURLOPT_TIMEOUT        => 120,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            return ['error' => 'Verbindungsfehler: ' . $err];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $msg = $decoded['error']['message'] ?? $decoded['message'] ?? ('HTTP ' . $httpCode);
            return ['error' => 'API-Fehler: ' . $msg];
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;

        // g4f liefert unter Umständen ein Array statt eines Strings
        if (is_array($content)) {
            $text = '';
            foreach ($content as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                } elseif (is_string($part)) {
                    $text .= $part;
                }
            }
            $content = $text;
        }

        if ($content === null) {
            return ['error' => 'Leere Antwort vom Provider.'];
        }

        return ['content' => $content];
    }
}
