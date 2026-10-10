// Нагрузочный тест для k6 (https://k6.io):
//   k6 run -e BASE_URL=http://localhost:8080 tests/load/health.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 20 },   // разгоняемся до 20 одновременных пользователей
    { duration: '30s', target: 20 },   // держим нагрузку
    { duration: '5s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],      // меньше 1% ошибок
    http_req_duration: ['p(95)<500'],    // 95% запросов быстрее 500 мс
  },
};

const BASE = __ENV.BASE_URL || 'http://localhost:8080';

export default function () {
  const health = http.get(`${BASE}/api/health.php`);
  check(health, { 'health: 200': (r) => r.status === 200 });

  const home = http.get(`${BASE}/`);
  check(home, { 'главная: 200': (r) => r.status === 200 });
  sleep(1);
}
