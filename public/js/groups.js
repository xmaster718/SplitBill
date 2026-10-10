"use strict";
(function () {
  // Ссылка-приглашение вида groups.html?join=K7M2QX9A: запоминаем код, даже если сначала нужно войти.
  const joinParam = new URLSearchParams(location.search).get("join");
  if (joinParam) sessionStorage.setItem("sb_join", joinParam.toUpperCase().slice(0, 8));

  if (!Auth.ensure()) return;
  initTopbar();

  const $ = (id) => document.getElementById(id);

  const pending = sessionStorage.getItem("sb_join");
  if (pending) {
    $("join-code").value = pending;
    sessionStorage.removeItem("sb_join");
    toast("Код приглашения подставлен. Нажмите «Войти в группу».");
  }

  async function loadGroups() {
    const box = $("group-list");
    try {
      const { groups } = await api("groups.php");
      box.replaceChildren();
      if (!groups.length) {
        box.append(h("p", { class: "empty", text: "У вас пока нет групп. Создайте первую или войдите по коду приглашения." }));
        return;
      }
      groups.forEach((g) => {
        box.append(h("a", { class: "group-card", href: "group.html?id=" + g.id },
          h("div", { class: "group-name", text: g.name }),
          h("div", { class: "muted small",
            text: `${g.members_count} ${plural(g.members_count, ["участник", "участника", "участников"])} · потрачено ${fmtMoney(g.total_cents)}${g.is_owner ? " · вы создатель" : ""}` })));
      });
    } catch (e) {
      box.replaceChildren(h("p", { class: "form-error", text: e.message }));
    }
  }

  $("create-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    $("create-error").textContent = "";
    const name = $("group-name").value.trim();
    if (name.length < 2) { $("create-error").textContent = "Название должно быть не короче 2 символов"; return; }
    const btn = e.target.querySelector("button");
    setBusy(btn, true);
    try {
      const data = await api("groups.php", { method: "POST", body: { name } });
      location.href = "group.html?id=" + data.id;
    } catch (err) {
      $("create-error").textContent = err.message;
      setBusy(btn, false);
    }
  });

  $("join-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    $("join-error").textContent = "";
    const code = $("join-code").value.trim().toUpperCase();
    if (code.length !== 8) { $("join-error").textContent = "Код состоит из 8 символов"; return; }
    const btn = e.target.querySelector("button");
    setBusy(btn, true);
    try {
      const data = await api("join.php", { method: "POST", body: { code } });
      location.href = "group.html?id=" + data.group_id;
    } catch (err) {
      $("join-error").textContent = err.message;
      setBusy(btn, false);
    }
  });

  loadGroups();
})();
