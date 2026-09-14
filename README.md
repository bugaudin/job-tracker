# Job tracker

A single-table CRM for a job search: one row per company, the status it is at,
what was asked for, and a dated log of everything that happened. Plain PHP, PDO
and MySQL — no framework, no build step, no dependencies. It is meant to run on
`localhost` on one machine, for one person.

It exists because the spreadsheet version stopped answering the only questions
that mattered — *who has replied, what is scheduled next, what did I ask for* —
once there were more than about thirty rows.

## What it does

- **One row per company.** A `UNIQUE (company, roles)` key means re-recording the
  same application updates the row instead of adding another one.
- **Inline editing.** Every text cell, the status dropdown and the interviewed
  checkbox save on the spot; nothing has an edit screen.
- **Notes as a dated log.** Each update is its own line, oldest first, and a
  line beginning `NEXT:` is picked out as the single upcoming commitment.
  Long notes are clamped and expand on hover.
- **Links per row** to the job posting, the employer's application form, the
  confirmation email and an interview-prep document.
- **A document browser** (`docs.php`) that lists and serves the tailored CV,
  cover letter and notes generated for that application. Chrome refuses to
  follow a `file://` link from an `http://` page, so local documents are served
  through Apache from an allowlist of directories rather than linked directly.

## Requirements

PHP 8.1+ (it uses `str_starts_with` and enums in SQL only), MySQL 8+, and any
web server that runs PHP. Developed against PHP 8.4, MySQL 9.3 and Apache.

## Setup

```sh
git clone <this repo> job-tracker
cd job-tracker
cp config.example.php config.php   # then edit it
mysql -u root --default-character-set=utf8mb4 < schema.sql
```

`config.php` holds four values and is gitignored:

| Constant | Meaning |
| --- | --- |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | database connection |
| `REPO` | absolute path to the repository whose documents `docs.php` may serve |

`docs.php` will only serve files beneath `cv-custom/`, `interviews/`, `jobs/` or
`documents/` inside `REPO`, resolved with `realpath` and prefix-checked, so a
`../` in the query string cannot walk out.

Point a vhost at the directory:

```apache
Listen 8080
<VirtualHost *:8080>
    ServerName      localhostwwwp
    DocumentRoot    "/path/to/job-tracker"
    <Directory "/path/to/job-tracker">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 localhostwwwp` to `/etc/hosts`, restart Apache, and open
<http://localhostwwwp:8080/>.

## Files

| File | |
| --- | --- |
| `index.php` | the table: filters, search, inline edit |
| `api.php` | JSON endpoint for create / update / delete |
| `docs.php` | document listing and file serving |
| `config.php` | credentials and shared helpers — **not in git** |
| `app.js` | inline editing |
| `style.css` | all of the styling |
| `schema.sql` | the table, no rows |

## Notes on the data

The database is deliberately not published: it is a record of real applications,
interviews and correspondence. `schema.sql` creates the table empty. Local MySQL
dumps live in `backup/`, which is gitignored.

## Security

There is none, by design — no login, no CSRF token, no per-user anything. It
binds to localhost and assumes the only person who can reach it is the person
whose job search it is. Do not expose it to a network.

## Licence

MIT.
