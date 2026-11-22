import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

/*
 * BookEase design tokens — Labnasan Resort, Basey, Samar.
 * World: a nautical chart of the bay — paper, hairlines, soundings, buff land,
 * teal water, and one chart-magenta (muted coral) for markers only.
 *
 *  sand    buff "land" tint (surfaces, borders)
 *  lagoon  deep teal water — primary brand / actions; lagoon-950 is the ink
 *  ocean   shallow-water blue — info, calendar
 *  coral   chart-marker coral — small markers and warnings, never large fills
 *  cream   chart paper (page background)
 *  gray    warm "driftwood" neutrals
 */

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Hanken Grotesk"', ...defaultTheme.fontFamily.sans],
                // Engraved chart roman: Display cut for large headings, Text cut for small ones.
                display: ['"Libre Caslon Display"', '"Libre Caslon Text"', 'Georgia', ...defaultTheme.fontFamily.serif],
                serif: ['"Libre Caslon Text"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                cream: {
                    DEFAULT: '#faf7f1',
                    deep: '#f1ebdf',
                },
                sand: {
                    50: '#fbf7f0',
                    100: '#f5ecdc',
                    200: '#ebdbbd',
                    300: '#dfc497',
                    400: '#cfa66c',
                    500: '#bf8c4c',
                    600: '#a4723c',
                    700: '#845a33',
                    800: '#6b4a2e',
                    900: '#583e28',
                },
                lagoon: {
                    50: '#eef8f7',
                    100: '#d3eeeb',
                    200: '#a9ddd8',
                    300: '#74c4bd',
                    400: '#45a39d',
                    500: '#2b8781',
                    600: '#216c69',
                    700: '#1e5756',
                    800: '#1c4747',
                    900: '#163a3b',
                    950: '#0b2324',
                },
                ocean: {
                    50: '#eff7fc',
                    100: '#dbecf7',
                    200: '#bfdef1',
                    300: '#93c9e7',
                    400: '#60acda',
                    500: '#3d8fcb',
                    600: '#2b74b4',
                    700: '#255e92',
                    800: '#234f78',
                    900: '#224364',
                },
                coral: {
                    50: '#fbf3f0',
                    100: '#f6e2db',
                    200: '#ecc3b6',
                    300: '#df9f8a',
                    400: '#d17f66',
                    500: '#c2654b',
                    600: '#a8513a',
                    700: '#89412f',
                    800: '#6f3628',
                    900: '#5b2e24',
                },
                gray: {
                    50: '#faf8f5',
                    100: '#f3efe9',
                    200: '#e6e0d6',
                    300: '#d3cabc',
                    400: '#aa9f8f',
                    500: '#7f7566',
                    600: '#635a4e',
                    700: '#4d463d',
                    800: '#36312b',
                    900: '#25211d',
                    950: '#161411',
                },
            },
            boxShadow: {
                soft: '0 1px 0 rgb(11 35 36 / 0.03), 0 1px 3px rgb(11 35 36 / 0.06)',
                lift: '0 1px 2px rgb(11 35 36 / 0.06), 0 22px 44px -26px rgb(11 35 36 / 0.45)',
                press: 'inset 0 1px 2px rgb(0 0 0 / 0.18)',
                // Legacy names kept so older markup stays quiet: no coloured halos.
                'coral-glow': '0 1px 2px rgb(11 35 36 / 0.14)',
                'lagoon-glow': '0 1px 2px rgb(11 35 36 / 0.14)',
                plate: '0 2px 4px rgb(11 35 36 / 0.08), 0 40px 80px -40px rgb(11 35 36 / 0.55)',
            },
            // Tight, drafted corners everywhere (overrides the stock scale on purpose).
            borderRadius: {
                sm: '2px',
                DEFAULT: '3px',
                md: '4px',
                lg: '5px',
                xl: '6px',
                '2xl': '7px',
                '3xl': '8px',
                '4xl': '10px',
            },
            transitionTimingFunction: {
                'out-soft': 'cubic-bezier(0.22, 1, 0.36, 1)',
                spring: 'cubic-bezier(0.3, 1.15, 0.5, 1)',
                expo: 'cubic-bezier(0.16, 1, 0.3, 1)',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'pop-in': {
                    '0%': { opacity: '0', transform: 'scale(0.98) translateY(10px)' },
                    '100%': { opacity: '1', transform: 'scale(1) translateY(0)' },
                },
                drift: {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '50%': { transform: 'translateX(-24px)' },
                },
                bob: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-6px)' },
                },
                ping: {
                    '75%, 100%': { transform: 'scale(2.2)', opacity: '0' },
                },
            },
            animation: {
                'fade-up': 'fade-up .7s cubic-bezier(0.22, 1, 0.36, 1) backwards',
                'pop-in': 'pop-in .6s cubic-bezier(0.34, 1.56, 0.64, 1) backwards',
                drift: 'drift 9s ease-in-out infinite',
                'drift-slow': 'drift 14s ease-in-out infinite',
                bob: 'bob 5s ease-in-out infinite',
            },
        },
    },

    plugins: [
        forms,
        // `short:` — viewports under 720px tall (1366×768 laptops, 1280×720
        // projectors), so one-screen pages like sign-in can tighten and fit.
        plugin(({ addVariant }) => addVariant('short', '@media (max-height: 720px)')),
    ],
};
