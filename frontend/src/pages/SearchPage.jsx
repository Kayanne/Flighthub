import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiGet, apiPost } from '../lib/api.js'

const TYPE_LABEL = {
    one_way: 'One-way',
    round_trip: 'Round trip',
    multi_city: 'Multi-city',
}

const SORT_LABEL = {
    price: 'Cheapest',
    departure_at: 'Earliest departure',
}

const CURRENCY_LABEL = {
    NTR: 'Neutral currency (NTR)',
}

function formatPrice(currency, amount) {
    const label = CURRENCY_LABEL[currency] || currency || ''
    const n = Number(amount)
    const value = Number.isFinite(n) ? n.toFixed(2) : String(amount ?? '')
    return `${label} ${value}`.trim()
}

function emptyFlight() {
    return { origin: '', destination: '', departure_date: '' }
}

export default function SearchPage() {
    const navigate = useNavigate()

    const [type, setType] = useState('one_way')
    const [sort, setSort] = useState('price')
    const [preferredAirline, setPreferredAirline] = useState('')

    const [origin, setOrigin] = useState('YUL')
    const [destination, setDestination] = useState('YVR')
    const [departureDate, setDepartureDate] = useState('2026-01-10')
    const [returnDate, setReturnDate] = useState('2026-01-15')

    const [flights, setFlights] = useState([
        { origin: 'YUL', destination: 'YVR', departure_date: '2026-01-10' },
        { origin: 'YVR', destination: 'YUL', departure_date: '2026-01-15' },
    ])

    const [airlines, setAirlines] = useState([])

    const [page, setPage] = useState(1)
    const [perPage, setPerPage] = useState(5)
    const [meta, setMeta] = useState({ page: 1, per_page: 5, total: 0 })

    const [loading, setLoading] = useState(false)
    const [savingIndex, setSavingIndex] = useState(null)
    const [error, setError] = useState('')
    const [results, setResults] = useState([])

    useEffect(() => {
        apiGet('/api/airlines')
            .then(res => setAirlines(res?.data || []))
            .catch(() => setAirlines([]))
    }, [])

    const canAddFlight = flights.length < 5

    const payload = useMemo(() => {
        const pref = (preferredAirline || '').trim()

        if (type === 'multi_city') {
            const cleanFlights = flights
                .map(f => ({
                    origin: (f.origin || '').trim().toUpperCase(),
                    destination: (f.destination || '').trim().toUpperCase(),
                    departure_date: (f.departure_date || '').trim(),
                }))
                .filter(f => f.origin && f.destination && f.departure_date)

            const base = { type, legs: cleanFlights, sort, page, per_page: perPage }
            if (pref) base.preferred_airline = pref.toUpperCase()
            return base
        }

        const base = {
            type,
            origin: (origin || '').trim().toUpperCase(),
            destination: (destination || '').trim().toUpperCase(),
            departure_date: departureDate,
            sort,
            page,
            per_page: perPage,
        }

        if (type === 'round_trip') base.return_date = returnDate
        if (pref) base.preferred_airline = pref.toUpperCase()
        return base
    }, [type, sort, preferredAirline, origin, destination, departureDate, returnDate, flights, page, perPage])

    async function doSearch(override = {}) {
        setError('')
        setLoading(true)
        try {
            const res = await apiPost('/api/trips/search', { ...payload, ...override })
            setResults(res?.data || [])
            setMeta(res?.meta || { page: override.page || page, per_page: override.per_page || perPage, total: 0 })
        } catch (err) {
            setResults([])
            setMeta({ page: 1, per_page: perPage, total: 0 })
            const msg =
                err?.data?.message ||
                (err?.data?.errors ? JSON.stringify(err.data.errors) : '') ||
                err?.message ||
                'Search failed'
            setError(msg)
        } finally {
            setLoading(false)
        }
    }

    async function onSearch(e) {
        e.preventDefault()
        setResults([])
        setMeta({ page: 1, per_page: perPage, total: 0 })
        setPage(1)
        await doSearch({ page: 1, per_page: perPage })
    }

    async function onSaveTrip(proposal, idx) {
        setError('')
        setSavingIndex(idx)
        try {
            const segments = (proposal.segments || []).map(s => ({
                flight_id: s.flight.id,
                departure_date: s.departure.date,
            }))

            const res = await apiPost('/api/trips', { type: proposal.type, segments })
            const id = res?.data?.id
            if (id) navigate(`/trips/${id}`)
        } catch (err) {
            const msg =
                err?.data?.message ||
                (err?.data?.errors ? JSON.stringify(err.data.errors) : '') ||
                err?.message ||
                'Save failed'
            setError(msg)
        } finally {
            setSavingIndex(null)
        }
    }

    function updateFlight(i, patch) {
        setFlights(prev => prev.map((f, idx) => (idx === i ? { ...f, ...patch } : f)))
    }

    function removeFlight(i) {
        setFlights(prev => prev.filter((_, idx) => idx !== i))
    }

    function addFlight() {
        if (!canAddFlight) return
        setFlights(prev => [...prev, emptyFlight()])
    }

    const hasPrev = (meta?.page || 1) > 1
    const hasNext = (meta?.page || 1) * (meta?.per_page || perPage) < (meta?.total || 0)

    async function goToPage(nextPage) {
        const p = Math.max(1, nextPage)
        setPage(p)
        await doSearch({ page: p, per_page: perPage })
    }

    async function onChangePerPage(v) {
        const n = Number(v)
        const next = Number.isFinite(n) ? n : 5
        setPerPage(next)
        setPage(1)
        await doSearch({ page: 1, per_page: next })
    }

    return (
        <div>
            <h3 style={{ marginTop: 0 }}>Search</h3>

            <form onSubmit={onSearch} style={{ display: 'grid', gap: 12 }}>
                <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'end' }}>
                    <label style={{ display: 'grid', gap: 6 }}>
                        Trip type
                        <select
                            value={type}
                            onChange={e => {
                                setType(e.target.value)
                                setPage(1)
                            }}
                        >
                            <option value="one_way">{TYPE_LABEL.one_way}</option>
                            <option value="round_trip">{TYPE_LABEL.round_trip}</option>
                            <option value="multi_city">{TYPE_LABEL.multi_city}</option>
                        </select>
                    </label>

                    <label style={{ display: 'grid', gap: 6 }}>
                        Sort by
                        <select value={sort} onChange={e => setSort(e.target.value)}>
                            <option value="price">{SORT_LABEL.price}</option>
                            <option value="departure_at">{SORT_LABEL.departure_at}</option>
                        </select>
                    </label>

                    <label style={{ display: 'grid', gap: 6 }}>
                        Airline
                        <select value={preferredAirline} onChange={e => setPreferredAirline(e.target.value)}>
                            <option value="">Any</option>
                            {airlines.map(a => (
                                <option key={a.code} value={a.code}>
                                    {a.code} — {a.name}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label style={{ display: 'grid', gap: 6 }}>
                        Per page
                        <select value={perPage} onChange={e => onChangePerPage(e.target.value)}>
                            <option value={3}>3</option>
                            <option value={5}>5</option>
                            <option value={10}>10</option>
                            <option value={25}>25</option>
                        </select>
                    </label>
                </div>

                {type !== 'multi_city' ? (
                    <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'end' }}>
                        <label style={{ display: 'grid', gap: 6 }}>
                            Origin (airport or city code)
                            <input value={origin} onChange={e => setOrigin(e.target.value)} placeholder="YUL or YMQ" />
                        </label>

                        <label style={{ display: 'grid', gap: 6 }}>
                            Destination (airport or city code)
                            <input value={destination} onChange={e => setDestination(e.target.value)} placeholder="YVR" />
                        </label>

                        <label style={{ display: 'grid', gap: 6 }}>
                            Departure date
                            <input type="date" value={departureDate} onChange={e => setDepartureDate(e.target.value)} />
                        </label>

                        {type === 'round_trip' && (
                            <label style={{ display: 'grid', gap: 6 }}>
                                Return date
                                <input type="date" value={returnDate} onChange={e => setReturnDate(e.target.value)} />
                            </label>
                        )}
                    </div>
                ) : (
                    <div style={{ display: 'grid', gap: 10 }}>
                        <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                            <strong>Flights</strong>
                            <button type="button" onClick={addFlight} disabled={!canAddFlight}>
                                + Add flight
                            </button>
                            <span style={{ opacity: 0.7 }}>{flights.length}/5</span>
                        </div>

                        {flights.map((f, i) => (
                            <div
                                key={i}
                                style={{
                                    display: 'flex',
                                    gap: 10,
                                    flexWrap: 'wrap',
                                    alignItems: 'end',
                                    padding: 10,
                                    border: '1px solid #ddd',
                                    borderRadius: 8,
                                }}
                            >
                                <div style={{ width: 40, opacity: 0.7 }}>#{i + 1}</div>

                                <label style={{ display: 'grid', gap: 6 }}>
                                    Origin
                                    <input value={f.origin} onChange={e => updateFlight(i, { origin: e.target.value })} placeholder="YUL or YMQ" />
                                </label>

                                <label style={{ display: 'grid', gap: 6 }}>
                                    Destination
                                    <input
                                        value={f.destination}
                                        onChange={e => updateFlight(i, { destination: e.target.value })}
                                        placeholder="YVR"
                                    />
                                </label>

                                <label style={{ display: 'grid', gap: 6 }}>
                                    Departure date
                                    <input
                                        type="date"
                                        value={f.departure_date}
                                        onChange={e => updateFlight(i, { departure_date: e.target.value })}
                                    />
                                </label>

                                <button type="button" onClick={() => removeFlight(i)} disabled={flights.length <= 1}>
                                    Remove
                                </button>
                            </div>
                        ))}
                    </div>
                )}

                <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
                    <button type="submit" disabled={loading}>
                        {loading ? 'Searching…' : 'Search'}
                    </button>

                    <button type="button" onClick={() => goToPage((meta?.page || 1) - 1)} disabled={loading || !hasPrev}>
                        Prev
                    </button>

                    <button type="button" onClick={() => goToPage((meta?.page || 1) + 1)} disabled={loading || !hasNext}>
                        Next
                    </button>

                    <div style={{ marginLeft: 'auto', opacity: 0.75 }}>
                        Page {meta?.page || 1} — Total {meta?.total || 0}
                    </div>

                    {error && <div style={{ color: 'crimson', whiteSpace: 'pre-wrap' }}>{error}</div>}
                </div>
            </form>

            <hr style={{ margin: '16px 0' }} />

            <h4 style={{ marginTop: 0 }}>Results ({results.length})</h4>

            <div style={{ display: 'grid', gap: 12 }}>
                {results.map((p, idx) => (
                    <div key={idx} style={{ border: '1px solid #ddd', borderRadius: 10, padding: 12 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                            <div>
                                <strong>{TYPE_LABEL[p.type] || p.type}</strong> — {formatPrice(p.currency, p.total_price)}
                            </div>
                            <button type="button" onClick={() => onSaveTrip(p, idx)} disabled={savingIndex === idx}>
                                {savingIndex === idx ? 'Saving…' : 'Save trip'}
                            </button>
                        </div>

                        <div style={{ marginTop: 10, display: 'grid', gap: 8 }}>
                            {(p.segments || []).map(s => (
                                <div key={s.segment_index} style={{ padding: 10, border: '1px solid #eee', borderRadius: 8 }}>
                                    <div>
                                        <strong>Flight {s.segment_index}</strong> — {s.flight.airline.code}
                                        {s.flight.number} — {s.flight.departure_airport.code} → {s.flight.arrival_airport.code}
                                    </div>
                                    <div style={{ opacity: 0.85 }}>Local: {s.departure.at_local} → {s.arrival.at_local}</div>
                                    <div style={{ opacity: 0.85 }}>UTC: {s.departure.at_utc} → {s.arrival.at_utc}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}

                {results.length === 0 && <div style={{ opacity: 0.7 }}>No results yet.</div>}
            </div>
        </div>
    )
}
