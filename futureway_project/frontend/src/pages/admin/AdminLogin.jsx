import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../api';
import { useApp } from '../../context/AppContext';

export default function AdminLogin() {
  const { t, setAdminToken } = useApp();
  const navigate = useNavigate();
  const [form, setForm] = useState({ email: 'admin@futureway.md', password: 'admin123' });
  const [error, setError] = useState('');

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    try {
      const response = await api.adminLogin(form);
      setAdminToken(response.data.token);
      navigate('/admin/dashboard');
    } catch (e) {
      setError(e.message || t.apiError);
    }
  };

  return (
    <section className="panel narrow-panel admin-login-card">
      <span className="eyebrow">JWT</span>
      <h2>{t.adminLogin}</h2>
      <p className="muted-line">Minimal dashboard access for managing questions, professions and institutions.</p>
      <form className="stack-form" onSubmit={submit}>
        <label className="field-label">{t.email}</label>
        <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder={t.email} required />
        <label className="field-label">{t.password}</label>
        <input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} placeholder={t.password} required />
        {error && <div className="error-text">{error}</div>}
        <button className="btn btn-primary">{t.login}</button>
      </form>
    </section>
  );
}
