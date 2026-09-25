'use strict';

/* ============================================================
 * TDOS Chatbot – Frontend-Logik
 * ============================================================ */

/* ---------- Quest-Definitionen (dynamisch vom Server) ---------- */
let QUESTS = []; // wird über /api/quests.php geladen

/* ---------- State ---------- */
const STORAGE_KEY_HISTORY = 'tdos_chat_history';
const STORAGE_KEY_QUESTS = 'tdos_completed_quests';
const STORAGE_KEY_NAME = 'tdos_student_name';
const STORAGE_KEY_STUDENT_ID = 'tdos_student_id';

let history = loadJSON(STORAGE_KEY_HISTORY, []);
let completedQuests = loadJSON(STORAGE_KEY_QUESTS, []);
let pendingImage = null; // { file, dataUrl }
let studentId = loadStudentId();
let pendingTan = null; // { questId, title, row } – wartet auf TAN-Eingabe

/* ---------- DOM ---------- */
const $ = (sel) => document.querySelector(sel);
const messagesEl = $('#messages');
const chatForm = $('#chatForm');
const chatInput = $('#chatInput');
const sendBtn = $('#sendBtn');
const fileInput = $('#fileInput');
const imagePreview = $('#imagePreview');
const previewImg = $('#previewImg');
const removeImgBtn = $('#removeImg');
const searchInput = $('#searchInput');
const questModal = $('#questModal');
const questList = $('#questList');
const questBadge = $('#questBadge');
const progressText = $('#progressText');
const toastEl = $('#toast');

/* ---------- Utilities ---------- */
function loadJSON(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch {
    return fallback;
  }
}
function saveJSON(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {}
}

function showToast(msg, type = '') {
  toastEl.textContent = msg;
  toastEl.className = 'toast ' + type;
  toastEl.hidden = false;
  clearTimeout(showToast._t);
  showToast._t = setTimeout(() => { toastEl.hidden = true; }, 3200);
}

/* Schüler-ID (pro Browser) – zur Zuordnung der Bilder beim Druck */
function loadStudentId() {
  let id = localStorage.getItem(STORAGE_KEY_STUDENT_ID);
  if (!id) {
    id = 's_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    localStorage.setItem(STORAGE_KEY_STUDENT_ID, id);
  }
  return id;
}

function normalize(s) {
  return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
}

/* ---------- Quests ---------- */
function isQuestDone(id) {
  return completedQuests.includes(id);
}

function markQuestDone(id) {
  if (!isQuestDone(id)) {
    completedQuests.push(id);
    saveJSON(STORAGE_KEY_QUESTS, completedQuests);
    renderQuests();
    const q = QUESTS.find((x) => x.id === id);
    showToast(`🎉 Quest ${id} erfüllt: ${q ? q.title : ''}`, 'success');

    // Wenn alle Quests erfüllt → Abschluss + Druckaufforderung
    if (completedQuests.length >= QUESTS.length && QUESTS.length > 0) {
      promptPrint();
    }
  }
}

function renderQuests() {
  questList.innerHTML = '';
  QUESTS.forEach((q) => {
    const done = isQuestDone(q.id);
    const li = document.createElement('li');
    li.className = 'quest-item' + (done ? ' done' : '');
    let imgReq = '';
    if (q.imageBased && q.imageRequirement) {
      imgReq = `<div class="quest-img-req">📷 ${escapeHtml(q.imageRequirement)}</div>`;
    }
    let questImg = '';
    if (q.imageUrl) {
      questImg = `<div class="quest-image"><img src="${escapeHtml(q.imageUrl)}" alt="Quest-Bild" loading="lazy"></div>`;
    }
    li.innerHTML = `
      <span class="quest-check">${done ? '✓' : ''}</span>
      <div class="quest-info">
        <div class="quest-title">Quest ${q.id}: ${escapeHtml(q.title)}</div>
        <div class="quest-desc">${escapeHtml(q.description || '')}</div>
        ${imgReq}
      </div>
    `;
    if (questImg) {
      const imgWrap = document.createElement('div');
      imgWrap.className = 'quest-image-full';
      imgWrap.innerHTML = questImg;
      li.appendChild(imgWrap);
    }
    questList.appendChild(li);
  });
  const total = QUESTS.length;
  const done = completedQuests.filter((id) => QUESTS.some((q) => q.id === id)).length;
  questBadge.textContent = `${done}/${total}`;
  progressText.textContent = `${done} von ${total} erledigt`;
}

/* ---------- Quest-Erkennung (serverseitig) ---------- */
async function verifyAnswer(text, hasImage) {
  try {
    const resp = await fetch('api/verify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, hasImage: hasImage, student_id: studentId }),
    });
    const data = await resp.json();
    if (!resp.ok) return null;
    return data.matched ? data : null;
  } catch (e) {
    console.error(e);
    return null;
  }
}

