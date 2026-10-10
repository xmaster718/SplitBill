"use strict";
(function () {
  if (!Auth.ensure()) return;
  initTopbar();

  const $ = (id) => document.getElementById(id);
  const me = Auth.user() || {};

  function header(...titles) {
    return h("thead", {}, h("tr", {}, titles.map((t) => h("th", { text: t }))));
  }

  async function remove(type, id, label) {
    if (!confirm(`Удалить «${label}»? Это действие нельзя отменить.`)) return;
    try {
      await api("admin.php", { method: "DELETE", query: { type, id } });
      toast("Удалено");
      load();
    } catch (e) { toast(e.message, true); }
  }

  async function load() {
    try {
      const data = await api("admin.php");

      $("stats").replaceChildren(
        ...[["Пользователей", data.stats.users], ["Групп", data.stats.groups], ["Записей о расходах", data.stats.expenses]]
          .map(([label, n]) => h("div", { class: "stat" }, h("b", { text: String(n) }), h("span", { class: "muted small", text: label })))
      );

      $("users-table").replaceChildren(
        header("ID", "Имя", "Email", "Роль", "Групп", "Создан", ""),
        h("tbody", {}, data.users.map((u) => h("tr", {},
          h("td", { text: u.id }), h("td", { text: u.name }), h("td", { text: u.email }),
          h("td", {}, h("span", { class: "pill" + (u.role === "admin" ? " sage" : ""), text: u.role })),
          h("td", { text: u.groups_count }), h("td", { text: u.created_at }),
          h("td", {}, Number(u.id) === me.id ? null : h("button", { class: "link-btn danger", text: "Удалить", onclick: () => remove("user", u.id, u.email) }))
        )))
      );

      $("groups-table").replaceChildren(
        header("ID", "Название", "Создатель", "Участников", "Записей", "Создана", ""),
        h("tbody", {}, data.groups.map((g) => h("tr", {},
          h("td", { text: g.id }), h("td", { text: g.name }), h("td", { text: g.owner_name }),
          h("td", { text: g.members_count }), h("td", { text: g.expenses_count }), h("td", { text: g.created_at }),
          h("td", {}, h("button", { class: "link-btn danger", text: "Удалить", onclick: () => remove("group", g.id, g.name) }))
        )))
      );
    } catch (e) {
      toast(e.message, true);
      setTimeout(() => location.replace("groups.html"), 1500);
    }
  }

  load();
})();
