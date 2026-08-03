/* Thin fetch wrapper shared by all frontend modules. */

async function apiGet(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
}

async function apiPost(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {}),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
}

/** Multipart upload — fields is a plain object of form fields, file is a File/Blob. */
async function apiUpload(url, fields, file) {
    const formData = new FormData();
    Object.keys(fields || {}).forEach(key => formData.append(key, fields[key]));
    formData.append('file', file);

    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        body: formData,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
}

function debounce(fn, wait) {
    let t;
    return function (...args) {
        clearTimeout(t);
        t = setTimeout(() => fn.apply(this, args), wait);
    };
}
