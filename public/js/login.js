"use strict";
(function () {
  if (Auth.token()) { location.replace("groups.html"); return; }

  const form = document.getElementById("login-form");
  const errorEl = document.getElementById("form-error");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    errorEl.textContent = "";
    const email = form.elements.email.value.trim();
    const password = form.elements.password.value;
    if (!email || !password) { errorEl.textContent = "Введите email и пароль"; return; }

    const btn = form.querySelector("button[type=submit]");
    setBusy(btn, true);
    try {
      const data = await api("login.php", { method: "POST", body: { email, password } });
      Auth.save(data.token, data.user);
      location.href = "groups.html";
    } catch (err) {
      errorEl.textContent = err.message;
    } finally {
      setBusy(btn, false);
    }
  });
})();
