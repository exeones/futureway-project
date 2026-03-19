import { useEffect, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { api } from '../api';
import { useApp } from '../context/AppContext';

export default function Academic() {
  const { t, lang, gradeLevel } = useApp();
  const { state } = useLocation();
  const navigate = useNavigate();
  const [academicScore, setAcademicScore] = useState('8.5');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!state?.answers) navigate('/test');
  }, [state, navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setError('');
    try {
      const response = await api.createResult({
        grade_level: gradeLevel,
        lang,
        academic_score: Number(academicScore),
        answers: state.answers
      });
      localStorage.setItem('last_result_uuid', response.data.uuid);
      localStorage.removeItem('draft_answers');
      navigate(`/result/${response.data.uuid}`);
    } catch {
      setError(t.apiError);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <section className="panel narrow-panel academic-panel">
      <div className="academic-art" />
      <span className="eyebrow">Step 2</span>
      <h2>{t.academicTitle}</h2>
      <p>{t.academicHint}</p>
      <form className="stack-form" onSubmit={handleSubmit}>
        <label className="field-label">Academic score</label>
        <input type="number" min="1" max="10" step="0.1" value={academicScore} onChange={(e) => setAcademicScore(e.target.value)} required />
        {error && <div className="error-text">{error}</div>}
        <button className="btn btn-primary" disabled={submitting}>{submitting ? t.loading : t.submit}</button>
      </form>
    </section>
  );
}
