<?php
// Lists a run's document folder and serves its files inline. Replaces an earlier
// attempt that shelled out to `open -R`: Apache runs as _www, which has no Finder
// session, so that silently did nothing.
require __DIR__ . '/config.php';

// REPO -- the document repository root -- is defined in config.php, which is
// machine-local and not in git.
const CV_ROOT = REPO . '/cv-custom';

// --- serve a repo file by path --------------------------------------------
// Chrome refuses to follow a file:// link from an http:// page, so any local
// document stored as a file:// URL is a dead link. Serve it through Apache
// instead, from an allowlist of roots inside the repo.
if (isset($_GET['p'])) {
    $rel   = ltrim(str_replace('\\', '/', (string)$_GET['p']), '/');
    $roots = ['cv-custom', 'interviews', 'jobs', 'documents'];
    $real  = realpath(REPO . '/' . $rel);
    $ok    = false;
    foreach ($roots as $root) {
        $base = realpath(REPO . '/' . $root);
        if ($base && $real && str_starts_with($real, $base . '/') && is_file($real)) { $ok = true; break; }
    }
    if (!$ok) { http_response_code(404); exit('Not found.'); }
    $types = ['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
              'txt'=>'text/plain; charset=utf-8','md'=>'text/plain; charset=utf-8',
              'html'=>'text/html; charset=utf-8','htm'=>'text/html; charset=utf-8',
              'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . basename($real) . '"');
    header('Content-Length: ' . filesize($real));
    readfile($real);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT company, roles, folders FROM applications WHERE id = :id');
$st->execute([':id' => $id]);
$row = $st->fetch();
if (!$row) { http_response_code(404); exit('No such row.'); }

$slugs = array_values(array_filter(preg_split('~\s+~', trim($row['folders']))));
$slugs = array_filter($slugs, fn($s) => (bool)preg_match('~^[A-Za-z0-9._-]+$~', $s));

function safeDir(string $slug): ?string {
    $p = realpath(CV_ROOT . '/' . $slug);
    return ($p && str_starts_with($p, CV_ROOT . '/') && is_dir($p)) ? $p : null;
}

// --- serve one file -------------------------------------------------------
if (isset($_GET['f'])) {
    $slug = $_GET['slug'] ?? ($slugs[0] ?? '');
    $dir  = preg_match('~^[A-Za-z0-9._-]+$~', $slug) ? safeDir($slug) : null;
    $file = $dir ? realpath($dir . '/' . basename($_GET['f'])) : null;
    if (!$dir || !$file || !str_starts_with($file, $dir . '/') || !is_file($file)) {
        http_response_code(404); exit('Not found.');
    }
    $types = ['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg',
              'txt'=>'text/plain; charset=utf-8','md'=>'text/plain; charset=utf-8',
              'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

// --- list the folder(s) ---------------------------------------------------
$groups = [];
foreach ($slugs as $slug) {
    $dir = safeDir($slug);
    if (!$dir) { $groups[$slug] = null; continue; }
    $files = array_values(array_diff(scandir($dir), ['.', '..', '.DS_Store']));
    natcasesort($files);
    $groups[$slug] = ['dir' => $dir, 'files' => array_values($files)];
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<title><?= e($row['company']) ?> — documents</title>
<link rel="stylesheet" href="<?= asset('style.css') ?>"></head>
<body>
<p class="crumb"><a href="./">&larr; Job tracker</a></p>
<h1><?= e($row['company']) ?><?= $row['roles'] !== '' ? ' <span class="muted">— ' . e($row['roles']) . '</span>' : '' ?></h1>

<?php if (!$groups): ?>
  <p class="empty">No document folder recorded for this row.</p>
<?php endif; ?>

<?php foreach ($groups as $slug => $g): ?>
  <h2 class="slug">cv-custom/<?= e($slug) ?></h2>
  <?php if ($g === null): ?>
    <p class="empty">Folder is not on disk.</p>
  <?php else: ?>
    <p class="path" title="click to select"><?= e($g['dir']) ?></p>
    <ul class="files">
      <?php foreach ($g['files'] as $f): ?>
        <li>
          <a href="docs.php?id=<?= $id ?>&slug=<?= urlencode($slug) ?>&f=<?= urlencode($f) ?>" target="_blank" rel="noopener"><?= e($f) ?></a>
          <span class="muted small"><?= number_format(filesize($g['dir'] . '/' . $f) / 1024, 0) ?> KB</span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endforeach; ?>
</body></html>
