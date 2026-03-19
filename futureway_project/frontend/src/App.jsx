import { Routes, Route } from 'react-router-dom';
import Layout from './components/Layout';
import Home from './pages/Home';
import Test from './pages/Test';
import Academic from './pages/Academic';
import Result from './pages/Result';
import AdminLogin from './pages/admin/AdminLogin';
import AdminDashboard from './pages/admin/AdminDashboard';
import AdminGuard from './components/AdminGuard';
import { AnimatePresence } from "framer-motion"
import { useLocation } from "react-router-dom"

export default function App() {
  return (
    <Routes>
      <Route element={<Layout />}>
        <Route path="/" element={<Home />} />
        <Route path="/test" element={<Test />} />
        <Route path="/academic" element={<Academic />} />
        <Route path="/result/:uuid" element={<Result />} />
        <Route path="/admin" element={<AdminLogin />} />
        <Route
          path="/admin/dashboard"
          element={
            <AdminGuard>
              <AdminDashboard />
            </AdminGuard>
          }
        />
      </Route>
    </Routes>
  );
}
