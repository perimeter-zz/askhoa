<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AskHOA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <header><div class="logo"><div class="logo-icon">H</div><div class="logo-text">Ask<span>HOA</span></div></div></header>
    <main>
        <div class="panel-left">
            <div class="panel-card">
                <h3>📄 Your Documents</h3>
                <div class="drop-zone">
                    <input type="file" id="fileInput" accept=".pdf,.txt" onchange="updateLabel()">
                    <p id="label"><strong>Drop your PDF here</strong><br>or click to browse</p>
                </div>
                <button class="btn-process" onclick="upload()">Process Document</button>
                <div id="status" style="margin-top:10px; font-size:0.8rem; display:none;">● Document ready</div>
            </div>

            <div class="panel-card">
                <h3>💡 Example Questions</h3>
                <ul class="example-list" style="list-style:none; padding:0; font-size:0.85rem;">
                    <li onclick="quickAsk('Can I install a satellite dish?')">→ Can I install a satellite dish?</li>
                    <li onclick="quickAsk('What are the fence height restrictions?')">→ What are the fence height restrictions?</li>
                    <li onclick="quickAsk('When are HOA fees due?')">→ When are HOA fees due?</li>
                    <li onclick="quickAsk('What is the pet policy?')">→ What's the pet policy?</li>
                </ul>
            </div>
        </div>
        <div class="panel-right">
            <div id="chat-log"></div>
            <div class="chat-input-area">
                <textarea id="questionInput" placeholder="Ask anything about your bylaws & CC&Rs..." onkeydown="handleKey(event)"></textarea>
                <button class="btn-ask" onclick="ask()">Send</button>
            </div>
        </div>
    </main>

    <script>
        let ready = false;
        function updateLabel() { 
            const f = document.getElementById('fileInput').files[0];
            if(f) document.getElementById('label').innerHTML = `📎 ${f.name}`; 
        }

        async function upload() {
            const f = document.getElementById('fileInput').files[0];
            if(!f) return;
            const btn = document.querySelector('.btn-process');
            btn.innerText = "Processing...";
            const fd = new FormData(); fd.append('file', f);
            const res = await fetch('askhoa_api.php?action=upload', { method: 'POST', body: fd }).then(r => r.json());
            ready = true;
            document.getElementById('status').style.display = 'block';
            btn.innerText = "Process Document";
            appendBot("✅ " + res.message);
        }

        function quickAsk(q) {
            document.getElementById('questionInput').value = q;
            ask();
        }

        async function ask() {
            const i = document.getElementById('questionInput');
            const q = i.value.trim();
            if(!q || !ready) return;
            appendUser(q); i.value = '';
            const res = await fetch('askhoa_api.php?action=ask', {
                method: 'POST', 
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({question: q})
            }).then(r => r.json());
            appendBot(res.answer, res.citation);
        }

        function appendUser(t) {
            document.getElementById('chat-log').innerHTML += `<div class="msg user"><div class="msg-bubble">${t}</div></div>`;
            scrollToBottom();
        }

        function appendBot(t, c='') {
            const cite = c ? `<br><small style="color:var(--accent); font-size:0.75rem;">📌 ${c}</small>` : '';
            document.getElementById('chat-log').innerHTML += `<div class="msg bot"><div class="msg-bubble">${t}${cite}</div></div>`;
            scrollToBottom();
        }

        function scrollToBottom() {
            const log = document.getElementById('chat-log');
            log.scrollTop = log.scrollHeight;
        }

        function handleKey(e) { if(e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(); } }
    </script>
</body>
</html>