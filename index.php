<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AskHOA – AI Document Assistant</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <div class="logo">
            <div class="logo-icon">H</div>
            <div class="logo-text">Ask<span>HOA</span></div>
        </div>
    </header>

    <main>
        <div class="panel-left">
            <div class="panel-card">
                <h3>📄 Your Documents</h3>
                <div class="drop-zone" onclick="document.getElementById('fileInput').click()">
                    <input type="file" id="fileInput" accept=".pdf,.txt" onchange="updateFileLabel()">
                    <p id="fileLabel">Drop PDF here or click</p>
                </div>
                <button class="btn-process" onclick="uploadFile()">Process Document</button>
            </div>
        </div>

        <div class="panel-right">
            <div id="chat-log"></div>
            <div class="chat-input-area">
                <textarea id="questionInput" placeholder="Ask about your CC&Rs..."></textarea>
                <button class="btn-ask" onclick="ask()">Ask</button>
            </div>
        </div>
    </main>

    <script>
        // Use your existing JS logic here, but update the fetch URLs 
        // to 'askhoa_api.php?action=upload' and 'askhoa_api.php?action=ask'
        function updateFileLabel() {
            const input = document.getElementById('fileInput');
            document.getElementById('fileLabel').innerText = input.files[0].name;
        }
        // ... include your upload/ask functions ...
    </script>
</body>
</html>