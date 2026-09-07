import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    500: '#E63946',
                    600: '#D62828',
                },
                dark: {
                    900: '#0f0f0f',
                    800: '#1a1a1a',
                    700: '#262626',
                    600: '#333333',
                },
            },
            fontFamily: {
                sans: ['Roboto', ...defaultTheme.fontFamily.sans],
                heading: ['Oswald', 'sans-serif'],
            },
        },
    },

    plugins: [forms],
};
