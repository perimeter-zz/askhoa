<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>AskHOA – AI Document Assistant</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:        #0d1117;
      --surface:   #161b22;
      --surface2:  #1c2230;
      --border:    #2a3444;
      --accent:    #e8a44a;
      --accent2:   #c47f25;
      --text:      #e6edf3;
      --muted:     #8b949e;
      --user-bg:   #1f3a5f;
      --bot-bg:    #1c2230;
      --radius:    14px;
      --shadow:    0 8px 32px rgba(0,0,0,0.5);
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── NOISE TEXTURE OVERLAY ── */
    body::before {
      content: '';
      position: fixed; inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
      pointer-events: none; z-index: 0; opacity: 0.5;
    }

    /* ── HEADER ── */
    header {
      position: relative; z-index: 10;
      padding: 1.4rem 2rem;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 1px solid var(--border);
      background: rgba(13,17,23,0.85);
      backdrop-filter: blur(12px);
    }

    .logo {
      display: flex; align-items: center; gap: 0.7rem;
    }
    .logo-icon {
      width: 38px; height: 38px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; font-weight: 700; color: #0d1117;
      box-shadow: 0 2px 12px rgba(232,164,74,0.3);
      flex-shrink: 0;
    }
    .logo-text { font-family: 'Playfair Display', serif; font-size: 1.35rem; }
    .logo-text span { color: var(--accent); }

    .back-btn {
      font-size: 0.82rem; color: var(--muted); text-decoration: none;
      border: 1px solid var(--border); padding: 0.4rem 0.9rem;
      border-radius: 8px; transition: all 0.2s;
    }
    .back-btn:hover { color: var(--text); border-color: var(--accent); }

    /* ── MAIN LAYOUT ── */
    main {
      position: relative; z-index: 1;
      flex: 1; display: flex; gap: 0;
      max-width: 1100px; width: 100%;
      margin: 2rem auto; padding: 0 1.5rem;
    }

    /* ── LEFT PANEL ── */
    .panel-left {
      width: 280px; flex-shrink: 0;
      display: flex; flex-direction: column; gap: 1rem;
      margin-right: 1.5rem;
    }

    .panel-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.2rem;
    }

    .panel-card h3 {
      font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.12em;
      color: var(--muted); margin-bottom: 1rem;
    }

    /* Drop Zone */
    .drop-zone {
      border: 2px dashed var(--border);
      border-radius: 10px;
      padding: 1.8rem 1rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.25s;
      position: relative;
    }
    .drop-zone:hover, .drop-zone.drag-over {
      border-color: var(--accent);
      background: rgba(232,164,74,0.05);
    }
    .drop-zone input[type="file"] {
      position: absolute; inset: 0; opacity: 0; cursor: pointer;
    }
    .drop-icon { font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.6; }
    .drop-label { font-size: 0.82rem; color: var(--muted); line-height: 1.5; }
    .drop-label strong { display: block; color: var(--text); font-size: 0.88rem; }

    .file-name {
      margin-top: 0.8rem; font-size: 0.78rem; color: var(--accent);
      display: none; word-break: break-all;
    }

    .btn-process {
      width: 100%; margin-top: 0.9rem;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: #0d1117; border: none; border-radius: 9px;
      padding: 0.65rem; font-family: 'DM Sans', sans-serif;
      font-size: 0.88rem; font-weight: 600; cursor: pointer;
      transition: all 0.2s; box-shadow: 0 4px 14px rgba(232,164,74,0.25);
    }
    .btn-process:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(232,164,74,0.35); }
    .btn-process:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    /* Status */
    .status-pill {
      display: inline-flex; align-items: center; gap: 0.4rem;
      font-size: 0.76rem; padding: 0.3rem 0.7rem;
      border-radius: 20px; margin-top: 0.6rem;
    }
    .status-pill.idle    { background: rgba(139,148,158,0.15); color: var(--muted); }
    .status-pill.ready   { background: rgba(46,160,67,0.15);  color: #3fb950; }
    .status-pill.loading { background: rgba(232,164,74,0.15); color: var(--accent); }
    .status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .status-pill.loading .status-dot { animation: pulse 1s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }

    /* Tips */
    .tips-list { list-style: none; display: flex; flex-direction: column; gap: 0.5rem; }
    .tips-list li {
      font-size: 0.78rem; color: var(--muted); line-height: 1.5;
      padding-left: 1.1rem; position: relative;
    }
    .tips-list li::before { content: '→'; position: absolute; left: 0; color: var(--accent); }

    /* ── RIGHT PANEL (CHAT) ── */
    .panel-right {
      flex: 1; display: flex; flex-direction: column;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      min-height: 540px;
    }

    .chat-header {
      padding: 1rem 1.4rem;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .chat-header-title { font-size: 0.88rem; font-weight: 500; }
    .chat-header-sub { font-size: 0.74rem; color: var(--muted); }

    .btn-clear {
      font-size: 0.74rem; color: var(--muted); background: none;
      border: 1px solid var(--border); border-radius: 6px;
      padding: 0.28rem 0.6rem; cursor: pointer; transition: all 0.2s;
    }
    .btn-clear:hover { color: var(--text); border-color: var(--muted); }

    /* Chat Log */
    #chat-log {
      flex: 1; overflow-y: auto; padding: 1.2rem;
      display: flex; flex-direction: column; gap: 1rem;
      scroll-behavior: smooth;
    }
    #chat-log::-webkit-scrollbar { width: 4px; }
    #chat-log::-webkit-scrollbar-track { background: transparent; }
    #chat-log::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

    /* Empty state */
    .empty-state {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      text-align: center; gap: 0.6rem; opacity: 0.5;
      padding: 2rem;
    }
    .empty-state .empty-icon { font-size: 2.5rem; }
    .empty-state p { font-size: 0.85rem; color: var(--muted); line-height: 1.6; }

    /* Messages */
    .msg { display: flex; gap: 0.7rem; animation: fadeUp 0.3s ease; }
    @keyframes fadeUp { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }

    .msg.user { flex-direction: row-reverse; }

    .msg-avatar {
      width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center; font-size: 0.75rem;
    }
    .msg.bot  .msg-avatar { background: rgba(232,164,74,0.2); color: var(--accent); }
    .msg.user .msg-avatar { background: var(--user-bg); color: #7db7f0; }

    .msg-bubble {
      max-width: 82%; padding: 0.75rem 1rem;
      border-radius: 12px; font-size: 0.875rem; line-height: 1.65;
    }
    .msg.bot  .msg-bubble { background: var(--bot-bg); border: 1px solid var(--border); border-top-left-radius: 4px; }
    .msg.user .msg-bubble { background: var(--user-bg); border-top-right-radius: 4px; }

    .msg-bubble .citation {
      display: inline-block; margin-top: 0.5rem;
      font-size: 0.72rem; color: var(--accent); opacity: 0.8;
      border-top: 1px solid var(--border); padding-top: 0.4rem; width: 100%;
    }

    /* Typing indicator */
    .typing-dots span {
      display: inline-block; width: 5px; height: 5px;
      background: var(--muted); border-radius: 50%; margin: 0 1px;
      animation: bounce 1.2s infinite;
    }
    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-5px)} }

    /* ── CHAT INPUT ── */
    .chat-input-area {
      border-top: 1px solid var(--border);
      padding: 1rem 1.2rem;
      display: flex; gap: 0.7rem; align-items: flex-end;
    }

    #questionInput {
      flex: 1; background: var(--surface2); color: var(--text);
      border: 1px solid var(--border); border-radius: 10px;
      padding: 0.65rem 0.9rem; font-family: 'DM Sans', sans-serif;
      font-size: 0.875rem; resize: none; outline: none;
      transition: border-color 0.2s; min-height: 44px; max-height: 120px;
    }
    #questionInput:focus { border-color: var(--accent); }
    #questionInput::placeholder { color: var(--muted); }

    .btn-ask {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border: none; border-radius: 10px; width: 44px; height: 44px;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      flex-shrink: 0; transition: all 0.2s;
      box-shadow: 0 2px 10px rgba(232,164,74,0.2);
    }
    .btn-ask:hover { transform: scale(1.05); box-shadow: 0 4px 16px rgba(232,164,74,0.35); }
    .btn-ask:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
    .btn-ask svg { fill: none; stroke: #0d1117; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }

    /* ── FOOTER ── */
    footer {
      position: relative; z-index: 1;
      text-align: center; font-size: 0.72rem; color: var(--muted);
      padding: 1rem; border-top: 1px solid var(--border);
    }
    footer a { color: var(--accent); text-decoration: none; }

    /* ── RESPONSIVE ── */
    @media (max-width: 720px) {
      main { flex-direction: column; }
      .panel-left { width: 100%; margin-right: 0; }
      .panel-right { min-height: 420px; }
    }
  </style>
</head>
<body>

<header>
  <div class="logo">
    <div class="logo-icon">H</div>
    <div class="logo-text">Ask<span>HOA</span></div>
  </div>
  <a href="index.php" class="back-btn">← Back</a>
</header>

<main>

  <!-- LEFT: Upload + Status -->
  <div class="panel-left">

    <div class="panel-card">
      <h3>📄 Your Documents</h3>
      <div class="drop-zone" id="dropZone">
        <input type="file" id="fileInput" accept=".pdf,.txt" onchange="handleFileSelect(this)">
        <div class="drop-icon">📋</div>
        <div class="drop-label">
          <strong>Drop your PDF here</strong>
          or click to browse
        </div>
        <div class="file-name" id="fileName"></div>
      </div>
      <button class="btn-process" id="processBtn" onclick="uploadFile()" disabled>Process Document</button>
      <div style="margin-top: 0.7rem;">
        <span class="status-pill idle" id="statusPill">
          <span class="status-dot"></span>
          <span id="statusText">No document loaded</span>
        </span>
      </div>
    </div>

    <div class="panel-card">
      <h3>💡 Example Questions</h3>
      <ul class="tips-list">
        <li>Can I install a satellite dish?</li>
        <li>What are the fence height restrictions?</li>
        <li>When are HOA fees due?</li>
        <li>Can I rent my unit short-term?</li>
        <li>What's the pet policy?</li>
      </ul>
    </div>

  </div>

  <!-- RIGHT: Chat -->
  <div class="panel-right">
    <div class="chat-header">
      <div>
        <div class="chat-header-title">HOA Document Chat</div>
        <div class="chat-header-sub">Ask anything about your bylaws &amp; CC&amp;Rs</div>
      </div>
      <button class="btn-clear" onclick="clearChat()">Clear</button>
    </div>

    <div id="chat-log">
      <div class="empty-state" id="emptyState">
        <div class="empty-icon">🏡</div>
        <p>Upload your HOA documents to get started.<br>I'll answer questions with specific citations.</p>
      </div>
    </div>

    <div class="chat-input-area">
      <textarea id="questionInput" placeholder="Ask about your CC&Rs or bylaws…" rows="1"
        onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
      <button class="btn-ask" id="askBtn" onclick="askQuestion()" disabled title="Ask">
        <svg viewBox="0 0 24 24" width="18" height="18">
          <line x1="22" y1="2" x2="11" y2="13"/>
          <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
      </button>
    </div>
  </div>

</main>

<footer>
  AskHOA by <a href="index.php">Will Botti</a> · AI answers are for reference only. Always verify with your HOA board.
</footer>

<script>
  let documentLoaded = false;

  // ── FILE HANDLING ──
  function handleFileSelect(input) {
    const file = input.files[0];
    if (!file) return;
    document.getElementById('fileName').style.display = 'block';
    document.getElementById('fileName').textContent = '📎 ' + file.name;
    document.getElementById('processBtn').disabled = false;
  }

  // Drag & drop
  const dropZone = document.getElementById('dropZone');
  dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
  dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) {
      document.getElementById('fileInput').files = e.dataTransfer.files;
      handleFileSelect(document.getElementById('fileInput'));
    }
  });

  // ── STATUS PILL ──
  function setStatus(state, text) {
    const pill = document.getElementById('statusPill');
    pill.className = 'status-pill ' + state;
    document.getElementById('statusText').textContent = text;
  }

  // ── UPLOAD ──
  async function uploadFile() {
    const fileInput = document.getElementById('fileInput');
    const file = fileInput.files[0];
    if (!file) return;

    document.getElementById('processBtn').disabled = true;
    setStatus('loading', 'Processing…');

    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await fetch('askhoa_api.php?action=upload', { method: 'POST', body: formData });
      const raw = await response.text();
      //  appendBot('🔍 Raw: ' + raw);          // AI recommend cutting this debug, but keep til we are green
      const result = JSON.parse(raw);
      setStatus('ready', 'Document ready');
      documentLoaded = true;
      document.getElementById('askBtn').disabled = false;
      appendBot('✅ ' + result.message + ' Ask me an HOA question!');
      hideEmpty();
    } catch (err) {
      setStatus('idle', 'Upload failed');
      document.getElementById('processBtn').disabled = false;
      appendBot('⚠️ Upload error: ' + err.message);
      hideEmpty();
    }
  }

  // ── CHAT ──
  function hideEmpty() {
    const e = document.getElementById('emptyState');
    if (e) e.remove();
  }

  function appendBot(text, citation) {
    hideEmpty();
    const log = document.getElementById('chat-log');
    const div = document.createElement('div');
    div.className = 'msg bot';
    div.innerHTML = `
      <div class="msg-avatar">H</div>
      <div class="msg-bubble">
        ${text}
        ${citation ? `<span class="citation">📌 ${citation}</span>` : ''}
      </div>`;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
  }

  function appendUser(text) {
    hideEmpty();
    const log = document.getElementById('chat-log');
    const div = document.createElement('div');
    div.className = 'msg user';
    div.innerHTML = `
      <div class="msg-avatar">You</div>
      <div class="msg-bubble">${text}</div>`;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
  }

  function showTyping() {
    hideEmpty();
    const log = document.getElementById('chat-log');
    const div = document.createElement('div');
    div.className = 'msg bot'; div.id = 'typing-indicator';
    div.innerHTML = `
      <div class="msg-avatar">H</div>
      <div class="msg-bubble">
        <div class="typing-dots"><span></span><span></span><span></span></div>
      </div>`;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
  }

  function removeTyping() {
    const t = document.getElementById('typing-indicator');
    if (t) t.remove();
  }

  async function askQuestion() {
    const input = document.getElementById('questionInput');
    const question = input.value.trim();
    if (!question) return;

    appendUser(question);
    input.value = '';
    input.style.height = 'auto';
    document.getElementById('askBtn').disabled = true;
    showTyping();

    try {
      const response = await fetch('askhoa_api.php?action=ask', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question })
      });
      const result = await response.json();
      removeTyping();
      appendBot(result.answer, result.citation || null);
    } catch (err) {
      removeTyping();
      appendBot('⚠️ Ask error: ' + err.message);
    }

    document.getElementById('askBtn').disabled = false;
    input.focus();
  }

  function clearChat() {
    const log = document.getElementById('chat-log');
    log.innerHTML = `<div class="empty-state" id="emptyState">
      <div class="empty-icon">🏡</div>
      <p>Upload your HOA documents to get started.<br>I'll answer questions with specific citations.</p>
    </div>`;
  }

  function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); askQuestion(); }
  }

  function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    document.getElementById('askBtn').disabled = !el.value.trim();
  }
</script>
</body>
</html>
