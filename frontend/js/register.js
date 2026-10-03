const form = document.getElementById("register-form");
const errorEl = document.getElementById("error");

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  errorEl.textContent = "";

  const name = document.getElementById("name").value.trim();
  const email = document.getElementById("email").value.trim();
  const password = document.getElementById("password").value;

  try {
    const res = await fetch(`${API_URL}/auth/register`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ name, email, password }),
    });

    if (!res.ok) throw new Error("Не удалось зарегистрироваться");

    const data = await res.json(); // ожидаем { token, user }
    saveSession(data.token, data.user);
    window.location.href = "groups.html";
  } catch (err) {
    errorEl.textContent = err.message || "Ошибка регистрации. Backend запущен?";
    console.error(err);
  }
});
