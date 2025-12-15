import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { apiGet } from '../lib/api.js'

const TYPE_LABEL = {
    one_way: 'One-way',
    round_trip: 'Round trip',
    multi_city: 'Multi-city',
}

const CURRENCY_LABEL = {
    NTR: 'Neutral currency',
}

function formatPrice(currency, amount) {
    const label = CURRENCY_LABEL[currency] || currency || ''
    const n = Number(amount)
    const value = Number.isFinite(n) ? n.toFixed(2) : String(amount ?? '')
    return `${label} ${value}`.trim()
}

function Card({ children }) {
    return (
        <div style={{ border: '1px solid #e6e6e6', borderRadius: 12, padding: 16, marginTop: 12, background: '#fff' }}>
            {children}
        </div>
    )
}

export default function TripDetailPage() {
    const { id } = useParams()
    const [trip, setTrip] = useState(null)
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState('')

    useEffect(() => {
        let cancelled = false

        async function load() {
            setLoading(true)
            setError('')
            try {
                const res = await apiGet(`/api/trips/${encodeURIComponent(id)}`)
                if (cancelled) return
                setTrip(res?.data || null)
            } catch (err) {
                if (cancelled) return
                setTrip(null)
                setError(err?.data?.message || err?.message || 'Load failed')
            } finally {
                if (!cancelled) setLoading(false)
            }
        }

        load()
        return () => {
            cancelled = true
        }
    }, [id])

    if (loading) return <div>Loading…</div>

    if (error) {
        return (
            <div>
                <div style={{ marginBottom: 10 }}>
                    <Link to="/trips">← Back</Link>
                </div>
                <div style={{ color: '#b00020' }}>{error}</div>
            </div>
        )
    }

    if (!trip) {
        return (
            <div>
                <div style={{ marginBottom: 10 }}>
                    <Link to="/trips">← Back</Link>
                </div>
                <div style={{ color: '#666' }}>Not found.</div>
            </div>
        )
    }

    return (
        <div>
            <div style={{ marginBottom: 10 }}>
                <Link to="/trips">← Back</Link>
            </div>

            <h3 style={{ marginTop: 0 }}>
                Trip #{trip.id} — {TYPE_LABEL[trip.type] || trip.type}
            </h3>

            <Card>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                    <div style={{ color: '#666' }}>Created: {trip.created_at}</div>
                    <div style={{ fontWeight: 700 }}>
                        {formatPrice(trip.currency, trip.total_price)}
                    </div>
                </div>

                <div style={{ marginTop: 12, display: 'grid', gap: 10 }}>
                    {(trip.segments || []).map((s) => (
                        <div key={s.segment_index} style={{ padding: 12, borderRadius: 10, background: '#fafafa' }}>
                            <div style={{ fontWeight: 700 }}>
                                Segment {s.segment_index} — {s.flight.airline.code}
                                {s.flight.number} — {s.flight.departure_airport.code} → {s.flight.arrival_airport.code}
                            </div>

                            <div style={{ color: '#555', marginTop: 6 }}>
                                Local: {s.departure.at_local} ({s.departure.timezone}) → {s.arrival.at_local} ({s.arrival.timezone})
                            </div>

                            <div style={{ color: '#777', marginTop: 2 }}>
                                UTC: {s.departure.at_utc} → {s.arrival.at_utc}
                            </div>

                            <div style={{ color: '#777', marginTop: 6 }}>
                                Flight price: {formatPrice(trip.currency, s.flight.price)}
                            </div>
                        </div>
                    ))}
                </div>
            </Card>
        </div>
    )
}