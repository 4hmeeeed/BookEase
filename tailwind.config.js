import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/*
 * BookEase design tokens — Labnasan Resort, Basey, Samar.
 *
 *  sand    warm beach neutrals (surfaces, borders)
 *  lagoon  deep teal — primary brand / actions
 *  ocean   open-sea blue — info, links, calendar
 *  coral   reef coral — accents, highlights, CTAs
 *  cream   page background
 *  gray    re-tinted warm "driftwood" so stock utilities never look cold
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
                display: ['Fraunces', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                cream: {
                    DEFAULT: '#fffaf2',
                    deep: '#f7efe1',
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
                    50: '#fff4f1',
                    100: '#ffe6df',
                    200: '#ffcfc3',
                    300: '#ffad99',
                    400: '#fc7f62',
                    500: '#f45d3c',
                    600: '#e1421f',
                    700: '#bd3417',
                    800: '#9c2e17',
                    900: '#812c19',
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
                soft: '0 1px 2px rgb(54 49 43 / 0.04), 0 8px 24px -12px rgb(54 49 43 / 0.18)',
                lift: '0 2px 4px rgb(22 58 59 / 0.06), 0 24px 48px -20px rgb(22 58 59 / 0.35)',
                press: 'inset 0 2px 4px rgb(0 0 0 / 0.12)',
                'coral-glow': '0 10px 30px -10px rgb(244 93 60 / 0.55)',
                'lagoon-glow': '0 10px 30px -10px rgb(33 108 105 / 0.55)',
            },
            borderRadius: {
                '4xl': '2rem',
            },
            transitionTimingFunction: {
                'out-soft': 'cubic-bezier(0.22, 1, 0.36, 1)',
                spring: 'cubic-bezier(0.34, 1.56, 0.64, 1)',
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'pop-in': {
                    '0%': { opacity: '0', transform: 'scale(0.9) translateY(12px)' },
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
                'fade-up': 'fade-up .7s cubic-bezier(0.22, 1, 0.36, 1) both',
                'pop-in': 'pop-in .6s cubic-bezier(0.34, 1.56, 0.64, 1) both',
                drift: 'drift 9s ease-in-out infinite',
                'drift-slow': 'drift 14s ease-in-out infinite',
                bob: 'bob 5s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