/* ---------- TAN prüfen (serverseitig) ---------- */
async function verifyTan(tan, questId, row) {
  try {
    const resp = await fetch('api/verify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ tan: tan, questId: questId, student_id: studentId, row: row }),
    });
    const data = await resp.json();
    if (!resp.ok) return false;
    return !!data.tanOk;
  } catch (e) {
    console.error(e);
    return false;
  }
}

/* ---------- Begrüßungsnachricht ---------- */
function ensureWelcome() {
  if (history.length === 0) {
    history.push({
      role: 'assistant',
      content: 'Herzlich Willkommen an der BHAK/BHAS Schwaz – deine Reise beginnt jetzt. Löse alle Aufgaben, um das Rätsel zu lösen. Wie ist dein Name? 😊',
    });
    saveJSON(STORAGE_KEY_HISTORY, history);
  }
}

/* ---------- Rendering ---------- */
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function urlifyAndImages(text) {
  // Bilder per URL im Chat anzeigen (z.B. https://...png)
  let escaped = escapeHtml(text);
  escaped = escaped.replace(
    /(https?:\/\/[^\s<>"']+\.(?:png|jpe?g|gif|webp))/gi,
    '<a class="img-link" href="$1" target="_blank" rel="noopener"><img class="msg-image" src="$1" alt="Bild"></a>'
  );
  // Clickbare Links
  escaped = escaped.replace(
    /(https?:\/\/[^\s<>"']+)/gi,
    '<a href="$1" target="_blank" rel="noopener">$1</a>'
  );
  return escaped;
}

function renderMessage(msg, { highlight = false } = {}) {
  const el = document.createElement('div');
  el.className = 'msg ' + (msg.role === 'user' ? 'user' : 'ai');
  if (highlight) el.classList.add('highlight');

  // Eigene hochgeladene Bilder (lokale URL)
  let contentHtml = urlifyAndImages(msg.content || '');
  if (msg.images && msg.images.length) {
    for (const img of msg.images) {
      contentHtml += `<a class="img-link" href="${img}" target="_blank" rel="noopener"><img class="msg-image" src="${img}" alt="Hochgeladenes Bild"></a>`;
    }
  }
  el.innerHTML = contentHtml;
  messagesEl.appendChild(el);

  if (msg.printPrompt) {
    renderPrintActions();
  }
  return el;
}

function renderAll() {
  // Merken ob gescrollt
  const shouldScroll = isNearBottom();
  messagesEl.innerHTML = '';
  const w = document.createElement('div');
  w.className = 'welcome';
  w.id = 'welcome';
  w.innerHTML = `
    <img src="assets/logo.png" alt="Logo" class="welcome-logo" id="welcomeLogo">
    <h2>Herzlich Willkommen!</h2>
    <p>Löse alle Quests, um das Rätsel zu lösen.</p>
  `;
  messagesEl.appendChild(w);

  history.forEach((m) => renderMessage(m));

  if (shouldScroll) scrollToBottom();
}

function isNearBottom() {
  return messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 120;
}
function scrollToBottom() {
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function addTypingIndicator() {
  const el = document.createElement('div');
  el.className = 'msg ai typing';
  el.id = 'typingIndicator';
  el.innerHTML = '<span></span><span></span><span></span>';
  messagesEl.appendChild(el);
  scrollToBottom();
  return el;
}

/* ---------- Chat senden ---------- */
async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text && !pendingImage) return;

  const hasImage = !!pendingImage;
  const imageFile = hasImage ? pendingImage.file : null;
  const imageDataUrl = hasImage ? pendingImage.url : null;

  const userMsg = {
    role: 'user',
    content: text,
  };
  if (hasImage) {
    userMsg.images = [imageDataUrl]; // Platzhalter, wird nach Upload ersetzt
  }

  history.push(userMsg);
  saveJSON(STORAGE_KEY_HISTORY, history);

  // UI aktualisieren
  chatInput.value = '';
  clearPendingImage();
  renderAll();
  const typing = addTypingIndicator();
  sendBtn.disabled = true;

  // Fall 1: Es wird auf eine TAN-Eingabe gewartet (nach richtiger Antwort)
  if (pendingTan) {
    const ok = await verifyTan(text, pendingTan.questId, pendingTan.row);
    typing.remove();

    if (ok) {
      markQuestDone(pendingTan.questId);
      history.push({
        role: 'assistant',
        content: `TAN-Code korrekt! ✅ Quest ${pendingTan.questId} ist erfüllt. Weiter so!`,
      });
    } else {
      history.push({
        role: 'assistant',
        content: `Das war leider der falsche TAN-Code. Frag einen Buddy oder Betreuer nach dem Code aus Zeile ${pendingTan.row} und versuche es erneut.`,
      });
    }
    pendingTan = null;
    saveJSON(STORAGE_KEY_HISTORY, history);
    renderAll();
    sendBtn.disabled = false;
    return;
  }

  let aiReply = null;

  if (hasImage) {
    // Bild uploaden + KI-Auswertung (Vision)
    const uploadResult = await uploadAndChat(text, imageFile);
    aiReply = uploadResult.content;

    // Foto-Aufgabe nur erfüllen, wenn die KI das gesuchte Ziel erkannt hat
    if (uploadResult.accepted) {
      const imgQuest = QUESTS.find((q) => q.imageBased && !isQuestDone(q.id));
      if (imgQuest) {
        markQuestDone(imgQuest.id);
      }
    }

    // Fehlerfall: konkrete Meldung anzeigen
    if (uploadResult.error) {
      aiReply = '⚠️ ' + uploadResult.error;
    } else if (!aiReply) {
      aiReply = 'Dein Bild wurde hochgeladen, aber ich konnte es nicht auswerten. Bitte versuche es erneut oder nenne das Codewort.';
    }
  } else {
    aiReply = await callChat(text);
  }

  typing.remove();

  // Serverseitige Quest-Erkennung (Antworten bleiben geheim)
  const verified = await verifyAnswer(text, hasImage);

  if (verified && verified.requireTan) {
    // Richtige Antwort, aber TAN wird verlangt → Quest noch NICHT erfüllen
    pendingTan = { questId: verified.questId, title: verified.title, row: verified.row };
  } else if (verified && verified.questId) {
    markQuestDone(verified.questId);
  }

  if (aiReply) {
    history.push({ role: 'assistant', content: aiReply });
  } else {
    history.push({
      role: 'assistant',
      content: 'Entschuldigung, ich konnte gerade keine Antwort erhalten. Bitte versuche es erneut.',
    });
  }

  // Wenn eine TAN-Abfrage nötig ist, zusätzliche Nachricht anhängen
  if (verified && verified.requireTan && !pendingImage) {
    history.push({
      role: 'assistant',
      content: `Richtige Antwort! 🔒 Zur Sicherheit brauche ich noch einen TAN-Code: Bitte nenne mir den Code aus Zeile ${verified.row} der TAN-Liste.`,
    });
  }

  saveJSON(STORAGE_KEY_HISTORY, history);

  renderAll();
  sendBtn.disabled = false;
}

async function callChat(text) {
  try {
    const resp = await fetch('api/chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text, history: history.slice(0, -1) }),
    });
    const data = await resp.json();
    if (!resp.ok) throw new Error(data.error || 'Fehler');
    return data.content;
  } catch (e) {
    console.error(e);
    return null;
  }
}

async function uploadAndChat(text, imageFile) {
  const fd = new FormData();
  fd.append('file', imageFile);
  fd.append('message', text);
  fd.append('student_id', studentId);
  fd.append('history', JSON.stringify(history.slice(0, -1)));
  try {
    const resp = await fetch('api/upload.php', { method: 'POST', body: fd });
    const data = await resp.json();
    if (!resp.ok) {
      return { content: null, accepted: false, error: data.error || 'Upload-Fehler' };
    }

    // URLs ersetzen: bereits gespeicherte URL verwenden
    const last = history[history.length - 1];
    if (last && last.images) {
      last.images = [data.url];
    }

    return {
      content: data.ai || null,
      accepted: !!data.accepted,
      error: null,
    };
  } catch (e) {
    console.error(e);
    return { content: null, accepted: false, error: 'Netzwerk- oder Serverfehler.' };
  }
}

/* ---------- Druck: Schüler fragen ---------- */
function promptPrint() {
  const aiMsg = {
    role: 'assistant',
    content: '🎉 Glückwunsch, du hast alle Quests erfüllt! Möchtest du deine hochgeladenen Bilder jetzt ausdrucken lassen?',
    printPrompt: true,
  };
  history.push(aiMsg);
  saveJSON(STORAGE_KEY_HISTORY, history);
  renderAll();
}

// Render-Druckdialog-Buttons als Teil einer Nachricht
function renderPrintActions() {
  // Buttons unterhalb der letzten KI-Nachricht anzeigen
  const actions = document.createElement('div');
  actions.className = 'msg-actions';
  const yesBtn = document.createElement('button');
  yesBtn.className = 'btn btn-primary btn-sm';
  yesBtn.textContent = '🖨️ Ja, drucken';
  yesBtn.addEventListener('click', () => openPrintView());

  const noBtn = document.createElement('button');
  noBtn.className = 'btn btn-ghost btn-sm';
  noBtn.textContent = 'Nein, danke';
  noBtn.addEventListener('click', () => {
    showToast('Ok, kein Druck. :)');
  });

  actions.appendChild(yesBtn);
  actions.appendChild(noBtn);
  messagesEl.appendChild(actions);
  scrollToBottom();
}

function openPrintView() {
  const url = 'print.php?student_id=' + encodeURIComponent(studentId);
  window.open(url, '_blank');
  showToast('Druckansicht wird geöffnet…', 'success');
}

/* ---------- Bild-Vorschau ---------- */
function setPendingImage(file) {
  const reader = new FileReader();
  reader.onload = (e) => {
    pendingImage = { file, url: e.target.result, dataUrl: e.target.result };
    previewImg.src = e.target.result;
    imagePreview.hidden = false;
  };
  reader.readAsDataURL(file);
}
function clearPendingImage() {
  pendingImage = null;
  fileInput.value = '';
  imagePreview.hidden = true;
  previewImg.src = '';
}

/* ---------- Suche ---------- */
function applySearch(term) {
  term = normalize(term);
  const cards = messagesEl.querySelectorAll('.msg');
  cards.forEach((card) => {
    if (!term) {
      card.classList.remove('highlight');
      card.style.display = '';
      return;
    }
    if (normalize(card.textContent).includes(term)) {
      card.classList.add('highlight');
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}

/* ---------- Auto-Resize Textarea ---------- */
function autoResize() {
  chatInput.style.height = 'auto';
  chatInput.style.height = Math.min(chatInput.scrollHeight, 140) + 'px';
}

/* ---------- Events ---------- */
chatForm.addEventListener('submit', (e) => {
  e.preventDefault();

  // Falls Bild ausgewählt, nicht sofort senden, sondern zuerst uploaden
  if (pendingImage) {
    sendMessage();
    return;
  }
  sendMessage();
});

chatInput.addEventListener('input', autoResize);
chatInput.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    chatForm.dispatchEvent(new Event('submit'));
  }
});

fileInput.addEventListener('change', () => {
  if (fileInput.files && fileInput.files[0]) {
    setPendingImage(fileInput.files[0]);
  }
});
removeImgBtn.addEventListener('click', clearPendingImage);

searchInput.addEventListener('input', () => applySearch(searchInput.value));

$('#btnQuests').addEventListener('click', () => {
  renderQuests();
  questModal.hidden = false;
});
$('#btnCloseModal').addEventListener('click', () => { questModal.hidden = true; });
$('#btnCloseModal2').addEventListener('click', () => { questModal.hidden = true; });
questModal.addEventListener('click', (e) => {
  if (e.target === questModal) questModal.hidden = true;
});
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') questModal.hidden = true;
});

