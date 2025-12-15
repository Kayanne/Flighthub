async function parseResponse(res) {
    const text = await res.text()
    const data = text ? JSON.parse(text) : null

    if (!res.ok) {
        const err = new Error(data?.message || `HTTP ${res.status}`)
        err.status = res.status
        err.data = data
        throw err
    }

    return data
}

export async function apiGet(path) {
    const res = await fetch(path, { headers: { Accept: 'application/json' } })
    return parseResponse(res)
}

export async function apiPost(path, body) {
    const res = await fetch(path, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(body),
    })
    return parseResponse(res)
}
