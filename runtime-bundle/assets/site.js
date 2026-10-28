/*
 * For every visitor: blocks fade in as they scroll into view (unless the visitor prefers reduced motion).
 */
const reveal = () => {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add('cz-in');
                observer.unobserve(entry.target);
            }
        }
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    document.querySelectorAll('main > .cz-node:not(.cz-in)').forEach((el, i) => {
        const r = el.getBoundingClientRect();
        if (r.top < innerHeight) { el.classList.add('cz-in'); return; }
        el.classList.add('cz-reveal');
        observer.observe(el);
    });
};
document.addEventListener('turbo:load', reveal);
if (document.readyState !== 'loading') reveal(); else document.addEventListener('DOMContentLoaded', reveal);

/*
 * Quiz: answer once per question, show right/wrong and the explanation, then the score.
 */
document.addEventListener('click', (e) => {
    const option = e.target.closest('[data-cz-question] [data-option]');
    const reset = e.target.closest('[data-cz-quiz-reset]');
    if (reset) {
        const quiz = reset.closest('[data-cz-quiz]');
        quiz.querySelectorAll('[data-option]').forEach((b) => { b.disabled = false; delete b.dataset.state; });
        quiz.querySelectorAll('[data-explanation]').forEach((p) => { p.hidden = true; });
        quiz.querySelectorAll('[data-cz-question]').forEach((q) => { delete q.dataset.answered; });
        quiz.querySelector('[data-cz-score]').hidden = true;
        return;
    }
    if (!option || option.closest('.cz-picking')) return;
    const question = option.closest('[data-cz-question]');
    if (question.dataset.answered) return;
    const right = option.dataset.option === question.dataset.correct;
    question.dataset.answered = right ? 'right' : 'wrong';
    option.dataset.state = right ? 'right' : 'wrong';
    question.querySelector(`[data-option="${question.dataset.correct}"]`)?.setAttribute('data-state', 'right');
    question.querySelectorAll('[data-option]').forEach((b) => { b.disabled = true; });
    const explanation = question.querySelector('[data-explanation]');
    if (explanation) explanation.hidden = false;
    const quiz = question.closest('[data-cz-quiz]');
    const all = quiz.querySelectorAll('[data-cz-question]');
    const answered = quiz.querySelectorAll('[data-cz-question][data-answered]');
    if (all.length === answered.length) {
        quiz.querySelector('[data-points]').textContent = quiz.querySelectorAll('[data-answered="right"]').length;
        quiz.querySelector('[data-cz-score]').hidden = false;
    }
});

/*
 * Collections: search, filter chips and sortable table columns, all in the browser.
 */
const applyCollection = (box) => {
    const term = (box.querySelector('[data-search]')?.value ?? '').trim().toLowerCase();
    const active = box.querySelector('[data-filter][data-active]')?.dataset.filter ?? '';
    box.querySelectorAll('[data-item]').forEach((item) => {
        item.hidden = (term && !item.dataset.text.includes(term)) || (active && item.dataset.category !== active);
    });
};
document.addEventListener('input', (e) => {
    if (e.target.matches('[data-cz-collection] [data-search]')) applyCollection(e.target.closest('[data-cz-collection]'));
});
document.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-cz-collection] [data-filter]');
    if (chip) {
        chip.parentElement.querySelectorAll('[data-filter]').forEach((c) => c.toggleAttribute('data-active', c === chip));
        applyCollection(chip.closest('[data-cz-collection]'));
        return;
    }
    const th = e.target.closest('[data-cz-sortable] th[data-sort]');
    if (!th) return;
    const table = th.closest('table');
    const index = Number(th.dataset.sort);
    const numeric = th.dataset.numeric === '1';
    const dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
    table.querySelectorAll('th[data-sort]').forEach((h) => delete h.dataset.dir);
    th.dataset.dir = dir;
    const rows = [...table.tBodies[0].rows];
    rows.sort((a, b) => {
        const x = a.cells[index]?.dataset.value ?? '';
        const y = b.cells[index]?.dataset.value ?? '';
        const r = numeric ? (parseFloat(x) || 0) - (parseFloat(y) || 0) : x.localeCompare(y, document.documentElement.lang || 'pl');
        return dir === 'asc' ? r : -r;
    });
    table.tBodies[0].append(...rows);
});

