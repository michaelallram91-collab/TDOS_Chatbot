<?php
/**
 * Ausdruck-Station (Mitarbeiter-Weboberfläche).
 *
 * Ein Mitarbeiter sitzt am PC neben dem Sekretariat und steigt HIER ein.
 * Ablauf:
 *   1) Login mit User-ID + PIN (wird dem Schüler am Ende angezeigt).
 *   2) Nach erfolgreichem Login werden die hochgeladenen Fotos des
 *      Schülers angezeigt und können gedruckt/heruntergeladen werden.
 *
 * Aufruf: print-station.php
 */

require __DIR__ . '/lib/helpers.php';

session_start();

$error = null;
$loggedIn = !empty($_SESSION['print_station_user']);

/* ---------- Login verarbeiten ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $userId = trim((string)($_POST['user_id'] ?? ''));
    $pin    = trim((string)($_POST['pin'] ?? ''));
    $acc = verifyAccount($userId, $pin);
    if ($acc) {
        $_SESSION['print_station_user'] = $acc;
        $loggedIn = true;
    } else {
        $error = 'Ungültige User-ID oder falscher PIN. Bitte erneut versuchen.';
    }
}

/* ---------- Logout ---------- */
if (isset($_GET['logout'])) {
    unset($_SESSION['print_station_user']);
    session_destroy();
    header('Location: print-station.php');
    exit;
}

$studentId = $loggedIn ? ($_SESSION['print_station_user']['student_id'] ?? '') : '';
$images = $loggedIn && $studentId !== '' ? getStudentImages($studentId) : [];

