/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                display: ['Poppins', 'ui-sans-serif', 'sans-serif'],
            },
            colors: {
                primary: {
                    DEFAULT: '#6a5cf5',
                    dark: '#5847e8',
                },
                accent: '#f5a623',
            },
        },
    },
    plugins: [],
};
