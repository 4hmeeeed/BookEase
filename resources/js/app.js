import './bootstrap';

import Alpine from 'alpinejs';
import stayCalendar from './stay-calendar';
import chatbot from './chatbot';

Alpine.data('stayCalendar', stayCalendar);
Alpine.data('chatbot', chatbot);

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/**
 * x-tilt — 3D tilt toward the pointer. Pair with the `.tilt` CSS class.
 *   <div class="card tilt" x-tilt>…</div>
 *   <div class="card tilt" x-tilt="12">…</div>   (max degrees, default 8)
 */
Alpine.directive('tilt', (el, { expression }, { evaluate, cleanup }) => {
    if (prefersReducedMotion || !finePointer) return;

    const max = expression ? Number(evaluate(expression)) : 8;
    let frame = null;

    const onMove = (event) => {
        const rect = el.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width;
        const y = (event.clientY - rect.top) / rect.height;

        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            el.classList.add('is-tilting');
            el.style.setProperty('--ry', `${(x - 0.5) * 2 * max}deg`);
            el.style.setProperty('--rx', `${(0.5 - y) * 2 * max}deg`);
            el.style.setProperty('--gx', `${x * 100}%`);
            el.style.setProperty('--gy', `${y * 100}%`);
        });
    };

    const onLeave = () => {
        cancelAnimationFrame(frame);
        el.classList.remove('is-tilting');
        el.style.setProperty('--rx', '0deg');
        el.style.setProperty('--ry', '0deg');
    };

    el.addEventListener('pointermove', onMove);
    el.addEventListener('pointerleave', onLeave);

    cleanup(() => {
        el.removeEventListener('pointermove', onMove);
        el.removeEventListener('pointerleave', onLeave);
    });
});

/**
 * x-reveal — fade/slide in when scrolled into view.
 *   <section x-reveal>…</section>
 *   <ul x-reveal.stagger>…</ul>   (each direct child reveals in sequence)
 */
const revealObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 })
    : null;

Alpine.directive('reveal', (el, { modifiers }, { cleanup }) => {
    const targets = modifiers.includes('stagger') ? [...el.children] : [el];

    targets.forEach((target, index) => {
        target.classList.add('reveal');
        target.style.setProperty('--i', modifiers.includes('stagger') ? index : 0);

        if (prefersReducedMotion || !revealObserver) {
            target.classList.add('is-visible');
        } else {
            revealObserver.observe(target);
        }
    });

    cleanup(() => targets.forEach((target) => revealObserver?.unobserve(target)));
});

window.Alpine = Alpine;

Alpine.start();
