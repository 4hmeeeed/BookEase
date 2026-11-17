import './bootstrap';

import Alpine from 'alpinejs';
import stayCalendar from './stay-calendar';
import chatbot from './chatbot';
import consoleBell from './console-bell';

Alpine.data('stayCalendar', stayCalendar);
Alpine.data('chatbot', chatbot);
Alpine.data('consoleBell', consoleBell);

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/**
 * x-tilt — 3D tilt toward the pointer. Pair with the `.tilt` CSS class.
 *   <div class="card tilt" x-tilt>…</div>
 *   <div class="card tilt" x-tilt="8">…</div>   (max degrees, default 5)
 */
Alpine.directive('tilt', (el, { expression }, { evaluate, cleanup }) => {
    if (prefersReducedMotion || !finePointer) return;

    const max = expression ? Number(evaluate(expression)) : 5;
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
 *   <ul x-reveal.stagger.rise>…</ul>   (children flip up in 3D as they enter)
 */
const revealObserver = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            const target = entry.target;
            target.classList.add('is-visible');
            observer.unobserve(target);
            // Drop the reveal classes after the entrance so hover/tilt transitions are untouched.
            const index = Number(target.style.getPropertyValue('--i')) || 0;
            setTimeout(() => target.classList.remove('reveal', 'reveal-rise', 'is-visible'), 1000 + index * 90);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 })
    : null;

Alpine.directive('reveal', (el, { modifiers }, { cleanup }) => {
    const targets = modifiers.includes('stagger') ? [...el.children] : [el];
    if (modifiers.includes('rise')) el.style.perspective = '1200px';

    targets.forEach((target, index) => {
        target.classList.add('reveal');
        if (modifiers.includes('rise')) target.classList.add('reveal-rise');
        target.style.setProperty('--i', modifiers.includes('stagger') ? index : 0);

        if (prefersReducedMotion || !revealObserver) {
            target.classList.add('is-visible');
        } else {
            revealObserver.observe(target);
        }
    });

    cleanup(() => targets.forEach((target) => revealObserver?.unobserve(target)));
});

/**
 * x-parallax — writes --px / --py (-1..1) from the pointer onto the element,
 * so children with .depth and --d drift at their own depth.
 */
Alpine.directive('parallax', (el, _, { cleanup }) => {
    if (prefersReducedMotion || !finePointer) return;

    let frame = null;
    const onMove = (event) => {
        const rect = el.getBoundingClientRect();
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            el.style.setProperty('--px', (((event.clientX - rect.left) / rect.width) - 0.5) * 2);
            el.style.setProperty('--py', (((event.clientY - rect.top) / rect.height) - 0.5) * 2);
        });
    };
    const onLeave = () => {
        el.style.setProperty('--px', 0);
        el.style.setProperty('--py', 0);
    };

    el.addEventListener('pointermove', onMove);
    el.addEventListener('pointerleave', onLeave);
    cleanup(() => {
        el.removeEventListener('pointermove', onMove);
        el.removeEventListener('pointerleave', onLeave);
    });
});

/**
 * x-bay — scroll-driven 3D chart plate. Writes --p (0 → 1) as the element
 * travels up the viewport, and --mx / --my from the pointer.
 */
Alpine.directive('bay', (el, _, { cleanup }) => {
    if (prefersReducedMotion) {
        el.style.setProperty('--p', 1);
        return;
    }

    let frame = null;
    const update = () => {
        frame = null;
        const rect = el.getBoundingClientRect();
        const vh = window.innerHeight || 1;
        // 0 when the top enters the bottom of the screen, 1 when its centre reaches 40% height.
        const progress = (vh - rect.top) / (vh * 0.6 + rect.height * 0.5);
        el.style.setProperty('--p', Math.min(1, Math.max(0, progress)).toFixed(3));
    };
    const onScroll = () => { if (!frame) frame = requestAnimationFrame(update); };
    const onMove = (event) => {
        const rect = el.getBoundingClientRect();
        el.style.setProperty('--mx', ((((event.clientX - rect.left) / rect.width) - 0.5) * 2).toFixed(3));
        el.style.setProperty('--my', ((((event.clientY - rect.top) / rect.height) - 0.5) * 2).toFixed(3));
    };
    const onLeave = () => {
        el.style.setProperty('--mx', 0);
        el.style.setProperty('--my', 0);
    };

    update();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    if (finePointer) {
        el.addEventListener('pointermove', onMove);
        el.addEventListener('pointerleave', onLeave);
    }

    cleanup(() => {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onScroll);
        el.removeEventListener('pointermove', onMove);
        el.removeEventListener('pointerleave', onLeave);
    });
});

window.Alpine = Alpine;

Alpine.start();
