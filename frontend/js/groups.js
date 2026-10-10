requireAuth(); // если не вошёл — перебросит на login.html

const form = document.getElementById("create-group-form");
const list = document.getElementById("group-list");
const errorEl = document.getElementById("error");
const emptyState = document.getElementById("empty-state");
const logoutBtn = document.getElementById("logout-btn");
const searchInput = document.getElementById("group-search");

const mockGroups = [
  { id: 1, name: "Поездка в Алматы", members: ["Алина", "Борис", "Сергей"] },
  { id: 2, name: "Домашние покупки", members: ["Марина", "Иван"] },
  { id: 3, name: "Командировка", members: ["Аня", "Роман", "Петр", "Лена"] },
];

let cachedGroups = [];

logoutBtn.addEventListener("click", logout);

function filterGroups(groups, query) {
  const value = query.trim().toLowerCase();
  if (!value) return groups;
  return groups.filter((group) => (group.name || "").toLowerCase().includes(value));
}

function renderGroups(groups) {
  list.innerHTML = "";

  if (groups.length === 0) {
    emptyState.style.display = "block";
    return;
  }

  emptyState.style.display = "none";

  groups.forEach((group, index) => {
    const li = document.createElement("li");
    li.className = "group-item";

    const content = document.createElement("div");
    content.className = "group-content";

    const link = document.createElement("a");
    link.href = `group.html?id=${group.id ?? index + 1}`;
    link.textContent = group.name || "Без названия";
    link.className = "group-link";

    const meta = document.createElement("span");
    meta.className = "group-meta";
    const memberCount = Array.isArray(group.members) ? group.members.length : 0;
    meta.textContent = `${memberCount} участника${memberCount === 1 ? "" : "ов"}`;

    content.appendChild(link);
    content.appendChild(meta);
    li.appendChild(content);
    list.appendChild(li);
  });
}

async function loadGroups() {
  errorEl.textContent = "";

  try {
    const res = await fetch(`${API_URL}/groups`, {
      headers: { ...authHeader() },
    });

    if (!res.ok) {
      throw new Error("Не удалось загрузить группы");
    }

    const payload = await res.json();
    cachedGroups = getResponseData(payload, mockGroups);
    renderGroups(filterGroups(cachedGroups, searchInput.value));
  } catch (err) {
    cachedGroups = mockGroups;
    renderGroups(filterGroups(cachedGroups, searchInput.value));
    errorEl.textContent = "Backend недоступен, показаны демо-данные.";
    console.error(err);
  }
}

searchInput.addEventListener("input", (event) => {
  renderGroups(filterGroups(cachedGroups, event.target.value));
});

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  errorEl.textContent = "";

  const name = document.getElementById("group-name").value.trim();
  if (!name) {
    errorEl.textContent = "Введите название группы.";
    return;
  }

  try {
    const res = await fetch(`${API_URL}/groups`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeader() },
      body: JSON.stringify({ name }),
    });

    if (!res.ok) {
      throw new Error("Не удалось создать группу");
    }

    const payload = await res.json();
    const createdGroup = payload.group || payload || { id: Date.now(), name, members: ["Вы"] };
    cachedGroups = [createdGroup, ...cachedGroups];
    form.reset();
    renderGroups(filterGroups(cachedGroups, searchInput.value));
  } catch (err) {
    const fallbackGroup = { id: Date.now(), name, members: ["Вы"] };
    cachedGroups = [fallbackGroup, ...cachedGroups];
    form.reset();
    renderGroups(filterGroups(cachedGroups, searchInput.value));
    errorEl.textContent = "Группа добавлена локально. Подключите backend для синхронизации.";
    console.error(err);
  }
});

loadGroups();