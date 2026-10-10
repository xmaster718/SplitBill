"use strict";
/* Страница группы: баланс и расчёты, добавление расходов, история с поиском и пагинацией, участники. */
(function () {
  if (!Auth.ensure()) return;
  initTopbar();

  const groupId = Number(new URLSearchParams(location.search).get("id"));
  if (!Number.isInteger(groupId) || groupId <= 0) { location.replace("groups.html"); return; }

  const me = Auth.user() || {};
  const $ = (id) => document.getElementById(id);
  const state = { group: null, members: [], page: 1, perPage: 8, sort: "date_desc", q: "", payer: "", split: "equal", seen: new Set() };
  const MEMBER_FORMS = ["участник", "участника", "участников"];

  // ================= Участники =================

  async function loadMembers() {
    const data = await api("members.php", { query: { group_id: groupId } });
    state.members = data.members;
    renderMembers();
    renderSelects();
    renderParticipants();
  }

  function renderMembers() {
    const list = $("member-list");
    list.replaceChildren();
    $("members-count").textContent = `${state.members.length} ${plural(state.members.length, MEMBER_FORMS)}`;
    state.members.forEach((m) => {
      const canRemove = state.group && state.group.is_owner && m.user_id === null;
      list.append(h("li", { class: "list-item" },
        h("div", { class: "item-title" },
          m.name,
          m.user_id === me.id ? h("span", { class: "pill sage", text: "вы" }) : null,
          m.user_id === null ? h("span", { class: "pill", text: "без аккаунта" }) : null),
        canRemove ? h("button", { class: "link-btn danger", type: "button", text: "Убрать", onclick: () => removeMember(m) }) : null));
    });
  }

  function renderSelects() {
    const paid = $("paid_by");
    const keepPaid = paid.value;
    paid.replaceChildren(...state.members.map((m) => h("option", { value: m.id, text: m.name })));
    const mine = state.members.find((m) => m.user_id === me.id);
    paid.value = state.members.some((m) => String(m.id) === keepPaid) ? keepPaid : (mine ? String(mine.id) : "");

    const filter = $("filter-payer");
    const keepFilter = filter.value;
    filter.replaceChildren(
      h("option", { value: "", text: "Все плательщики" }),
      ...state.members.map((m) => h("option", { value: m.id, text: m.name }))
    );
    filter.value = keepFilter;
  }

  async function removeMember(m) {
    if (!confirm(`Убрать участника «${m.name}»?`)) return;
    try {
      await api("members.php", { method: "DELETE", query: { id: m.id } });
      await loadMembers();
      toast("Участник удалён");
    } catch (e) { toast(e.message, true); }
  }

  $("member-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const input = $("member-name");
    const name = input.value.trim();
    if (!name) return;
    try {
      await api("members.php", { method: "POST", body: { group_id: groupId, name } });
      input.value = "";
      await loadMembers();
      await loadBalances();
      toast("Участник добавлен");
    } catch (err) { toast(err.message, true); }
  });

  // ================= Форма расхода =================

  function checkedBoxes() {
    return [...document.querySelectorAll("#participants input[type=checkbox]:checked")];
  }

  function renderParticipants() {
    const box = $("participants");
    const prev = new Set(checkedBoxes().map((c) => c.value));
    box.replaceChildren();
    state.members.forEach((m) => {
      // Новый участник отмечен сразу; у остальных сохраняем выбор пользователя.
      const isChecked = !state.seen.has(m.id) || prev.has(String(m.id));
      const cb = h("input", { type: "checkbox", id: "p-" + m.id, value: m.id, checked: isChecked });
      const amount = h("input", {
        class: "input share-input", type: "text", inputmode: "decimal", placeholder: "0",
        "aria-label": "Сумма для " + m.name, autocomplete: "off",
      });
      cb.addEventListener("change", updateSplitHint);
      amount.addEventListener("input", updateSplitHint);
      box.append(h("div", { class: "participant" }, h("label", { for: "p-" + m.id }, cb, m.name), amount));
      state.seen.add(m.id);
    });
    box.classList.toggle("exact", state.split === "exact");
    updateSplitHint();
  }

  function updateSplitHint() {
    const hint = $("split-hint");
    hint.className = "split-hint";
    const total = toCents($("amount").value);
    const boxes = checkedBoxes();

    if (state.split === "equal") {
      hint.textContent = total && boxes.length
        ? `≈ по ${fmtMoney(Math.ceil(total / boxes.length))} с человека (${boxes.length} ${plural(boxes.length, MEMBER_FORMS)})`
        : "";
      return;
    }
    let sum = 0;
    boxes.forEach((cb) => {
      const v = toCents(cb.closest(".participant").querySelector(".share-input").value);
      if (v) sum += v;
    });
    if (!total) { hint.textContent = "Сначала введите общую сумму расхода"; return; }
    const diff = total - sum;
    if (diff === 0) {
      hint.textContent = "Распределено полностью";
      hint.classList.add("ok");
    } else {
      hint.textContent = `Распределено ${fmtMoney(sum)} из ${fmtMoney(total)}. ${diff > 0 ? "Осталось" : "Лишнее"}: ${fmtMoney(Math.abs(diff))}`;
      hint.classList.add("bad");
    }
  }

  document.querySelectorAll("input[name=split]").forEach((radio) => {
    radio.addEventListener("change", () => {
      state.split = radio.value;
      $("participants").classList.toggle("exact", state.split === "exact");
      updateSplitHint();
    });
  });
  $("amount").addEventListener("input", updateSplitHint);

  $("expense-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const err = $("expense-error");
    err.textContent = "";

    const description = $("description").value.trim();
    const amountStr = $("amount").value.trim();
    const total = toCents(amountStr);
    const boxes = checkedBoxes();

    if (!description) { err.textContent = "Введите описание"; return; }
    if (total === null) { err.textContent = "Введите сумму больше нуля, например 1500 или 1500,50"; return; }
    if (!boxes.length) { err.textContent = "Отметьте хотя бы одного участника"; return; }
    if (!$("paid_by").value) { err.textContent = "Выберите, кто заплатил"; return; }

    const body = {
      group_id: groupId, kind: "expense", description, amount: amountStr,
      paid_by: Number($("paid_by").value), spent_on: $("spent_on").value || todayStr(),
    };

    if (state.split === "exact") {
      let sum = 0;
      body.shares = [];
      for (const cb of boxes) {
        const raw = cb.closest(".participant").querySelector(".share-input").value;
        const cents = toCents(raw);
        if (cents === null) { err.textContent = "Укажите долю для каждого отмеченного участника (больше нуля)"; return; }
        sum += cents;
        body.shares.push({ member_id: Number(cb.value), amount: raw });
      }
      if (sum !== total) { err.textContent = "Сумма долей должна равняться сумме расхода"; return; }
    } else {
      body.participant_ids = boxes.map((cb) => Number(cb.value));
    }

    const btn = e.target.querySelector("button[type=submit]");
    setBusy(btn, true);
    try {
      await api("expenses.php", { method: "POST", body });
      $("description").value = "";
      $("amount").value = "";
      state.page = 1;
      renderParticipants();
      toast("Расход добавлен");
      await refresh();
    } catch (ex) {
      err.textContent = ex.message;
    } finally {
      setBusy(btn, false);
    }
  });

  // ================= История расходов =================

  async function loadExpenses() {
    const data = await api("expenses.php", {
      query: { group_id: groupId, page: state.page, per_page: state.perPage, sort: state.sort, q: state.q, paid_by: state.payer },
    });
    state.page = data.page;
    renderExpenses(data);
  }

  function renderExpenses(data) {
    const list = $("expense-list");
    list.replaceChildren();
    if (!data.items.length) {
      list.append(h("li", { class: "empty", text: state.q || state.payer ? "Ничего не найдено" : "Пока нет расходов. Добавьте первый выше." }));
    }
    data.items.forEach((x) => {
      const isPayment = x.kind === "payment";
      const who = isPayment
        ? `${x.paid_by_name} → ${x.shares[0] ? x.shares[0].name : "?"}`
        : `Заплатил(а) ${x.paid_by_name}`;
      const split = isPayment ? "" : x.shares.map((s) => `${s.name} ${fmtMoney(s.share_cents)}`).join(" · ");
      list.append(h("li", { class: "list-item" },
        h("div", { class: "grow" },
          h("div", { class: "item-title" }, x.description, isPayment ? h("span", { class: "pill sage", text: "перевод" }) : null),
          h("div", { class: "muted small", text: `${fmtDate(x.spent_on)} · ${who}` }),
          split ? h("div", { class: "muted small", text: split }) : null),
        h("div", { class: "right" },
          h("div", { class: "amount", text: fmtMoney(x.amount_cents) }),
          x.can_delete ? h("button", { class: "link-btn danger", type: "button", text: "Удалить", onclick: () => removeExpense(x) }) : null)));
    });
    $("page-info").textContent = `Стр. ${data.page} из ${data.total_pages}`;
    $("prev").disabled = data.page <= 1;
    $("next").disabled = data.page >= data.total_pages;
    $("expenses-total").textContent = `Всего потрачено: ${fmtMoney(data.group_total_cents)}`;
  }

  async function removeExpense(x) {
    if (!confirm(`Удалить «${x.description}» на ${fmtMoney(x.amount_cents)}?`)) return;
    try {
      await api("expenses.php", { method: "DELETE", query: { id: x.id } });
      toast("Запись удалена");
      await refresh();
    } catch (e) { toast(e.message, true); }
  }

  $("search").addEventListener("input", debounce((e) => { state.q = e.target.value.trim(); state.page = 1; loadExpenses().catch((x) => toast(x.message, true)); }, 300));
  $("filter-payer").addEventListener("change", (e) => { state.payer = e.target.value; state.page = 1; loadExpenses().catch((x) => toast(x.message, true)); });
  $("sort").addEventListener("change", (e) => { state.sort = e.target.value; state.page = 1; loadExpenses().catch((x) => toast(x.message, true)); });
  $("prev").addEventListener("click", () => { state.page = Math.max(1, state.page - 1); loadExpenses().catch((x) => toast(x.message, true)); });
  $("next").addEventListener("click", () => { state.page += 1; loadExpenses().catch((x) => toast(x.message, true)); });

  // ================= Баланс =================

  async function loadBalances() {
    const data = await api("balances.php", { query: { group_id: groupId } });
    $("balance-total").textContent = `Всего потрачено: ${fmtMoney(data.total_cents)}`;

    const list = $("balances");
    list.replaceChildren();
    data.balances.forEach((b) => {
      const cls = b.net_cents > 0 ? "pos" : b.net_cents < 0 ? "neg" : "";
      const status = b.net_cents > 0 ? "ему должны" : b.net_cents < 0 ? "должен(на)" : "в расчёте";
      list.append(h("li", { class: "list-item" },
        h("div", { class: "grow" },
          h("div", { class: "item-title", text: b.name + (b.is_me ? " (вы)" : "") }),
          h("div", { class: "muted small", text: `заплатил(а) ${fmtMoney(b.paid_cents)} · доля ${fmtMoney(b.owed_cents)}` })),
        h("div", { class: "right" },
          h("div", { class: "amount " + cls, text: (b.net_cents > 0 ? "+" : "") + fmtMoney(b.net_cents) }),
          h("div", { class: "muted small", text: status }))));
    });

    const box = $("settlements");
    box.replaceChildren();
    if (!data.settlements.length) {
      box.append(h("p", { class: "empty", text: data.total_cents > 0 ? "Все в расчёте. Никто никому не должен." : "Добавьте расходы, и здесь появится, кто кому должен." }));
      return;
    }
    box.append(h("p", { class: "muted small", text: "Чтобы закрыть все долги, достаточно этих переводов:" }));
    data.settlements.forEach((s) => {
      box.append(h("div", { class: "settlement" },
        h("span", {}, h("b", { text: s.from_name }), " → ", h("b", { text: s.to_name })),
        h("span", { class: "amount", text: fmtMoney(s.amount_cents) }),
        h("button", { class: "btn btn-sm", type: "button", text: "Отметить оплаченным", onclick: () => settle(s) })));
    });
  }

  async function settle(s) {
    if (!confirm(`Записать перевод: ${s.from_name} → ${s.to_name}, ${fmtMoney(s.amount_cents)}?`)) return;
    try {
      await api("expenses.php", { method: "POST", body: {
        group_id: groupId, kind: "payment", amount: (s.amount_cents / 100).toFixed(2),
        paid_by: s.from_id, participant_ids: [s.to_id], spent_on: todayStr(),
      } });
      toast("Перевод записан");
      await refresh();
    } catch (e) { toast(e.message, true); }
  }

  function refresh() {
    return Promise.all([loadExpenses(), loadBalances()]);
  }

  // ================= Приглашение и удаление группы =================

  $("copy-code").addEventListener("click", async () => {
    try { await navigator.clipboard.writeText(state.group.invite_code); toast("Код скопирован"); }
    catch (e) { toast("Скопируйте код вручную: " + state.group.invite_code); }
  });

  $("copy-link").addEventListener("click", async () => {
    const link = new URL("groups.html?join=" + state.group.invite_code, location.href).href;
    try { await navigator.clipboard.writeText(link); toast("Ссылка скопирована"); }
    catch (e) { toast("Скопируйте ссылку вручную: " + link); }
  });

  $("delete-group").addEventListener("click", async () => {
    if (!confirm(`Удалить группу «${state.group.name}» вместе со всеми расходами? Это нельзя отменить.`)) return;
    try {
      await api("groups.php", { method: "DELETE", query: { id: groupId } });
      location.replace("groups.html");
    } catch (e) { toast(e.message, true); }
  });

  // ================= Запуск =================

  (async function init() {
    try {
      const { group } = await api("groups.php", { query: { id: groupId } });
      state.group = group;
      $("group-title").textContent = group.name;
      $("invite-code").textContent = group.invite_code;
      document.title = group.name + " — SplitBill";
      if (group.is_owner) $("danger-zone").classList.remove("hidden");
      $("spent_on").value = todayStr();
      await loadMembers();
      await refresh();
    } catch (e) {
      toast(e.message, true);
      setTimeout(() => location.replace("groups.html"), 1600);
    }
  })();
})();