$('#btnClearHistory').addEventListener('click', () => {
  if (confirm('Möchtest du den Chatverlauf wirklich zurücksetzen?')) {
    history = [];
    pendingTan = null;
    saveJSON(STORAGE_KEY_HISTORY, history);
    ensureWelcome();
    renderAll();
    showToast('Verlauf zurückgesetzt.');
  }
});

/* ---------- Init ---------- */
function initLogoFallback() {
  // Falls kein Logo vorhanden, zeige einen Platzhalter (Inline-SVG als Data-URI)
  const logoImg = $('#logoImg');
  logoImg.onerror = () => {
    const svg = encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" rx="12" fill="#0ea5e9"/><text x="32" y="42" font-size="30" font-family="Arial" font-weight="bold" fill="#fff" text-anchor="middle">B</text></svg>');
    logoImg.src = 'data:image/svg+xml;charset=utf-8,' + svg;
  };
  const welcomeLogo = $('#welcomeLogo');
  if (welcomeLogo) {
    welcomeLogo.onerror = () => {
      const svg = encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64" rx="12" fill="#0ea5e9"/><text x="32" y="42" font-size="30" font-family="Arial" font-weight="bold" fill="#fff" text-anchor="middle">B</text></svg>');
      welcomeLogo.src = 'data:image/svg+xml;charset=utf-8,' + svg;
    };
  }
}

async function loadQuestsFromServer() {
  try {
    const resp = await fetch('api/quests.php');
    const data = await resp.json();
    if (resp.ok && Array.isArray(data.quests)) {
      QUESTS = data.quests;
    }
  } catch (e) {
    console.error('Quests konnten nicht geladen werden.', e);
  }
  renderQuests();
}

(async function init() {
  initLogoFallback();
  await loadQuestsFromServer();
  ensureWelcome();
  renderAll();
  autoResize();
})();
