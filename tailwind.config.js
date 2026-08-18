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
                navy: {
                    50: '#EEF1F6',
                    100: '#E6E8ED',
                    200: '#C3CAD8',
                    300: '#9AA6BC',
                    500: '#606B87',
                    700: '#0A1B47',
                    800: '#081537',
                    900: '#060F27',
                },
                gold: {
                    50: '#FDF8EE',
                    100: '#F9EFD9',
                    300: '#E4B44C',
                    500: '#D99400',
                    600: '#BD8100',
                    700: '#A36F00',
                    // 500/600/700 all fail WCAG AA (4.5:1) for text on white
                    // or gold-50/100 backgrounds - measured 2.6:1/3.3:1/4.35:1
                    // respectively. 800 is the accent color's darkest step,
                    // specifically for text on light backgrounds (5.6:1) -
                    // 500/600/700 remain fine as-is on dark navy backgrounds
                    // (~7-8:1) or for large/bold headline text.
                    800: '#8C5F00',
                },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                heading: ['Sora', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                shimmer: {
                    '100%': { transform: 'translateX(100%)' },
                },
                shake: {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '20%, 60%': { transform: 'translateX(-6px)' },
                    '40%, 80%': { transform: 'translateX(6px)' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.6s ease-out both',
                'fade-in': 'fade-in 0.6s ease-out both',
                shimmer: 'shimmer 1.6s infinite',
                shake: 'shake 0.4s ease-in-out',
            },
            boxShadow: {
                brand: '0 20px 40px -12px rgba(10, 27, 71, 0.25)',
            },
        },
    },

    plugins: [forms],
};
