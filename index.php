<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AskHOA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- pdf.js CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>
<body>
    <header>
        <?php $version = trim(@file_get_contents(__DIR__ . '/version.txt') ?: '1.0.0'); ?>
        <div class="logo" title="v<?= htmlspecialchars($version) ?>" style="cursor:default;">
            <div class="logo-icon">H</div>
            <div class="logo-text">Ask<span>HOA</span></div>
            <span style="font-size:0.65rem; color:#8b949e; margin-left:0.4rem; align-self:flex-end; padding-bottom:2px;">v<?= htmlspecialchars($version) ?></span>
        </div>
    </header>
    <main>
        <div class="panel-left">
            <div class="panel-card">
                <h3>📄 Your Documents</h3>
                <div class="drop-zone">
                    <input type="file" id="fileInput" accept=".pdf,.txt" onchange="updateLabel()">
                    <p id="label"><strong>Drop PDF here</strong><br>or click to browse</p>
                </div>
                <button class="btn-process" onclick="upload()">Process Document</button>
                <div id="status" style="margin-top:10px; font-size:0.85rem; display:none; color: #4caf50;">● Document ready</div>
            </div>

            <div class="panel-card">
                <h3>💡 Example Questions</h3>
                <ul class="example-list">
                    <li onclick="quickAsk('Who is the Declarant?')">→ Who is the Declarant?</li>
                    <li onclick="quickAsk('What are the house paint rules?')">→ What are the house paint rules?</li>
                    <li onclick="quickAsk('What is the max annual assessment?')">→ What is the max annual assessment?</li>
                </ul>
            </div>
        </div>

        <div class="panel-right">
            <div id="chat-log"></div>
            <div class="chat-input-area">
                <textarea id="questionInput" placeholder="Ask about your bylaws..." onkeydown="handleKey(event)"></textarea>
                <button class="btn-ask" id="sendBtn" onclick="ask()">Send</button>
            </div>
        </div>
    </main>

<script>
    // Point pdf.js worker at CDN
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    let isReady = false;
    let docId = null;

    function updateLabel() {
        const f = document.getElementById('fileInput').files[0];
        if (f) document.getElementById('label').innerHTML = `📎 ${f.name}`;
    }

    async function extractTextFromPDF(file) {
        const arrayBuffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        let fullText = '';
        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const content = await page.getTextContent();
            const pageText = content.items.map(item => item.str).join(' ');
            fullText += pageText + '\n';
        }
        return fullText;
    }

    async function upload() {
        const f = document.getElementById('fileInput').files[0];
        if (!f) return;

        const btn = document.querySelector('.btn-process');
        btn.innerText = "Extracting text...";
        btn.disabled = true;

        try {
            let text = '';

            if (f.name.toLowerCase().endsWith('.txt')) {
                text = await f.text();
            } else {
                // PDF: extract in browser using pdf.js
                appendBot("⏳ Reading PDF in browser...");
                text = await extractTextFromPDF(f);
            }

            if (!text || text.trim().length < 100) {
                appendBot("❌ Could not extract text from this PDF. Try a text-based PDF.");
                btn.innerText = "Process Document";
                btn.disabled = false;
                return;
            }

            btn.innerText = "Sending to server...";

            // Send raw text to PHP
            const res = await fetch('askhoa_api.php?action=upload', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: text }),
                credentials: 'same-origin'
            }).then(r => r.json());

            if (res.docId) {
                docId = res.docId;
                isReady = true;
                document.getElementById('status').style.display = 'block';
            }

            btn.innerText = "Process Document";
            btn.disabled = false;
            appendBot("✅ " + (res.message || "Upload complete"));

        } catch (e) {
            appendBot("❌ Upload failed: " + e.message);
            btn.innerText = "Process Document";
            btn.disabled = false;
        }
    }

    function quickAsk(q) {
        document.getElementById('questionInput').value = q;
        ask();
    }

    async function ask() {
        const input = document.getElementById('questionInput');
        const btn = document.getElementById('sendBtn');
        const q = input.value.trim();

        if (!q || !isReady) {
            if (!isReady) alert("Please process a document first.");
            return;
        }

        appendUser(q);
        input.value = '';
        btn.disabled = true;

        try {
            const res = await fetch(`askhoa_api.php?action=ask&docId=${docId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ question: q }),
                credentials: 'same-origin'
            }).then(r => r.json());

            appendBot(
                res.answer || "⚠️ No response from server.",
                res.citation || ''
            );

        } catch (e) {
            appendBot("Sorry, I encountered an error processing that question.");
        }

        btn.disabled = false;
    }

    function appendUser(t) {
        document.getElementById('chat-log').innerHTML += `<div class="msg user"><div class="msg-bubble">${t}</div></div>`;
        scrollToBottom();
    }

    function appendBot(t, c = '') {
        const cite = c ? `<br><small style="color:var(--accent); font-weight:bold; font-size:0.8rem;">📌 ${c}</small>` : '';
        document.getElementById('chat-log').innerHTML += `<div class="msg bot"><div class="msg-bubble">${t}${cite}</div></div>`;
        scrollToBottom();
    }

    function scrollToBottom() {
        const log = document.getElementById('chat-log');
        log.scrollTop = log.scrollHeight;
    }

    function handleKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            ask();
        }
    }
</script>
</body>
</html>
