<?php
/**
 * Druckansicht: Zeigt alle hochgeladenen Bilder auf A6 optimiert,
 * inkl. Logo, und löst automatisch den Druckdialog aus.
 * Aufruf: print.php
 */

require __DIR__ . '/lib/helpers.php';

$studentId = trim((string)($_GET['student_id'] ?? ''));
$studentId = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);

$imagesRaw = getStudentImages($studentId);
$images = array_column($imagesRaw, 'url');

$logoPath = 'assets/logo.png';
$logoExists = is_file($logoPath);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Druckansicht - TDOS Chatbot</title>
<style>
  @page { size: A6; margin: 8mm; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: #f3f4f6;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  }
  .toolbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 10;
    background: #1e293b;
    color: #fff;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .toolbar h1 { font-size: 16px; margin: 0; font-weight: 600; }
  .toolbar .count { font-size: 13px; color: #cbd5e1; }
  .toolbar button {
    background: #0ea5e9;
    color: #fff;
    border: none;
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    font-weight: 600;
  }
  .toolbar button:hover { background: #0284c7; }
  .grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: center;
    padding: 80px 20px 20px;
  }
  .card {
    width: 105mm;   /* A6 Breite */
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    overflow: hidden;
    page-break-inside: avoid;
  }
  .logo-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #0f172a;
  }
  .logo-row img { height: 34px; object-fit: contain; }
  .logo-row .school {
    color: #fff;
    font-size: 11px;
    line-height: 1.2;
    font-weight: 600;
  }
  .logo-row .school span { display: block; color: #94a3b8; font-weight: 400; font-size: 9px; }
  .card img.photo {
    width: 100%;
    height: 68mm;
    object-fit: cover;
    display: block;
  }
  .caption {
    padding: 8px 12px;
    font-size: 11px;
    color: #475569;
    text-align: center;
  }
  .empty {
    text-align: center;
    color: #94a3b8;
    padding: 80px 20px;
    font-size: 15px;
  }
  @media print {
    .toolbar { display: none; }
    .grid { padding: 0; gap: 0; }
    .card { width: 105mm; margin: 0; border-radius: 0; box-shadow: none; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <h1>Druckansicht &middot; Tag der offenen Tür</h1>
    <div class="count"><?= count($images) ?> Bild(er)</div>
    <button onclick="window.print()">Jetzt drucken (A6)</button>
  </div>

  <?php if (count($images) === 0): ?>
    <div class="empty">Noch keine Bilder hochgeladen.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($images as $img): ?>
        <div class="card">
          <div class="logo-row">
            <?php if ($logoExists): ?>
              <img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo">
            <?php endif; ?>
            <div class="school">
              BHAK/BHAS Schwaz
              <span>Tag der offenen Tür &middot; Interaktive Schulrallye</span>
            </div>
          </div>
          <img class="photo" src="<?= htmlspecialchars($img) ?>" alt="Foto">
          <div class="caption">Dein Erinnerungsfoto &middot; BHAK/BHAS Schwaz</div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <script>
    // Automatisch Druckdialog öffnen (per URL-Parameter ?autoprint=1 steuerbar)
    const params = new URLSearchParams(window.location.search);
    if (params.get('autoprint') !== '0') {
      window.addEventListener('load', () => setTimeout(() => window.print(), 600));
    }
  </script>
</body>
</html>
