<?php
/**
 * Kanzlei Rogalla Admin - Artikel bearbeiten / erstellen
 */
require_once __DIR__ . '/auth.php';
require_login();

$slug       = trim($_GET['slug'] ?? '');
$isNew      = empty($slug);
$filePath   = CONTENT_PATH . ($isNew ? '' : $slug . '.md');
$sha        = '';
$message    = '';
$messageType = '';

// Felder
$fm = [
    'title'       => '',
    'description' => '',
    'pubDate'     => date('Y-m-d'),
    'category'    => 'Insolvenzrecht',
    'author'      => 'Christina Rogalla',
    'tags'        => '',
];
$mdContent = '';

// --- GET: Artikel laden ---
if (!$isNew && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $fileData = github_get_file($filePath);
    if (isset($fileData['error'])) {
        $message     = 'Fehler beim Laden: ' . $fileData['error'];
        $messageType = 'error';
    } else {
        $sha     = $fileData['sha'] ?? '';
        $raw     = base64_decode(str_replace(["\n", "\r"], '', $fileData['content'] ?? ''));
        $parsed  = parse_frontmatter($raw);
        $pfm     = $parsed['frontmatter'];
        $fm['title']       = $pfm['title'] ?? '';
        $fm['description'] = $pfm['description'] ?? '';
        $fm['pubDate']     = $pfm['pubDate'] ?? date('Y-m-d');
        $fm['category']    = $pfm['category'] ?? 'Insolvenzrecht';
        $fm['author']      = $pfm['author'] ?? 'Christina Rogalla';
        $tags = $pfm['tags'] ?? [];
        if (is_array($tags)) {
            $fm['tags'] = implode(', ', $tags);
        } else {
            $fm['tags'] = (string)$tags;
        }
        $mdContent = $parsed['content'];
    }
}

// --- POST: Artikel speichern ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF pruefen
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message     = 'Sicherheitsfehler. Bitte Seite neu laden.';
        $messageType = 'error';
    } else {
        // Felder aus POST
        $fm['title']       = trim($_POST['title'] ?? '');
        $fm['description'] = trim($_POST['description'] ?? '');
        $fm['pubDate']     = trim($_POST['pubDate'] ?? date('Y-m-d'));
        $fm['category']    = trim($_POST['category'] ?? 'Insolvenzrecht');
        $fm['author']      = trim($_POST['author'] ?? 'Christina Rogalla');
        $fm['tags']        = trim($_POST['tags'] ?? '');
        $mdContent         = trim($_POST['content'] ?? '');
        $sha               = trim($_POST['sha'] ?? '');
        $newSlug           = trim($_POST['slug'] ?? '');

        // Validierung
        $errors = [];
        if (empty($fm['title']))    $errors[] = 'Titel ist erforderlich.';
        if (empty($fm['pubDate']))  $errors[] = 'Datum ist erforderlich.';
        if (empty($mdContent))      $errors[] = 'Inhalt ist erforderlich.';
        if (empty($newSlug))        $errors[] = 'URL-Slug ist erforderlich.';

        // Slug bereinigen
        $newSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower($newSlug));
        if (empty($newSlug)) $errors[] = 'Ungültiger URL-Slug.';

        if (empty($errors)) {
            // Tags als Array
            $tagsArr = array_map('trim', explode(',', $fm['tags']));
            $tagsArr = array_filter($tagsArr);

            $markdown = build_markdown(
                [
                    'title'       => $fm['title'],
                    'description' => $fm['description'],
                    'pubDate'     => $fm['pubDate'],
                    'category'    => $fm['category'],
                    'author'      => $fm['author'],
                    'tags'        => $tagsArr,
                ],
                $mdContent
            );

            $targetPath    = CONTENT_PATH . $newSlug . '.md';
            $commitMessage = ($isNew || empty($sha))
                ? 'content: add blog article ' . $newSlug
                : 'content: update blog article ' . $newSlug;

            // Wenn Slug geaendert wurde und alter Slug existiert: alten loeschen
            if (!$isNew && $newSlug !== $slug) {
                // Neue Datei erstellen
                $result = github_put_file($targetPath, $markdown, $commitMessage);
                // Alte loeschen
                if (!isset($result['error'])) {
                    $oldFileData = github_get_file(CONTENT_PATH . $slug . '.md');
                    if (!isset($oldFileData['error']) && !empty($oldFileData['sha'])) {
                        github_api('DELETE', '/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/contents/' . CONTENT_PATH . $slug . '.md', [
                            'message' => 'content: remove old slug ' . $slug,
                            'sha'     => $oldFileData['sha'],
                            'branch'  => GITHUB_BRANCH,
                        ]);
                    }
                    $slug = $newSlug;
                    $sha  = $result['content']['sha'] ?? '';
                    $message     = 'Artikel gespeichert! Deployment laeuft...';
                    $messageType = 'success';
                } else {
                    $message     = 'Fehler beim Speichern: ' . $result['error'];
                    $messageType = 'error';
                }
            } else {
                // Normale Aktualisierung / Erstellung
                $result = github_put_file($targetPath, $markdown, $commitMessage, $sha);
                if (isset($result['error'])) {
                    $message     = 'Fehler beim Speichern: ' . $result['error'];
                    $messageType = 'error';
                } else {
                    $sha         = $result['content']['sha'] ?? $sha;
                    $slug        = $newSlug;
                    $isNew       = false;
                    $message     = 'Artikel gespeichert! Deployment laeuft...';
                    $messageType = 'success';
                }
            }
        } else {
            $message     = implode(' ', $errors);
            $messageType = 'error';
        }
    }
}

