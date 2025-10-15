import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'

export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
    './resources/**/*.vue'
  ],
  theme: {
    extend: {
      colors: {
        brandBlue: { 
          DEFAULT: '#1f5aa6',
          50: '#eff6ff',
          100: '#dbeafe',
          200: '#bfdbfe',
          300: '#93c5fd',
          400: '#60a5fa',
          500: '#3b82f6',
          600: '#1f5aa6',
          700: '#1d4ed8',
          800: '#1e40af',
          900: '#1e3a8a',
        },
        brandGray: { 
          DEFAULT: '#4b5563',
          50: '#f9fafb',
          100: '#f3f4f6',
          200: '#e5e7eb',
          300: '#d1d5db',
          400: '#9ca3af',
          500: '#6b7280',
          600: '#4b5563',
          700: '#374151',
          800: '#1f2937',
          900: '#111827',
        },
      },
      fontFamily: { 
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
        arabic: ['Amiri', 'serif'],
      },
      container: { 
        center: true, 
        padding: '1rem',
        screens: {
          sm: '640px',
          md: '768px',
          lg: '1024px',
          xl: '1280px',
          '2xl': '1400px',
        },
      },
    },
  },
  plugins: [forms],
}