
const API_URL = "http://localhost:4000/api";

const form = document.getElementById("expense-form");
const list = document.getElementById("expense-list");
const errorEl = document.getElementById("error");
const totalEl = document.getElementById("total");
const emptyState = document.getElementById("empty-state");

function renderExpenses(expenses) {
  list.innerHTML = "";

  if (expenses.length === 0) {
    emptyState.style.display = "block";
    totalEl.textContent = "";
    return;
  }

  emptyState.style.display = "none";

  let total = 0;
  expenses.forEach((exp) => {
    total += Number(exp.amount);

    const li = document.createElement("li");

    const desc = document.createElement("span");
    desc.className = "desc";
    desc.textContent = exp.description;

    const amount = document.createElement("span");
    amount.className = "amount";
    amount.textContent = `${exp.amount} ₸`;

    li.appendChild(desc);
    li.appendChild(amount);
    list.appendChild(li);
  });

  totalEl.textContent = `Всего: ${total} ₸`;
}

async function loadExpenses() {
  errorEl.textContent = "";
  try {
    const res = await fetch(`${API_URL}/expenses`);
    if (!res.ok) throw new Error("Сервер вернул ошибку");
    const expenses = await res.json();
    renderExpenses(expenses);
  } catch (err) {
    errorEl.textContent = "Не удалось загрузить расходы. Backend запущен?";
    console.error(err);
  }
}

form.addEventListener("submit", async (e) => {
  e.preventDefault();
  errorEl.textContent = "";

  const description = document.getElementById("description").value.trim();
  const amount = Number(document.getElementById("amount").value);

  if (!description || !amount || amount <= 0) {
    errorEl.textContent = "Проверьте описание и сумму.";
    return;
  }

  try {
    const res = await fetch(`${API_URL}/expenses`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ description, amount }),
    });
    if (!res.ok) throw new Error("Не удалось добавить расход");

    form.reset();
    loadExpenses();
  } catch (err) {
    errorEl.textContent = "Ошибка при добавлении расхода.";
    console.error(err);
  }
});

loadExpenses();