/*
 * Forms: send without leaving the page, show what is missing next to the field, then the thank-you note.
 */
const formMessages = {
    pl: { email: 'Podaj poprawny adres e-mail', phone: 'Podaj poprawny numer telefonu', number: 'Podaj liczbę', date: 'Podaj datę', option: 'Wybierz jedną z opcji', option_full: 'Ten termin właśnie się zapełnił — wybierz inny', too_many: 'Za dużo prób, spróbuj za kilka minut.', closed: 'Zapisy są już zamknięte.', not_enough: 'Za mało wolnych miejsc — zostało: ', draft: 'Ten formularz nie jest jeszcze opublikowany.', failed: 'Nie udało się wysłać, spróbuj ponownie.' },
    en: { email: 'Enter a valid e-mail address', phone: 'Enter a valid phone number', number: 'Enter a number', date: 'Enter a date', option: 'Choose one of the options', option_full: 'This one has just filled up — choose another', too_many: 'Too many tries, please wait a few minutes.', closed: 'This form is closed.', not_enough: 'Not enough places left: ', draft: 'This form is not published yet.', failed: 'Could not send, please try again.' },
};
document.addEventListener('submit', async (e) => {
    const form = e.target.closest('[data-cz-form]');
    if (!form) return;
    e.preventDefault();
    const t = formMessages[(document.body.lang || document.documentElement.lang || 'en').slice(0, 2)] ?? formMessages.en;
    const message = form.querySelector('[data-cz-form-message]');
    const button = form.querySelector('[type=submit]');
    form.querySelectorAll('[data-error]').forEach((p) => { p.hidden = true; });
    form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    message.hidden = true;
    button.disabled = true;
    try {
        const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (result.ok) {
            form.hidden = true;
            form.parentElement.querySelector('[data-cz-form-done]').hidden = false;
            const left = form.parentElement.querySelector('[data-cz-left]');
            if (left && result.left !== null && result.left !== undefined) left.textContent = left.textContent.replace(/^\d+/, result.left);
            return;
        }
        if (result.errors) {
            let first = null;
            for (const [index, code] of Object.entries(result.errors)) {
                const field = form.querySelector(`[data-field="${index}"]`);
                if (!field) continue;
                const error = field.querySelector('[data-error]');
                error.textContent = code === 'required' ? form.dataset.requiredText : (t[code] ?? code);
                error.hidden = false;
                field.querySelectorAll('input, textarea, select').forEach((el) => el.setAttribute('aria-invalid', 'true'));
                first ??= field;
            }
            first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            message.textContent = (t[result.error] ?? t.failed) + (result.error === 'not_enough' ? result.left : '');
            message.hidden = false;
        }
    } catch {
        message.textContent = t.failed;
        message.hidden = false;
    } finally {
        button.disabled = false;
    }
});

/*
 * Announcement bar: a visitor who closed it does not see the same note again.
 */
const announcement = () => {
    const bar = document.querySelector('[data-cz-announcement]');
    try {
        if (bar && localStorage.getItem('cz-announcement') === bar.dataset.czAnnouncement) bar.hidden = true;
    } catch {}
};
document.addEventListener('turbo:load', announcement);
if (document.readyState !== 'loading') announcement(); else document.addEventListener('DOMContentLoaded', announcement);
document.addEventListener('click', (e) => {
    const close = e.target.closest('[data-cz-announcement-close]');
    if (!close) return;
    const bar = close.closest('[data-cz-announcement]');
    bar.hidden = true;
    try { localStorage.setItem('cz-announcement', bar.dataset.czAnnouncement); } catch {}
});

