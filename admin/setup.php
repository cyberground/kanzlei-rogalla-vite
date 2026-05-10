<?php
/**
 * Kanzlei Rogalla Admin - Setup-Helfer
 *
 * Nur erreichbar wenn SETUP_TOKEN Umgebungsvariable gesetzt ist.
 * Nach der Einrichtung: SETUP_TOKEN entfernen oder diese Datei loeschen.
 *
 * Aufruf: https://example.com/admin/setup.php?token=IHR_SETUP_TOKEN
 */

// config.php einbinden (setzt SETUP_TOKEN Konstante)
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    die('<h2>config.php nicht gefunden.</h2>');
}

// Direktaufruf-Schutz in config.php umgehen
define('_SETUP_ACCESS', true);
// config.php kann nicht direkt required werden, da sie Direktaufruf blockt
// Daher: SETUP_TOKEN direkt aus Umgebung lesen
$setupToken = getenv('SETUP_TOKEN');

if (empty($setupToken)) {
    http_response_code(403);
    die(<<<HTML
    <!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>403</title>
    <style>body{font-family:sans-serif;padding:3rem;color:#6b7280;}h2{color:#dc2626}</style>
    </head><body>
    <h2>Zugriff verweigert</h2>
    <p>SETUP_TOKEN Umgebungsvariable ist nicht gesetzt.</p>
    <p>Setze zuerst die Variable und rufe die Seite erneut auf:</p>
    <pre style="background:#f3f4f6;padding:1rem;border-radius:6px;font-size:0.875rem">export SETUP_TOKEN=dein-geheimes-token</pre>
    <p>Dann: <code>?token=dein-geheimes-token</code> anhaengen.</p>
    </body></html>
    HTML);
}

$token = $_GET['token'] ?? '';
if (!hash_equals($setupToken, $token)) {
    http_response_code(403);
    die(<<<HTML
    <!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>403</title>
    <style>body{font-family:sans-serif;padding:3rem;color:#6b7280;}h2{color:#dc2626}</style>
    </head><body>
    <h2>Falsches Token</h2>
    <p>Das angegebene Token stimmt nicht mit SETUP_TOKEN ueberein.</p>
    </body></html>
    HTML);
}

// Hash generieren
$hash    = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if (strlen($pw) < 8) {
        $message = 'Passwort muss mindestens 8 Zeichen haben.';
    } else {
        $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup – Kanzlei Rogalla</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary:   #023a51;
            --secondary: #9c8466;
            --light:     #f4f1ec;
            --border:    #d1c9bb;
            --radius:    8px;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 3rem 1rem;
        }
        .wrap { width: 100%; max-width: 560px; }
        h1 { font-size: 1.5rem; color: var(--primary); margin-bottom: 0.5rem; }
        .warn {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            margin-bottom: 1.75rem;
        }
        .card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: 0 4px 24px rgba(2,58,81,0.1);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
        }
        h2 { font-size: 1.1rem; color: var(--primary); margin-bottom: 1.25rem; }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            margin-bottom: 1rem;
            outline: none;
        }
        input:focus { border-color: var(--primary); }
        button {
            width: 100%;
            padding: 0.875rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #011f2c; }
        .hash-result {
            margin-top: 1.25rem;
            padding: 1rem;
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: var(--radius);
        }
        .hash-result h3 { color: #15803d; margin-bottom: 0.75rem; font-size: 0.95rem; }
        .hash-code {
            font-family: monospace;
            font-size: 0.8rem;
            word-break: break-all;
            background: #fff;
            padding: 0.75rem;
            border-radius: 5px;
            border: 1px solid #d1fae5;
            margin-bottom: 0.75rem;
            color: #065f46;
        }
        .steps { font-size: 0.85rem; color: #374151; }
        .steps li { margin-bottom: 0.5rem; padding-left: 0.25rem; }
        .steps code {
            background: #f3f4f6;
            padding: 1px 5px;
            border-radius: 3px;
            font-family: monospace;
        }
        .error-msg { color: #dc2626; font-size: 0.875rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>&#9881; Admin Setup</h1>
    <p style="color:#6b7280;margin-bottom:1.5rem">Einmaliges Setup fuer Kanzlei Rogalla Admin</p>

    <div class="warn">
        <strong>Sicherheitshinweis:</strong> Diese Seite nach der Einrichtung
        loeschen oder <code>SETUP_TOKEN</code> Umgebungsvariable entfernen!
    </div>

    <div class="card">
        <h2>Passwort-Hash generieren</h2>
        <?php if ($message): ?>
            <p class="error-msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <label for="password">Neues Passwort</label>
            <input type="password" id="password" name="password" placeholder="Mindestens 8 Zeichen..." autofocus>
            <button type="submit">Hash generieren</button>
        </form>

        <?php if ($hash): ?>
        <div class="hash-result">
            <h3>&#10003; Hash wurde generiert</h3>
            <div class="hash-code" id="hashCode"><?= htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') ?></div>
            <ol class="steps">
                <li>Kopiere den Hash oben</li>
                <li>Oeffne <code>admin/config.php</code></li>
                <li>Ersetze den Wert von <code>ADMIN_PASSWORD_HASH</code> mit dem neuen Hash</li>
                <li>Speichern und mit altem Passwort testen</li>
            </ol>
            <button
                type="button"
                onclick="navigator.clipboard.writeText(document.getElementById('hashCode').textContent).then(()=>{this.textContent='Kopiert!';setTimeout(()=>{this.textContent='In Zwischenablage kopieren'},2000)})"
                style="margin-top:1rem;background:var(--secondary)">
                In Zwischenablage kopieren
            </button>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>GitHub Token pruefen</h2>
        <?php
        $ghToken = getenv('GITHUB_TOKEN');
        if ($ghToken): ?>
            <p style="color:#15803d;font-size:0.9rem">
                &#10003; <strong>GITHUB_TOKEN</strong> ist gesetzt
                (<?= strlen($ghToken) ?> Zeichen).
            </p>
        <?php else: ?>
            <p style="color:#dc2626;font-size:0.9rem">
                &#10007; <strong>GITHUB_TOKEN</strong> ist <strong>nicht</strong> gesetzt.
            </p>
            <ol class="steps" style="margin-top:0.75rem">
                <li>GitHub Personal Access Token erstellen:
                    <a href="https://github.com/settings/tokens/new" target="_blank" rel="noopener">github.com/settings/tokens</a>
                </li>
                <li>Scope: <code>repo</code> oder <code>contents:write</code> (Fine-grained)</li>
                <li>Token als Umgebungsvariable setzen:<br>
                    <code>export GITHUB_TOKEN=ghp_xxxxxxxxxxxx</code>
                </li>
                <li>PHP/Apache neu starten</li>
            </ol>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
