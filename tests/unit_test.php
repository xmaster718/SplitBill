<?php
declare(strict_types=1);
/*
 * Юнит-тесты чистой логики (деление денег, упрощение долгов, валидация).
 * Запуск:  php tests/unit_test.php
 */
require __DIR__ . '/../public/api/lib.php';

$failed = 0;
$total = 0;

function check(string $name, $actual, $expected): void
{
    global $failed, $total;
    $total++;
    if ($actual === $expected) {
        echo "  ok    $name\n";
        return;
    }
    $failed++;
    echo "  FAIL  $name\n        ожидали: " . json_encode($expected) . "\n        получили: " . json_encode($actual) . "\n";
}

echo "split_equal\n";
check('100.00 на троих', split_equal(10000, [1, 2, 3]), [1 => 3334, 2 => 3333, 3 => 3333]);
check('сумма долей = сумме расхода', array_sum(split_equal(10001, [5, 6, 7, 8])), 10001);
check('1 цент на двоих', split_equal(1, [2, 1]), [1 => 1, 2 => 0]);
check('дубли участников игнорируются', split_equal(1000, [4, 4, 9]), [4 => 500, 9 => 500]);

echo "parse_amount_cents\n";
check('"1500"', parse_amount_cents('1500'), 150000);
check('"1 500,50" (пробел и запятая)', parse_amount_cents('1 500,50'), 150050);
check('"0.5"', parse_amount_cents('0.5'), 50);
check('число 12.5', parse_amount_cents(12.5), 1250);
check('float 0.1+0.2', parse_amount_cents(0.1 + 0.2), 30);
check('целое 7', parse_amount_cents(7), 700);
check('ноль отклоняется', parse_amount_cents('0'), null);
check('минус отклоняется', parse_amount_cents('-5'), null);
check('3 знака после запятой отклоняются', parse_amount_cents('10.999'), null);
check('текст отклоняется', parse_amount_cents('abc'), null);
check('пустая строка', parse_amount_cents(''), null);
check('null', parse_amount_cents(null), null);
check('слишком большая сумма', parse_amount_cents('99999999999'), null);

echo "compute_settlements\n";
$s = compute_settlements([1 => 6666, 2 => -3333, 3 => -3333]);
check('2 перевода на 3 человек', count($s), 2);
check('все переводы адресованы кредитору', array_unique(array_column($s, 'to')), [1]);
check('сумма переводов = долгу', array_sum(array_column($s, 'amount_cents')), 6666);
check('все в расчёте — переводов нет', compute_settlements([1 => 0, 2 => 0]), []);
// произвольный случай: после всех переводов балансы должны обнулиться
$nets = [1 => 5000, 2 => -1200, 3 => -2300, 4 => 800, 5 => -2300];
$bal = $nets;
foreach (compute_settlements($nets) as $t) {
    $bal[$t['from']] += $t['amount_cents'];
    $bal[$t['to']] -= $t['amount_cents'];
}
check('балансы обнуляются после переводов', array_sum(array_map('abs', $bal)), 0);
check('переводов не больше, чем (людей − 1)', count(compute_settlements($nets)) <= 4, true);

echo "прочее\n";
check('email приводится к нижнему регистру', normalize_email('  Diyas@Mail.RU '), 'diyas@mail.ru');
check('некорректный email', normalize_email('not-an-email'), null);
check('дата 2026-10-09 валидна', valid_date('2026-10-09'), true);
check('дата 2026-02-30 невалидна', valid_date('2026-02-30'), false);
check('дата в далёком будущем невалидна', valid_date('2999-01-01'), false);
check('код приглашения: 8 символов без 0/O/1/I', (bool)preg_match('/^[A-HJ-NP-Z2-9]{8}$/', generate_invite_code()), true);

echo "\n" . ($total - $failed) . " из $total тестов пройдено\n";
exit($failed === 0 ? 0 : 1);
