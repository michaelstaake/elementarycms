<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($errorName) ?> - <?= htmlspecialchars($appName) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f0;
            color: #1a1a1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .error-container {
            max-width: 720px;
            width: 100%;
            background: #fff;
            border: 1px solid #e0e0d8;
            border-radius: 8px;
            padding: 2.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .error-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        .error-message {
            color: #555;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .debug-section {
            margin-top: 2rem;
            border-top: 1px solid #e0e0d8;
            padding-top: 1.5rem;
        }
        .debug-section h2 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1a1a1a;
        }
        .debug-trace {
            background: #1a1a1a;
            color: #e8e8e0;
            padding: 1rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
            line-height: 1.5;
        }
        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #333;
            color: #e8e8e0;
            border: 1px solid #555;
            border-radius: 4px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .copy-btn:hover { background: #444; }
        .copy-btn svg { width: 14px; height: 14px; fill: currentColor; }
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #1a1a1a;
            text-decoration: underline;
        }
        .back-link:hover { color: #555; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code"><?= (int) $code ?></div>
        <div class="error-name"><?= htmlspecialchars($errorName) ?></div>
        <p class="error-message"><?= htmlspecialchars($friendlyMessage) ?></p>

        <?php if ($debug): ?>
        <div class="debug-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="margin: 0;">Debug Information</h2>
                <button class="copy-btn" onclick="copyDebugInfo()">
                    <svg viewBox="0 0 24 24"><path d="M16 1H4a2 2 0 0 0-2 2v14h2V3h12V1zm3 4H8a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H8V7h11v14z"/></svg>
                    <span id="copy-text">Copy</span>
                </button>
            </div>
            <div class="debug-trace" id="debug-trace"><?= htmlspecialchars($e->getMessage()) ?>

File: <?= htmlspecialchars($e->getFile()) ?>:<?= $e->getLine() ?>

Stack Trace:
<?= htmlspecialchars($e->getTraceAsString()) ?></div>
        </div>
        <script>
        function copyDebugInfo() {
            const text = document.getElementById('debug-trace').textContent;
            navigator.clipboard.writeText(text).then(() => {
                const btn = document.getElementById('copy-text');
                btn.textContent = 'Copied!';
                setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
            });
        }
        </script>
        <?php endif; ?>

        <a href="/" class="back-link">Return to homepage</a>
    </div>
</body>
</html>
