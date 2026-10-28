/*
 * Cadenza studio panel: the owner's chat bar and side panel, the element picker, sketches and approvals.
 * Talks to the Cadenza harness (agent + proposals) over HTTP with server-sent events.
 */

const I18N = {
    en: {
        placeholder: 'Tell me what to change…',
        placeholderPicked: 'What should happen to the selection?',
        send: 'Send', open: 'Open panel', pick: 'Point at an element', picking: 'Click elements on the page, Esc to finish',
        newChat: 'New conversation', collapse: 'Collapse to bar', hide: 'Hide', show: 'Show Cadenza',
        idle: 'Ready', thinking: 'Thinking', working: 'Working', waiting: 'Waiting for you',
        hello: 'Hi! I am Cadenza. Tell me what your site should be — or point at anything on the page and tell me what to change.',
        suggestions: ['A website for my café with a menu and opening hours', 'A portfolio for my travel photos', 'A page that explains photosynthesis for high school'],
        showSketch: 'Show sketch', approve: 'Approve', reject: 'Reject', revise: 'Ask for changes',
        approved: 'Published', rejected: 'Rejected', pending: 'Waiting for your decision', sketch: 'Sketch',
        added: 'added', changed: 'changed', removed: 'removed', pages: 'pages', themeChanged: 'theme',
        before: 'Before', after: 'After', published: 'Published — the change is live.', discarded: 'Draft rejected.',
        error: 'Something went wrong', harnessDown: 'Cannot reach the Cadenza studio (harness). Is it running?',
        steps: 'steps', selection: 'Selected', showChanges: 'Show changes',
    },
    pl: {
        placeholder: 'Powiedz, co zmienić…',
        placeholderPicked: 'Co zrobić z zaznaczeniem?',
        send: 'Wyślij', open: 'Otwórz panel', pick: 'Wskaż element', picking: 'Klikaj elementy na stronie, Esc kończy',
        newChat: 'Nowa rozmowa', collapse: 'Zwiń do paska', hide: 'Schowaj', show: 'Pokaż Cadenzę',
        idle: 'Gotowa', thinking: 'Myślę', working: 'Pracuję', waiting: 'Czekam na Ciebie',
        hello: 'Cześć! Jestem Cadenza. Opowiedz, jaka ma być Twoja strona — albo wskaż coś na stronie i powiedz, co zmienić.',
        suggestions: ['Strona mojej kawiarni z menu i godzinami otwarcia', 'Portfolio moich zdjęć z podróży', 'Strona, która wytłumaczy fotosyntezę na poziomie matury'],
        showSketch: 'Pokaż szkic', approve: 'Zatwierdź', reject: 'Odrzuć', revise: 'Popraw',
        approved: 'Opublikowano', rejected: 'Odrzucono', pending: 'Czeka na Twoją decyzję', sketch: 'Szkic',
        added: 'nowe', changed: 'zmienione', removed: 'usunięte', pages: 'strony', themeChanged: 'motyw',
        before: 'Przed', after: 'Po', published: 'Opublikowano — zmiana jest na stronie.', discarded: 'Szkic odrzucony.',
        error: 'Coś poszło nie tak', harnessDown: 'Nie mogę połączyć się ze studiem Cadenzy (harness). Czy działa?',
        steps: 'kroki', selection: 'Zaznaczone', showChanges: 'Pokaż zmiany',
    },
};
const LANG = (navigator.language || 'en').toLowerCase().startsWith('pl') ? 'pl' : 'en';
const t = (key) => I18N[LANG][key] ?? I18N.en[key] ?? key;

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content ?? null;
const HARNESS = meta('cz-harness');
const TOKEN = meta('cz-token');

const store = {
    get(key, fallback = null) { try { return localStorage.getItem('cz.' + key) ?? fallback; } catch { return fallback; } },
    set(key, value) { try { value === null ? localStorage.removeItem('cz.' + key) : localStorage.setItem('cz.' + key, value); } catch {} },
};

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function markdown(text) {
    const inline = (s) => escapeHtml(s)
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>')
        .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]*)\)/g, '<a class="underline underline-offset-2" href="$2">$1</a>');
    const blocks = String(text ?? '').trim().split(/\n{2,}/);
    return blocks.map((block) => {
        const lines = block.split('\n');
        if (lines.every((l) => /^\s*[-*]\s+/.test(l))) {
            return '<ul>' + lines.map((l) => '<li>' + inline(l.replace(/^\s*[-*]\s+/, '')) + '</li>').join('') + '</ul>';
        }
        if (lines.every((l) => /^\s*\d+[.)]\s+/.test(l))) {
            return '<ol>' + lines.map((l) => '<li>' + inline(l.replace(/^\s*\d+[.)]\s+/, '')) + '</li>').join('') + '</ol>';
        }
        return '<p>' + lines.map(inline).join('<br>') + '</p>';
    }).join('');
}

