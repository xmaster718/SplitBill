#!/usr/bin/env python3
"""
Сквозной (интеграционный) тест API SplitBill. Нужен только Python 3, библиотеки не нужны.

Запуск:  python tests/integration_test.py http://localhost:8080
Для XAMPP: python tests/integration_test.py http://localhost/SplitBill/public
"""
import json
import sys
import time
import urllib.error
import urllib.request

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080").rstrip("/")
failed = 0
total = 0


def call(path, method="GET", body=None, token=None):
    headers = {"Content-Type": "application/json"} if body is not None else {}
    if token:
        headers["Authorization"] = "Bearer " + token
    req = urllib.request.Request(
        BASE + "/api/" + path, method=method, headers=headers,
        data=json.dumps(body).encode() if body is not None else None,
    )
    try:
        with urllib.request.urlopen(req, timeout=15) as r:
            return r.status, json.loads(r.read() or b"{}")
    except urllib.error.HTTPError as e:
        raw = e.read()
        try:
            return e.code, json.loads(raw or b"{}")
        except ValueError:
            return e.code, {"raw": raw.decode(errors="replace")[:200]}


def check(name, cond, extra=""):
    global failed, total
    total += 1
    if cond:
        print("  ok    " + name)
    else:
        failed += 1
        print("  FAIL  " + name + ("   → " + str(extra) if extra else ""))


stamp = str(int(time.time() * 1000))
pw = "correct-horse-battery"

print("Здоровье сервиса")
s, d = call("health.php")
check("health.php отвечает ok", s == 200 and d.get("status") == "ok", (s, d))

print("Регистрация и вход")
s, a = call("register.php", "POST", {"name": "Диас", "email": f"dias{stamp}@test.kz", "password": pw})
check("регистрация создаёт пользователя (201) и токен", s == 201 and len(a.get("token", "")) == 64, (s, a))
ta = a.get("token")
s, d = call("register.php", "POST", {"name": "Диас", "email": f"dias{stamp}@test.kz", "password": pw})
check("повторный email → 409", s == 409, (s, d))
s, d = call("register.php", "POST", {"name": "X", "email": "bad", "password": "123"})
check("некорректные данные → 400", s == 400, (s, d))
s, d = call("login.php", "POST", {"email": f"dias{stamp}@test.kz", "password": "wrong-password"})
check("неверный пароль → 401", s == 401, (s, d))
s, d = call("login.php", "POST", {"email": f"dias{stamp}@test.kz", "password": pw})
check("верный пароль → токен", s == 200 and "token" in d, (s, d))
ta = d.get("token", ta)
s, d = call("me.php", token=ta)
check("me.php возвращает пользователя", s == 200 and d["user"]["name"] == "Диас", (s, d))
s, d = call("me.php")
check("без токена → 401", s == 401, (s, d))
s, d = call("groups.php")
check("список групп без токена → 401", s == 401, (s, d))

print("Группы и участники")
s, g = call("groups.php", "POST", {"name": "Поездка в Алматы"}, ta)
check("создание группы (201)", s == 201 and len(g.get("invite_code", "")) == 8, (s, g))
gid, code = g["id"], g["invite_code"]
s, d = call("members.php?group_id=%d" % gid, token=ta)
check("создатель автоматически стал участником", s == 200 and len(d["members"]) == 1 and d["members"][0]["name"] == "Диас", (s, d))
me_id = d["members"][0]["id"]
s, guest = call("members.php", "POST", {"group_id": gid, "name": "Аружан"}, ta)
check("добавление гостя по имени", s == 201, (s, guest))
s, d = call("members.php", "POST", {"group_id": gid, "name": "аружан"}, ta)
check("дубль имени (без учёта регистра) → 409", s == 409, (s, d))

s, b = call("register.php", "POST", {"name": "Мадина", "email": f"madina{stamp}@test.kz", "password": pw})
tb = b["token"]
s, d = call("groups.php?id=%d" % gid, token=tb)
check("чужой пользователь не видит группу (404)", s == 404, (s, d))
s, d = call("join.php", "POST", {"code": "ZZZZZZZZ"}, tb)
check("неверный код приглашения → 404", s == 404, (s, d))
s, d = call("join.php", "POST", {"code": code.lower()}, tb)
check("вход по коду приглашения (201)", s == 201, (s, d))
s, d = call("groups.php?id=%d" % gid, token=tb)
check("после входа по коду группа доступна", s == 200 and d["group"]["name"] == "Поездка в Алматы", (s, d))
s, d = call("members.php?group_id=%d" % gid, token=ta)
ids = {m["name"]: m["id"] for m in d["members"]}
check("в группе 3 участника", len(ids) == 3, ids)
mad_id, aru_id = ids["Мадина"], ids["Аружан"]

print("Расходы и деление")
s, d = call("expenses.php", "POST", {"group_id": gid, "description": "Такси", "amount": "100.00",
            "paid_by": me_id, "participant_ids": [me_id, aru_id, mad_id]}, ta)
check("расход 100.00 поровну на троих (201)", s == 201, (s, d))
s, bal = call("balances.php?group_id=%d" % gid, token=ta)
nets = {x["name"]: x["net_cents"] for x in bal["balances"]}
check("баланс плательщика +66.66", nets["Диас"] == 6666, nets)
check("баланс остальных −33.33", nets["Аружан"] == -3333 and nets["Мадина"] == -3333, nets)
check("сумма всех балансов = 0 (копейки не теряются)", sum(nets.values()) == 0, nets)
check("2 перевода вместо хаоса", len(bal["settlements"]) == 2, bal["settlements"])

