<?php
// Local-only job tracker. Apache vhost: localhostwwwp:8080
//
// TEMPLATE. Copy this file to config.php and fill in the four values below;
// config.php is gitignored so credentials and local paths stay off GitHub.
//
//     cp config.example.php config.php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'job_tracker';
const DB_USER = 'jobtracker';
const DB_PASS = 'change-me';

// Absolute path to the repository whose documents docs.php may serve.
const REPO = '/absolute/path/to/your/documents/repo';
const STATUSES = ['Interviewing', 'Applied - awaiting reply', 'CV prepared - not submitted', 'Rejected', 'Offer', 'Withdrawn'];

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $pdo;
}

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Cache-buster: browsers hold onto style.css and app.js across edits, so a CSS
// change lands on disk and the page keeps rendering the old layout. Stamping the
// file's mtime onto the URL makes every edit a new URL, and no edit needs a hard
// refresh to show up.
function asset(string $f): string {
    $p = __DIR__ . '/' . $f;
    return $f . '?v=' . (is_file($p) ? filemtime($p) : time());
}

// Notes are the one long-prose field in this table, and they accumulate: each
// pass appends another sentence, so a mature note is a 600-character run-on
// paragraph squeezed into a narrow column. Rendering it as one blob is what made
// them unreadable. Two separators carry meaning already and both become blocks:
//   " | "  -- how successive updates were appended
//   "\n"   -- what Ben types in the textarea
// A leading ALL-CAPS label (NEXT:, JOB DESCRIPTION:, INTERVIEW) is what the eye
// looks for when scanning, so it gets picked out. Escaping happens FIRST; the
// only markup added afterwards is markup this function produced itself.
function note_html(?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') { return '<i>add a note</i>'; }
    $raw = preg_replace('/(?<=[.;:!?]) +(?=[A-Z][A-Z0-9 \/&\-]{2,}:)/u', ' | ', $raw);
    $parts = preg_split('/\s*\|\s*|\r?\n+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach ($parts as $part) {
        $line = e(trim($part));
        $isNext = (bool)preg_match('/^NEXT\b/u', $line);
        $line = preg_replace('/^([A-Z][A-Z0-9 \/&\-]{2,}:)/u',
                             '<b class="lbl' . ($isNext ? ' next' : '') . '">$1</b>', $line, 1);
        $out .= '<span class="np' . ($isNext ? ' nextline' : '') . '">' . $line . '</span>';
    }
    return $out;
}
