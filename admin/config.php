<?php
/**
 * Kanzlei Rogalla Admin - Konfiguration
 *
 * WICHTIG: Diese Datei NICHT direkt im Browser aufrufen.
 * GitHub Token wird aus der Umgebungsvariable GITHUB_TOKEN gelesen.
 *
 * Passwort aendern: admin/setup.php aufrufen (SETUP_TOKEN Env-Variable erforderlich)
 * Standard-Passwort: rogalla2025!
 */

// Direktaufruf verhindern
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Forbidden');
}

// Admin-Passwort (bcrypt Hash, generiert mit PHP password_hash())
//
// ERSTER START: Hash auf dem Server generieren:
//   php -r "echo password_hash('rogalla2025!', PASSWORD_BCRYPT);"
// Oder: admin/setup.php (SETUP_TOKEN Env-Variable setzen, dann Hash eintragen)
//
// Das Leerzeichen als Platzhalter signalisiert: noch nicht eingerichtet.
// login.php erkennt dies und zeigt Setup-Anleitung.
define('ADMIN_PASSWORD_HASH', '');

// Session-Konfiguration
define('SESSION_TIMEOUT', 3600 * 8); // 8 Stunden

// Rate Limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 Minuten in Sekunden

// GitHub API
define('GITHUB_TOKEN', getenv('GITHUB_TOKEN') ?: '');
define('GITHUB_OWNER', 'cyberground');
define('GITHUB_REPO', 'kanzlei-rogalla-vite');
define('GITHUB_BRANCH', 'main');
define('CONTENT_PATH', 'src/content/blog/');

// Setup-Schutz
define('SETUP_TOKEN', getenv('SETUP_TOKEN') ?: '');

// Blog-Kategorien (aus Astro content config.ts)
define('BLOG_CATEGORIES', serialize([
    'Insolvenzrecht',
    'Erbrecht',
    'Forderungsmanagement',
    'Allgemein',
]));