s, d = call("expenses.php", "POST", {"group_id": gid, "description": "Ужин", "amount": "90",
            "paid_by": mad_id, "shares": [{"member_id": me_id, "amount": "50"}, {"member_id": aru_id, "amount": "40"}]}, tb)
check("деление «по суммам» (201)", s == 201, (s, d))
s, d = call("expenses.php", "POST", {"group_id": gid, "description": "Ужин 2", "amount": "90",
            "paid_by": mad_id, "shares": [{"member_id": me_id, "amount": "50"}, {"member_id": aru_id, "amount": "30"}]}, tb)
check("сумма долей ≠ сумме расхода → 400", s == 400, (s, d))
s, d = call("expenses.php", "POST", {"group_id": gid, "description": "X", "amount": "-5",
            "paid_by": me_id, "participant_ids": [me_id]}, ta)
check("отрицательная сумма → 400", s == 400, (s, d))
s, d = call("expenses.php", "POST", {"group_id": gid, "description": "X", "amount": "5",
            "paid_by": 999999, "participant_ids": [me_id]}, ta)
check("плательщик не из группы → 400", s == 400, (s, d))

# перевод долга
s, bal = call("balances.php?group_id=%d" % gid, token=ta)
mad_debt = [t for t in bal["settlements"] if t["from_name"] == "Аружан"]
tr = bal["settlements"][0]
s, d = call("expenses.php", "POST", {"group_id": gid, "kind": "payment", "amount": "%.2f" % (tr["amount_cents"] / 100),
            "paid_by": tr["from_id"], "participant_ids": [tr["to_id"]]}, ta)
check("перевод долга записывается (201)", s == 201, (s, d))
s, bal2 = call("balances.php?group_id=%d" % gid, token=ta)
check("после перевода сумма балансов по-прежнему 0", sum(x["net_cents"] for x in bal2["balances"]) == 0)
check("после перевода долгов стало меньше", sum(t["amount_cents"] for t in bal2["settlements"]) < sum(t["amount_cents"] for t in bal["settlements"]))

print("Поиск, сортировка, пагинация")
for i in range(12):
    call("expenses.php", "POST", {"group_id": gid, "description": f"Кофе {i+1}", "amount": str(10 + i),
         "paid_by": me_id, "participant_ids": [me_id]}, ta)
s, d = call("expenses.php?group_id=%d&per_page=5&page=1" % gid, token=ta)
check("пагинация: 5 на странице, несколько страниц", s == 200 and len(d["items"]) == 5 and d["total_pages"] >= 3, (d.get("total"), d.get("total_pages")))
s, d = call("expenses.php?group_id=%d&q=%%D0%%9A%%D0%%BE%%D1%%84%%D0%%B5" % gid, token=ta)
check("поиск по описанию («Кофе») находит 12", s == 200 and d["total"] == 12, d.get("total"))
s, d = call("expenses.php?group_id=%d&sort=amount_desc&per_page=3&kind=expense" % gid, token=ta)
am = [x["amount_cents"] for x in d["items"]]
check("сортировка по сумме по убыванию", am == sorted(am, reverse=True), am)
s, d = call("expenses.php?group_id=%d&q=%%25" % gid, token=ta)
check("символ % в поиске не ломает запрос", s == 200 and d["total"] == 0, (s, d.get("total")))
s, d = call("expenses.php?group_id=%d&sort=id;DROP+TABLE+users" % gid, token=ta)
check("SQL-инъекция через sort не проходит", s == 200, (s, d))
s, d = call("me.php", token=ta)
check("таблица users цела после попытки инъекции", s == 200)

print("Права доступа")
s, lst = call("expenses.php?group_id=%d&q=%%D0%%A2%%D0%%B0%%D0%%BA%%D1%%81%%D0%%B8" % gid, token=tb)
taxi = lst["items"][0]
check("участник видит расход, но не может удалить чужой", taxi["can_delete"] is False, taxi)
s, d = call("expenses.php?id=%d" % taxi["id"], "DELETE", token=tb)
check("удаление чужого расхода → 403", s == 403, (s, d))
s, d = call("groups.php?id=%d" % gid, "DELETE", token=tb)
check("удалить группу не создатель не может → 403", s == 403, (s, d))
s, d = call("admin.php", token=ta)
check("админка недоступна обычному пользователю → 403", s == 403, (s, d))
s, d = call("expenses.php?id=%d" % taxi["id"], "DELETE", token=ta)
check("создатель группы удаляет расход", s == 200, (s, d))
s, d = call("members.php?id=%d" % aru_id, "DELETE", token=ta)
check("нельзя удалить гостя, у которого есть расходы → 409", s == 409, (s, d))

print("XSS: опасный текст хранится как текст")
evil = "<script>alert(1)</script>"
s, d = call("expenses.php", "POST", {"group_id": gid, "description": evil, "amount": "1",
            "paid_by": me_id, "participant_ids": [me_id]}, ta)
s, lst = call("expenses.php?group_id=%d&q=script" % gid, token=ta)
check("текст сохраняется без выполнения (экранируется на выводе)", s == 200 and lst["items"] and lst["items"][0]["description"] == evil, lst.get("items"))

print("Выход")
s, d = call("logout.php", "POST", token=tb)
check("logout → ok", s == 200)
s, d = call("me.php", token=tb)
check("после выхода токен недействителен (401)", s == 401, (s, d))

print("Удаление группы")
s, d = call("groups.php?id=%d" % gid, "DELETE", token=ta)
check("создатель удаляет группу", s == 200, (s, d))
s, d = call("groups.php?id=%d" % gid, token=ta)
check("удалённая группа недоступна (404)", s == 404, (s, d))

print("\n%d из %d проверок пройдено" % (total - failed, total))
sys.exit(1 if failed else 0)
