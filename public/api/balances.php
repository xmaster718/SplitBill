<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
require_method('GET');
$g = load_group_for_user(int_value($_GET['group_id'] ?? null, 'group_id'), $user);

// Баланс участника = сколько он заплатил − сколько составляют его доли.
$stmt = db()->prepare(
    'SELECT m.id, m.name, m.user_id,
            (SELECT COALESCE(SUM(e.amount_cents), 0)  FROM expenses e        WHERE e.paid_by   = m.id) AS paid,
            (SELECT COALESCE(SUM(s.share_cents), 0)   FROM expense_shares s  WHERE s.member_id = m.id) AS owed
       FROM group_members m
      WHERE m.group_id = ?
      ORDER BY m.id'
);
$stmt->execute([$g['id']]);

$balances = [];
$nets = [];
$names = [];
foreach ($stmt->fetchAll() as $r) {
    $id = (int)$r['id'];
    $paid = (int)$r['paid'];
    $owed = (int)$r['owed'];
    $net = $paid - $owed;
    $balances[] = [
        'member_id' => $id, 'name' => $r['name'], 'is_me' => $r['user_id'] !== null && (int)$r['user_id'] === $user['id'],
        'paid_cents' => $paid, 'owed_cents' => $owed, 'net_cents' => $net,
    ];
    $nets[$id] = $net;
    $names[$id] = $r['name'];
}

$settlements = array_map(fn(array $s): array => [
    'from_id' => $s['from'], 'from_name' => $names[$s['from']],
    'to_id'   => $s['to'],   'to_name'   => $names[$s['to']],
    'amount_cents' => $s['amount_cents'],
], compute_settlements($nets));

$sum = db()->prepare("SELECT COALESCE(SUM(amount_cents), 0) FROM expenses WHERE group_id = ? AND kind = 'expense'");
$sum->execute([$g['id']]);

respond(['balances' => $balances, 'settlements' => $settlements, 'total_cents' => (int)$sum->fetchColumn()]);
