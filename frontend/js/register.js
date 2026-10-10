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

    if (!res.ok) {
      throw new Error("Не удалось зарегистрироваться");
    }

    const payload = await res.json();
    const token = payload.token || payload.accessToken || payload.data?.token || payload.user?.token;
    const user = payload.user || payload.profile || payload.data?.user || { name, email };

    if (!token) {
      throw new Error("Сервер не вернул токен авторизации");
    }

    saveSession(token, user);
    window.location.href = "groups.html";
  } catch (err) {
    errorEl.textContent = err.message || "Ошибка регистрации. Backend запущен?";
    console.error(err);
  }
});