const ICONS = {
    sparkle: '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
    send: '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
    target: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4"/>',
    panel: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M15 3v18"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    minimize: '<path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    tool: '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
    chevron: '<path d="m9 18 6-6-6-6"/>',
    alert: '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
};
const icon = (name, cls = 'size-4') => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="${cls}">${ICONS[name]}</svg>`;

class Studio {
    constructor(root) {
        this.root = root;
        this.conversationId = store.get('conversation');
        this.selection = [];
        this.busy = false;
        this.proposals = new Map();
        this.exitingSketch = false;
        this.build();
        this.bind();
        this.setState(store.get('panel', 'closed'));
        this.loadHistory();
    }

    // ---------------------------------------------------------------------------------------------- DOM

    build() {
        this.root.innerHTML = `
<div class="cz-bar fixed bottom-5 left-1/2 z-50 w-[min(680px,calc(100vw-2rem))] -translate-x-1/2">
  <form data-cz="bar-form" class="flex items-center gap-1.5 rounded-2xl border border-border bg-background/95 p-1.5 pl-2 shadow-[0_18px_60px_-15px_rgba(0,0,0,.35)] backdrop-blur">
    <span class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white">${icon('sparkle')}</span>
    <button type="button" data-cz="pick" title="${t('pick')}" class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('target')}</button>
    <div data-cz="bar-chips" class="flex shrink-0 gap-1"></div>
    <input data-cz="bar-input" class="h-9 min-w-0 flex-1 bg-transparent px-1 text-[15px] outline-none placeholder:text-muted-foreground" placeholder="${t('placeholder')}" autocomplete="off">
    <span data-cz="bar-status" class="hidden shrink-0 items-center gap-1.5 rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground sm:flex"></span>
    <button type="button" data-cz="open" title="${t('open')} (⌘K)" class="flex h-8 shrink-0 items-center gap-1 rounded-lg px-2 text-xs text-muted-foreground hover:bg-muted hover:text-foreground">${icon('panel')}<kbd class="hidden font-sans sm:inline">⌘K</kbd></button>
    <button type="submit" class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-foreground text-background disabled:opacity-40">${icon('send')}</button>
  </form>
</div>

<aside class="cz-sheet fixed inset-y-3 right-3 z-50 flex w-[min(440px,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-2xl border border-border bg-background text-foreground shadow-[0_30px_80px_-20px_rgba(0,0,0,.45)]">
  <header class="flex items-center gap-2 border-b border-border px-4 py-3">
    <span class="flex size-8 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white">${icon('sparkle')}</span>
    <div class="min-w-0 flex-1">
      <div class="text-sm font-semibold leading-tight">Cadenza</div>
      <div class="flex items-center gap-2 text-xs text-muted-foreground"><span data-cz="status" class="flex items-center gap-1.5"></span><span data-cz="model" class="truncate opacity-70"></span></div>
    </div>
    <button type="button" data-cz="new" title="${t('newChat')}" class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('plus')}</button>
    <button type="button" data-cz="collapse" title="${t('collapse')}" class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('minimize')}</button>
    <button type="button" data-cz="hide" title="${t('hide')}" class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('x')}</button>
  </header>
  <div data-cz="messages" class="flex flex-1 flex-col gap-4 overflow-y-auto px-4 py-5 text-[14px] leading-relaxed"></div>
  <form data-cz="form" class="border-t border-border p-3">
    <div data-cz="chips" class="mb-2 hidden flex-wrap gap-1.5"></div>
    <div class="flex items-end gap-2 rounded-xl border border-border bg-muted/40 p-1.5 focus-within:border-foreground/30">
      <button type="button" data-cz="pick" title="${t('pick')}" class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-background hover:text-foreground">${icon('target')}</button>
      <textarea data-cz="input" rows="1" class="max-h-40 min-h-8 flex-1 resize-none bg-transparent px-1 py-1.5 text-[14px] outline-none placeholder:text-muted-foreground" placeholder="${t('placeholder')}"></textarea>
      <button type="submit" class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-foreground text-background disabled:opacity-40">${icon('send')}</button>
    </div>
  </form>
</aside>

<button type="button" data-cz="show" title="${t('show')} (⌘K)" class="cz-dot fixed bottom-4 right-4 z-50 hidden size-11 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-lg">${icon('sparkle', 'size-5')}</button>

