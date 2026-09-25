<?php
/**
 * TDOS Chatbot - Konfiguration
 * Kopiere diese Datei nach config.php und trage deine Werte ein.
 */

return [
    // Aktiver AI-Provider: 'freegpt' oder 'deepseek'
    'provider' => 'freegpt',

    // DeepSeek API Konfiguration
    'deepseek' => [
        'api_key'      => 'DEIN_DEEPSEEK_API_KEY',
        'base_url'     => 'https://api.deepseek.com',
        'model'        => 'deepseek-chat',       // oder 'deepseek-vl' für Vision
        'vision_model' => 'deepseek-vl',         // Modell mit Bilderkennung
    ],

    // FreeGPT Konfiguration (https://github.com/nativelink/freegpt oder eigener Proxy)
    'freegpt' => [
        'base_url' => 'http://localhost:1338',   // FreeGPT API Endpoint (g4f)
        'model'    => 'gpt-4o-mini',
    ],

    // Datei mit den statischen Instruktionen für die KI
    'context_file' => __DIR__ . '/context/instructions.txt',

    // Verzeichnis für hochgeladene Bilder
    'upload_dir'  => __DIR__ . '/uploads',

    // Maximale Upload-Größe (Bytes)
    'max_upload'  => 10 * 1024 * 1024, // 10 MB

    // Erlaubte Bildtypen
    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
];
