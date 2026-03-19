import { Link } from 'react-router-dom';
import { useApp } from '../context/AppContext';

export default function Home() {
  const { t, gradeLevel, setGradeLevel } = useApp();
  const lastResultUuid = localStorage.getItem('last_result_uuid');

  return (
    <div className="home-page stack-gap-lg">
      <section className="hero-card editorial-card">
        <div className="hero-copy">
          <span className="eyebrow">Career Navigator • Moldova</span>
          <h1>{t.heroTitle}</h1>
          <p className="hero-lead">{t.heroText}</p>

          <div className="choice-pills">
            <button className={`pill-choice ${gradeLevel === '9' ? 'active' : ''}`} onClick={() => setGradeLevel('9')}>
              {t.class9}
            </button>
            <button className={`pill-choice ${gradeLevel === '12' ? 'active' : ''}`} onClick={() => setGradeLevel('12')}>
              {t.class12}
            </button>
          </div>

          <div className="cta-row">
            <Link className="btn btn-primary" to="/test">{t.startTest}</Link>
            {lastResultUuid && <Link className="btn btn-secondary" to={`/result/${lastResultUuid}`}>{t.continueResult}</Link>}
          </div>
        </div>

        <div className="hero-aside">
          <div className="shape shape-one" />
          <div className="shape shape-two" />
          <div className="editorial-note large">
            <span>RU / RO</span>
            <strong>Clean flow for students of grades 9 and 12</strong>
          </div>
          <div className="editorial-note small">
            <span>Freemium</span>
            <strong>Preview first, unlock the full path later</strong>
          </div>
        </div>
      </section>

      <section className="feature-strip">
        <div className="feature-card feature-soft">
          <span className="feature-index">01</span>
          <h3>{t.featuresTitle}</h3>
          <p>{t.feature1}</p>
        </div>
        <div className="feature-card feature-warm">
          <span className="feature-index">02</span>
          <h3>Match %</h3>
          <p>{t.feature2}</p>
        </div>
        <div className="feature-card feature-cool">
          <span className="feature-index">03</span>
          <h3>Institutions</h3>
          <p>{t.feature3}</p>
        </div>
      </section>
    </div>
  );
}