<div id="cz-agent-cursor"><svg width="26" height="26" viewBox="0 0 24 24"><path d="M4 2l16 9-7 2-3 7z" fill="oklch(0.62 0.2 290)" stroke="white" stroke-width="1.5" stroke-linejoin="round"/></svg><span class="ml-4 -mt-1 inline-block rounded-md bg-[oklch(0.62_0.2_290)] px-2 py-0.5 text-[11px] font-semibold text-white">Cadenza</span></div>
<div id="cz-picker-box" hidden><span></span></div>
<div data-cz="toasts" class="pointer-events-none fixed left-1/2 top-4 z-[80] flex -translate-x-1/2 flex-col items-center gap-2"></div>`;

        this.el = (name) => this.root.querySelector(`[data-cz="${name}"]`);
        this.messages = this.el('messages');
        this.input = this.el('input');
        this.barInput = this.el('bar-input');
        this.pickerBox = this.root.querySelector('#cz-picker-box');
        this.cursor = this.root.querySelector('#cz-agent-cursor');
    }

    bind() {
        this.root.querySelectorAll('[data-cz="pick"]').forEach((b) => b.addEventListener('click', () => this.togglePicker()));
        this.el('open').addEventListener('click', () => this.setState('open'));
        this.el('collapse').addEventListener('click', () => this.setState('closed'));
        this.el('hide').addEventListener('click', () => this.setState('hidden'));
        this.el('show').addEventListener('click', () => this.setState('open'));
        this.el('new').addEventListener('click', () => this.newConversation());

        this.el('bar-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const text = this.barInput.value.trim();
            if (!text && !this.selection.length) { this.setState('open'); return; }
            this.barInput.value = '';
            this.setState('open');
            this.send(text);
        });
        this.el('form').addEventListener('submit', (e) => {
            e.preventDefault();
            const text = this.input.value.trim();
            if (!text) return;
            this.input.value = '';
            this.autosize();
            this.send(text);
        });
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); this.el('form').requestSubmit(); }
        });
        this.input.addEventListener('input', () => this.autosize());

        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                const state = this.root.dataset.state;
                this.setState(state === 'open' ? 'closed' : 'open');
            } else if (e.key === 'Escape') {
                if (this.picking) this.togglePicker(false);
            }
        });

        // Picker
        this.onPickMove = (e) => this.pickMove(e);
        this.onPickClick = (e) => this.pickClick(e);

        // Sketch banner and links inside the page
        document.addEventListener('click', (e) => {
            const action = e.target.closest('[data-cz-action]');
            if (!action) return;
            if (action.dataset.czAction === 'marks') {
                document.documentElement.classList.toggle('cz-marks', action.checked);
                store.set('marks', action.checked ? '1' : null);
                return;
            }
            e.preventDefault();
            this.bannerAction(action.dataset.czAction);
        });
        document.addEventListener('turbo:before-visit', (e) => this.keepSketchOnVisit(e));
        document.addEventListener('turbo:load', () => this.afterLoad());
        this.afterLoad();
    }

    autosize() {
        this.input.style.height = 'auto';
        this.input.style.height = Math.min(this.input.scrollHeight, 160) + 'px';
    }

    setState(state) {
        this.root.dataset.state = state;
        store.set('panel', state);
        if (state === 'open') setTimeout(() => this.input.focus(), 250);
        if (state === 'closed') setTimeout(() => this.barInput.focus({ preventScroll: true }), 50);
    }

    setStatus(kind, text = null) {
        const dot = { idle: 'bg-emerald-500', thinking: 'bg-violet-500 animate-pulse', working: 'bg-violet-500 animate-pulse', waiting: 'bg-amber-500 animate-pulse', error: 'bg-rose-500' }[kind] ?? 'bg-muted-foreground';
        const label = escapeHtml(text ?? t(kind));
        this.el('status').innerHTML = `<span class="size-1.5 rounded-full ${dot}"></span>${label}`;
        const bar = this.el('bar-status');
        bar.innerHTML = `<span class="size-1.5 rounded-full ${dot}"></span>${label}`;
        bar.classList.toggle('sm:flex', kind !== 'idle');
    }

    toast(text, kind = 'ok') {
        const el = document.createElement('div');
        el.className = 'cz-msg pointer-events-auto flex items-center gap-2 rounded-full border border-border bg-background px-4 py-2 text-sm shadow-lg';
        el.innerHTML = (kind === 'ok' ? `<span class="text-emerald-600">${icon('check')}</span>` : `<span class="text-rose-600">${icon('alert')}</span>`) + escapeHtml(text);
        this.el('toasts').append(el);
        setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, 3200);
    }

    // ---------------------------------------------------------------------------------------------- messages

    scrollDown() {
        requestAnimationFrame(() => { this.messages.scrollTop = this.messages.scrollHeight; });
    }

    addUser(text, selection = []) {
        const el = document.createElement('div');
        el.className = 'cz-msg ml-10 self-end rounded-2xl rounded-br-md bg-foreground px-3.5 py-2 text-background';
        el.innerHTML = (selection.length ? `<div class="mb-1 flex flex-wrap gap-1">${selection.map((s) => `<span class="rounded-md bg-background/15 px-1.5 py-0.5 text-[11px]">◎ ${escapeHtml(s.label)}</span>`).join('')}</div>` : '') + `<div class="whitespace-pre-wrap">${escapeHtml(text)}</div>`;
        this.messages.append(el);
        this.scrollDown();
    }

    addAssistant() {
        const el = document.createElement('div');
        el.className = 'cz-msg mr-6 flex flex-col gap-2';
        el.innerHTML = `
