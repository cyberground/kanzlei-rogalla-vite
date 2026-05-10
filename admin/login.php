<?php
/**
 * Kanzlei Rogalla Admin - Login
 */
require_once __DIR__ . '/auth.php';

admin_session_start();

// Bereits eingeloggt? Weiterleiten
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error      = '';
$setupMode  = empty(ADMIN_PASSWORD_HASH);

if (!$setupMode && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF pruefen
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sicherheitsfehler. Bitte Seite neu laden.';
    } elseif (!check_rate_limit()) {
        $remaining = ceil(get_lockout_remaining() / 60);
        $error = "Zu viele Fehlversuche. Bitte noch {$remaining} Minute(n) warten.";
    } else {
        $password = $_POST['password'] ?? '';
        if (!empty($password) && password_verify($password, ADMIN_PASSWORD_HASH)) {
            // Erfolgreich
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['last_activity']   = time();
            $_SESSION['login_attempts']  = [];
            header('Location: index.php');
            exit;
        } else {
            record_login_attempt();
            $error = 'Falsches Passwort. Bitte erneut versuchen.';
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login – Kanzlei Rogalla</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:   #023a51;
            --secondary: #9c8466;
            --light:     #f4f1ec;
            --text:      #1a1a1a;
            --muted:     #6b7280;
            --border:    #d1c9bb;
            --error-bg:  #fef2f2;
            --error:     #dc2626;
            --radius:    8px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-wrap {
            width: 100%;
            max-width: 420px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo .monogram {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px;
            height: 64px;
            background: var(--primary);
            color: #fff;
            border-radius: 50%;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .login-logo h1 {
            font-size: 1.25rem;
            color: var(--primary);
            font-weight: 700;
        }

        .login-logo p {
            color: var(--muted);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: 0 4px 24px rgba(2, 58, 81, 0.10);
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        input[type="password"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            color: var(--text);
            background: #fff;
            transition: border-color 0.2s;
            outline: none;
        }

        input[type="password"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(2, 58, 81, 0.1);
        }

        .btn-primary {
            display: block;
            width: 100%;
            padding: 0.875rem;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 1.5rem;
        }

        .btn-primary:hover { background: #034a68; }
        .btn-primary:active { transform: scale(0.99); }

        .alert {
            padding: 0.875rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }

        .alert-error {
            background: var(--error-bg);
            color: var(--error);
            border: 1px solid #fca5a5;
        }

        .hint {
            margin-top: 1.25rem;
            padding: 0.875rem 1rem;
            background: var(--light);
            border-radius: var(--radius);
            font-size: 0.8rem;
            color: var(--muted);
            border-left: 3px solid var(--secondary);
        }

        .hint code {
            background: rgba(0,0,0,0.07);
            padding: 1px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo">
        <div class="monogram">R</div>
        <h1>Kanzlei Rogalla</h1>
        <p>Content Management</p>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($setupMode): ?>
            <div class="alert" style="background:#fffbeb;color:#92400e;border:1px solid #fcd34d;margin-bottom:1.25rem">
                <strong>Ersteinrichtung erforderlich!</strong><br>
                Kein Passwort-Hash in <code>admin/config.php</code> gesetzt.<br><br>
                Hash auf dem Server generieren (SSH / PHP-CLI):
                <pre style="margin-top:0.5rem;background:rgba(0,0,0,0.06);padding:0.5rem;border-radius:4px;font-size:0.75rem;overflow-x:auto;font-family:monospace">php -r "echo password_hash('rogalla2025!', PASSWORD_BCRYPT);"</pre>
                Den generierten Hash in <code>admin/config.php</code> bei
                <code>ADMIN_PASSWORD_HASH</code> eintragen.<br><br>
                Alternativ: <code>SETUP_TOKEN=xxx</code> setzen und <a href="setup.php?token=xxx" style="color:var(--primary)">setup.php</a> aufrufen.
            </div>
        <?php else: ?>
        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-group">
                <label for="password">Passwort</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autofocus
                    autocomplete="current-password"
                    placeholder="Admin-Passwort eingeben"
                >
            </div>

            <button type="submit" class="btn-primary">Anmelden</button>
        </form>

        <div class="hint">
            Das Passwort wird in <code>admin/config.php</code> als bcrypt-Hash gespeichert.<br>
            Passwort aendern: <code>admin/setup.php</code> aufrufen (SETUP_TOKEN erforderlich).
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
