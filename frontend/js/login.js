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

    if (!res.ok) {
      throw new Error("Неверный email или пароль");
    }

    const payload = await res.json();
    const token = payload.token || payload.accessToken || payload.data?.token || payload.user?.token;
    const user = payload.user || payload.profile || payload.data?.user || {
      name: email.split("@")[0],
      email,
    };

    if (!token) {
      throw new Error("Сервер не вернул токен авторизации");
    }

    saveSession(token, user);
    window.location.href = "groups.html";
  } catch (err) {
    errorEl.textContent = err.message || "Ошибка входа. Backend запущен?";
    console.error(err);
  }
});