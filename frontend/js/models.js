// Этот файл не содержит логики — только описание формы данных (JSDoc),
// чтобы фронтенд и бэкенд были синхронизированы по структуре объектов.
// Реальные модели в БД создаёт тот, кто отвечает за backend/БД.

/**
 * @typedef {Object} User
 * @property {string} id
 * @property {string} name
 * @property {string} email
 */

/**
 * @typedef {Object} Group
 * @property {string} id
 * @property {string} name
 * @property {string} createdBy - id пользователя-создателя
 * @property {User[]} members
 */

/**
 * @typedef {Object} Expense
 * @property {string} id
 * @property {string} groupId
 * @property {string} description
 * @property {number} amount
 * @property {string} paidBy - id пользователя, который заплатил
 * @property {string} date
 */
