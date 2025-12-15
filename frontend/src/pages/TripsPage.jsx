import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiGet } from '../lib/api.js'

const TYPE_LABEL = {
    one_way: 'One-way',
    round_trip: 'Round trip',
    multi_city: 'Multi-city',
}

const SORT_LABEL = {
    created_at: 'Created date',
    price: 'Cheapest',
    departure_at: 'Earliest departure',
}

const DIR_LABEL = {
    asc: 'Ascending',
    desc: 'Descending',
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

export default function TripsPage() {
    const [sort, setSort] = useState('created_at')
    const [dir, setDir] = useState('desc')
    const [page, setPage] = useState(1)
    const [perPage] = useState(3)

    const [data, setData] = useState([])
    const [meta, setMeta] = useState({ page: 1, per_page: 10, total: 0 })
    const [loading, setLoading] = useState(false)
    const [error, setError] = useState('')

    const queryString = useMemo(() => {
        const p = new URLSearchParams()
        p.set('sort', sort)
        p.set('dir', dir)
        p.set('page', String(page))
        p.set('per_page', String(perPage))
        return p.toString()
    }, [sort, dir, page, perPage])

    async function load() {
        setLoading(true)
        setError('')
        try {
            const res = await apiGet(`/api/trips?${queryString}`)
            setData(Array.isArray(res?.data) ? res.data : [])
            setMeta(res?.meta || { page: 1, per_page: 10, total: 0 })
        } catch (err) {
            setData([])
            setMeta({ page: 1, per_page: 10, total: 0 })
            setError(err?.data?.message || err?.message || 'Load failed')
        } finally {
            setLoading(false)
        }
    }

    useEffect(() => {
        load()
    }, [queryString])

    const hasPrev = (meta?.page || 1) > 1
    const hasNext = (meta?.page || 1) * (meta?.per_page || 10) < (meta?.total || 0)

    return (
        <div>
            <h3 style={{ marginTop: 0 }}>Trips</h3>

            <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
                <select
                    value={sort}
                    onChange={(e) => { setPage(1); setSort(e.target.value) }}
                    style={{ padding: 10, borderRadius: 10, border: '1px solid #ccc' }}
                >
                    <option value="created_at">{SORT_LABEL.created_at}</option>
                    <option value="price">{SORT_LABEL.price}</option>
                    <option value="departure_at">{SORT_LABEL.departure_at}</option>
                </select>

                <select
                    value={dir}
                    onChange={(e) => { setPage(1); setDir(e.target.value) }}
                    style={{ padding: 10, borderRadius: 10, border: '1px solid #ccc' }}
                >
                    <option value="asc">{DIR_LABEL.asc}</option>
                    <option value="desc">{DIR_LABEL.desc}</option>
                </select>

                <button
                    onClick={load}
                    disabled={loading}
                    style={{ padding: '10px 14px', borderRadius: 10, border: '1px solid #111', background: '#fff', cursor: 'pointer' }}
                >
                    {loading ? 'Loading…' : 'Refresh'}
                </button>

                <div style={{ marginLeft: 'auto', color: '#666' }}>Total: {meta?.total || 0}</div>
            </div>

            {error ? <div style={{ color: '#b00020', marginTop: 10 }}>{error}</div> : null}

            <div style={{ marginTop: 12 }}>
                {data.map((t) => (
                    <Card key={t.id}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                            <div>
                                <div style={{ fontSize: 16, fontWeight: 700 }}>
                                    <Link to={`/trips/${t.id}`} style={{ textDecoration: 'none' }}>
                                        Trip #{t.id} — {TYPE_LABEL[t.type] || t.type}
                                    </Link>
                                </div>
                                <div style={{ color: '#666', marginTop: 4 }}>Created: {t.created_at}</div>
                            </div>

                            <div style={{ fontWeight: 700 }}>
                                {formatPrice(t.currency, t.total_price)}
                            </div>
                        </div>

                        <div style={{ marginTop: 10, display: 'grid', gap: 8 }}>
                            {(t.segments || []).map((s) => (
                                <div key={s.segment_index} style={{ color: '#444' }}>
                                    {s.flight.airline.code}
                                    {s.flight.number} — {s.flight.departure_airport.code} → {s.flight.arrival_airport.code} — {s.departure.at_local} → {s.arrival.at_local}
                                </div>
                            ))}
                        </div>
                    </Card>
                ))}
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginTop: 14 }}>
                <button
                    disabled={!hasPrev}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                    style={{ padding: '10px 14px', borderRadius: 10, border: '1px solid #ccc', background: '#fff', cursor: hasPrev ? 'pointer' : 'not-allowed', opacity: hasPrev ? 1 : 0.5 }}
                >
                    Prev
                </button>

                <button
                    disabled={!hasNext}
                    onClick={() => setPage((p) => p + 1)}
                    style={{ padding: '10px 14px', borderRadius: 10, border: '1px solid #ccc', background: '#fff', cursor: hasNext ? 'pointer' : 'not-allowed', opacity: hasNext ? 1 : 0.5 }}
                >
                    Next
                </button>

                <div style={{ marginLeft: 'auto', color: '#666' }}>Page {meta?.page || 1}</div>
            </div>
        </div>
    )
}
