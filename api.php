<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
try {
    if ($action === 'update') {
        $id     = (int)($_POST['id'] ?? 0);
        $field  = $_POST['field'] ?? '';
        $value  = $_POST['value'] ?? '';
        $allowed = ['company','roles','requested_salary','apply_url','posting_url','email_url','prep_url','applied','status','interviewed','notes','folders'];
        if (!in_array($field, $allowed, true)) {
            throw new RuntimeException('Field not editable: ' . $field);
        }
        if ($field === 'status' && !in_array($value, STATUSES, true)) {
            throw new RuntimeException('Unknown status');
        }
        if ($field === 'interviewed') { $value = $value === '1' ? 1 : 0; }
        if ($field === 'applied')     { $value = $value !== '' ? $value : null; }

        $sql = "UPDATE applications SET `$field` = :v, last_update = CURDATE() WHERE id = :id";
        db()->prepare($sql)->execute([':v' => $value, ':id' => $id]);
        echo json_encode(['ok' => true, 'last_update' => date('Y-m-d')]);
        exit;
    }

    if ($action === 'create') {
        $company = trim($_POST['company'] ?? '');
        if ($company === '') { throw new RuntimeException('Company is required'); }
        $st = db()->prepare(
            'INSERT INTO applications (company, roles, requested_salary, apply_url, posting_url, applied, status, interviewed, last_update, notes)
             VALUES (:c, :r, :sal, :u, :p, :a, :s, :i, CURDATE(), :n)'
        );
        $st->execute([
            ':c' => $company,
            ':r' => trim($_POST['roles'] ?? ''),
            ':sal' => trim($_POST['requested_salary'] ?? ''),
            ':u' => trim($_POST['apply_url'] ?? ''),
            ':p' => trim($_POST['posting_url'] ?? ''),
            ':a' => ($_POST['applied'] ?? '') ?: date('Y-m-d'),
            ':s' => in_array($_POST['status'] ?? '', STATUSES, true) ? $_POST['status'] : 'Applied - awaiting reply',
            ':i' => !empty($_POST['interviewed']) ? 1 : 0,
            ':n' => trim($_POST['notes'] ?? ''),
        ]);
        echo json_encode(['ok' => true, 'id' => (int)db()->lastInsertId()]);
        exit;
    }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM applications WHERE id = :id')->execute([':id' => (int)($_POST['id'] ?? 0)]);
        echo json_encode(['ok' => true]);
        exit;
    }

    throw new RuntimeException('Unknown action');
} catch (Throwable $ex) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
}
