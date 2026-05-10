# Kanzlei Rogalla – Admin-Panel

PHP-basiertes Content-Management-Panel fuer die Kanzlei Rogalla Astro-Website.
Blog-Artikel werden direkt ueber die GitHub REST API bearbeitet und gespeichert.
Jedes Speichern triggert automatisch den GitHub Actions Deploy-Workflow.

---

## Schnellstart

### 1. GitHub Token erstellen

1. GitHub-Konto oeffnen → [Settings > Developer Settings > Personal Access Tokens](https://github.com/settings/tokens/new)
2. Token-Typ: **Fine-grained** (empfohlen) oder **Classic**
3. Berechtigungen:
   - Fine-grained: `Contents: Read and Write` fuer das Repo `kanzlei-rogalla-vite`
   - Classic: Scope `repo`
4. Token speichern – wird nur einmal angezeigt!

### 2. Token als Umgebungsvariable setzen

**Apache (empfohlen via SetEnv):**
```apache
# In /etc/apache2/sites-available/deine-seite.conf oder .htaccess:
SetEnv GITHUB_TOKEN ghp_deintoken
```

**oder systemweit (Linux):**
```bash
echo 'export GITHUB_TOKEN=ghp_deintoken' >> /etc/environment
# Danach: Apache neu starten
sudo systemctl restart apache2
```

**oder fuer Entwicklung (.env / Shell):**
```bash
export GITHUB_TOKEN=ghp_deintoken
php -S localhost:8080 -t .
# Dann: http://localhost:8080/admin/
```

### 3. Admin-Passwort aendern (empfohlen)

Standard-Passwort: `rogalla2025!`

1. `SETUP_TOKEN` Umgebungsvariable setzen:
   ```bash
   export SETUP_TOKEN=mein-geheimes-setup-token
   ```
2. Browser: `https://deine-domain.de/admin/setup.php?token=mein-geheimes-setup-token`
3. Neues Passwort eingeben → Hash kopieren
4. `admin/config.php` oeffnen → `ADMIN_PASSWORD_HASH` mit dem neuen Hash ersetzen
5. `SETUP_TOKEN` wieder entfernen oder `setup.php` loeschen

### 4. Login

URL: `https://deine-domain.de/admin/`

Zugangsdaten:
- Passwort: `rogalla2025!` (oder das neue Passwort aus Schritt 3)

---

## Wie funktioniert das Deployment?

```
Admin speichert Artikel
     ↓
GitHub REST API: PUT /repos/.../contents/...
(Datei wird im Repo aktualisiert = neuer Commit)
     ↓
GitHub erkennt Push auf main-Branch
     ↓
GitHub Actions Workflow startet
(astro build + deploy auf GitHub Pages / Hosting)
     ↓
Website ist live
```

Der Commit-Message-Format ist:
- Neuer Artikel: `content: add blog article <slug>`
- Bearbeiteter Artikel: `content: update blog article <slug>`

---

## Dateistruktur

```
admin/
├── index.php        # Dashboard – Artikelliste
├── login.php        # Login-Seite
├── logout.php       # Session beenden
├── edit-article.php # Artikel erstellen / bearbeiten
├── setup.php        # Einmalig: Passwort-Hash-Generator
├── config.php       # Konfiguration (nicht web-zugaenglich)
├── auth.php         # Auth-Helfer + GitHub API (nicht web-zugaenglich)
├── .htaccess        # Apache-Sicherheitsregeln
└── README.md        # Diese Datei
```

---

## Konfiguration (config.php)

| Konstante              | Standard                    | Beschreibung                              |
|------------------------|-----------------------------|-------------------------------------------|
| `ADMIN_PASSWORD_HASH`  | Hash von `rogalla2025!`     | bcrypt-Hash des Admin-Passworts           |
| `GITHUB_TOKEN`         | Aus `GITHUB_TOKEN` Env-Var  | GitHub Personal Access Token              |
| `GITHUB_OWNER`         | `cyberground`               | GitHub-Benutzername / Organisation        |
| `GITHUB_REPO`          | `kanzlei-rogalla-vite`      | Repository-Name                           |
| `GITHUB_BRANCH`        | `main`                      | Branch fuer Commits                       |
| `CONTENT_PATH`         | `src/content/blog/`         | Pfad zu Blog-Artikeln im Repo             |
| `SESSION_TIMEOUT`      | `28800` (8 Stunden)         | Session-Ablauf in Sekunden                |
| `MAX_LOGIN_ATTEMPTS`   | `5`                         | Max. Fehlversuche vor Sperre              |
| `LOGIN_LOCKOUT_TIME`   | `900` (15 Minuten)          | Sperrdauer nach zu vielen Versuchen       |

---

## Sicherheitshinweise

- `config.php` und `auth.php` sind durch `.htaccess` vor Direktaufruf geschuetzt
- CSRF-Schutz bei allen Formularen (Token in Session)
- Passwort wird als bcrypt-Hash gespeichert (niemals im Klartext)
- GitHub Token wird NICHT in Dateien gespeichert – nur als Env-Variable
- Rate Limiting: Max. 5 Fehlversuche pro 15 Minuten pro Session
- `setup.php` nur mit gueltigem `SETUP_TOKEN` erreichbar

---

## Artikel-Frontmatter-Format

Alle Blog-Artikel verwenden dieses YAML-Frontmatter:

```yaml
---
title: "Artikeltitel"
description: "SEO-Beschreibung (max. 160 Zeichen)"
pubDate: 2025-01-15
category: "Insolvenzrecht"  # oder: Erbrecht, Forderungsmanagement, Allgemein
author: "Christina Rogalla"
tags: ["Tag1", "Tag2", "Tag3"]
---

Artikelinhalt in Markdown...
```

---

## Fehlerbehebung

**"GitHub Token nicht konfiguriert"**
→ `GITHUB_TOKEN` Umgebungsvariable setzen (siehe Abschnitt 2)

**"GitHub API Fehler 401"**
→ Token abgelaufen oder ungueltig – neues Token erstellen

**"GitHub API Fehler 404"**
→ Repo-Name oder Pfad falsch – `config.php` pruefen

**"GitHub API Fehler 409"**
→ Konflikt beim Commit (parallele Aenderung) – Seite neu laden und erneut speichern

**Session laeuft sofort ab**
→ PHP Session-Konfiguration pruefen (`session.save_path` muss schreibbar sein)
