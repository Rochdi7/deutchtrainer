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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                arabic: ['"Noto Naskh Arabic"', 'serif'],
            },
            colors: {
                brand: {
                    50: '#eef6ff',
                    100: '#d9eaff',
                    200: '#bcdaff',
                    300: '#8ec2ff',
                    400: '#599fff',
                    500: '#3379ff',
                    600: '#1b57f5',
                    700: '#1443e1',
                    800: '#1737b6',
                    900: '#19338f',
                },
            },
        },
    },

    plugins: [forms],
};
