requireAuth();

const params = new URLSearchParams(window.location.search);
const groupId = Number(params.get("id") || 1);
const groupNameEl = document.getElementById("group-name");
const totalSumEl = document.getElementById("total-sum");
const membersCountEl = document.getElementById("members-count");
const expenseList = document.getElementById("expense-list");
const expenseForm = document.getElementById("expense-form");
const expenseNameInput = document.getElementById("expense-name");
const expenseAmountInput = document.getElementById("expense-amount");
const searchInput = document.getElementById("expense-search");
const errorEl = document.getElementById("error");
const emptyState = document.getElementById("empty-state");
const logoutBtn = document.getElementById("logout-btn");

const mockGroups = [
  { id: 1, name: "Поездка в Алматы", members: ["Алина", "Борис", "Сергей"], expenses: [
    { id: 1, description: "Такси", amount: 2400 },
    { id: 2, description: "Питание", amount: 4600 },
  ] },
  { id: 2, name: "Домашние покупки", members: ["Марина", "Иван"], expenses: [
    { id: 1, description: "Мука и яйца", amount: 1450 },
    { id: 2, description: "Уборка", amount: 900 },
  ] },
  { id: 3, name: "Командировка", members: ["Аня", "Роман", "Петр", "Лена"], expenses: [
    { id: 1, description: "Отель", amount: 18000 },
    { id: 2, description: "Ж/Д билеты", amount: 9600 },
  ] },
];

let cachedExpenses = [];

logoutBtn.addEventListener("click", logout);

function filterExpenses(expenses, query) {
  const value = query.trim().toLowerCase();
  if (!value) return expenses;
  return expenses.filter((expense) => (expense.description || "").toLowerCase().includes(value));
}

function renderExpenses(expenses) {
  expenseList.innerHTML = "";

  if (expenses.length === 0) {
    emptyState.style.display = "block";
    totalSumEl.textContent = "0 ₸";
    return;
  }

  emptyState.style.display = "none";
  const total = expenses.reduce((sum, expense) => sum + Number(expense.amount || 0), 0);
  totalSumEl.textContent = `${total.toLocaleString("ru-RU")} ₸`;

  expenses.forEach((expense) => {
    const li = document.createElement("li");
    li.className = "expense-item";

    const content = document.createElement("div");
    content.className = "expense-content";

    const name = document.createElement("span");
    name.className = "expense-link";
    name.textContent = expense.description || "Новый расход";

    const meta = document.createElement("span");
    meta.className = "expense-meta";
    meta.textContent = expense.paidBy ? `Оплатил: ${expense.paidBy}` : "Участник";

    const amount = document.createElement("span");
    amount.className = "amount";
    amount.textContent = `${Number(expense.amount || 0).toLocaleString("ru-RU")} ₸`;

    content.appendChild(name);
    content.appendChild(meta);
    li.appendChild(content);
    li.appendChild(amount);
    expenseList.appendChild(li);
  });
}

async function loadGroupData() {
  try {
    const res = await fetch(`${API_URL}/groups/${groupId}`, { headers: { ...authHeader() } });
    if (!res.ok) throw new Error("Не удалось загрузить группу");

    const payload = await res.json();
    const group = payload.group || payload || {};
    const expenses = getResponseData(group.expenses || payload.expenses || payload.items || [], []);
    const groupName = group.name || `Группа #${groupId}`;

    groupNameEl.textContent = groupName;
    membersCountEl.textContent = String(Array.isArray(group.members) ? group.members.length : 0);
    cachedExpenses = expenses;
    renderExpenses(filterExpenses(cachedExpenses, searchInput.value));
  } catch (error) {
    const selectedGroup = mockGroups.find((item) => item.id === groupId) || mockGroups[0];
    groupNameEl.textContent = selectedGroup.name;
    membersCountEl.textContent = String(selectedGroup.members.length);
    cachedExpenses = selectedGroup.expenses || [];
    renderExpenses(filterExpenses(cachedExpenses, searchInput.value));
    errorEl.textContent = "Backend недоступен, показаны демо-данные.";
    console.error(error);
  }
}

searchInput.addEventListener("input", (event) => {
  renderExpenses(filterExpenses(cachedExpenses, event.target.value));
});

expenseForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  errorEl.textContent = "";

  const description = expenseNameInput.value.trim();
  const amount = Number(expenseAmountInput.value);

  if (!description || !amount || amount <= 0) {
    errorEl.textContent = "Проверьте описание и сумму.";
    return;
  }

  try {
    const res = await fetch(`${API_URL}/groups/${groupId}/expenses`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeader() },
      body: JSON.stringify({ description, amount, paidBy: "Вы" }),
    });

    if (!res.ok) {
      throw new Error("Не удалось добавить расход");
    }

    const payload = await res.json();
    const createdExpense = payload.expense || payload || { description, amount, paidBy: "Вы" };
    cachedExpenses = [createdExpense, ...cachedExpenses];
    expenseForm.reset();
    renderExpenses(filterExpenses(cachedExpenses, searchInput.value));
  } catch (error) {
    const createdExpense = { id: Date.now(), description, amount, paidBy: "Вы" };
    cachedExpenses = [createdExpense, ...cachedExpenses];
    expenseForm.reset();
    renderExpenses(filterExpenses(cachedExpenses, searchInput.value));
    errorEl.textContent = "Расход добавлен локально. Подключите backend для синхронизации.";
    console.error(error);
  }
});

loadGroupData();