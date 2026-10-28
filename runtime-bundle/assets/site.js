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