$categories = unserialize(BLOG_CATEGORIES);
$csrf       = csrf_token();
$pageTitle  = $isNew ? 'Neuen Artikel erstellen' : 'Artikel bearbeiten';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> – Kanzlei Rogalla Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary:    #023a51;
            --primary-dk: #011f2c;
            --secondary:  #9c8466;
            --light:      #f4f1ec;
            --white:      #ffffff;
            --text:       #1a1a1a;
            --muted:      #6b7280;
            --border:     #d1c9bb;
            --focus:      rgba(2,58,81,0.15);
            --success-bg: #f0fdf4;
            --success:    #16a34a;
            --error-bg:   #fef2f2;
            --error:      #dc2626;
            --radius:     8px;
            --shadow:     0 2px 12px rgba(2,58,81,0.09);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, sans-serif;
            background: var(--light);
            color: var(--text);
            min-height: 100vh;
        }

        /* ---- Topbar ---- */
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
            font-size: 1rem;
            text-decoration: none;
            color: #fff;
        }

        .topbar-brand .monogram {
            width: 34px;
            height: 34px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            font-weight: 800;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
        }

        .topbar-right a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: color 0.2s;
        }

        .topbar-right a:hover { color: #fff; }

        /* ---- Layout ---- */
        .editor-layout {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 1.5rem;
            align-items: start;
        }

        @media (max-width: 900px) {
            .editor-layout {
                grid-template-columns: 1fr;
            }
        }

        /* ---- Card ---- */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* ---- Form ---- */
        .form-group {
            margin-bottom: 1.25rem;
        }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        input[type="text"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.9375rem;
            color: var(--text);
            background: var(--white);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
            font-family: inherit;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--focus);
        }

        textarea {
            resize: vertical;
            min-height: 420px;
            font-family: 'SFMono-Regular', 'Cascadia Code', Consolas, 'Liberation Mono', Menlo, monospace;
            font-size: 0.875rem;
            line-height: 1.6;
            tab-size: 2;
        }

        /* ---- Buttons ---- */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.625rem 1.25rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: background 0.2s, transform 0.1s;
            font-family: inherit;
        }

        .btn:active { transform: scale(0.98); }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            width: 100%;
            justify-content: center;
            padding: 0.875rem;
            font-size: 1rem;
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

        .btn-outline:hover { background: var(--primary); color: #fff; }

        /* ---- Alert ---- */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .alert-error   { background: var(--error-bg);   color: var(--error);   border: 1px solid #fca5a5; }
        .alert-success { background: var(--success-bg); color: var(--success); border: 1px solid #86efac; }

        /* ---- Cheatsheet ---- */
        .cheatsheet dt {
            font-weight: 700;
            font-family: monospace;
            font-size: 0.875rem;
            color: var(--primary);
            margin-top: 0.75rem;
        }

        .cheatsheet dd {
            font-size: 0.8rem;
            color: var(--muted);
            margin-left: 0;
        }

        /* ---- Breadcrumb ---- */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--muted);
            margin-bottom: 1.5rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover { text-decoration: underline; }

        /* ---- Deploy hint ---- */
        .deploy-hint {
            padding: 0.875rem 1rem;
            background: var(--success-bg);
            border-radius: var(--radius);
            border: 1px solid #86efac;
            font-size: 0.85rem;
            color: #15803d;
            margin-top: 1rem;
        }

        /* ---- Separator ---- */
        hr {
            border: none;
            border-top: 1px solid var(--border);
            margin: 1.25rem 0;
        }
    </style>
</head>
<body>

<!-- Topbar -->
<header class="topbar">
    <a href="index.php" class="topbar-brand">
        <div class="monogram">R</div>
        <span>Kanzlei Rogalla &mdash; Admin</span>
    </a>
    <nav class="topbar-right">
        <a href="index.php">&larr; Alle Artikel</a>
        <a href="logout.php">Abmelden</a>
    </nav>
</header>

<div style="max-width:1200px;margin:0 auto;padding:1.5rem 1.5rem 0">
    <div class="breadcrumb">
        <a href="index.php">Dashboard</a>
        <span>&rsaquo;</span>
        <span><?= $isNew ? 'Neuer Artikel' : e($fm['title'] ?: $slug) ?></span>
    </div>
</div>

<form method="POST" action="edit-article.php<?= $isNew ? '' : '?slug=' . urlencode($slug) ?>" id="editor-form">
<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<input type="hidden" name="sha" value="<?= e($sha) ?>">

<div class="editor-layout">

    <!-- Left: main editor -->
    <div>
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= e($message) ?>
                <?php if ($messageType === 'success'): ?>
                    <br>
                    <small>
                        Commit wurde auf GitHub gepusht &rarr;
                        GitHub Actions deployt die Seite automatisch.
                    </small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Title -->
        <div class="card" style="margin-bottom:1.25rem">
            <div class="card-body">
                <div class="form-group">
                    <label for="title">Titel</label>
                    <input type="text" id="title" name="title" value="<?= e($fm['title']) ?>" placeholder="Artikeltitel..." required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label for="description">Meta-Beschreibung (SEO)</label>
                    <input type="text" id="description" name="description" value="<?= e($fm['description']) ?>" placeholder="Kurze Beschreibung fuer Suchmaschinen (max. 160 Zeichen)..." maxlength="200">
                </div>
            </div>
        </div>

        <!-- Content editor -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Inhalt (Markdown)</h2>
                <span style="font-size:0.8rem;color:var(--muted)">Markdown-Syntax verwenden</span>
            </div>
            <div class="card-body">
                <textarea id="content" name="content" placeholder="Artikelinhalt in Markdown..."><?= e($mdContent) ?></textarea>
            </div>
        </div>
    </div>

    <!-- Right: sidebar -->
    <div>
        <!-- Save button -->
        <div class="card" style="margin-bottom:1.25rem">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    &#10003; <?= $isNew ? 'Artikel erstellen' : 'Artikel speichern' ?>
                </button>
                <div class="deploy-hint" style="margin-top:1rem">
                    Speichern = Commit auf GitHub &rarr; automatisches Deployment
                </div>
            </div>
        </div>

        <!-- Meta -->
        <div class="card" style="margin-bottom:1.25rem">
            <div class="card-header">
                <h2 class="card-title">Metadaten</h2>
            </div>
            <div class="card-body">

                <div class="form-group">
                    <label for="slug">URL-Slug</label>
                    <input
                        type="text"
                        id="slug"
                        name="slug"
                        value="<?= e($isNew ? '' : $slug) ?>"
                        pattern="[a-z0-9\-]+"
                        placeholder="z.B. privatinsolvenz-ablauf"
                        required
                    >
                    <small style="font-size:0.75rem;color:var(--muted);margin-top:0.3rem;display:block">
                        Nur Kleinbuchstaben, Ziffern und Bindestriche
                    </small>
                </div>

                <div class="form-group">
                    <label for="pubDate">Veroffentlichungsdatum</label>
                    <input type="date" id="pubDate" name="pubDate" value="<?= e($fm['pubDate']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="category">Kategorie</label>
                    <select id="category" name="category">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>" <?= $fm['category'] === $cat ? 'selected' : '' ?>>
                                <?= e($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="author">Autor</label>
                    <input type="text" id="author" name="author" value="<?= e($fm['author']) ?>" placeholder="Christina Rogalla">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label for="tags">Tags (kommagetrennt)</label>
                    <input type="text" id="tags" name="tags" value="<?= e($fm['tags']) ?>" placeholder="Privatinsolvenz, Schulden, Restschuldbefreiung">
                </div>
            </div>
        </div>

        <!-- Markdown Cheatsheet -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Markdown-Hilfe</h2>
            </div>
            <div class="card-body">
                <dl class="cheatsheet">
                    <dt># Ueberschrift 1</dt>
                    <dd>H1 &ndash; Seitenthema</dd>

                    <dt>## Ueberschrift 2</dt>
                    <dd>H2 &ndash; Hauptabschnitte</dd>

                    <dt>### Ueberschrift 3</dt>
                    <dd>H3 &ndash; Unterabschnitte</dd>

                    <hr>

                    <dt>**Fettdruck**</dt>
                    <dd>Wichtige Begriffe hervorheben</dd>

                    <dt>*Kursiv*</dt>
                    <dd>Leichte Betonung</dd>

                    <dt>[Linktext](URL)</dt>
                    <dd>Hyperlink einfuegen</dd>

                    <hr>

                    <dt>- Listenpunkt</dt>
                    <dd>Ungeordnete Liste</dd>

                    <dt>1. Schritt</dt>
                    <dd>Geordnete Liste</dd>

                    <dt>&gt; Zitat</dt>
                    <dd>Blockzitat</dd>

                    <hr>

                    <dt>`Code`</dt>
                    <dd>Inline-Code</dd>

                    <dt>---</dt>
                    <dd>Horizontale Linie</dd>
                </dl>
            </div>
        </div>
    </div>

</div>
</form>

<script>
// Slug automatisch aus Titel generieren (nur bei neuem Artikel)
<?php if ($isNew): ?>
document.getElementById('title').addEventListener('input', function() {
    const slug = this.value
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/ae/g, 'ae')
        .replace(/oe/g, 'oe')
        .replace(/ue/g, 'ue')
        .replace(/ä/g, 'ae')
        .replace(/ö/g, 'oe')
        .replace(/ü/g, 'ue')
        .replace(/ß/g, 'ss')
        .replace(/[^a-z0-9\-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('slug').value = slug;
});
<?php endif; ?>

// Slug-Feld bereinigen bei Eingabe
document.getElementById('slug').addEventListener('input', function() {
    this.value = this.value
        .toLowerCase()
        .replace(/[^a-z0-9\-]/g, '')
        .replace(/-+/g, '-');
});

// Warnung beim Verlassen ohne Speichern
let formChanged = false;
document.getElementById('editor-form').addEventListener('input', () => { formChanged = true; });
document.getElementById('editor-form').addEventListener('submit', () => { formChanged = false; });
window.addEventListener('beforeunload', (e) => {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = 'Aenderungen nicht gespeichert. Wirklich verlassen?';
    }
});
</script>

</body>
</html>
