<?php
/**
 * Kanzlei Rogalla Admin - Dashboard
 */
require_once __DIR__ . '/auth.php';
require_login();

// Blog-Artikel laden
$articles = [];
$apiError = '';
$githubConfigured = !empty(GITHUB_TOKEN);

if ($githubConfigured) {
    $files = github_list_articles();
    if (isset($files['error'])) {
        $apiError = $files['error'];
    } else {
        foreach ($files as $file) {
            $slug    = str_replace('.md', '', $file['name']);
            $fileData = github_get_file(CONTENT_PATH . $file['name']);
            $meta = ['title' => $slug, 'pubDate' => '', 'category' => '', 'slug' => $slug];
            if (!isset($fileData['error']) && !empty($fileData['content'])) {
                $decoded = base64_decode($fileData['content']);
                $parsed  = parse_frontmatter($decoded);
                $fm      = $parsed['frontmatter'];
                $meta['title']    = $fm['title'] ?? $slug;
                $meta['pubDate']  = $fm['pubDate'] ?? '';
                $meta['category'] = $fm['category'] ?? '';
                $meta['tags']     = $fm['tags'] ?? [];
            }
            $articles[] = $meta;
        }
        // Nach Datum sortieren (neueste zuerst)
        usort($articles, fn($a, $b) => strcmp($b['pubDate'], $a['pubDate']));
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Kanzlei Rogalla Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:    #023a51;
            --primary-dk: #011f2c;
            --secondary:  #9c8466;
            --secondary-lt: #b5a08a;
            --light:      #f4f1ec;
            --white:      #ffffff;
            --text:       #1a1a1a;
            --muted:      #6b7280;
            --border:     #d1c9bb;
            --success-bg: #f0fdf4;
            --success:    #16a34a;
            --error-bg:   #fef2f2;
            --error:      #dc2626;
            --warn-bg:    #fffbeb;
            --warn:       #d97706;
            --radius:     8px;
            --shadow:     0 2px 12px rgba(2,58,81,0.09);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, sans-serif;
            background: var(--light);
            color: var(--text);
            min-height: 100vh;
        }

        /* ---- Header ---- */
        .topbar {
            background: var(--primary);
            color: #fff;
            padding: 0 1.5rem;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .topbar-brand .monogram {
            width: 36px;
            height: 36px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-actions a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.2s;
        }

        .topbar-actions a:hover { color: #fff; }

        .topbar-actions .btn-logout {
            background: rgba(255,255,255,0.15);
            padding: 0.375rem 0.875rem;
            border-radius: var(--radius);
            color: #fff;
        }

        .topbar-actions .btn-logout:hover {
            background: rgba(255,255,255,0.25);
        }

        /* ---- Main ---- */
        .main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .page-subtitle {
            color: var(--muted);
            font-size: 0.875rem;
            margin-top: 0.2rem;
        }

        /* ---- Buttons ---- */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.625rem 1.125rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: background 0.2s, transform 0.1s;
        }

        .btn:active { transform: scale(0.98); }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover { background: var(--primary-dk); }

        .btn-secondary {
            background: var(--secondary);
            color: #fff;
        }

        .btn-secondary:hover { background: #8a6e52; }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1.5px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: #fff;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
        }

        /* ---- Alert ---- */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-error  { background: var(--error-bg);   color: var(--error);   border: 1px solid #fca5a5; }
        .alert-warn   { background: var(--warn-bg);    color: var(--warn);    border: 1px solid #fcd34d; }
        .alert-success{ background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }

        /* ---- Stats bar ---- */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--secondary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 0.4rem;
        }

        /* ---- Card ---- */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* ---- Article Table ---- */
        .article-table {
            width: 100%;
            border-collapse: collapse;
        }

        .article-table th {
            text-align: left;
            padding: 0.875rem 1.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--light);
            border-bottom: 1px solid var(--border);
        }

        .article-table td {
            padding: 1rem 1.5rem;
            font-size: 0.9rem;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .article-table tr:last-child td {
            border-bottom: none;
        }

        .article-table tr:hover td {
            background: #f9f7f4;
        }

        .article-title {
            font-weight: 600;
            color: var(--text);
        }

        .article-slug {
            font-size: 0.75rem;
            color: var(--muted);
            font-family: monospace;
            margin-top: 0.15rem;
        }

        /* ---- Badges ---- */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 20px;
            font-size: 0.725rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-insolvenz  { background: #dbeafe; color: #1d4ed8; }
        .badge-erb        { background: #dcfce7; color: #15803d; }
        .badge-forderung  { background: #fef9c3; color: #a16207; }
        .badge-allgemein  { background: #f3e8ff; color: #7e22ce; }

        /* ---- No token warning ---- */
        .no-token {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--muted);
        }

        .no-token .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .no-token h3 {
            color: var(--warn);
            margin-bottom: 0.75rem;
        }

        .no-token code {
            background: #f3f4f6;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.875rem;
        }

        /* ---- Footer ---- */
        .admin-footer {
            text-align: center;
            padding: 2rem;
            color: var(--muted);
            font-size: 0.8rem;
        }

        /* ---- Responsive ---- */
        @media (max-width: 700px) {
            .article-table th:nth-child(3),
            .article-table td:nth-child(3) { display: none; }

            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- Topbar -->
<header class="topbar">
    <div class="topbar-brand">
        <div class="monogram">R</div>
        <span>Kanzlei Rogalla &mdash; Admin</span>
    </div>
    <nav class="topbar-actions">
        <a href="https://<?= e(GITHUB_OWNER) ?>.github.io/<?= e(GITHUB_REPO) ?>/" target="_blank" rel="noopener">Website &rarr;</a>
        <a href="logout.php" class="btn-logout">Abmelden</a>
    </nav>
</header>

<main class="main">

    <!-- Page header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Blog-Artikel</h1>
            <p class="page-subtitle">Alle Artikel bearbeiten und veroffentlichen</p>
        </div>
        <a href="edit-article.php" class="btn btn-primary">
            <span>+</span> Neuen Artikel erstellen
        </a>
    </div>

    <?php if (!$githubConfigured): ?>
        <div class="alert alert-warn">
            <strong>GitHub Token fehlt!</strong>
            Bitte die Umgebungsvariable <code>GITHUB_TOKEN</code> setzen.
            Ohne Token koennen keine Artikel geladen oder gespeichert werden.
        </div>
    <?php endif; ?>

    <?php if ($apiError): ?>
        <div class="alert alert-error">
            <div>
                <strong>GitHub API Fehler:</strong><br>
                <?= e($apiError) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <?php if (!empty($articles)): ?>
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-number"><?= count($articles) ?></div>
            <div class="stat-label">Artikel gesamt</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count(array_filter($articles, fn($a) => $a['category'] === 'Insolvenzrecht')) ?></div>
            <div class="stat-label">Insolvenzrecht</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count(array_filter($articles, fn($a) => $a['category'] === 'Erbrecht')) ?></div>
            <div class="stat-label">Erbrecht</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count(array_filter($articles, fn($a) => $a['category'] === 'Forderungsmanagement')) ?></div>
            <div class="stat-label">Forderungsmanagement</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Article list -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Alle Blog-Artikel</h2>
            <span style="font-size:0.8rem;color:var(--muted);">
                <?= count($articles) ?> Artikel &bull;
                Aenderungen werden direkt auf GitHub committed
            </span>
        </div>

        <?php if (!$githubConfigured || $apiError): ?>
            <div class="no-token">
                <span class="icon">&#128274;</span>
                <h3>Keine GitHub-Verbindung</h3>
                <p>
                    GitHub Token setzen:<br>
                    <code>export GITHUB_TOKEN=ghp_xxxxxxxxxxxx</code><br><br>
                    Danach Server neu starten.
                </p>
            </div>
        <?php elseif (empty($articles)): ?>
            <div class="no-token">
                <span class="icon">&#128196;</span>
                <h3>Noch keine Artikel</h3>
                <p>Erstelle den ersten Artikel mit dem Button oben.</p>
            </div>
        <?php else: ?>
            <table class="article-table">
                <thead>
                    <tr>
                        <th>Titel / Slug</th>
                        <th>Kategorie</th>
                        <th>Datum</th>
                        <th style="text-align:right">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($articles as $art): ?>
                    <tr>
                        <td>
                            <div class="article-title"><?= e($art['title']) ?></div>
                            <div class="article-slug"><?= e($art['slug']) ?></div>
                        </td>
                        <td>
                            <?php
                            $badgeClass = match($art['category']) {
                                'Insolvenzrecht'     => 'badge-insolvenz',
                                'Erbrecht'           => 'badge-erb',
                                'Forderungsmanagement' => 'badge-forderung',
                                default              => 'badge-allgemein',
                            };
                            ?>
                            <?php if ($art['category']): ?>
                                <span class="badge <?= $badgeClass ?>"><?= e($art['category']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:0.8rem">–</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted);font-size:0.875rem">
                            <?= $art['pubDate'] ? date('d.m.Y', strtotime($art['pubDate'])) : '–' ?>
                        </td>
                        <td style="text-align:right">
                            <a href="edit-article.php?slug=<?= urlencode($art['slug']) ?>" class="btn btn-outline btn-sm">
                                Bearbeiten
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- GitHub Status -->
    <div style="margin-top:1.5rem;padding:1rem 1.25rem;background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);font-size:0.85rem;color:var(--muted);display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
        <span style="color:<?= $githubConfigured ? 'var(--success)' : 'var(--warn)' ?>;font-size:1.1rem">
            <?= $githubConfigured ? '&#9679;' : '&#9675;' ?>
        </span>
        <span>
            GitHub:
            <strong style="color:var(--primary)"><?= e(GITHUB_OWNER) ?>/<?= e(GITHUB_REPO) ?></strong>
            &bull; Branch: <strong><?= e(GITHUB_BRANCH) ?></strong>
            &bull; Inhaltspfad: <code style="font-family:monospace;background:#f3f4f6;padding:1px 4px;border-radius:3px"><?= e(CONTENT_PATH) ?></code>
        </span>
        <span style="margin-left:auto">
            Token: <?= $githubConfigured ? '<span style="color:var(--success)">&#10003; Aktiv</span>' : '<span style="color:var(--warn)">&#10007; Fehlt</span>' ?>
        </span>
    </div>

</main>

<footer class="admin-footer">
    Kanzlei Rogalla Admin &bull; Gespeicherte Aenderungen werden automatisch deployt
</footer>

</body>
</html>
