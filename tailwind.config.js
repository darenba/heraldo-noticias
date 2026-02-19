import defaultTheme from 'tailwindcss/defaultTheme';

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
            colors: {
                heraldo: {
                    'sepia-50':  '#fdf8f0',
                    'sepia-100': '#f5ead8',
                    'sepia-200': '#e8d5b7',
                    'sepia-300': '#d4b896',
                    'sepia-700': '#6b4c2a',
                    'sepia-900': '#2c1a0e',
                    'ink':       '#1a1208',
                    'gold':      '#b8860b',
                    'red':       '#8b1a1a',
                },
            },
            fontFamily: {
                'serif': ['Playfair Display', ...defaultTheme.fontFamily.serif],
                'body':  ['Crimson Text', ...defaultTheme.fontFamily.serif],
                'sans':  ['Inter', ...defaultTheme.fontFamily.sans],
            },
            maxWidth: {
                'article': '720px',
            },
        },
    },
    plugins: [],
};
