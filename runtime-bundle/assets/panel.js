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
        attach: 'Attach photos', draw: 'Draw a sketch', sketchPrompt: 'Build the page following my sketch',
        uploading: 'Uploading…', chat: 'Conversation', history: 'History', version: 'Version', current: 'current',
        preview: 'Preview', restore: 'Restore', restoreThis: 'Restore this version', close: 'Close', undo: 'Undo',
        startOver: 'Start over', startOverConfirm: 'Sure? Click again — history is kept.', restored: 'Restored — a new version was published.',
        pickPhoto: 'Pick a photo', picked: 'In the sketch', noHistory: 'Nothing published yet.',
        pen: 'Pen', box: 'Box', text: 'Text', eraser: 'Eraser', clear: 'Clear', cancel: 'Cancel', useSketch: 'Add to message',
        sketchHint: 'Draw the layout: boxes for sections, words for what goes where. Cadenza will read it.', typeLabel: 'Type a label, Enter',
        signIn: 'Sign in with', uploadExport: 'Upload the export (ZIP)', connectedAs: 'Connected as', or: 'or',
        exportHelp: 'Instagram → Settings → Accounts Center → Your information and permissions → Download your information → choose JSON format.',
        exportUploaded: 'Here is my export file.', importing: 'Importing', importDone: 'Import finished', imported: 'imported', skipped: 'already there',
        connectedOk: 'Connected', connectFailed: 'Connection failed',
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
        attach: 'Dołącz zdjęcia', draw: 'Narysuj szkic', sketchPrompt: 'Zbuduj stronę według mojego szkicu',
        uploading: 'Wysyłam…', chat: 'Rozmowa', history: 'Historia', version: 'Wersja', current: 'bieżąca',
        preview: 'Podgląd', restore: 'Przywróć', restoreThis: 'Przywróć tę wersję', close: 'Zamknij', undo: 'Cofnij',
        startOver: 'Zacznij od nowa', startOverConfirm: 'Na pewno? Kliknij jeszcze raz — historia zostaje.', restored: 'Przywrócono — opublikowano nową wersję.',
        pickPhoto: 'Wybierz zdjęcie', picked: 'W szkicu', noHistory: 'Nic jeszcze nie opublikowano.',
        pen: 'Ołówek', box: 'Ramka', text: 'Tekst', eraser: 'Gumka', clear: 'Wyczyść', cancel: 'Anuluj', useSketch: 'Dodaj do wiadomości',
        sketchHint: 'Narysuj układ: ramki to sekcje, słowa mówią, co gdzie ma być. Cadenza to odczyta.', typeLabel: 'Wpisz etykietę, Enter',
        signIn: 'Zaloguj przez', uploadExport: 'Wgraj plik eksportu (ZIP)', connectedAs: 'Połączono jako', or: 'albo',
        exportHelp: 'Instagram → Ustawienia → Centrum kont → Twoje informacje i uprawnienia → Pobierz swoje informacje → wybierz format JSON.',
        exportUploaded: 'Oto mój plik eksportu.', importing: 'Importuję', importDone: 'Import zakończony', imported: 'zaimportowano', skipped: 'już było',
        connectedOk: 'Połączono', connectFailed: 'Nie udało się połączyć',
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
    clip: '<path d="m21.4 11.1-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
    pencil: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    square: '<rect x="4" y="4" width="16" height="16" rx="1"/>',
    type: '<path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/>',
    eraser: '<path d="m7 21-4.3-4.3a1 1 0 0 1 0-1.4l10-10a1 1 0 0 1 1.4 0l5.6 5.6a1 1 0 0 1 0 1.4L11 21z"/><path d="M22 21H7"/>',
    undo: '<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-1"/>',
    trash: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/>',
};
const icon = (name, cls = 'size-4') => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="${cls}">${ICONS[name]}</svg>`;

class Studio {
    constructor(root) {
        this.root = root;
        this.conversationId = store.get('conversation');
        this.selection = [];
        this.attachments = [];
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
    <button type="button" data-cz="bar-draw" title="${t('draw')}" class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('pencil')}</button>
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
    <button type="button" data-cz="tab-history" title="${t('history')}" class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('clock')}</button>
    <button type="button" data-cz="new" title="${t('newChat')}" class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('plus')}</button>
    <button type="button" data-cz="collapse" title="${t('collapse')}" class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('minimize')}</button>
    <button type="button" data-cz="hide" title="${t('hide')}" class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground">${icon('x')}</button>
  </header>
  <div data-cz="messages" class="flex flex-1 flex-col gap-4 overflow-y-auto px-4 py-5 text-[14px] leading-relaxed"></div>
  <div data-cz="history" class="hidden flex-1 flex-col overflow-y-auto px-4 py-4 text-[13px]"></div>
  <form data-cz="form" class="border-t border-border p-3">
    <div data-cz="chips" class="mb-2 hidden flex-wrap gap-1.5"></div>
    <div data-cz="attachments" class="mb-2 hidden flex-wrap gap-2"></div>
    <input type="file" data-cz="file" accept="image/*,.zip,application/zip" multiple class="hidden">
    <div class="flex items-end gap-2 rounded-xl border border-border bg-muted/40 p-1.5 focus-within:border-foreground/30">
      <div class="flex shrink-0 flex-col">
        <button type="button" data-cz="pick" title="${t('pick')}" class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-background hover:text-foreground">${icon('target')}</button>
      </div>
      <textarea data-cz="input" rows="1" class="max-h-40 min-h-8 flex-1 resize-none bg-transparent px-1 py-1.5 text-[14px] outline-none placeholder:text-muted-foreground" placeholder="${t('placeholder')}"></textarea>
      <button type="button" data-cz="attach" title="${t('attach')}" class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-background hover:text-foreground">${icon('clip')}</button>
      <button type="button" data-cz="draw" title="${t('draw')}" class="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-background hover:text-foreground">${icon('pencil')}</button>
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
        this.el('tab-history').addEventListener('click', () => this.toggleHistory());
        this.el('attach').addEventListener('click', () => this.el('file').click());
        this.el('file').addEventListener('change', (e) => { this.addFiles([...e.target.files]); e.target.value = ''; });
        this.el('draw').addEventListener('click', () => this.openSketchPad());
        this.el('bar-draw').addEventListener('click', () => { this.setState('open'); this.openSketchPad(); });
        const onPaste = (e) => {
            const files = [...(e.clipboardData?.files ?? [])].filter((f) => f.type.startsWith('image/'));
            if (files.length) { e.preventDefault(); this.setState('open'); this.addFiles(files); }
        };
        this.input.addEventListener('paste', onPaste);
        this.barInput.addEventListener('paste', onPaste);
        const sheet = this.root.querySelector('.cz-sheet');
        sheet.addEventListener('dragover', (e) => { e.preventDefault(); sheet.classList.add('ring-2', 'ring-violet-500'); });
        sheet.addEventListener('dragleave', () => sheet.classList.remove('ring-2', 'ring-violet-500'));
        sheet.addEventListener('drop', (e) => {
            e.preventDefault();
            sheet.classList.remove('ring-2', 'ring-violet-500');
            this.addFiles([...e.dataTransfer.files].filter((f) => f.type.startsWith('image/')));
        });

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
            if (!text && !this.attachments.length) return;
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
        window.addEventListener('message', (e) => {
            if (e.origin !== location.origin || e.data?.type !== 'cadenza:connected') return;
            if (e.data.ok) {
                this.toast(`${t('connectedOk')}: ${e.data.detail}`);
                this.send(`${t('connectedOk')}: ${e.data.connector} ${e.data.detail}`, { event: { type: 'account_connected', connector: e.data.connector, account: e.data.detail } });
            } else {
                this.toast(`${t('connectFailed')}: ${e.data.detail}`, 'error');
            }
        });
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

    toast(text, kind = 'ok', action = null) {
        const el = document.createElement('div');
        el.className = 'cz-msg pointer-events-auto flex items-center gap-2 rounded-full border border-border bg-background px-4 py-2 text-sm shadow-lg';
        el.innerHTML = (kind === 'ok' ? `<span class="text-emerald-600">${icon('check')}</span>` : `<span class="text-rose-600">${icon('alert')}</span>`) + escapeHtml(text);
        if (action) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'ml-2 flex items-center gap-1 rounded-full bg-foreground px-2.5 py-0.5 text-xs font-semibold text-background';
            b.innerHTML = icon('undo', 'size-3.5') + escapeHtml(action.label);
            b.addEventListener('click', () => { el.remove(); action.run(); });
            el.append(b);
        }
        this.el('toasts').append(el);
        setTimeout(() => { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 400); }, action ? 8000 : 3200);
    }

    // ---------------------------------------------------------------------------------------------- messages

    scrollDown() {
        requestAnimationFrame(() => { this.messages.scrollTop = this.messages.scrollHeight; });
    }

    addUser(text, selection = [], attachments = []) {
        const el = document.createElement('div');
        el.className = 'cz-msg ml-10 self-end rounded-2xl rounded-br-md bg-foreground px-3.5 py-2 text-background';
        el.innerHTML = (attachments.length ? `<div class="mb-1.5 flex flex-wrap gap-1.5">${attachments.map((a) => a.url && !/\.zip$/.test(a.id ?? '') ? `<img src="${escapeHtml(a.url)}" alt="" class="h-16 max-w-28 rounded-lg bg-white object-cover">` : `<span class="flex items-center gap-1 rounded-lg bg-background/15 px-2 py-1 text-xs">${icon('clip', 'size-3.5')}${escapeHtml(a.name ?? a.id)}</span>`).join('')}</div>` : '') + (selection.length ? `<div class="mb-1 flex flex-wrap gap-1">${selection.map((s) => `<span class="rounded-md bg-background/15 px-1.5 py-0.5 text-[11px]">◎ ${escapeHtml(s.label)}</span>`).join('')}</div>` : '') + `<div class="whitespace-pre-wrap">${escapeHtml(text)}</div>`;
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
                if (turn.role === 'user' && turn.event) this.addNote(turn.text);
                else if (turn.role === 'user') this.addUser(turn.text, turn.selection ?? [], turn.attachments ?? []);
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

    async send(text, { event = null } = {}) {
        if (this.busy) { if (event) setTimeout(() => this.send(text, { event }), 1500); return; }
        if (this.attachments.some((a) => a.uploading)) { this.toast(t('uploading')); return; }
        const selection = this.selection.map(({ id, label, type }) => ({ id, label, type }));
        const attachments = this.attachments.map(({ id, kind, name, url }) => ({ id, kind, name, url }));
        if (!text) text = attachments.some((a) => a.kind === 'sketch') ? t('sketchPrompt') : '';
        if (event) this.addNote(text);
        else this.addUser(text, selection, attachments);
        this.clearSelection();
        this.attachments = [];
        this.renderAttachments();
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
                    attachments: attachments.map(({ id, kind, name }) => ({ id, kind, name })),
                    context: { event, path: url.pathname, draftId: meta('cz-draft') ? Number(meta('cz-draft')) : null, selection, language: LANG, viewport: innerWidth < 768 ? 'mobile' : 'desktop' },
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
            case 'choice':
                this.choiceGrid(turn.part('cards'), data);
                break;
            case 'connect':
                this.connectCard(turn.part('cards'), data);
                break;
            case 'import':
                this.importCard(turn.part('cards'), data);
                break;
            case 'generated':
                turn.part('cards').insertAdjacentHTML('beforeend', `<img src="${escapeHtml(data.url)}" alt="" title="${escapeHtml(data.prompt)}" class="cz-msg w-full rounded-xl">`);
                this.scrollDown();
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
        if (action === 'close-preview') {
            url.searchParams.delete('cz_version');
            await this.visit(url.pathname + url.search, { exitSketch: true });
            return;
        }
        if (action === 'restore') {
            const version = document.querySelector('[data-cz-action="restore"]')?.dataset.version;
            if (version) await this.historyAction(`/api/history/${version}/restore`);
            return;
        }
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
        if (action === 'approve') this.toast(t('published'), 'ok', { label: t('undo'), run: () => this.historyAction('/api/history/undo') });
        else this.toast(t('discarded'));
        this.refreshWaiting();
        if (this.showingHistory) this.loadTimeline();
    }

    afterLoad() {
        const banner = document.getElementById('cz-sketch-banner');
        const root = document.documentElement;
        root.style.setProperty('--cz-label-added', JSON.stringify(t('added')));
        root.style.setProperty('--cz-label-changed', JSON.stringify(t('changed')));
        root.style.setProperty('--cz-label-removed', JSON.stringify(t('removed')));
        document.querySelectorAll('#cz-version-banner [data-cz-i18n]').forEach((el) => { el.textContent = t(el.dataset.czI18n === 'restore' ? 'restoreThis' : el.dataset.czI18n); });
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

    addNote(text) {
        const el = document.createElement('div');
        el.className = 'cz-msg flex items-center gap-2 self-center rounded-full bg-muted px-3 py-1 text-xs text-muted-foreground';
        el.innerHTML = icon('check', 'size-3.5') + escapeHtml(text);
        this.messages.append(el);
        this.scrollDown();
    }

    // ---------------------------------------------------------------------------------------------- connectors

    connectCard(container, c) {
        const card = document.createElement('div');
        card.className = 'cz-msg rounded-xl border border-border p-3';
        const brand = c.connector === 'instagram' ? 'bg-gradient-to-br from-amber-400 via-pink-500 to-violet-600' : 'bg-foreground';
        card.innerHTML = `
