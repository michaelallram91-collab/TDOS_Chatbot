<?php
/**
 * TDOS Chatbot - Konfiguration
 * Kopiere diese Datei nach config.php und trage deine Werte ein.
 */

return [
    // Aktiver AI-Provider: 'mistral', 'freegpt', 'deepseek' oder 'ollama'
    // 'ollama' = zwei getrennte lokale Server (Sprache auf Server 1, Bild auf Server 2).
    'provider' => 'ollama',

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  LOKALE KIs – HIER DIE ADRESSEN EINTRAGEN                     ║
    // ╚══════════════════════════════════════════════════════════════╝
    //
    // Sprach-KI (Server 1): base_url = Adresse deiner Ollama-Sprach-KI
    //   → z. B. 'http://192.168.0.10:11434'
    'ollama_text' => [
        'base_url' => 'http://SERVER1_IP:11434',   // ⬅️ HIER Server-1-Adresse eintragen
        'model'    => 'qwen2.5:8b',
        'api_key'  => '',                           // meist leer bei lokalen Instanzen
    ],

    // Bild-KI (Server 2): base_url = Adresse deiner Ollama-Bild-KI
    //   → z. B. 'http://192.168.0.11:11434'
    'ollama_vision' => [
        'base_url' => 'http://SERVER2_IP:11434',   // ⬅️ HIER Server-2-Adresse eintragen
        'model'    => 'llava',                     // z. B. llava, llava-phi3, bakllava
        'api_key'  => '',
    ],

    // Mistral API Konfiguration (https://console.mistral.ai/)
    'mistral' => [
        'api_key'      => 'DEIN_MISTRAL_API_KEY',
        'base_url'     => 'https://api.mistral.ai/v1',
        'model'        => 'mistral-small-2603',   // Text-Modell
        'vision_model' => 'pixtral-12b-2409',     // Modell mit Bilderkennung
    ],

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

    // Admin-Dashboard (admin.php) – Zugangsdaten für die Übersicht.
    'admin' => [
        'username' => 'admin',
        'password' => 'aendere-mich-bitte',   // ⚠️ VOR DEM EINSATZ ÄNDERN!
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
