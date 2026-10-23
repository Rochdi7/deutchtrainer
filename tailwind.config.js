import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Nunito', 'Inter', ...defaultTheme.fontFamily.sans],
                arabic: ['"Noto Sans Arabic"', '"Noto Naskh Arabic"', 'sans-serif'],
            },
            colors: {
                // Primary = playful green (language-app look). Scale keeps existing brand-* utilities working.
                brand: {
                    50: '#f3f0ff',
                    100: '#e6deff',
                    200: '#cfc0ff',
                    300: '#b096ff',
                    400: '#9270ff',
                    500: '#7c5cff',
                    600: '#6243d6',
                    700: '#5036ad',
                    800: '#412c8c',
                    900: '#352571',
                },
                pop: {
                    primary: '#7c5cff',
                    'primary-dark': '#6243d6',
                    green: '#1fb57a',
                    'green-dark': '#178f60',
                    blue: '#4f6df5',
                    'blue-dark': '#3b55d6',
                    red: '#f0506e',
                    'red-dark': '#d43a58',
                    yellow: '#f6b73c',
                    'yellow-dark': '#d99a1e',
                    orange: '#fb8c4b',
                    purple: '#9b6bf2',
                    pink: '#e05aa0',
                    teal: '#22b8cf',
                    text: '#3f3f46',
                    muted: '#a1a1aa',
                    line: '#e4e4e7',
                },
            },
            boxShadow: {
                'btn': '0 4px 0 0 var(--tw-shadow-color)',
                'btn-lg': '0 6px 0 0 var(--tw-shadow-color)',
            },
            keyframes: {
                bob: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-6px)' } },
                pop: { '0%': { transform: 'scale(.9)', opacity: 0 }, '100%': { transform: 'scale(1)', opacity: 1 } },
            },
            animation: {
                bob: 'bob 1.4s ease-in-out infinite',
                pop: 'pop .18s ease-out',
            },
        },
    },

    plugins: [forms],
};
