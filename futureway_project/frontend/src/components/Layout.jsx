import { Link, Outlet, useLocation } from 'react-router-dom';
import { useApp } from '../context/AppContext';

export default function Layout() {
  const { t, lang, setLang } = useApp();
  const location = useLocation();
  const isAdmin = location.pathname.startsWith('/admin');

  return (
    <div className="app-shell">
      <div className="page-noise" />
      <header className="site-header">
        <div className="container topbar">
          <Link to="/" className="brand">
            <img src="/public/logo.svg" className="logo" />
            <span>{t.brand}</span>
          </Link>

          <nav className="nav-links">
            <Link className={location.pathname === '/' ? 'active' : ''} to="/">{t.navHome}</Link>
            <Link className={isAdmin ? 'active' : ''} to="/admin">{t.navAdmin}</Link>
            <div className="lang-switch">
              <button className={lang === 'ru' ? 'active' : ''} onClick={() => setLang('ru')}>RU</button>
              <button className={lang === 'ro' ? 'active' : ''} onClick={() => setLang('ro')}>RO</button>
            </div>
          </nav>
        </div>
      </header>

      <main className="container page-wrap">
        <Outlet />
      </main>

      <footer className="site-footer">
        <div className="container footer-inner">
          <div>
            <div className="footer-brand">{t.brand}</div>
            <p>{t.footerText}</p>
          </div>
          <div className="footer-meta">
            <span>Career test</span>
            <span>Freemium MVP</span>
            <span>Moldova</span>
          </div>
        </div>
      </footer>
    </div>
  );
}