/*
 * Countdown to an event, in the visitor's clock.
 */
const tick = () => {
    document.querySelectorAll('[data-cz-countdown]').forEach((box) => {
        const left = Math.max(0, new Date(box.dataset.czCountdown) - Date.now());
        const done = left === 0;
        box.querySelector('[data-running]').hidden = done;
        box.querySelector('[data-done]').hidden = !done;
        const parts = [Math.floor(left / 864e5), Math.floor(left / 36e5) % 24, Math.floor(left / 6e4) % 60, Math.floor(left / 1e3) % 60];
        parts.forEach((value, i) => { box.querySelector(`[data-unit="${i}"]`).textContent = i ? String(value).padStart(2, '0') : value; });
    });
};
setInterval(tick, 1000);
document.addEventListener('turbo:load', tick);
if (document.readyState !== 'loading') tick(); else document.addEventListener('DOMContentLoaded', tick);

/*
 * Maps (Leaflet, loaded only when a page has one): photo pins with a route in time order, or places.
 */
const escapeText = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const maps = async () => {
    const boxes = [...document.querySelectorAll('[data-cz-map]:not([data-ready])')];
    if (!boxes.length) return;
    const L = (await import('leaflet')).default;
    for (const box of boxes) {
        box.dataset.ready = '1';
        const { points, route, dark } = JSON.parse(box.dataset.czMap);
        const map = L.map(box, { scrollWheelZoom: false, zoomControl: true, attributionControl: true });
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);
        box.classList.toggle('cz-map-dark', !!dark);
        const markers = points.map((p, i) => {
            const html = p.image
                ? `<span class="cz-pin cz-pin-photo" style="background-image:url('${encodeURI(p.image)}')"></span>`
                : `<span class="cz-pin">${i + 1}</span>`;
            const marker = L.marker([p.lat, p.lng], { icon: L.divIcon({ html, className: '', iconSize: p.image ? [44, 44] : [32, 32], iconAnchor: p.image ? [22, 22] : [16, 16] }) });
            const body = [
                p.image ? `<img src="${encodeURI(p.image)}" alt="" class="cz-pop-img">` : '',
                `<strong>${escapeText(p.title)}</strong>`,
                p.date ? `<span class="cz-pop-meta">${escapeText(new Date(p.date).toLocaleDateString(document.body.lang || document.documentElement.lang || undefined, { day: 'numeric', month: 'long', year: 'numeric' }))}</span>` : '',
                p.text ? `<span class="cz-pop-meta">${escapeText(p.text)}</span>` : '',
                p.href ? `<a href="${encodeURI(p.href)}">→</a>` : '',
            ].join('');
            marker.bindPopup(`<div class="cz-pop">${body}</div>`, { maxWidth: 260 });
            marker.addTo(map);
            marker.group = p.group;
            return marker;
        });
        if (route && points.length > 1) {
            L.polyline(points.map((p) => [p.lat, p.lng]), { color: getComputedStyle(box).getPropertyValue('--primary') || '#555', weight: 2, opacity: 0.7, dashArray: '6 8' }).addTo(map);
        }
        const fit = (list) => list.length === 1 ? map.setView(list[0].getLatLng(), 13) : map.fitBounds(L.featureGroup(list).getBounds().pad(0.15));
        fit(markers);
        box.closest('.cz-node')?.querySelectorAll('[data-cz-map-focus]').forEach((b) => b.addEventListener('click', () => {
            const group = markers.filter((m) => m.group === b.dataset.czMapFocus);
            if (group.length) { map.flyToBounds(L.featureGroup(group).getBounds().pad(0.4), { maxZoom: 14, duration: 1.2 }); group[0].openPopup(); }
        }));
        box.czMap = map;
    }
};
document.addEventListener('turbo:load', maps);
if (document.readyState !== 'loading') maps(); else document.addEventListener('DOMContentLoaded', maps);
// Turbo keeps a snapshot of the page; a live Leaflet map in it would come back broken.
document.addEventListener('turbo:before-cache', () => document.querySelectorAll('[data-cz-map][data-ready]').forEach((box) => { box.czMap?.remove(); delete box.dataset.ready; }));

