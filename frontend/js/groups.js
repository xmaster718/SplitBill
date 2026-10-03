requireAuth(); // если не вошёл — перебросит на login.html

const form = document.getElementById("create-group-form");
const list = document.getElementById("group-list");
const errorEl = document.getElementById("error");
const emptyState = document.getElementById("empty-state");
const logoutBtn = document.getElementById("logout-btn");

logoutBtn.addEventListener("click", logout);

function renderGroups(groups) {
  list.innerHTML = "";

  if (groups.length === 0) {
    emptyState.style.display = "block";
    return;
  }
  emptyState.style.display = "none";

  groups.forEach((group) => {
    const li = document.createElement("li");

    const link = document.createElement("a");
    link.href = `group.html?id=${group.id}`;
    link.textContent = group.name;
    link.style.textDecoration = "none";
    link.style.color = "inherit";
    link.style.fontWeight = "500";

    li.appendChild(link);
    list.appendChild(li);
  });
}

async function loadGroups() {
  errorEl.textContent = "";
  try {
    const res = await fetch(`${API_URL}/groups`, {
      headers: { ...authHeader() },
    });
    if (!res.ok) throw new Error("Не удалось загрузить группы");
    const groups = await res.json();
    renderGroups(groups);
  } catch (err) {
    errorEl.textContent = "Ошибка загрузки групп. Backend запущен?";
    console.error(err);
  }
}

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  errorEl.textContent = "";

  const name = document.getElementById("group-name").value.trim();
  if (!name) return;

  try {
    const res = await fetch(`${API_URL}/groups`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeader() },
      body: JSON.stringify({ name }),
    });
    if (!res.ok) throw new Error("Не удалось создать группу");

    form.reset();
    loadGroups();
  } catch (err) {
    errorEl.textContent = "Ошибка при создании группы.";
    console.error(err);
  }
});

loadGroups();
