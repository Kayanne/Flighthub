import { Link, Route, Routes, Navigate } from 'react-router-dom'
import SearchPage from './pages/SearchPage.jsx'
import TripsPage from './pages/TripsPage.jsx'
import TripDetailPage from './pages/TripDetailPage.jsx'

export default function App() {
    return (
        <div style={{ fontFamily: 'system-ui, -apple-system, Segoe UI, Roboto, Arial', padding: 16, maxWidth: 1000, margin: '0 auto' }}>
            <header style={{ display: 'flex', gap: 16, alignItems: 'center', marginBottom: 16 }}>
                <h2 style={{ margin: 0 }}>Trip Builder</h2>
                <nav style={{ display: 'flex', gap: 12 }}>
                    <Link to="/search">Search</Link>
                    <Link to="/trips">Trips</Link>
                </nav>
            </header>

            <Routes>
                <Route path="/" element={<Navigate to="/search" replace />} />
                <Route path="/search" element={<SearchPage />} />
                <Route path="/trips" element={<TripsPage />} />
                <Route path="/trips/:id" element={<TripDetailPage />} />
            </Routes>
        </div>
    )
}
