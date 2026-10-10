"use strict";
/* Общие функции для всех страниц: запросы к API, вход/выход, безопасное создание элементов. */

const TOKEN_KEY = "sb_token";
const USER_KEY = "sb_user";
const CURRENCY = "₸";

const Auth = {
  token: () => localStorage.getItem(TOKEN_KEY),
  user() {
    try { return JSON.parse(localStorage.getItem(USER_KEY)); } catch (e) { return null; }
  },
  save(token, user) {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  },
  clear() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
  },
  /** true, если пользователь вошёл; иначе отправляет на страницу входа. */
  ensure() {
    if (Auth.token()) return true;
    location.replace("login.html");
    return false;
  },
  async logout() {
    try { await api("logout.php", { method: "POST" }); } catch (e) { /* неважно */ }
    Auth.clear();
    location.replace("login.html");
  },
};

/** Запрос к API. Возвращает разобранный JSON или бросает Error с понятным текстом. */
async function api(path, { method = "GET", body, query } = {}) {
  let url = "api/" + path;
  if (query) {
    const qs = new URLSearchParams();
    Object.entries(query).forEach(([k, v]) => { if (v !== "" && v != null) qs.set(k, v); });
    const s = qs.toString();
    if (s) url += (url.includes("?") ? "&" : "?") + s;
  }
  const headers = {};
  const token = Auth.token();
  if (token) {
    headers["Authorization"] = "Bearer " + token;
    headers["X-Auth-Token"] = token;      // запасной вариант для хостингов, которые вырезают Authorization
  }
  if (body !== undefined) headers["Content-Type"] = "application/json";

  let res;
  try {
    res = await fetch(url, { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined });
  } catch (e) {
    throw new Error("Нет связи с сервером. Проверьте, что он запущен.");
  }
  let data = null;
  try { data = await res.json(); } catch (e) { /* пустой ответ */ }

  if (res.status === 401 && token) {
    Auth.clear();
    location.replace("login.html");
    throw new Error("Сессия истекла, войдите снова");
  }
  if (!res.ok) throw new Error((data && data.error) || "Ошибка сервера (" + res.status + ")");
  return data;
}

/**
 * Безопасное создание элементов. Текст всегда вставляется как текст (textContent),
 * а не как HTML — поэтому «<script>» от пользователя никогда не выполнится (защита от XSS).
 */
function h(tag, attrs = {}, ...children) {
  const el = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs)) {
    if (value == null || value === false) continue;
    if (key === "class") el.className = value;
    else if (key === "text") el.textContent = value;
    else if (key.startsWith("on") && typeof value === "function") el.addEventListener(key.slice(2), value);
    else el.setAttribute(key, value === true ? "" : value);
  }
  for (const child of children.flat()) {
    if (child == null || child === false) continue;
    el.append(child);
  }
  return el;
}

function fmtMoney(cents) {
  const whole = cents % 100 === 0;
  return (cents / 100).toLocaleString("ru-RU", {
    minimumFractionDigits: whole ? 0 : 2,
    maximumFractionDigits: 2,
  }) + " " + CURRENCY;
}

function fmtDate(iso) {
  const [y, m, d] = String(iso).split("-");
  return d && m && y ? `${d}.${m}.${y}` : iso;
}

function todayStr() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

/** Ввод пользователя ("1 500,50") → центы или null. Те же правила, что и на сервере. */
function toCents(str) {
  const s = String(str).replace(/[\s\u00A0]/g, "").replace(",", ".");
  if (!/^\d{1,10}(\.\d{1,2})?$/.test(s)) return null;
  const [w, f = ""] = s.split(".");
  const cents = parseInt(w, 10) * 100 + parseInt(f.padEnd(2, "0"), 10);
  return cents > 0 ? cents : null;
}

function plural(n, forms) {
  const a = Math.abs(n) % 100, b = a % 10;
  if (a > 10 && a < 20) return forms[2];
  if (b > 1 && b < 5) return forms[1];
  if (b === 1) return forms[0];
  return forms[2];
}

function toast(message, isError = false) {
  let box = document.getElementById("toasts");
  if (!box) { box = h("div", { class: "toasts", id: "toasts" }); document.body.append(box); }
  const t = h("div", { class: "toast" + (isError ? " error" : ""), role: "status", text: message });
  box.append(t);
  setTimeout(() => t.remove(), 3500);
}

function setBusy(button, busy) {
  if (!button) return;
  button.disabled = busy;
}

function debounce(fn, ms) {
  let timer;
  return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
}

/** Шапка приложения: имя пользователя, ссылка на админку (если админ), кнопка выхода. */
function initTopbar() {
  const name = document.getElementById("user-name");
  const admin = document.getElementById("admin-link");
  const out = document.getElementById("logout-btn");
  if (out) out.addEventListener("click", Auth.logout);

  const apply = (user) => {
    if (!user) return;
    if (name) name.textContent = user.name;
    if (admin) admin.classList.toggle("hidden", user.role !== "admin");
  };
  apply(Auth.user());
  // Обновляем данные пользователя с сервера (заодно проверяется, что сессия ещё действует)
  api("me.php").then((data) => { Auth.save(Auth.token(), data.user); apply(data.user); }).catch(() => {});
}
