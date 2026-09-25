# TDOS Chatbot – Interaktive Schulrallye (BHAK/BHAS Schwaz)

Eine cleane One-Page-Web-App für den Tag der offenen Tür. Schüler lösen Quests über einen KI-Chatbot (Chat mit Text & Bildern), die Fortschritte werden lokal im Browser gespeichert. Hochgeladene Bilder werden am Server gespeichert und können per PHP-Script auf A6 mit Logo ausgedruckt werden.

## Verzeichnisstruktur

```
TDOS_Chatbot/
├── index.html            # One-Page Frontend
├── assets/
│   ├── style.css         # Modernes UI-Design
│   ├── app.js            # Frontend-Logik (Chat, Quests, Speicherung)
│   └── logo.png          # Schul-Logo (bitte hier ablegen)
├── api/
│   ├── chat.php          # Chat-Endpoint (AI Proxy)
│   ├── upload.php        # Bild-Upload + lokale Speicherung + Bildprüfung
│   ├── images.php        # Bilderliste (filterbar nach Schüler)
│   ├── quests.php        # Liefert die dynamischen Quests
│   └── verify.php        # Serverseitige Antwortprüfung
├── lib/
│   ├── helpers.php       # Hilfsfunktionen + Kontext-/Quest-Aufbau
│   └── AiClient.php      # AI-Client (DeepSeek / FreeGPT)
├── context/
│   └── instructions.txt  # Statische KI-Instruktionen (Persona + Regeln)
├── quests.php            # ⭐ Dynamische Quest-Definitionen (Fragen, Hinweise, Lösungen, Bildanforderungen)
├── uploads/              # Hochgeladene Bilder + _manifest.json (Zuordnung Schüler→Bilder)
├── config.php            # Konfiguration (API-Keys, Provider)
├── config.example.php    # Vorlage
└── print.php             # Druckansicht (A6 + Logo + AutoPrint, filterbar nach Schüler)
```

## Einrichtung

1. **PHP-Server starten** (im Projektordner):

   ```bash
   php -S localhost:8000
   ```

2. **Konfiguration anpassen** in `config.php`:
   - `provider`: `'freegpt'` oder `'deepseek'`
   - Bei DeepSeek den `api_key` eintragen
   - Bei FreeGPT die `base_url` deines FreeGPT/g4f-Proxys anpassen

3. **Logo** unter `assets/logo.png` ablegen (sonst wird ein Platzhalter angezeigt).

4. **KI-Instruktionen** unter `context/instructions.txt` bearbeiten.

## Provider

- **FreeGPT / g4f**: kostenlos zum Testen. Eigenen g4f-Server starten (z. B. `g4f api` → `http://localhost:1338`).
- **DeepSeek**: `provider = 'deepseek'`, Key eintragen. Für Vision (Foto-Erkennung) wird `vision_model` verwendet.

## Funktionen

- 🤖 KI-Chatbot mit statischem Kontext + dynamischen Quests + Chatverlauf als Prompt
- 🏆 Quests als Popup (dynamisch aus `quests.php` geladen), erledigte grün mit ✓
- 📝 Nachrichten-Suche
- 📷 Text- und Bild-Upload (Bilder lokal am Server gespeichert, pro Schüler zugeordnet)
- 🖼️ Bildanzeige über Web-URL im Chat
- 🔐 Serverseitige Antwortprüfung – Lösungswörter bleiben im Browser geheim
- 💾 Quest- & Chat-Speicherung im Browser (`localStorage`)
- 🖨️ Druck-Abfrage im Chat: nur die Bilder des jeweiligen Schülers werden gedruckt

## Quests dynamisch verwalten

Alle Quests stehen in **`quests.php`**. Du kannst sie jederzeit ändern, löschen oder ergänzen – die KI und das Frontend übernehmen die Änderungen automatisch.

Jedes Quest unterstützt:

| Feld | Bedeutung |
|------|-----------|
| `id` | eindeutige Nummer |
| `title` | Kurztitel (Popup) |
| `description` | Kurzbeschreibung (Popup) |
| `question` | vollständige Fragestellung (an die KI) |
| `answers` | richtige Antworten (mehrere Schreibweisen möglich) |
| `hints` | abgestufte Hinweise (Hinweis 1 → 2 → 3 …) |
| `imageBased` | `true`, wenn ein Foto hochgeladen werden muss |
| `imageRequirement` | was auf dem Foto zu sehen sein muss (z. B. „High five mit dem Direktor") |
| `codeword` | optionales Codewort als Ersatz für das Foto |

## TAN-Codes (zusätzliche Sicherheit)

Damit die Lösungswörter nicht einfach unter den Schülern getauscht werden können, wird nach einer richtigen Antwort zusätzlich ein **TAN-Code** abgefragt.

- Die TAN-Liste steht in **`tans.php`** (10 Einträge, Zeile 1–10).
- Nach einer korrekten Antwort fordert der Bot den **TAN-Code einer bestimmten Zeile** (z. B. „Code aus Zeile 3").
- Erst wenn der richtige TAN eingegeben wird, wird das Quest grün erfüllt.
- Die Zeile wird pro Schüler+Quest deterministisch gewählt – die Betreuer am Tag der offenen Tür halten die ausgedruckte Liste bereit und nennen dem Schüler den passenden Code.
- Codes jederzeit in `tans.php` änderbar.

## Druck

- Am Ende (alle Quests erfüllt) fragt der Chatbot, ob die Bilder gedruckt werden sollen.
- Mit „Ja, drucken" öffnet sich `print.php?student_id=…` → es werden **nur die Bilder dieses Schülers** gedruckt.
- Der Druckdialog startet automatisch. Mit `?autoprint=0` deaktivieren.
- A6-Ausrichtung ist über CSS `@page { size: A6; }` konfiguriert.
- Als Admin-Gesamtübersicht: `print.php` ohne `student_id` zeigt alle Bilder.
