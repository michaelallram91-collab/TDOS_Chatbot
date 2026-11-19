<?php
/**
 * Admin-Dashboard – Übersicht über den Quest-Fortschritt aller Schüler.
 *
 * Zeigt:
 *  - Gesamtstatistik (User gesamt, fertig, offen)
 *  - Pro Quest, wie viele User sie erledigt haben
 *  - Eine Tabelle mit jedem User und dessen Fortschritt
 *
 * Login: Benutzername + Passwort (aus config.php -> 'admin').
 * Aufruf: admin.php
 */

require __DIR__ . '/lib/helpers.php';

session_start();

$config = loadConfig();
$adminCfg = $config['admin'] ?? ['username' => 'admin', 'password' => ''];

$error = null;
$loggedIn = !empty($_SESSION['admin_logged_in']);

/* ---------- Login ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if ($user !== '' && hash_equals((string)$adminCfg['username'], $user) && hash_equals((string)$adminCfg['password'], $pass)) {
        $_SESSION['admin_logged_in'] = true;
        $loggedIn = true;
    } else {
        $error = 'Ungültiger Benutzername oder falsches Passwort.';
    }
}

/* ---------- Logout ---------- */
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header('Location: admin.php');
    exit;
}

$quests = loadQuests();
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard &middot; BHAK/BHAS Schwaz</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: #f1f5f9;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: #0f172a;
  }
  .topbar {
    background: #0f172a; color: #fff; padding: 14px 22px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
  }
  .topbar h1 { font-size: 17px; margin: 0; font-weight: 600; }
  .topbar .sub { font-size: 12px; color: #cbd5e1; }
  .topbar a.logout {
    background: #e11d48; color: #fff; padding: 8px 16px; border-radius: 8px;
    text-decoration: none; font-size: 13px; font-weight: 600;
  }
  .topbar a.logout:hover { background: #be123c; }

  /* Login */
  .login-wrap { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 60px); padding: 20px; }
  .login-card { background: #fff; border-radius: 14px; box-shadow: 0 8px 30px rgba(0,0,0,.10); padding: 32px; width: 100%; max-width: 380px; }
  .login-card h2 { margin: 0 0 4px; font-size: 20px; }
  .login-card p.sub { margin: 0 0 20px; color: #64748b; font-size: 14px; }
  .login-card label { display: block; font-size: 13px; font-weight: 600; margin: 12px 0 4px; }
  .login-card input { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 15px; }
  .login-card button { width: 100%; margin-top: 20px; background: #0ea5e9; color: #fff; border: none; padding: 12px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
  .login-error { background: #fee2e2; color: #b91c1c; padding: 10px 12px; border-radius: 8px; font-size: 13px; margin-top: 12px; }

  .wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
  .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
  .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); padding: 18px 20px; }
  .card .label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
  .card .value { font-size: 30px; font-weight: 700; margin-top: 4px; }
  .card.green .value { color: #059669; }
  .card.blue .value { color: #0284c7; }
  .card.amber .value { color: #d97706; }

  h2 { font-size: 16px; margin: 24px 0 12px; }
  .quest-breakdown { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; margin-bottom: 24px; }
  .qb { background: #fff; border-radius: 12px; padding: 14px 16px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
  .qb .qt { font-size: 13px; font-weight: 600; margin-bottom: 6px; }
  .qb .bar { height: 8px; background: #e2e8f0; border-radius: 999px; overflow: hidden; }
  .qb .bar > div { height: 100%; background: #22c55e; }
  .qb .qm { font-size: 12px; color: #64748b; margin-top: 6px; }

  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
  thead th { background: #f8fafc; text-align: left; padding: 12px 14px; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: .3px; }
  tbody td { padding: 12px 14px; font-size: 14px; border-top: 1px solid #f1f5f9; }
  .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
  .badge.done { background: #dcfce7; color: #15803d; }
  .badge.progress { background: #fef3c7; color: #b45309; }
  .badge.none { background: #f1f5f9; color: #64748b; }
  .muted { color: #94a3b8; }
  .refresh { color: #64748b; font-size: 12px; margin-top: 16px; }
  .empty { color: #94a3b8; text-align: center; padding: 40px 0; }
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
  <div class="topbar">
    <h1>📊 Dashboard</h1>
    <span class="sub">Tag der offenen Tür &middot; BHAK/BHAS Schwaz</span>
  </div>
  <div class="login-wrap">
    <div class="login-card">
      <h2>Admin-Login</h2>
      <p class="sub">Bitte anmelden, um die Fortschritts-Übersicht zu sehen.</p>
      <form method="post" action="admin.php" autocomplete="off">
        <input type="hidden" name="action" value="login">
        <label for="username">Benutzername</label>
        <input type="text" id="username" name="username" required autocomplete="off">
        <label for="password">Passwort</label>
        <input type="password" id="password" name="password" required autocomplete="off">
        <button type="submit">Anmelden</button>
        <?php if ($error): ?><div class="login-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      </form>
    </div>
  </div>

<?php else: ?>
  <div class="topbar">
    <div>
      <h1>📊 Dashboard &middot; Quest-Fortschritt</h1>
      <span class="sub">Tag der offenen Tür &middot; BHAK/BHAS Schwaz</span>
    </div>
    <div style="display:flex; align-items:center; gap:12px;">
      <button onclick="loadData()" style="background:#0ea5e9;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-weight:600;cursor:pointer;">🔄 Aktualisieren</button>
      <a class="logout" href="admin.php?logout=1">Abmelden</a>
    </div>
  </div>

  <div class="wrap">
    <div class="cards">
      <div class="card"><div class="label">User gesamt</div><div class="value blue" id="statTotal">–</div></div>
      <div class="card green"><div class="label">Alles erledigt</div><div class="value" id="statDone">–</div></div>
      <div class="card amber"><div class="label">In Arbeit</div><div class="value" id="statProgress">–</div></div>
      <div class="card"><div class="label">Noch nichts</div><div class="value" id="statNone">–</div></div>
    </div>

    <h2>Erledigung pro Quest</h2>
    <div class="quest-breakdown" id="questBreakdown"></div>

    <h2>Schüler-Übersicht</h2>
    <div id="studentTable"></div>
    <div class="refresh" id="lastUpdated"></div>
  </div>
<?php endif; ?>

<script>
  const QUESTS = <?= json_encode(array_values($quests), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
  }

  function statusBadge(s) {
    if (s.done) return '<span class="badge done">✓ Alles erledigt</span>';
    if (s.completed > 0) return '<span class="badge progress">' + s.completed + ' / ' + s.total + '</span>';
    return '<span class="badge none">Noch nichts</span>';
  }

  function render(data) {
    const students = data.students || [];
    const totalQuests = data.total_quests || 0;

    const doneCount = students.filter((s) => s.done).length;
    const progressCount = students.filter((s) => !s.done && s.completed > 0).length;
    const noneCount = students.filter((s) => s.completed === 0).length;

    document.getElementById('statTotal').textContent = students.length;
    document.getElementById('statDone').textContent = doneCount;
    document.getElementById('statProgress').textContent = progressCount;
    document.getElementById('statNone').textContent = noneCount;

    // Erledigung pro Quest (exakt über per_quest vom Server).
    renderQuestBreakdown(data);

    // Tabelle
    const table = document.getElementById('studentTable');
    if (students.length === 0) {
      table.innerHTML = '<div class="empty">Noch keine Schüler aktiv.</div>';
      return;
    }
    let rows = '<table><thead><tr>' +
      '<th>User-ID</th>' +
      '<th>Fortschritt</th>' +
      '<th>Status</th>' +
      '<th>Fotos</th>' +
      '<th>Erstellt</th>' +
      '</tr></thead><tbody>';
    students.forEach((s) => {
      const created = s.created ? new Date(s.created * 1000).toLocaleString('de-AT') : '–';
      rows += '<tr>' +
        '<td>' + (s.user_id ? '<strong>' + esc(s.user_id) + '</strong>' : '<span class="muted">–</span>') + '</td>' +
        '<td>' + esc(s.completed) + ' / ' + esc(s.total) + '</td>' +
        '<td>' + statusBadge(s) + '</td>' +
        '<td>' + (s.images > 0 ? '📷 ' + esc(s.images) : '–') + '</td>' +
        '<td class="muted">' + esc(created) + '</td>' +
        '</tr>';
    });
    rows += '</tbody></table>';
    table.innerHTML = rows;

    const lu = document.getElementById('lastUpdated');
    lu.textContent = 'Zuletzt aktualisiert: ' + new Date().toLocaleTimeString('de-AT');
  }

  function renderQuestBreakdown(data) {
    const qb = document.getElementById('questBreakdown');
    const perQuest = data.per_quest || {};
    const total = data.total_quests || 0;
    const studentsLen = (data.students || []).length;
    qb.innerHTML = QUESTS.map((q) => {
      const solved = perQuest[q.id] || 0;
      const pct = studentsLen > 0 ? Math.round((solved / studentsLen) * 100) : 0;
      return '<div class="qb">' +
        '<div class="qt">Quest ' + esc(q.id) + ': ' + esc(q.title) + '</div>' +
        '<div class="bar"><div style="width:' + pct + '%"></div></div>' +
        '<div class="qm">' + solved + ' von ' + studentsLen + ' Schülern (' + pct + '%)</div>' +
        '</div>';
    }).join('');
  }

  async function loadData() {
    try {
      const resp = await fetch('api/admin.php');
      if (resp.status === 401) { location.reload(); return; }
      const data = await resp.json();
      render(data);
    } catch (e) {
      console.error(e);
    }
  }

  loadData();
  setInterval(loadData, 15000); // alle 15 Sekunden automatisch aktualisieren
</script>
</body>
</html>