<div class="flex items-center gap-2"><span class="flex size-8 items-center justify-center rounded-lg ${brand} text-white">${icon('sparkle')}</span><div class="font-semibold">${escapeHtml(c.label)}</div></div>
${c.connection ? `<div class="mt-2 text-xs text-emerald-700 dark:text-emerald-300">${t('connectedAs')} ${escapeHtml(c.connection.account)}</div>` : ''}
<div class="mt-3 flex flex-col gap-2">
  ${c.signInUrl ? `<button type="button" data-do="signin" class="rounded-lg ${brand} px-3 py-2 text-sm font-semibold text-white">${t('signIn')} ${escapeHtml(c.label)}</button>` : ''}
  ${c.methods.includes('file') ? `${c.signInUrl ? `<div class="text-center text-[11px] text-muted-foreground">${t('or')}</div>` : ''}<button type="button" data-do="upload" class="rounded-lg border border-border px-3 py-2 text-sm font-medium hover:bg-muted">${icon('clip', 'size-4 inline -mt-0.5 mr-1')}${t('uploadExport')}</button>
  <p class="text-[11px] leading-snug text-muted-foreground">${t('exportHelp')}</p>` : ''}
</div>
<input type="file" accept=".zip,application/zip" class="hidden">`;
        card.querySelector('[data-do="signin"]')?.addEventListener('click', () => {
            const w = 520, h = 720;
            window.open(c.signInUrl, 'cadenza-connect', `width=${w},height=${h},left=${screenX + (outerWidth - w) / 2},top=${screenY + 60}`);
        });
        const input = card.querySelector('input[type=file]');
        card.querySelector('[data-do="upload"]')?.addEventListener('click', () => input.click());
        input.addEventListener('change', async () => {
            if (!input.files.length) return;
            await this.addFiles([...input.files]);
            this.send(t('exportUploaded'));
        });
        container.append(card);
        this.scrollDown();
    }

    importCard(container, data) {
        const card = document.createElement('div');
        card.className = 'cz-msg rounded-xl border border-border p-3';
        card.innerHTML = `<div class="flex items-center justify-between text-xs"><span class="font-semibold" data-part="title">${t('importing')}…</span><span data-part="count" class="text-muted-foreground"></span></div>
