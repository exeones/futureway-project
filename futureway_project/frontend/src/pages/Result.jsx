import { useEffect, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api';
import { useApp } from '../context/AppContext';
import { strengthKeys } from '../i18n';

export default function Result() {
  const { uuid } = useParams();
  const { t, lang } = useApp();
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [paymentInfo, setPaymentInfo] = useState(null);

  const loadResult = () => {
    setLoading(true);
    api.getResult(uuid)
      .then((res) => {
        setResult(res.data);
        localStorage.setItem('last_result_uuid', uuid);
      })
      .catch(() => setError(t.emptyResult))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    loadResult();
  }, [uuid]);

  const strengths = useMemo(() => {
    if (!result?.scores) return [];
    return [...strengthKeys]
      .map((key) => ({ key, value: result.scores[key] || 0 }))
      .sort((a, b) => b.value - a.value)
      .slice(0, 3);
  }, [result]);

  const recommendations = result?.recommendations || [];

  const handleCreatePayment = async () => {
    try {
      const response = await api.createPayment({ result_uuid: uuid });
      setPaymentInfo(response.data);
    } catch {
      setError(t.apiError);
    }
  };

  const handleConfirmPayment = async () => {
    try {
      await api.confirmPayment({
        result_uuid: uuid,
        provider_payment_id: paymentInfo.provider_payment_id,
        status: 'paid',
        sandbox_token: paymentInfo.sandbox_token
      });
      setPaymentInfo(null);
      loadResult();
    } catch {
      setError(t.apiError);
    }
  };

  if (loading) return <div className="panel">{t.loading}</div>;
  if (!result) return <div className="panel error-text">{error || t.emptyResult}</div>;

  return (
    <div className="result-page stack-gap">
      <section className="panel result-hero editorial-card">
        <div>
          <span className={`badge ${result.is_paid ? 'badge-success' : 'badge-neutral'}`}>
            {result.is_paid ? t.paidBadge : t.unpaidBadge}
          </span>
          <h2>{t.resultTitle}</h2>
          <p className="muted-line">UUID: {result.uuid}</p>
        </div>
        <div className="grade-pill">{result.grade_level}</div>
      </section>

      <section className="strengths-layout">
        <div className="panel strengths-intro">
          <span className="eyebrow">Profile</span>
          <h3>{t.strengths}</h3>
          <p>Three strongest directions based on the test profile and academic score.</p>
        </div>

        <div className="grid strengths-grid">
          {strengths.map((item) => (
            <div className="strength-card minimal" key={item.key}>
              <span>{t[`category${item.key.charAt(0).toUpperCase() + item.key.slice(1)}`]}</span>
              <strong>{item.value}</strong>
            </div>
          ))}
        </div>
      </section>

      <section className="grid results-grid">
        {recommendations.map((item) => (
          <article className="result-card editorial-result" key={`${item.id}-${item.match_percent || 'free'}`}>
            <div className="result-card-head">
              <div>
                <span className="card-kicker">Recommendation</span>
                <h3>{item[`title_${lang}`] || item.title_ru || item.title}</h3>
              </div>
              {result.is_paid && item.match_percent !== undefined && <span className="match-badge">{item.match_percent}%</span>}
            </div>

            <p><strong>{t.shortDescription}:</strong> {item[`short_desc_${lang}`] || item.short_desc_ru || ''}</p>
            {result.is_paid && <p><strong>{t.fullDescription}:</strong> {item[`full_desc_${lang}`] || item.full_desc_ru || ''}</p>}
            {result.is_paid && item.reason && <p><strong>{t.whyFits}:</strong> {item.reason}</p>}
          </article>
        ))}
      </section>

      {result.is_paid && Array.isArray(result.institutions) && result.institutions.length > 0 && (
        <section className="panel">
          <div className="section-head">
            <span className="eyebrow">Study</span>
            <h3>{t.institutions}</h3>
          </div>
          <div className="grid three-up">
            {result.institutions.map((item) => (
              <div className="feature-card institution-card" key={`${item.id}-${item.name_ru}`}>
                <h4>{item[`name_${lang}`] || item.name_ru}</h4>
                <p className="institution-city">{item.city}</p>
                <p>{item[`description_${lang}`] || item.description_ru}</p>
                {item.website_url && <a href={item.website_url} target="_blank" rel="noreferrer">Visit website</a>}
              </div>
            ))}
          </div>
        </section>
      )}

      {!result.is_paid && (
        <section className="panel payment-panel editorial-card">
          <div>
            <span className="eyebrow">Unlock</span>
            <h3>{t.fullResult}</h3>
            <p>{t.paymentPending}</p>
          </div>
          <div className="actions-row">
            <button className="btn btn-primary" onClick={handleCreatePayment}>{t.payButton}</button>
            {paymentInfo && <button className="btn btn-secondary" onClick={handleConfirmPayment}>{t.sandboxPay}</button>}
          </div>
        </section>
      )}

      {error && <div className="error-text">{error}</div>}
    </div>
  );
}
