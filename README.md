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
│   ├── upload.php        # Bild-Upload + lokale Speicherung (pro Schüler-Ordner) + Bildprüfung
│   ├── images.php        # Bilderliste (filterbar nach Schüler)
│   ├── quests.php        # Liefert die dynamischen Quests
│   ├── verify.php        # Serverseitige Antwort- und TAN-Prüfung (protokolliert Erfüllung)
│   └── account.php       # Liefert User-ID + PIN (erst nach Abschluss aller Quests)
├── lib/
│   ├── helpers.php       # Hilfsfunktionen + Kontext-/Quest-Aufbau
│   └── AiClient.php      # AI-Client (DeepSeek / FreeGPT)
├── context/
│   └── instructions.txt  # Statische KI-Instruktionen (Persona + Regeln)
├── quests.php            # ⭐ Dynamische Quest-Definitionen (Fragen, Hinweise, Lösungen, Bildanforderungen)
├── uploads/              # Hochgeladene Bilder; pro Schüler ein eigener Ordner
│   ├── <student_id>/     # Fotos eines Schülers (eindeutige Zuordnung)
│   ├── _manifest.json    # Zuordnung Schüler→Bilder
│   ├── _completed.json   # serverseitig erledigte Quests (Quelle der Wahrheit)
│   └── _accounts.json    # User-ID + PIN der Ausdruck-Konten
├── config.php            # Konfiguration (API-Keys, Provider)
├── config.example.php    # Vorlage
├── print-station.php     # 🖨️ Ausdruck-Station (Mitarbeiter-Login mit User-ID + PIN)
└── print.php             # Weiterleitung auf print-station.php
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
- Die Zeile wird pro Anfrage zufällig gewählt – die Betreuer halten die ausgedruckte Liste bereit und nennen dem Schüler den passenden Code.
- Codes jederzeit in `tans.php` änderbar.

**Garantierte Reihenfolge:** Eine Quest gilt erst dann als „erfüllt“ (im Chat und im Quests-Popup), wenn BOTH die richtige Antwort UND anschließend der richtige TAN bestätigt wurden. Die Erfüllung wird dabei serverseitig in `uploads/_completed.json` protokolliert und ist die verbindliche Quelle.

## Ausdruck-Station (Druck der Fotos)

Am Ende der letzten Quest wird dem Schüler automatisch angezeigt:

- 🎉 Glückwunsch + Hinweis, dass im **Sekretariat** ein kleines Geschenk bereitliegt.
- 🖨️ Hinweis, dass hochgeladene Fotos an der **Ausdruck-Station** (neben dem Sekretariat) ausgedruckt werden können.
- 🔑 Eine **User-ID** und ein **PIN**, mit denen sich der Mitarbeiter einloggt.

Der Mitarbeiter öffnet dazu **`print-station.php`** und meldet sich mit User-ID + PIN an. Danach sieht er genau die Fotos des entsprechenden Schülers, kann sie herunterladen und ausdrucken (A6, mit Logo).

- Die Konten (User-ID + PIN) werden serverseitig in `uploads/_accounts.json` verwaltet und erst nach Abschluss aller Quests freigegeben.
- Die Zuordnung der Fotos erfolgt eindeutig über den Schüler-Ordner `uploads/<student_id>/`.