$logoPath = 'assets/logo.png';
$logoExists = is_file($logoPath);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ausdruck-Station &middot; BHAK/BHAS Schwaz</title>
<style>
  @page { size: A6; margin: 8mm; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: #f3f4f6;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: #0f172a;
  }
  .topbar {
    background: #1e293b;
    color: #fff;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  .topbar h1 { font-size: 17px; margin: 0; font-weight: 600; }
  .topbar .hint { font-size: 12px; color: #cbd5e1; }
  .topbar a.logout {
    background: #0ea5e9; color: #fff; border: none; padding: 8px 16px;
    border-radius: 8px; font-size: 13px; cursor: pointer; text-decoration: none; font-weight: 600;
  }
  .topbar a.logout:hover { background: #0284c7; }

  /* ---------- Login ---------- */
  .login-wrap { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 60px); padding: 20px; }
  .login-card {
    background: #fff; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,.10);
    padding: 32px; width: 100%; max-width: 400px;
  }
  .login-card h2 { margin: 0 0 4px; font-size: 20px; }
  .login-card p.sub { margin: 0 0 20px; color: #64748b; font-size: 14px; }
  .login-card label { display: block; font-size: 13px; font-weight: 600; margin: 12px 0 4px; }
  .login-card input {
    width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px;
    font-size: 16px; letter-spacing: 1px;
  }
  .login-card input:focus { outline: none; border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,.15); }
  .login-card button {
    width: 100%; margin-top: 20px; background: #0ea5e9; color: #fff; border: none;
    padding: 12px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer;
  }
  .login-card button:hover { background: #0284c7; }
  .login-error { background: #fee2e2; color: #b91c1c; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-top: 12px; }

  /* ---------- Galerie ---------- */
  .toolbar {
    position: sticky; top: 0; z-index: 10; background: #1e293b; color: #fff;
    padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px;
  }
  .toolbar .info { font-size: 13px; color: #cbd5e1; }
  .toolbar button {
    background: #0ea5e9; color: #fff; border: none; padding: 9px 18px;
    border-radius: 8px; font-size: 14px; cursor: pointer; font-weight: 600;
  }
  .toolbar button:hover { background: #0284c7; }
  .grid { display: flex; flex-wrap: wrap; gap: 16px; justify-content: center; padding: 24px 20px; }
  .card {
    width: 105mm; background: #fff; border-radius: 10px;
    box-shadow: 0 4px 16px rgba(0,0,0,.08); overflow: hidden; page-break-inside: avoid;
  }
  .logo-row { display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #0f172a; }
  .logo-row img { height: 34px; object-fit: contain; }
  .logo-row .school { color: #fff; font-size: 11px; line-height: 1.2; font-weight: 600; }
  .logo-row .school span { display: block; color: #94a3b8; font-weight: 400; font-size: 9px; }
  .card img.photo { width: 100%; height: 68mm; object-fit: cover; display: block; }
  .caption { padding: 8px 12px; font-size: 11px; color: #475569; text-align: center; }
  .empty { text-align: center; color: #94a3b8; padding: 80px 20px; font-size: 15px; }
  .download-all { text-align: center; margin: 8px 0 24px; }
  .download-all a {
    display: inline-block; background: #0f172a; color: #fff; text-decoration: none;
    padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600;
  }
  @media print {
    .toolbar, .download-all { display: none; }
    .grid { padding: 0; gap: 0; }
    .card { width: 105mm; margin: 0; border-radius: 0; box-shadow: none; }
  }
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
  <div class="topbar">
    <h1>🖨️ Ausdruck-Station</h1>
    <span class="hint">Neben dem Sekretariat · BHAK/BHAS Schwaz</span>
  </div>
  <div class="login-wrap">
    <div class="login-card">
      <h2>Mitarbeiter-Login</h2>
      <p class="sub">Bitte User-ID und PIN des Schülers eingeben, um dessen Fotos anzuzeigen und auszudrucken.</p>
      <form method="post" action="print-station.php" autocomplete="off">
        <input type="hidden" name="action" value="login">
        <label for="userId">User-ID</label>
        <input type="text" id="userId" name="user_id" placeholder="z. B. TD-4K7Q" required autocomplete="off">
        <label for="pin">PIN</label>
        <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="4" placeholder="4-stelliger PIN" required autocomplete="off">
        <button type="submit">Anmelden</button>
        <?php if ($error): ?>
          <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
      </form>
    </div>
  </div>

<?php else: ?>
  <div class="toolbar">
    <h1>🖨️ Ausdruck-Station</h1>
    <div class="info">
      Angemeldet als <strong><?= htmlspecialchars($_SESSION['print_station_user']['user_id'] ?? '') ?></strong>
      &middot; <?= count($images) ?> Bild(er)
    </div>
    <div style="display:flex;gap:8px;">
      <button onclick="printAll()">Jetzt drucken (A6)</button>
      <a class="logout" href="print-station.php?logout=1">Abmelden</a>
    </div>
  </div>

  <?php if (count($images) === 0): ?>
    <div class="empty">Für diesen Schüler wurden keine Fotos hochgeladen.</div>
  <?php else: ?>
    <div class="download-all">
      <a href="#" onclick="downloadAll(); return false;">⬇️ Alle Bilder herunterladen</a>
    </div>
    <div class="grid" id="grid">
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
          <img class="photo" src="<?= htmlspecialchars($img['url']) ?>" alt="Foto">
          <div class="caption">Erinnerungsfoto &middot; BHAK/BHAS Schwaz</div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<script>
  // Alle Bild-URLs (aus dem PHP-Rendering) für Download/Print bereitstellen
  const imageUrls = <?= json_encode(array_column($images, 'url'), JSON_UNESCAPED_SLASHES) ?>;

  function printAll() {
    window.print();
  }

  function downloadAll() {
    if (!imageUrls.length) return;
    imageUrls.forEach((url, i) => {
      setTimeout(() => {
        const a = document.createElement('a');
        a.href = url;
        a.download = url.split('/').pop();
        document.body.appendChild(a);
        a.click();
        a.remove();
      }, i * 400);
    });
  }
</script>
</body>
</html>
