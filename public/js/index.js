"use strict";
/* Главная: быстрый калькулятор (работает без входа) и переключение кнопок для вошедших. */
(function () {
  const $ = (id) => document.getElementById(id);

  if (Auth.token()) {
    $("nav-login").classList.add("hidden");
    $("nav-register").classList.add("hidden");
    $("nav-app").classList.remove("hidden");
    $("cta-primary").textContent = "Открыть мои группы";
    $("cta-primary").setAttribute("href", "groups.html");
  }

  function recalc() {
    const bill = toCents($("calc-bill").value);
    const tip = Math.min(100, Math.max(0, Number($("calc-tip").value) || 0));
    const people = Math.min(100, Math.max(1, Math.floor(Number($("calc-people").value) || 1)));
    if (bill === null) {
      $("calc-each").textContent = "—";
      $("calc-note").textContent = "Введите сумму счёта, например 18500.";
      return;
    }
    const total = bill + Math.round((bill * tip) / 100);
    const each = Math.ceil(total / people);
    $("calc-each").textContent = fmtMoney(each);
    $("calc-note").textContent =
      `Всего с чаевыми ${fmtMoney(total)} на ${people} ${plural(people, ["человека", "человек", "человек"])}.`;
  }

  ["calc-bill", "calc-tip", "calc-people"].forEach((id) => $(id).addEventListener("input", recalc));
  recalc();
})();
