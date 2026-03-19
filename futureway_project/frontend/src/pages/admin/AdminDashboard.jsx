import { useEffect, useState } from 'react';
import { api } from '../../api';
import { useApp } from '../../context/AppContext';

const entityMap = {
  questions: 'questions',
  professions: 'professions',
  institutions: 'institutions'
};

export default function AdminDashboard() {
  const { t, adminToken, setAdminToken } = useApp();
  const [tab, setTab] = useState('questions');
  const [stats, setStats] = useState(null);
  const [items, setItems] = useState([]);
  const [formJson, setFormJson] = useState('{}');
  const [editId, setEditId] = useState(null);
  const [message, setMessage] = useState('');

  const loadStats = () => api.adminStats(adminToken).then((res) => setStats(res.data)).catch(() => {});
  const loadItems = (currentTab = tab) => api.adminList(entityMap[currentTab], adminToken).then((res) => setItems(res.data || [])).catch(() => setItems([]));

  useEffect(() => { loadStats(); }, []);
  useEffect(() => { loadItems(tab); setFormJson('{}'); setEditId(null); }, [tab]);

  const onSave = async () => {
    try {
      const payload = JSON.parse(formJson);
      if (editId) {
        await api.adminUpdate(entityMap[tab], editId, payload, adminToken);
      } else {
        await api.adminCreate(entityMap[tab], payload, adminToken);
      }
      setMessage('Saved');
      setFormJson('{}');
      setEditId(null);
      loadItems(tab);
      loadStats();
    } catch (e) {
      setMessage(e.message);
    }
  };

  const startEdit = (item) => {
    setEditId(item.id);
    setFormJson(JSON.stringify(item, null, 2));
  };

  const onDelete = async (id) => {
    if (!window.confirm('Delete item?')) return;
    try {
      await api.adminDelete(entityMap[tab], id, adminToken);
      loadItems(tab);
      loadStats();
    } catch (e) {
      setMessage(e.message);
    }
  };

  return (
    <div className="stack-gap">
      <section className="panel admin-header editorial-card">
        <div>
          <span className="eyebrow">Admin</span>
          <h2>FutureWay Dashboard</h2>
          <p className="muted-line">Manage core content with the same API and structure you already have.</p>
        </div>
        <button className="btn btn-secondary" onClick={() => setAdminToken('')}>{t.logout}</button>
      </section>

      {stats && (
        <section className="grid three-up">
          <div className="feature-card stat-surface"><h3>{t.testsCount}</h3><p>{stats.tests_count}</p></div>
          <div className="feature-card stat-surface"><h3>{t.paymentsCount}</h3><p>{stats.payments_count}</p></div>
          <div className="feature-card stat-surface"><h3>{t.revenue}</h3><p>${stats.revenue_total}</p></div>
        </section>
      )}

      <section className="panel">
        <div className="tab-row">
          <button className={`tab-btn ${tab === 'questions' ? 'active' : ''}`} onClick={() => setTab('questions')}>{t.questions}</button>
          <button className={`tab-btn ${tab === 'professions' ? 'active' : ''}`} onClick={() => setTab('professions')}>{t.professions}</button>
          <button className={`tab-btn ${tab === 'institutions' ? 'active' : ''}`} onClick={() => setTab('institutions')}>{t.institutionsTab}</button>
        </div>
      </section>

      <section className="admin-grid">
        <div className="panel">
          <div className="section-head">
            <span className="eyebrow">Content</span>
            <h3>{tab}</h3>
          </div>
          <div className="admin-list">
            {items.map((item) => (
              <div className="admin-item" key={item.id || item.uuid}>
                <div>
                  <strong>{item.title_ru || item.name_ru || item.text_ru || item.id}</strong>
                  <p>{item.title_ro || item.name_ro || item.text_ro || item.category || ''}</p>
                </div>
                <div className="actions-row compact">
                  <button className="btn btn-secondary btn-small" onClick={() => startEdit(item)}>{t.save}</button>
                  <button className="btn btn-danger btn-small" onClick={() => onDelete(item.id)}>{t.delete}</button>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="panel">
          <div className="section-head">
            <span className="eyebrow">Editor</span>
            <h3>{editId ? `Edit #${editId}` : t.create}</h3>
          </div>
          <p className="muted-line">JSON editor</p>
          <textarea className="json-area" value={formJson} onChange={(e) => setFormJson(e.target.value)} />
          {message && <div className="error-text">{message}</div>}
          <button className="btn btn-primary" onClick={onSave}>{editId ? t.save : t.create}</button>
        </div>
      </section>
    </div>
  );
}
