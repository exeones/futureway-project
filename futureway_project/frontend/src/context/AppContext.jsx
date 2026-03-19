import { createContext, useContext, useMemo, useState } from 'react';
import { translations } from '../i18n';

const AppContext = createContext(null);

export function AppProvider({ children }) {
  const [lang, setLang] = useState(localStorage.getItem('lang') || 'ru');
  const [gradeLevel, setGradeLevel] = useState(localStorage.getItem('grade_level') || '9');
  const [adminToken, setAdminToken] = useState(localStorage.getItem('admin_token') || '');

  const updateLang = (value) => {
    setLang(value);
    localStorage.setItem('lang', value);
  };

  const updateGradeLevel = (value) => {
    setGradeLevel(value);
    localStorage.setItem('grade_level', value);
  };

  const updateAdminToken = (token) => {
    setAdminToken(token || '');
    if (token) localStorage.setItem('admin_token', token);
    else localStorage.removeItem('admin_token');
  };

  const value = useMemo(() => ({
    lang,
    setLang: updateLang,
    gradeLevel,
    setGradeLevel: updateGradeLevel,
    adminToken,
    setAdminToken: updateAdminToken,
    t: translations[lang]
  }), [lang, gradeLevel, adminToken]);

  return <AppContext.Provider value={value}>{children}</AppContext.Provider>;
}

export function useApp() {
  return useContext(AppContext);
}
