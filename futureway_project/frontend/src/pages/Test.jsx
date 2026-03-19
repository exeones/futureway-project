import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { useApp } from '../context/AppContext';

export default function Test() {
  const { t, lang } = useApp();
  const navigate = useNavigate();
  const [questions, setQuestions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [index, setIndex] = useState(0);
  const [answers, setAnswers] = useState(() => {
    const saved = localStorage.getItem('draft_answers');
    return saved ? JSON.parse(saved) : {};
  });

  useEffect(() => {
    api.getQuestions()
      .then((res) => setQuestions(res.data || []))
      .catch(() => setError(t.apiError))
      .finally(() => setLoading(false));
  }, [t.apiError]);

  useEffect(() => {
    localStorage.setItem('draft_answers', JSON.stringify(answers));
  }, [answers]);

  const current = questions[index];
  const currentAnswer = current ? answers[current.id] : null;
  const progress = questions.length ? Math.round(((index + 1) / questions.length) * 100) : 0;

  const labelForOption = (option) => option[`text_${lang}`] || option.text_ru;
  const labelForQuestion = useMemo(() => current ? (current[`text_${lang}`] || current.text_ru) : '', [current, lang]);

  const handleNext = () => {
    if (!currentAnswer) {
      setError(t.requiredAnswer);
      return;
    }
    setError('');
    if (index < questions.length - 1) setIndex((v) => v + 1);
    else navigate('/academic', { state: { answers, questions } });
  };

  if (loading) return <div className="panel">{t.loading}</div>;
  if (error && !current) return <div className="panel error-text">{error}</div>;
  if (!current) return <div className="panel">{t.noData}</div>;

  return (
    <section className="panel test-panel">
      <div className="test-grid">
        <div className="test-copy">
          <span className="eyebrow">Step 1</span>
          <div className="question-meta">
            <span className="question-kicker">{t.testTitle}</span>
            <span className="question-counter">{index + 1} / {questions.length}</span>
          </div>
          <h2>{labelForQuestion}</h2>
          <p>{t.testText}</p>

          <div className="progress-rail">
            <span style={{ width: `${progress}%` }} />
          </div>

          <div className="progress-box minimal">
            <strong>{progress}%</strong>
            <span>progress</span>
          </div>
        </div>

        <div className="options-column">
          <div className="options-grid">
            {current.options.map((option) => (
              <button
                key={option.id}
                className={`option-card option-minimal ${currentAnswer === option.id ? 'active' : ''}`}
                onClick={() => setAnswers((prev) => ({ ...prev, [current.id]: option.id }))}
              >
                <div>
                  <span className="option-label">{labelForOption(option)}</span>
                  <small>score</small>
                </div>
                <strong>{option.value}</strong>
              </button>
            ))}
          </div>

          {error && <div className="error-text">{error}</div>}

          <div className="actions-row">
            <button className="btn btn-secondary" onClick={() => setIndex((v) => Math.max(0, v - 1))} disabled={index === 0}>{t.previous}</button>
            <button className="btn btn-primary" onClick={handleNext}>{index === questions.length - 1 ? t.finish : t.next}</button>
          </div>
        </div>
      </div>
    </section>
  );
}
