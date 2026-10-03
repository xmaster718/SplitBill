// Адрес backend API. Для локального теста оставьте так.
// Когда бэкенд задеплоят — поменяете на реальный адрес (например, https://ваш-backend.onrender.com/api)
const API_URL = "http://localhost:4000/api";

function saveSession(token, user) {
  localStorage.setItem("token", token);
  localStorage.setItem("user", JSON.stringify(user));
}

function getToken() {
  return localStorage.getItem("token");
}

function getUser() {
  const raw = localStorage.getItem("user");
  return raw ? JSON.parse(raw) : null;
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

// Вызывайте в начале страниц, доступных только вошедшим пользователям
function requireAuth() {
  if (!getToken()) {
    window.location.href = "login.html";
  }
}
