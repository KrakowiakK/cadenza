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
