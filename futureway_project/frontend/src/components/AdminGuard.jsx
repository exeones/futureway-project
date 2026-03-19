import { Navigate } from 'react-router-dom';
import { useApp } from '../context/AppContext';

export default function AdminGuard({ children }) {
  const { adminToken } = useApp();
  if (!adminToken) return <Navigate to="/admin" replace />;
  return children;
}