/*
 * Phone menu: close it after choosing a section on the same page, or when tapping elsewhere.
 */
document.addEventListener('click', (e) => {
    document.querySelectorAll('details.cz-mobile-nav[open]').forEach((menu) => {
        if (!menu.contains(e.target) || e.target.closest('a')) menu.removeAttribute('open');
    });
});

/*
 * Widgets: games, 3D scenes and animations the agent wrote, each in a sandboxed frame. The page keeps what a widget
 * stores (a high score) in this browser, sizes frames that fit their content, and tells the owner's studio when a
 * widget breaks. Messages from frames are only trusted for what they are: a number, a small object, a line of text.
 */
const widgetKey = (name) => `cz-widget:${name}`;
const widgets = () => {
    document.querySelectorAll('iframe[data-cz-widget]:not([src])').forEach((frame) => {
        let stored = '';
        try { stored = localStorage.getItem(widgetKey(frame.dataset.czWidget)) || ''; } catch (e) { /* storage blocked */ }
        frame.src = frame.dataset.src + (stored ? '#' + encodeURIComponent(stored) : '');
    });
};
const reported = new Set();
window.addEventListener('message', (e) => {
    const frame = [...document.querySelectorAll('iframe[data-cz-widget]')].find((f) => f.contentWindow === e.source);
    if (!frame || !e.data || e.data.cadenzaWidget !== frame.dataset.czWidget) return;
    const name = frame.dataset.czWidget;
    if (typeof e.data.height === 'number' && frame.src.includes('auto=1')) {
        frame.style.height = Math.min(Math.max(e.data.height, 48), 4000) + 'px';
    }
    if (e.data.store && typeof e.data.store === 'object') {
        const json = JSON.stringify(e.data.store);
        if (json.length < 50000) { try { localStorage.setItem(widgetKey(name), json); } catch (err) { /* storage full or blocked */ } }
    }
    if (typeof e.data.error === 'string' && !reported.has(name + e.data.error)) {
        // Kept only when the owner is looking (the site answers 404 to anyone else).
        reported.add(name + e.data.error);
        fetch(`/_cadenza/widget/${encodeURIComponent(name)}/error`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ version: Number(frame.dataset.version), error: e.data.error.slice(0, 500) }),
        }).catch(() => {});
    }
});
// What a frame cannot see by itself: where it is as the page scrolls (0 = just below the screen, 1 = just above it)
// and where the pointer is over it (0–1) — so a background can follow the mouse and the scroll.
let widgetTick = 0;
const tellWidgets = (pointer) => {
    if (widgetTick) return;
    widgetTick = requestAnimationFrame(() => {
        widgetTick = 0;
        const vh = window.innerHeight;
        document.querySelectorAll('iframe[data-cz-widget][src]').forEach((frame) => {
            const r = frame.getBoundingClientRect();
            if (r.bottom < -vh || r.top > 2 * vh) return;
            const message = { cadenzaView: { scroll: Math.min(1, Math.max(0, (vh - r.top) / (vh + r.height))), visible: r.bottom > 0 && r.top < vh } };
            if (pointer) message.cadenzaView.pointer = { x: (pointer.clientX - r.left) / r.width, y: (pointer.clientY - r.top) / r.height };
            frame.contentWindow?.postMessage(message, '*');
        });
    });
};
let lastPointer = null;
window.addEventListener('scroll', () => tellWidgets(lastPointer), { passive: true });
window.addEventListener('pointermove', (e) => { lastPointer = { clientX: e.clientX, clientY: e.clientY }; tellWidgets(lastPointer); }, { passive: true });
document.addEventListener('turbo:load', widgets);
document.addEventListener('turbo:frame-load', widgets);
if (document.readyState !== 'loading') widgets(); else document.addEventListener('DOMContentLoaded', widgets);
