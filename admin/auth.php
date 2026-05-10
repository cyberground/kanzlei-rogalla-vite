<?php
/**
 * Kanzlei Rogalla Admin - Auth Helper
 * Gemeinsame Auth-Funktionen fuer alle Admin-Seiten
 */

if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config.php';

// Session sicher starten
function admin_session_start(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// Prueft ob Admin eingeloggt ist
function is_logged_in(): bool {
    admin_session_start();
    if (empty($_SESSION['admin_logged_in'])) {
        return false;
    }
    // Session-Timeout pruefen
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// Login erforderlich - leitet weiter wenn nicht eingeloggt
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// CSRF Token generieren oder aus Session lesen
function csrf_token(): string {
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token validieren
function verify_csrf(string $token): bool {
    admin_session_start();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Rate Limiting fuer Login
function check_rate_limit(): bool {
    admin_session_start();
    $now = time();

    if (empty($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }

    // Alte Versuche entfernen (aelter als Lockout-Zeit)
    $_SESSION['login_attempts'] = array_filter(
        $_SESSION['login_attempts'],
        fn($t) => ($now - $t) < LOGIN_LOCKOUT_TIME
    );

    return count($_SESSION['login_attempts']) < MAX_LOGIN_ATTEMPTS;
}

function record_login_attempt(): void {
    admin_session_start();
    if (empty($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $_SESSION['login_attempts'][] = time();
}

function get_lockout_remaining(): int {
    admin_session_start();
    if (empty($_SESSION['login_attempts'])) {
        return 0;
    }
    $oldest = min($_SESSION['login_attempts']);
    $remaining = LOGIN_LOCKOUT_TIME - (time() - $oldest);
    return max(0, (int)$remaining);
}

// GitHub API Request
function github_api(string $method, string $endpoint, array $data = []): array {
    $token = GITHUB_TOKEN;
    if (empty($token)) {
        return ['error' => 'GitHub Token nicht konfiguriert. Bitte GITHUB_TOKEN Umgebungsvariable setzen.'];
    }

    $url = 'https://api.github.com' . $endpoint;

    $headers = [
        'Authorization: token ' . $token,
        'Accept: application/vnd.github.v3+json',
        'User-Agent: KanzleiRogalla-Admin/1.0',
        'Content-Type: application/json',
        'X-GitHub-Api-Version: 2022-11-28',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['error' => 'cURL Fehler: ' . $error];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Ungueltige API-Antwort (HTTP ' . $httpCode . ')'];
    }

    if ($httpCode >= 400) {
        $msg = $decoded['message'] ?? 'Unbekannter API-Fehler';
        return ['error' => 'GitHub API Fehler ' . $httpCode . ': ' . $msg];
    }

    return $decoded;
}

// Datei von GitHub laden
function github_get_file(string $path): array {
    $endpoint = '/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/contents/' . $path . '?ref=' . GITHUB_BRANCH;
    return github_api('GET', $endpoint);
}

// Datei auf GitHub speichern (Erstellen oder Aktualisieren)
function github_put_file(string $path, string $content, string $message, string $sha = ''): array {
    $endpoint = '/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/contents/' . $path;
    $data = [
        'message' => $message,
        'content' => base64_encode($content),
        'branch'  => GITHUB_BRANCH,
    ];
    if (!empty($sha)) {
        $data['sha'] = $sha;
    }
    return github_api('PUT', $endpoint, $data);
}

// Alle Blog-Artikel von GitHub laden
function github_list_articles(): array {
    $endpoint = '/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/contents/' . CONTENT_PATH . '?ref=' . GITHUB_BRANCH;
    $result = github_api('GET', $endpoint);
    if (isset($result['error'])) {
        return $result;
    }
    // Nur .md Dateien
    return array_filter($result, fn($f) => str_ends_with($f['name'] ?? '', '.md'));
}

// Frontmatter aus Markdown parsen
function parse_frontmatter(string $markdown): array {
    $frontmatter = [];
    $content = $markdown;

    if (str_starts_with(ltrim($markdown), '---')) {
        $pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)$/s';
        if (preg_match($pattern, ltrim($markdown), $matches)) {
            $yamlBlock = $matches[1];
            $content   = $matches[2];

            // Einfacher YAML-Parser fuer die bekannten Felder
            foreach (explode("\n", $yamlBlock) as $line) {
                if (preg_match('/^(\w+):\s*(.+)$/', $line, $m)) {
                    $key = trim($m[1]);
                    $val = trim($m[2], ' "\'');
                    $frontmatter[$key] = $val;
                }
                // Tags als Array: tags: ["Tag1", "Tag2"]
                if (preg_match('/^tags:\s*\[(.+)\]/', $line, $m)) {
                    $tagsRaw = $m[1];
                    $tags = array_map(fn($t) => trim($t, ' "\''), explode(',', $tagsRaw));
                    $frontmatter['tags'] = $tags;
                }
            }
        }
    }

    return ['frontmatter' => $frontmatter, 'content' => trim($content)];
}

// Markdown mit Frontmatter zusammenbauen
function build_markdown(array $frontmatter, string $content): string {
    $yaml = "---\n";
    $yaml .= 'title: "' . addslashes($frontmatter['title'] ?? '') . '"' . "\n";
    $yaml .= 'description: "' . addslashes($frontmatter['description'] ?? '') . '"' . "\n";
    $yaml .= 'pubDate: ' . ($frontmatter['pubDate'] ?? date('Y-m-d')) . "\n";
    $yaml .= 'category: "' . ($frontmatter['category'] ?? 'Allgemein') . '"' . "\n";
    $yaml .= 'author: "' . addslashes($frontmatter['author'] ?? 'Christina Rogalla') . '"' . "\n";

    // Tags
    $tags = $frontmatter['tags'] ?? [];
    if (is_string($tags)) {
        $tags = array_map('trim', explode(',', $tags));
    }
    $tags = array_filter($tags);
    $tagsFormatted = implode(', ', array_map(fn($t) => '"' . addslashes(trim($t)) . '"', $tags));
    $yaml .= 'tags: [' . $tagsFormatted . ']' . "\n";

    $yaml .= "---\n\n";
    return $yaml . $content;
}

// HTML escapen
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
