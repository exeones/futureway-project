const API_URL = import.meta.env.VITE_API_URL || 'http://localhost/futureway_project/backend/api';

async function request(path, options = {}) {
  const response = await fetch(`${API_URL}${path}`, {
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    },
    ...options
  });

  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.message || 'API Error');
  }
  return data;
}

export const api = {
  getQuestions: () => request('/questions'),
  createResult: (payload) => request('/results', { method: 'POST', body: JSON.stringify(payload) }),
  getResult: (uuid) => request(`/results/${uuid}`),
  createPayment: (payload) => request('/payments/create', { method: 'POST', body: JSON.stringify(payload) }),
  confirmPayment: (payload) => request('/payments/webhook', { method: 'POST', body: JSON.stringify(payload) }),
  adminLogin: (payload) => request('/admin/login', { method: 'POST', body: JSON.stringify(payload) }),
  adminStats: (token) => request('/admin/stats', { headers: { Authorization: `Bearer ${token}` } }),
  adminList: (entity, token) => request(`/admin/${entity}`, { headers: { Authorization: `Bearer ${token}` } }),
  adminCreate: (entity, payload, token) => request(`/admin/${entity}`, {
    method: 'POST',
    body: JSON.stringify(payload),
    headers: { Authorization: `Bearer ${token}` }
  }),
  adminUpdate: (entity, id, payload, token) => request(`/admin/${entity}/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
    headers: { Authorization: `Bearer ${token}` }
  }),
  adminDelete: (entity, id, token) => request(`/admin/${entity}/${id}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${token}` }
  })
};