<div data-part="trace" class="hidden"></div>
<div data-part="thinking" class="flex items-center gap-2 text-muted-foreground"><span class="cz-thinking flex gap-1"><span class="size-1.5 rounded-full bg-current"></span><span class="size-1.5 rounded-full bg-current"></span><span class="size-1.5 rounded-full bg-current"></span></span><span data-part="thinking-label">${t('thinking')}…</span></div>
<div data-part="text" class="cz-md hidden"></div>
<div data-part="cards" class="flex flex-col gap-2"></div>`;
        this.messages.append(el);
        this.scrollDown();
        return {
            el,
            part: (name) => el.querySelector(`[data-part="${name}"]`),
        };
    }

    addAssistantText(text) {
        const turn = this.addAssistant();
        turn.part('thinking').remove();
        const box = turn.part('text');
        box.classList.remove('hidden');
        box.innerHTML = markdown(text);
        return turn;
    }

    welcome() {
        const turn = this.addAssistantText(t('hello'));
        const list = document.createElement('div');
        list.className = 'flex flex-col gap-1.5';
        list.innerHTML = t('suggestions').map((s) => `<button type="button" class="rounded-xl border border-border px-3 py-2 text-left text-[13px] hover:bg-muted">${escapeHtml(s)}</button>`).join('');
        list.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => this.send(b.textContent)));
        turn.part('cards').append(list);
    }

    traceStep(turn, data) {
        const trace = turn.part('trace');
        trace.classList.remove('hidden');
        if (!trace.dataset.ready) {
            trace.dataset.ready = '1';
            trace.innerHTML = `<details class="group rounded-xl border border-border bg-muted/30 text-[12px]"><summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2 text-muted-foreground"><span class="transition group-open:rotate-90">${icon('chevron', 'size-3.5')}</span><span data-part="trace-title"></span></summary><ol data-part="trace-list" class="space-y-1.5 px-3 pb-3"></ol></details>`;
        }
        const list = trace.querySelector('[data-part="trace-list"]');
        let row = list.querySelector(`[data-step="${CSS.escape(data.id)}"]`);
        if (!row) {
            row = document.createElement('li');
            row.dataset.step = data.id;
            row.className = 'flex items-start gap-2';
            list.append(row);
        }
        const mark = data.state === 'done' ? `<span class="mt-0.5 text-emerald-600">${icon('check', 'size-3.5')}</span>`
            : data.state === 'error' ? `<span class="mt-0.5 text-rose-600">${icon('alert', 'size-3.5')}</span>`
            : `<span class="mt-1 size-2.5 animate-spin rounded-full border-2 border-violet-500 border-t-transparent"></span>`;
        row.innerHTML = `${mark}<div class="min-w-0 flex-1"><div class="text-foreground">${escapeHtml(data.label ?? data.name)}</div>${data.detail ? `<div class="truncate text-muted-foreground">${escapeHtml(data.detail)}</div>` : ''}</div>`;
        const count = list.children.length;
        trace.querySelector('[data-part="trace-title"]').textContent = `${data.state === 'start' ? (data.label ?? data.name) + '…' : t('working')} · ${count} ${t('steps')}`;
        if (data.state === 'start') turn.part('thinking-label').textContent = (data.label ?? t('working')) + '…';
        this.scrollDown();
    }

    proposalCard(container, p) {
        let card = this.root.querySelector(`[data-proposal="${CSS.escape(p.proposalId)}"]`);
        if (!card) {
            card = document.createElement('div');
            card.dataset.proposal = p.proposalId;
            container.append(card);
        }
        this.proposals.set(p.proposalId, p);
        const chips = [];
        const c = p.changes ?? {};
        if (c.added) chips.push(`<span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-emerald-700 dark:text-emerald-300">+${c.added} ${t('added')}</span>`);
        if (c.changed) chips.push(`<span class="rounded-full bg-sky-500/15 px-2 py-0.5 text-sky-700 dark:text-sky-300">~${c.changed} ${t('changed')}</span>`);
        if (c.removed) chips.push(`<span class="rounded-full bg-rose-500/15 px-2 py-0.5 text-rose-700 dark:text-rose-300">−${c.removed} ${t('removed')}</span>`);
        if (c.pagesAdded) chips.push(`<span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-emerald-700 dark:text-emerald-300">+${c.pagesAdded} ${t('pages')}</span>`);
        if (c.theme) chips.push(`<span class="rounded-full bg-violet-500/15 px-2 py-0.5 text-violet-700 dark:text-violet-300">${t('themeChanged')}</span>`);
        const decided = p.status === 'applied' || p.status === 'rejected';
        const statusLine = p.status === 'applied' ? `<span class="text-emerald-600">${icon('check', 'size-3.5')}</span>${t('approved')}`
            : p.status === 'rejected' ? t('rejected') : `<span class="size-1.5 animate-pulse rounded-full bg-amber-500"></span>${t('pending')}`;
        card.className = `rounded-xl border ${decided ? 'border-border opacity-80' : 'border-amber-400/60 bg-amber-50/60 dark:bg-amber-950/30'} p-3`;
        card.innerHTML = `
<div class="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">${t('sketch')} #${escapeHtml(p.draftId)}</div>
<div class="mt-1 font-medium leading-snug">${escapeHtml(p.summary)}</div>
<div class="mt-2 flex flex-wrap gap-1 text-[11px]">${chips.join('')}</div>
<div class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">${statusLine}</div>
${decided ? '' : `<div class="mt-3 flex flex-wrap gap-1.5">
  <button type="button" data-do="show" class="flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs font-medium hover:bg-muted">${icon('eye', 'size-3.5')}${t('showSketch')}</button>
  <button type="button" data-do="approve" class="flex items-center gap-1.5 rounded-lg bg-foreground px-2.5 py-1.5 text-xs font-semibold text-background hover:opacity-90">${icon('check', 'size-3.5')}${t('approve')}</button>
  <button type="button" data-do="reject" class="rounded-lg px-2.5 py-1.5 text-xs text-muted-foreground hover:bg-muted">${t('reject')}</button>
</div>`}`;
        card.querySelector('[data-do="show"]')?.addEventListener('click', () => this.showSketch(p.draftId, p.focusPath));
        card.querySelector('[data-do="approve"]')?.addEventListener('click', () => this.decide(p.proposalId, 'approve'));
        card.querySelector('[data-do="reject"]')?.addEventListener('click', () => this.decide(p.proposalId, 'reject'));
        this.refreshWaiting();
        this.scrollDown();
    }

    refreshWaiting() {
        if (this.busy) return;
        const pending = [...this.proposals.values()].filter((p) => p.status !== 'applied' && p.status !== 'rejected').length;
        this.setStatus(pending ? 'waiting' : 'idle', pending ? `${t('waiting')} (${pending})` : null);
    }

    // ---------------------------------------------------------------------------------------------- talking to the harness

    async api(path, body = null) {
        const res = await fetch(HARNESS + path, {
            method: body === null ? 'GET' : 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Cadenza-Token': TOKEN },
            body: body === null ? undefined : JSON.stringify(body),
        });
        if (!res.ok) {
            const raw = await res.text();
            let message = raw;
            try { message = JSON.parse(raw).error ?? raw; } catch {}
            if (/<html|<!DOCTYPE/i.test(message)) message = (message.match(/<title>([^<]*)/i)?.[1] ?? 'Server error').trim();
            throw new Error(`${res.status} ${message.slice(0, 180)}`);
        }
        return res.json();
    }

    showModel(model) {
        const el = this.el('model');
        el.textContent = '· ' + model;
        el.title = model;
    }

    async loadHistory() {
        this.api('/api/info').then((info) => this.showModel(info.model)).catch(() => {});
        if (!this.conversationId) { this.welcome(); this.setStatus('idle'); return; }
        try {
            const data = await this.api(`/api/conversations/${encodeURIComponent(this.conversationId)}`);
            if (!data.turns?.length) { this.welcome(); }
            for (const turn of data.turns ?? []) {
                if (turn.role === 'user') this.addUser(turn.text, turn.selection ?? []);
                else {
                    const tt = this.addAssistantText(turn.text || '');
                    if (!turn.text) tt.part('text').classList.add('hidden');
                    for (const p of turn.proposals ?? []) this.proposalCard(tt.part('cards'), p);
                }
            }
            this.refreshWaiting();
        } catch (e) {
            this.welcome();
            this.setStatus('error', t('harnessDown'));
        }
    }

    newConversation() {
        this.conversationId = null;
        store.set('conversation', null);
        this.proposals.clear();
        this.messages.innerHTML = '';
        this.welcome();
        this.setStatus('idle');
    }

    async send(text) {
        if (this.busy) return;
        const selection = this.selection.map(({ id, label, type }) => ({ id, label, type }));
        if (!text) text = '';
        this.addUser(text, selection);
        this.clearSelection();
        if (this.picking) this.togglePicker(false);
        this.busy = true;
        this.setStatus('thinking');
        const turn = this.addAssistant();
        const url = new URL(location.href);

        try {
            const res = await fetch(HARNESS + '/api/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'X-Cadenza-Token': TOKEN },
                body: JSON.stringify({
                    conversationId: this.conversationId,
                    message: text,
                    context: { path: url.pathname, draftId: meta('cz-draft') ? Number(meta('cz-draft')) : null, selection, language: LANG, viewport: innerWidth < 768 ? 'mobile' : 'desktop' },
                }),
            });
            if (!res.ok || !res.body) throw new Error(`${res.status}`);
            await this.readStream(res.body, turn);
        } catch (e) {
            console.error('[Cadenza]', e);
            const box = turn.part('text');
            box.classList.remove('hidden');
            box.innerHTML = `<span class="text-rose-600">${escapeHtml(e.message === 'Failed to fetch' ? t('harnessDown') : t('error') + ': ' + e.message)}</span>`;
        } finally {
            turn.part('thinking')?.remove();
            this.busy = false;
            this.refreshWaiting();
            this.hideCursor();
        }
    }

    async readStream(body, turn) {
        const reader = body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        for (;;) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            let index;
            while ((index = buffer.indexOf('\n\n')) >= 0) {
                const raw = buffer.slice(0, index);
                buffer = buffer.slice(index + 2);
                let event = 'message';
                let data = '';
                for (const line of raw.split('\n')) {
                    if (line.startsWith('event:')) event = line.slice(6).trim();
                    else if (line.startsWith('data:')) data += line.slice(5).trim();
                }
                if (!data) continue;
                try { await this.handle(event, JSON.parse(data), turn); } catch (e) { console.error('[Cadenza] event', event, e); }
            }
        }
    }

    async handle(event, data, turn) {
        switch (event) {
            case 'conversation':
                if (data.model) this.showModel(data.model);
                this.conversationId = data.id;
                store.set('conversation', data.id);
                break;
            case 'status':
                turn.part('thinking-label').textContent = data.text + '…';
                this.setStatus('working', data.text);
                break;
            case 'tool':
                this.traceStep(turn, data);
                this.setStatus('working', data.label ?? data.name);
                break;
            case 'ui':
                await this.uiCommand(data);
                break;
            case 'proposal':
                this.proposalCard(turn.part('cards'), data);
                break;
            case 'message': {
                const box = turn.part('text');
                box.classList.remove('hidden');
                box.innerHTML = markdown(data.text);
                this.scrollDown();
                break;
            }
            case 'error': {
                const box = turn.part('text');
                box.classList.remove('hidden');
                box.insertAdjacentHTML('beforeend', `<p class="text-rose-600">${escapeHtml(data.message)}</p>`);
                break;
            }
        }
    }

    // ---------------------------------------------------------------------------------------------- driving the page

    visit(url, { exitSketch = false } = {}) {
        this.exitingSketch = exitSketch;
        if (window.Turbo) window.Turbo.visit(url);
        else location.href = url;
        return new Promise((resolve) => {
            const done = () => { document.removeEventListener('turbo:load', done); setTimeout(resolve, 50); };
            document.addEventListener('turbo:load', done);
            setTimeout(resolve, 4000);
        });
    }

    async uiCommand(cmd) {
        switch (cmd.command) {
            case 'navigate': {
                const target = new URL(cmd.path, location.origin);
                const draft = meta('cz-draft');
                if (draft && !target.searchParams.has('cz_draft')) target.searchParams.set('cz_draft', draft);
                if (target.pathname + target.search !== location.pathname + location.search) await this.visit(target.pathname + target.search);
                break;
            }
            case 'showSketch':
                await this.showSketch(cmd.draftId, cmd.path);
                break;
            case 'highlight':
                this.highlight(cmd.nodeIds ?? []);
                break;
            case 'toast':
                this.toast(cmd.text);
                break;
        }
    }

    async showSketch(draftId, path) {
        const target = new URL(path || location.pathname, location.origin);
        target.searchParams.set('cz_draft', draftId);
        await this.visit(target.pathname + target.search);
        const first = document.querySelector('.cz-node[data-cz-state]');
        if (first) {
            this.moveCursorTo(first);
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    highlight(ids) {
        let first = null;
        for (const id of ids) {
            const el = document.querySelector(`.cz-node[data-node-id="${CSS.escape(id)}"]`);
            if (!el) continue;
            first ??= el;
            el.classList.remove('cz-highlight');
            void el.offsetWidth;
            el.classList.add('cz-highlight');
            setTimeout(() => el.classList.remove('cz-highlight'), 2500);
        }
        if (first) {
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            this.moveCursorTo(first);
        }
    }

    moveCursorTo(el) {
        const r = el.getBoundingClientRect();
        const x = Math.min(innerWidth - 120, Math.max(10, r.left + Math.min(r.width * 0.6, 260)));
        const y = Math.min(innerHeight - 40, Math.max(10, r.top + Math.min(r.height * 0.4, 120)));
        this.cursor.classList.add('cz-visible');
        this.cursor.style.transform = `translate(${x}px, ${y}px)`;
        clearTimeout(this.cursorTimer);
        this.cursorTimer = setTimeout(() => this.hideCursor(), 3500);
    }

    hideCursor() {
        this.cursor?.classList.remove('cz-visible');
    }

    keepSketchOnVisit(e) {
        const draft = meta('cz-draft');
        if (!draft || this.exitingSketch) { this.exitingSketch = false; return; }
        const target = new URL(e.detail.url);
        if (target.origin !== location.origin || target.searchParams.has('cz_draft')) return;
        e.preventDefault();
        target.searchParams.set('cz_draft', draft);
        window.Turbo.visit(target.pathname + target.search);
    }

    async bannerAction(action) {
        const draft = meta('cz-draft');
        const url = new URL(location.href);
        if (action === 'before' || action === 'after') {
            action === 'before' ? url.searchParams.set('cz_view', 'before') : url.searchParams.delete('cz_view');
            await this.visit(url.pathname + url.search);
            return;
        }
        if (!draft) return;
        const proposal = [...this.proposals.values()].find((p) => String(p.draftId) === draft);
        if (proposal) return this.decide(proposal.proposalId, action);
        return this.decideDraft(Number(draft), action);
    }

    async decide(proposalId, action) {
        try {
            const res = await this.api(`/api/proposals/${encodeURIComponent(proposalId)}/${action}`, {});
            this.afterDecision(res, action);
        } catch (e) {
            this.toast(t('error') + ': ' + e.message, 'error');
        }
    }

    async decideDraft(draftId, action) {
        try {
            const res = await this.api(`/api/drafts/${draftId}/${action}`, {});
            this.afterDecision(res, action);
        } catch (e) {
            this.toast(t('error') + ': ' + e.message, 'error');
        }
    }

    async afterDecision(res, action) {
        if (res.proposal) {
            const container = this.root.querySelector(`[data-proposal="${CSS.escape(res.proposal.proposalId)}"]`)?.parentElement ?? this.messages;
            this.proposalCard(container, res.proposal);
        }
        const url = new URL(location.href);
        const inSketch = url.searchParams.has('cz_draft');
        url.searchParams.delete('cz_draft');
        url.searchParams.delete('cz_view');
        const path = action === 'approve' ? (res.path || url.pathname) : url.pathname;
        if (inSketch || action === 'approve') await this.visit(path + (url.search === '?' ? '' : url.search), { exitSketch: true });
        this.toast(action === 'approve' ? t('published') : t('discarded'));
        this.refreshWaiting();
    }

    afterLoad() {
        const banner = document.getElementById('cz-sketch-banner');
        const root = document.documentElement;
        root.style.setProperty('--cz-label-added', JSON.stringify(t('added')));
        root.style.setProperty('--cz-label-changed', JSON.stringify(t('changed')));
        root.style.setProperty('--cz-label-removed', JSON.stringify(t('removed')));
        if (banner) {
            banner.querySelectorAll('[data-cz-i18n]').forEach((el) => { el.textContent = t(el.dataset.czI18n); });
            const marks = banner.querySelector('[data-cz-action="marks"]');
            marks.checked = store.get('marks') === '1';
            root.classList.toggle('cz-marks', marks.checked);
            const draft = banner.dataset.czDraft;
            if (this.flashedDraft !== draft) {
                this.flashedDraft = draft;
                root.classList.remove('cz-flash');
                void root.offsetWidth;
                root.classList.add('cz-flash');
                setTimeout(() => root.classList.remove('cz-flash'), 1700);
            }
        } else {
            root.classList.remove('cz-marks');
        }
        document.querySelectorAll('[data-cz-blank-hint]').forEach((el) => {
            el.textContent = LANG === 'pl' ? 'Opisz, czego chcesz, w pasku poniżej — albo wskaż element na stronie.' : el.textContent;
        });
        // Selection highlights survive navigation
        for (const s of this.selection) document.querySelector(`.cz-node[data-node-id="${CSS.escape(s.id)}"]`)?.classList.add('cz-picked');
    }

    // ---------------------------------------------------------------------------------------------- picker

    togglePicker(force) {
        this.picking = force ?? !this.picking;
        document.documentElement.classList.toggle('cz-picking', this.picking);
        this.root.querySelectorAll('[data-cz="pick"]').forEach((b) => {
            b.classList.toggle('bg-violet-500', this.picking);
            b.classList.toggle('text-white', this.picking);
        });
        if (this.picking) {
            document.addEventListener('mousemove', this.onPickMove, true);
            document.addEventListener('click', this.onPickClick, true);
            this.toast(t('picking'));
        } else {
            document.removeEventListener('mousemove', this.onPickMove, true);
            document.removeEventListener('click', this.onPickClick, true);
            this.pickerBox.hidden = true;
        }
    }

    pickTarget(e) {
        if (e.target.closest('.cz-ui')) return null;
        return e.target.closest('.cz-node');
    }

    pickMove(e) {
        const node = this.pickTarget(e);
        if (!node) { this.pickerBox.hidden = true; return; }
        const r = node.getBoundingClientRect();
        Object.assign(this.pickerBox.style, { left: r.left - 3 + 'px', top: r.top - 3 + 'px', width: r.width + 6 + 'px', height: r.height + 6 + 'px' });
        this.pickerBox.querySelector('span').textContent = `${node.dataset.nodeLabel} · ${node.dataset.nodeType}`;
        this.pickerBox.hidden = false;
    }

    pickClick(e) {
        const node = this.pickTarget(e);
        if (!node) return;
        e.preventDefault();
        e.stopPropagation();
        const id = node.dataset.nodeId;
        const existing = this.selection.findIndex((s) => s.id === id);
        if (existing >= 0) {
            this.selection.splice(existing, 1);
            node.classList.remove('cz-picked');
        } else {
            this.selection.push({ id, label: node.dataset.nodeLabel, type: node.dataset.nodeType });
            node.classList.add('cz-picked');
        }
        this.renderChips();
    }

    renderChips() {
        const html = this.selection.map((s) => `<span class="inline-flex items-center gap-1 rounded-md bg-violet-500/15 px-1.5 py-0.5 text-[12px] text-violet-700 dark:text-violet-300">◎ ${escapeHtml(s.label)}<button type="button" data-unpick="${escapeHtml(s.id)}" class="opacity-60 hover:opacity-100">×</button></span>`).join('');
        const chips = this.el('chips');
        chips.innerHTML = html;
        chips.classList.toggle('hidden', !this.selection.length);
        chips.classList.toggle('flex', !!this.selection.length);
        this.el('bar-chips').innerHTML = this.selection.length ? `<span class="rounded-md bg-violet-500/15 px-1.5 py-1 text-[12px] text-violet-700 dark:text-violet-300">◎ ${this.selection.length}</span>` : '';
        this.input.placeholder = this.barInput.placeholder = this.selection.length ? t('placeholderPicked') : t('placeholder');
        this.root.querySelectorAll('[data-unpick]').forEach((b) => b.addEventListener('click', () => {
            this.selection = this.selection.filter((s) => s.id !== b.dataset.unpick);
            document.querySelector(`.cz-node[data-node-id="${CSS.escape(b.dataset.unpick)}"]`)?.classList.remove('cz-picked');
            this.renderChips();
        }));
    }

    clearSelection() {
        document.querySelectorAll('.cz-picked').forEach((el) => el.classList.remove('cz-picked'));
        this.selection = [];
        this.renderChips();
    }
}

function boot() {
    const root = document.getElementById('cz-panel');
    if (!root || root.dataset.booted) return;
    root.dataset.booted = '1';
    window.cadenza = new Studio(root);
}

boot();
document.addEventListener('turbo:load', boot);
