const form = document.getElementById("login-form");
const errorEl = document.getElementById("error");

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  errorEl.textContent = "";

  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value;

  try {
    const res = await fetch(`${API_URL}/auth/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    });

    if (!res.ok) throw new Error("Неверный email или пароль");

    const data = await res.json(); // ожидаем { token, user }
    saveSession(data.token, data.user);
    window.location.href = "groups.html";
  } catch (err) {
    errorEl.textContent = err.message || "Ошибка входа. Backend запущен?";
    console.error(err);
  }
});
