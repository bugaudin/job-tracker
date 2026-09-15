<?php
require __DIR__ . '/config.php';

$filter = $_GET['status'] ?? '';
$q      = trim($_GET['q'] ?? '');
$onlyIv = isset($_GET['iv']);

$where = [];
$args  = [];
if ($filter !== '' && in_array($filter, STATUSES, true)) { $where[] = 'status = :s'; $args[':s'] = $filter; }
if ($q !== '')  {
    // one placeholder per occurrence: native prepares will not reuse a named param
    $where[] = '(company LIKE :q1 OR roles LIKE :q2 OR notes LIKE :q3)';
    $args[':q1'] = $args[':q2'] = $args[':q3'] = "%$q%";
}
if ($onlyIv)    { $where[] = 'interviewed = 1'; }
$sql = 'SELECT * FROM applications';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= " ORDER BY FIELD(status,'Interviewing','Offer','Applied - awaiting reply','Rejected','Withdrawn'),
          interviewed DESC, applied DESC";
$st = db()->prepare($sql); $st->execute($args); $rows = $st->fetchAll();

$counts = [];
foreach (db()->query('SELECT status, COUNT(*) c FROM applications GROUP BY status') as $r) { $counts[$r['status']] = (int)$r['c']; }
$total = array_sum($counts);
$ivTotal = (int)db()->query('SELECT COUNT(*) FROM applications WHERE interviewed = 1')->fetchColumn();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Job tracker</title>
<link rel="stylesheet" href="<?= asset('style.css') ?>">
</head>
<body>
<header>
  <h1>Job tracker</h1>
  <div class="tally">
    <a class="pill <?= $filter===''?'on':'' ?>" href="?">All <b><?= $total ?></b></a>
    <?php foreach (STATUSES as $s): if (empty($counts[$s]) && !in_array($s,['Interviewing','Rejected'],true)) continue; ?>
      <a class="pill s-<?= e(strtolower(explode(' ',$s)[0])) ?> <?= $filter===$s?'on':'' ?>"
         href="?status=<?= urlencode($s) ?>"><?= e($s) ?> <b><?= (int)($counts[$s] ?? 0) ?></b></a>
    <?php endforeach; ?>
    <a class="pill iv <?= $onlyIv?'on':'' ?>" href="?iv=1">Interviewed <b><?= $ivTotal ?></b></a>
  </div>
  <form class="search" method="get">
    <?php if ($filter !== ''): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search company, role or notes">
    <button>Search</button>
    <?php if ($q !== '' || $filter !== '' || $onlyIv): ?><a class="clear" href="?">Clear</a><?php endif; ?>
  </form>
</header>

<details class="addbox">
  <summary>+ Add a company</summary>
  <form id="addForm">
    <input name="company" placeholder="Company" required>
    <input name="roles" placeholder="Role">
    <input name="requested_salary" placeholder="Salary asked">
    <input name="posting_url" placeholder="Job posting URL">
    <input name="apply_url" placeholder="Apply URL">
    <input name="applied" type="date" value="<?= date('Y-m-d') ?>">
    <select name="status"><?php foreach (STATUSES as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
    <label class="cb"><input type="checkbox" name="interviewed" value="1"> interviewed</label>
    <input name="notes" placeholder="Notes">
    <button>Add</button>
  </form>
</details>

<table>
<thead><tr>
  <th>Company</th><th>Role</th><th>Salary asked</th><th>Applied</th><th>Status</th><th class="c" title="Interviewed">IV</th><th>Notes</th><th>Links</th><th>Updated</th><th></th>
</tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
  <tr data-id="<?= (int)$r['id'] ?>" class="st-<?= e(strtolower(explode(' ',$r['status'])[0])) ?>">
    <td><span class="ed b" data-field="company"><?= e($r['company']) ?></span></td>
    <td class="role"><span class="ed muted" data-field="roles" title="<?= e($r['roles']) ?>"><?= e($r['roles']) ?: '<i>—</i>' ?></span></td>
    <td class="nw"><span class="ed sal" data-field="requested_salary"><?= e($r['requested_salary']) ?: '<i>—</i>' ?></span></td>
    <td class="nw"><span class="ed" data-field="applied"><?= e($r['applied']) ?></span></td>
    <td>
      <select class="status" data-field="status">
        <?php foreach (STATUSES as $s): ?>
          <option <?= $s===$r['status']?'selected':'' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td class="c"><input type="checkbox" class="iv" data-field="interviewed" <?= $r['interviewed']?'checked':'' ?>></td>
    <td class="notes"><span class="ed note" data-field="notes" data-raw="<?= e($r['notes']) ?>"><?= note_html($r['notes']) ?></span></td>
    <td class="nw links">
      <?php if (($r['posting_url'] ?? '') !== ''): ?>
        <a href="<?= e($r['posting_url']) ?>" target="_blank" rel="noopener" class="post" title="<?= e($r['posting_url']) ?>">post</a>
      <?php endif; ?>
      <?php if ($r['apply_url'] !== ''): ?>
        <a href="<?= e($r['apply_url']) ?>" target="_blank" rel="noopener" title="<?= e($r['apply_url']) ?>">form</a>
      <?php endif; ?>
      <?php if (($r['email_url'] ?? '') !== ''): ?>
        <a href="<?= e($r['email_url']) ?>" target="_blank" rel="noopener" title="<?= e($r['email_url']) ?>">mail</a>
      <?php endif; ?>
      <?php if (($r['prep_url'] ?? '') !== ''): ?>
        <a href="<?= e($r['prep_url']) ?>" target="_blank" rel="noopener" class="prep" title="<?= e($r['prep_url']) ?>">prep</a>
      <?php endif; ?>
      <?php if (($r['stack_url'] ?? '') !== ''): ?>
        <a href="<?= e($r['stack_url']) ?>" target="_blank" rel="noopener" class="prep" title="<?= e($r['stack_url']) ?>">stack</a>
      <?php endif; ?>
      <?php if ($r['folders'] !== ''): ?>
        <a href="docs.php?id=<?= (int)$r['id'] ?>" class="folder" title="Open cv-custom/<?= e(strtok($r['folders'],' ')) ?>">docs</a>
      <?php endif; ?>
      <span class="ed url" data-field="posting_url" title="<?= e($r['posting_url'] ?? '') ?>"><?= ($r['posting_url'] ?? '') !== '' ? 'edit post' : '<i>+ post</i>' ?></span>
      <span class="ed url" data-field="apply_url" title="<?= e($r['apply_url']) ?>"><?= $r['apply_url'] !== '' ? 'edit form' : '<i>+ form</i>' ?></span>
      <span class="ed url" data-field="email_url" title="<?= e($r['email_url'] ?? '') ?>"><?= ($r['email_url'] ?? '') !== '' ? 'edit mail' : '<i>+ mail</i>' ?></span>
      <span class="ed url" data-field="prep_url" title="<?= e($r['prep_url'] ?? '') ?>"><?= ($r['prep_url'] ?? '') !== '' ? 'edit prep' : '<i>+ prep</i>' ?></span>
      <span class="ed url" data-field="stack_url" title="<?= e($r['stack_url'] ?? '') ?>"><?= ($r['stack_url'] ?? '') !== '' ? 'edit stack' : '<i>+ stack</i>' ?></span>
    </td>
    <td class="nw muted small upd"><?= e($r['last_update']) ?></td>
    <td class="c"><button class="del" title="Delete">&times;</button></td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>
<?php if (!$rows): ?><p class="empty">Nothing matches that.</p><?php endif; ?>

<script src="<?= asset('app.js') ?>"></script>
</body>
</html>
