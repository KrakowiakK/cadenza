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
