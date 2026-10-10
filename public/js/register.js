"use strict";
(function () {
  if (Auth.token()) { location.replace("groups.html"); return; }

  const form = document.getElementById("register-form");
  const errorEl = document.getElementById("form-error");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    errorEl.textContent = "";
    const f = form.elements;   // обращаемся к полям через form.elements: так надёжнее, чем form.name
    const name = f.name.value.trim();
    const email = f.email.value.trim();
    const password = f.password.value;

    // Проверка на клиенте (сервер проверит всё то же самое ещё раз)
    if (name.length < 2) { errorEl.textContent = "Введите имя (минимум 2 символа)"; return; }
    if (!/^\S+@\S+\.\S+$/.test(email)) { errorEl.textContent = "Введите корректный email"; return; }
    if (password.length < 8) { errorEl.textContent = "Пароль должен быть не короче 8 символов"; return; }
    if (password !== f.password2.value) { errorEl.textContent = "Пароли не совпадают"; return; }

    const btn = form.querySelector("button[type=submit]");
    setBusy(btn, true);
    try {
      const data = await api("register.php", { method: "POST", body: { name, email, password } });
      Auth.save(data.token, data.user);
      location.href = "groups.html";
    } catch (err) {
      errorEl.textContent = err.message;
    } finally {
      setBusy(btn, false);
    }
  });
})();
