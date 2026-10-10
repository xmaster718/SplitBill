<?php
declare(strict_types=1);

/*
 * Чистые функции без доступа к БД — их легко тестировать (см. tests/unit_test.php).
 * Все деньги считаются в целых «центах» (1 ₸ = 100), без чисел с плавающей точкой.
 */

/** Делит сумму поровну между участниками. Сумма долей ВСЕГДА равна сумме расхода. */
function split_equal(int $totalCents, array $memberIds): array
{
    $ids = array_values(array_unique(array_map('intval', $memberIds)));
    sort($ids);
    $n = count($ids);
    if ($n === 0) {
        throw new InvalidArgumentException('Нет участников');
    }
    $base = intdiv($totalCents, $n);
    $rest = $totalCents % $n;      // «лишние» центы раздаём по одному первым участникам
    $shares = [];
    foreach ($ids as $i => $id) {
        $shares[$id] = $base + ($i < $rest ? 1 : 0);
    }
    return $shares;                // [id участника => доля в центах]
}

/** Превращает ввод пользователя ("1500", "1 500,50", 12.5) в центы. null — если ввод некорректен. */
function parse_amount_cents($value): ?int
{
    if (is_int($value)) {
        $s = (string)$value;
    } elseif (is_float($value)) {
        $c = round($value * 100);
        if (abs($value * 100 - $c) > 1e-6 || $c <= 0 || $c > 999999999999) {
            return null;
        }
        return (int)$c;
    } elseif (is_string($value)) {
        $s = trim(str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $value));
    } else {
        return null;
    }
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $s)) {
        return null;
    }
    $parts = explode('.', $s, 2);
    $cents = (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
    return $cents > 0 ? $cents : null;
}

/**
 * Упрощение долгов: из балансов [id => net] строит минимальный набор переводов.
 * net > 0 — участнику должны, net < 0 — участник должен.
 */
function compute_settlements(array $nets): array
{
    $debtors = [];
    $creditors = [];
    foreach ($nets as $id => $net) {
        if ($net < 0) {
            $debtors[] = [(int)$id, -$net];
        } elseif ($net > 0) {
            $creditors[] = [(int)$id, $net];
        }
    }
    // Самые крупные долги и требования — первыми: так переводов получается меньше.
    $cmp = fn(array $a, array $b): int => ($b[1] <=> $a[1]) ?: ($a[0] <=> $b[0]);
    usort($debtors, $cmp);
    usort($creditors, $cmp);

    $result = [];
    $i = 0;
    $j = 0;
    while ($i < count($debtors) && $j < count($creditors)) {
        $pay = min($debtors[$i][1], $creditors[$j][1]);
        $result[] = ['from' => $debtors[$i][0], 'to' => $creditors[$j][0], 'amount_cents' => $pay];
        $debtors[$i][1] -= $pay;
        $creditors[$j][1] -= $pay;
        if ($debtors[$i][1] === 0) {
            $i++;
        }
        if ($creditors[$j][1] === 0) {
            $j++;
        }
    }
    return $result;
}

function normalize_email($value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $email = mb_strtolower(trim($value));
    if ($email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $email;
}

/** Проверяет дату формата ГГГГ-ММ-ДД в разумных пределах (2000 год … сегодня + 1 год). */
function valid_date($value): bool
{
    if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return false;
    }
    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return false;
    }
    return $value >= '2000-01-01' && $value <= date('Y-m-d', strtotime('+1 year'));
}

/** Код приглашения: 8 символов без похожих (0/O, 1/I). */
function generate_invite_code(int $length = 8): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $alphabet[random_int(0, $max)];
    }
    return $code;
}
