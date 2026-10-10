// Адрес backend API. Для локального теста оставьте так.
// Когда бэкенд задеплоят — поменяете на реальный адрес (например, https://ваш-backend.onrender.com/api)
const API_URL = (() => {
  const configuredUrl = localStorage.getItem("apiUrl");
  if (configuredUrl) {
    return configuredUrl.replace(/\/+$/, "");
  }

  const isLocalHost = ["localhost", "127.0.0.1"].includes(window.location.hostname);
  return isLocalHost ? "http://localhost:4000/api" : "/api";
})();

function saveSession(token, user) {
  localStorage.setItem("token", token);
  localStorage.setItem("user", JSON.stringify(user));
}

function getToken() {
  return localStorage.getItem("token");
}

function getUser() {
  const raw = localStorage.getItem("user");
  if (!raw) return null;

  try {
    return JSON.parse(raw);
  } catch (error) {
    console.warn("Не удалось распарсить данные пользователя из localStorage", error);
    return null;
  }
}

function logout() {
  localStorage.removeItem("token");
  localStorage.removeItem("user");
  window.location.href = "login.html";
}

function authHeader() {
  const token = getToken();
  return token ? { Authorization: `Bearer ${token}` } : {};
}

function getResponseData(payload, fallback = []) {
  if (Array.isArray(payload)) return payload;
  if (payload && Array.isArray(payload.data)) return payload.data;
  if (payload && Array.isArray(payload.items)) return payload.items;
  if (payload && Array.isArray(payload.groups)) return payload.groups;
  if (payload && Array.isArray(payload.expenses)) return payload.expenses;
  return fallback;
}

// Вызывайте в начале страниц, доступных только вошедшим пользователям
function requireAuth() {
  if (!getToken()) {
    window.location.href = "login.html";
  }
}