<div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"><div data-part="bar" class="h-full w-0 rounded-full bg-gradient-to-r from-violet-500 to-fuchsia-500 transition-all duration-500"></div></div>`;
        container.append(card);
        this.scrollDown();
        const poll = async () => {
            let job;
            try { job = await this.api(`/api/imports/${data.jobId}`); } catch { setTimeout(poll, 3000); return; }
            const pct = job.total ? Math.round((job.done / job.total) * 100) : (job.status === 'done' ? 100 : 15);
            card.querySelector('[data-part="bar"]').style.width = pct + '%';
            card.querySelector('[data-part="count"]').textContent = job.total ? `${job.done}/${job.total}` : `${job.done}`;
            if (job.status === 'done' || job.status === 'failed') {
                const summary = `${t('importDone')}: ${job.imported} ${t('imported')}${job.skipped ? `, ${job.skipped} ${t('skipped')}` : ''}`;
                card.querySelector('[data-part="title"]').textContent = job.status === 'failed' ? `${t('error')}: ${job.errors?.[0] ?? ''}` : summary;
                this.send(summary, { event: { type: 'import_finished', ...job } });
                return;
            }
            setTimeout(poll, 1500);
        };
        poll();
    }

    // ---------------------------------------------------------------------------------------------- attachments

    async addFiles(files) {
        for (const file of files.slice(0, 6)) {
            const isZip = /\.zip$/i.test(file.name) || file.type === 'application/zip';
            const item = { id: null, kind: isZip ? 'file' : 'photo', name: file.name, url: isZip ? null : URL.createObjectURL(file), uploading: true };
            this.attachments.push(item);
            this.renderAttachments();
            try {
                const form = new FormData();
                form.append('file', file);
                const res = await fetch(HARNESS + '/api/uploads', { method: 'POST', headers: { 'X-Cadenza-Token': TOKEN }, body: form });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error ?? res.status);
                Object.assign(item, { id: data.id, url: item.kind === 'file' ? null : data.url, uploading: false });
            } catch (e) {
                this.attachments = this.attachments.filter((a) => a !== item);
                this.toast(t('error') + ': ' + e.message, 'error');
            }
            this.renderAttachments();
        }
    }

    async addDataUrl(dataUrl, kind, name) {
        const item = { id: null, kind, name, url: dataUrl, uploading: true };
        this.attachments.push(item);
        this.renderAttachments();
        try {
            const data = await this.api('/api/uploads', { dataUrl, name });
            Object.assign(item, { id: data.id, url: data.url, uploading: false });
        } catch (e) {
            this.attachments = this.attachments.filter((a) => a !== item);
            this.toast(t('error') + ': ' + e.message, 'error');
        }
        this.renderAttachments();
    }

    renderAttachments() {
        const box = this.el('attachments');
        box.classList.toggle('hidden', !this.attachments.length);
        box.classList.toggle('flex', !!this.attachments.length);
        box.innerHTML = this.attachments.map((a, i) => `<div class="relative">${a.kind === 'file' ? `<div class="flex size-16 flex-col items-center justify-center gap-0.5 rounded-lg border border-border bg-muted text-[10px] text-muted-foreground ${a.uploading ? 'opacity-50' : ''}">${icon('clip')}<span class="max-w-14 truncate">${escapeHtml(a.name)}</span></div>` : `<img src="${escapeHtml(a.url)}" alt="" class="size-16 rounded-lg border border-border bg-white object-cover ${a.uploading ? 'opacity-50' : ''}">`}${a.kind === 'sketch' ? `<span class="absolute bottom-1 left-1 rounded bg-violet-600 px-1 text-[10px] font-semibold text-white">✎</span>` : ''}${a.uploading ? '<span class="absolute inset-0 m-auto size-4 animate-spin rounded-full border-2 border-violet-500 border-t-transparent"></span>' : ''}<button type="button" data-remove="${i}" class="absolute -right-1.5 -top-1.5 flex size-5 items-center justify-center rounded-full bg-foreground text-[11px] text-background">×</button></div>`).join('');
        box.querySelectorAll('[data-remove]').forEach((b) => b.addEventListener('click', () => { this.attachments.splice(Number(b.dataset.remove), 1); this.renderAttachments(); }));
    }

    // ---------------------------------------------------------------------------------------------- sketch pad

    openSketchPad() {
        new SketchPad((dataUrl) => {
            this.addDataUrl(dataUrl, 'sketch', 'sketch.png');
            if (!this.input.value.trim()) this.input.value = t('sketchPrompt');
            this.setState('open');
        });
    }

    // ---------------------------------------------------------------------------------------------- photo choices

    choiceGrid(container, offer) {
        const card = document.createElement('div');
        card.className = 'cz-msg rounded-xl border border-border p-3';
        card.innerHTML = `<div class="mb-2 flex items-center justify-between gap-2"><div class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">${t('pickPhoto')}</div><div class="truncate text-xs font-medium">${escapeHtml(offer.label)}</div></div>
<div class="grid grid-cols-2 gap-1.5">${offer.candidates.map((c) => `<button type="button" data-candidate="${escapeHtml(c.id)}" class="group relative overflow-hidden rounded-lg bg-muted text-left ring-offset-2 ring-offset-background">
  <img src="${escapeHtml(c.thumb)}" alt="${escapeHtml(c.title)}" loading="lazy" class="aspect-[4/3] w-full object-cover transition duration-300 group-hover:scale-105">
  <span class="pointer-events-none absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/70 to-transparent px-1.5 pb-1 pt-4 text-[10px] text-white/90">${escapeHtml((c.author ?? '').replace(/\s+/g, ' ').slice(0, 40))}${c.license ? ' · ' + escapeHtml(c.license) : ''}</span>
  <span data-state class="absolute inset-0 hidden items-center justify-center bg-black/40 text-white"></span>
</button>`).join('')}</div>`;
        card.querySelectorAll('[data-candidate]').forEach((tile) => tile.addEventListener('click', () => this.pickCandidate(offer, tile, card)));
        container.append(card);
        this.scrollDown();
    }

    async pickCandidate(offer, tile, card) {
        const state = tile.querySelector('[data-state]');
        card.querySelectorAll('[data-candidate]').forEach((b) => b.classList.remove('ring-2', 'ring-violet-500'));
        state.className = 'absolute inset-0 flex items-center justify-center bg-black/40 text-white';
        state.innerHTML = '<span class="size-5 animate-spin rounded-full border-2 border-white border-t-transparent"></span>';
        try {
            const res = await this.api(`/api/offers/${encodeURIComponent(offer.offerId)}/pick`, { candidateId: tile.dataset.candidate });
            tile.classList.add('ring-2', 'ring-violet-500');
            state.innerHTML = `<span class="flex items-center gap-1 rounded-full bg-violet-600 px-2 py-0.5 text-[11px] font-semibold">${icon('check', 'size-3')}${t('picked')}</span>`;
            if (res.proposal) {
                const existing = this.root.querySelector(`[data-proposal="${CSS.escape(res.proposal.proposalId)}"]`);
                this.proposalCard(existing?.parentElement ?? card.parentElement, res.proposal);
                await this.showSketch(res.proposal.draftId, location.pathname);
            }
        } catch (e) {
            state.className = 'absolute inset-0 hidden';
            this.toast(t('error') + ': ' + e.message, 'error');
        }
    }

    // ---------------------------------------------------------------------------------------------- timeline

    toggleHistory(force) {
        this.showingHistory = force ?? !this.showingHistory;
        this.messages.classList.toggle('hidden', this.showingHistory);
        this.el('form').classList.toggle('hidden', this.showingHistory);
        const history = this.el('history');
        history.classList.toggle('hidden', !this.showingHistory);
        history.classList.toggle('flex', this.showingHistory);
        this.el('tab-history').classList.toggle('bg-muted', this.showingHistory);
        this.el('tab-history').innerHTML = icon(this.showingHistory ? 'chat' : 'clock');
        this.el('tab-history').title = this.showingHistory ? t('chat') : t('history');
        if (this.showingHistory) this.loadTimeline();
    }

    async loadTimeline() {
        const box = this.el('history');
        box.innerHTML = `<div class="text-muted-foreground">…</div>`;
        let versions = [];
        try { versions = (await this.api('/api/history')).versions ?? []; } catch (e) { box.innerHTML = `<p class="text-rose-600">${escapeHtml(e.message)}</p>`; return; }
        const viewing = meta('cz-version');
        const rel = (iso) => {
            const diff = (Date.now() - new Date(iso).getTime()) / 1000;
            const rtf = new Intl.RelativeTimeFormat(LANG, { numeric: 'auto' });
            if (diff < 60) return rtf.format(-Math.round(diff), 'second');
            if (diff < 3600) return rtf.format(-Math.round(diff / 60), 'minute');
            if (diff < 86400) return rtf.format(-Math.round(diff / 3600), 'hour');
            return rtf.format(-Math.round(diff / 86400), 'day');
        };
        box.innerHTML = `<h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">${t('history')}</h3>
<ol class="relative flex flex-col gap-1 border-l border-border pl-4">${versions.map((v, i) => `
  <li class="group relative rounded-lg px-2 py-2 ${String(v.version) === viewing ? 'bg-sky-500/10' : 'hover:bg-muted'}">
    <span class="absolute -left-[21px] top-3 size-2.5 rounded-full ${i === 0 ? 'bg-emerald-500 ring-4 ring-emerald-500/20' : 'bg-border'}"></span>
    <div class="flex items-center gap-2"><span class="font-semibold">v${v.version}</span>${i === 0 ? `<span class="rounded-full bg-emerald-500/15 px-1.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-300">${t('current')}</span>` : ''}<span class="ml-auto text-[11px] text-muted-foreground">${escapeHtml(rel(v.publishedAt))}</span></div>
    <div class="mt-0.5 text-muted-foreground">${escapeHtml(v.summary)}</div>
    <div class="mt-1.5 flex gap-1.5 ${i === 0 ? 'hidden' : ''}">
      <button type="button" data-preview="${v.version}" class="rounded-md border border-border px-2 py-0.5 text-xs hover:bg-background">${t('preview')}</button>
      <button type="button" data-restore="${v.version}" class="rounded-md bg-foreground px-2 py-0.5 text-xs font-medium text-background">${t('restore')}</button>
    </div>
  </li>`).join('') || `<li class="text-muted-foreground">${t('noHistory')}</li>`}</ol>
<button type="button" data-start-over class="mt-6 flex items-center justify-center gap-2 rounded-lg border border-dashed border-border px-3 py-2 text-xs text-muted-foreground hover:border-rose-400 hover:text-rose-600">${icon('trash', 'size-3.5')}${t('startOver')}</button>`;
        box.querySelectorAll('[data-preview]').forEach((b) => b.addEventListener('click', () => this.visit(`${location.pathname}?cz_version=${b.dataset.preview}`)));
        box.querySelectorAll('[data-restore]').forEach((b) => b.addEventListener('click', () => this.historyAction(`/api/history/${b.dataset.restore}/restore`)));
        const startOver = box.querySelector('[data-start-over]');
        startOver.addEventListener('click', () => {
            if (startOver.dataset.armed) { this.historyAction('/api/history/start-over'); return; }
            startOver.dataset.armed = '1';
            startOver.classList.add('border-rose-400', 'text-rose-600');
            startOver.lastChild.textContent = t('startOverConfirm');
            setTimeout(() => { delete startOver.dataset.armed; startOver.classList.remove('border-rose-400', 'text-rose-600'); startOver.lastChild.textContent = t('startOver'); }, 4000);
        });
    }

    async historyAction(path) {
        try {
            await this.api(path, {});
            const url = new URL(location.href);
            ['cz_version', 'cz_draft', 'cz_view'].forEach((k) => url.searchParams.delete(k));
            await this.visit(url.pathname + url.search, { exitSketch: true });
            this.toast(t('restored'));
            if (this.showingHistory) this.loadTimeline();
        } catch (e) {
            this.toast(t('error') + ': ' + e.message, 'error');
        }
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

/*
 * A sheet of paper over the page: pen, boxes, text labels and an eraser. The drawing goes to the model as an image.
 */
class SketchPad {
    constructor(onDone) {
        this.onDone = onDone;
        this.tool = 'pen';
        this.history = [];
        this.el = document.createElement('div');
        this.el.className = 'cz-ui fixed inset-0 z-[90] flex flex-col bg-black/40 p-3 backdrop-blur-sm sm:p-6';
        const tools = [['pen', 'pencil'], ['box', 'square'], ['text', 'type'], ['eraser', 'eraser']];
        this.el.innerHTML = `
<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col overflow-hidden rounded-2xl bg-white text-neutral-900 shadow-2xl">
  <div class="flex flex-wrap items-center gap-1.5 border-b border-neutral-200 px-3 py-2">
    ${tools.map(([tool, ic]) => `<button type="button" data-tool="${tool}" class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm hover:bg-neutral-100">${icon(ic)}<span class="hidden sm:inline">${t(tool)}</span></button>`).join('')}
    <span class="mx-1 h-5 w-px bg-neutral-200"></span>
    ${['#171717', '#7c3aed', '#dc2626'].map((c) => `<button type="button" data-color="${c}" class="size-6 rounded-full ring-offset-2" style="background:${c}"></button>`).join('')}
    <span class="mx-1 h-5 w-px bg-neutral-200"></span>
    <button type="button" data-do="undo" class="flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm hover:bg-neutral-100">${icon('undo')}</button>
    <button type="button" data-do="clear" class="rounded-lg px-2.5 py-1.5 text-sm hover:bg-neutral-100">${t('clear')}</button>
    <span class="ml-auto hidden text-xs text-neutral-500 lg:inline">${t('sketchHint')}</span>
    <button type="button" data-do="cancel" class="rounded-lg px-3 py-1.5 text-sm hover:bg-neutral-100">${t('cancel')}</button>
    <button type="button" data-do="done" class="rounded-lg bg-neutral-900 px-3 py-1.5 text-sm font-semibold text-white">${t('useSketch')}</button>
  </div>
  <div class="relative flex-1"><canvas class="absolute inset-0 h-full w-full touch-none" style="cursor:crosshair"></canvas><input data-label class="absolute hidden rounded border border-violet-400 bg-white px-1 text-lg outline-none" placeholder="${t('typeLabel')}"></div>
</div>`;
        document.body.append(this.el);
        this.canvas = this.el.querySelector('canvas');
        this.ctx = this.canvas.getContext('2d');
        this.label = this.el.querySelector('[data-label]');
        this.color = '#171717';
        this.resize();
        this.bind();
        this.select('pen');
        this.selectColor(this.color);
    }

    resize() {
        const r = this.canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        this.canvas.width = r.width * ratio;
        this.canvas.height = r.height * ratio;
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.paper();
    }

    paper() {
        const { width, height } = this.canvas.getBoundingClientRect();
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, width, height);
        this.ctx.fillStyle = '#e5e7eb';
        for (let x = 12; x < width; x += 24) for (let y = 12; y < height; y += 24) this.ctx.fillRect(x, y, 1.5, 1.5);
    }

    snapshot() {
        this.history.push(this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height));
        if (this.history.length > 40) this.history.shift();
    }

    select(tool) {
        this.tool = tool;
        this.el.querySelectorAll('[data-tool]').forEach((b) => b.classList.toggle('bg-violet-100', b.dataset.tool === tool));
    }

    selectColor(color) {
        this.color = color;
        this.el.querySelectorAll('[data-color]').forEach((b) => b.classList.toggle('ring-2', b.dataset.color === color));
        this.el.querySelectorAll('[data-color]').forEach((b) => { b.style.setProperty('--tw-ring-color', b.dataset.color); });
    }

    point(e) {
        const r = this.canvas.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    bind() {
        this.el.querySelectorAll('[data-tool]').forEach((b) => b.addEventListener('click', () => this.select(b.dataset.tool)));
        this.el.querySelectorAll('[data-color]').forEach((b) => b.addEventListener('click', () => this.selectColor(b.dataset.color)));
        this.el.querySelector('[data-do="undo"]').addEventListener('click', () => { const last = this.history.pop(); if (last) this.ctx.putImageData(last, 0, 0); });
        this.el.querySelector('[data-do="clear"]').addEventListener('click', () => { this.snapshot(); this.paper(); });
        this.el.querySelector('[data-do="cancel"]').addEventListener('click', () => this.close());
        this.el.querySelector('[data-do="done"]').addEventListener('click', () => { this.commitLabel(); this.onDone(this.canvas.toDataURL('image/png')); this.close(); });
        this.onKey = (e) => { if (e.key === 'Escape' && this.label.classList.contains('hidden')) this.close(); };
        document.addEventListener('keydown', this.onKey);

        this.canvas.addEventListener('pointerdown', (e) => {
            const p = this.point(e);
            if (this.tool === 'text') { this.placeLabel(p); return; }
            this.commitLabel();
            this.canvas.setPointerCapture(e.pointerId);
            this.snapshot();
            this.start = p;
            this.last = p;
            this.base = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
            this.drawing = true;
        });
        this.canvas.addEventListener('pointermove', (e) => {
            if (!this.drawing) return;
            const p = this.point(e);
            const ctx = this.ctx;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            if (this.tool === 'box') {
                ctx.putImageData(this.base, 0, 0);
                ctx.strokeStyle = this.color;
                ctx.lineWidth = 2.5;
                ctx.strokeRect(this.start.x, this.start.y, p.x - this.start.x, p.y - this.start.y);
                return;
            }
            ctx.strokeStyle = this.tool === 'eraser' ? '#ffffff' : this.color;
            ctx.lineWidth = this.tool === 'eraser' ? 22 : 3;
            ctx.beginPath();
            ctx.moveTo(this.last.x, this.last.y);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            this.last = p;
        });
        const stop = () => { this.drawing = false; };
        this.canvas.addEventListener('pointerup', stop);
        this.canvas.addEventListener('pointercancel', stop);
        this.label.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); this.commitLabel(); }
            if (e.key === 'Escape') { this.label.value = ''; this.commitLabel(); }
        });
    }

    placeLabel(p) {
        this.commitLabel();
        this.labelAt = p;
        Object.assign(this.label.style, { left: p.x + 'px', top: p.y - 16 + 'px' });
        this.label.classList.remove('hidden');
        this.label.value = '';
        setTimeout(() => this.label.focus(), 0);
    }

    commitLabel() {
        if (this.label.classList.contains('hidden')) return;
        const text = this.label.value.trim();
        this.label.classList.add('hidden');
        if (!text) return;
        this.snapshot();
        this.ctx.fillStyle = this.color;
        this.ctx.font = '600 20px "Comic Sans MS", "Segoe Print", ui-sans-serif, system-ui';
        this.ctx.fillText(text, this.labelAt.x, this.labelAt.y + 6);
    }

    close() {
        document.removeEventListener('keydown', this.onKey);
        this.el.remove();
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
