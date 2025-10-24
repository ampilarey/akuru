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
        // Akuru Maroon + Gold Theme - Heritage & Excellence
        brandMaroon: { 
          DEFAULT: '#6E1E25', // Main maroon
          50: '#FDF5F6',      // Very light maroon/pink for backgrounds
          100: '#F9EAEB',     // Light maroon
          200: '#F3D5D7',     // Soft maroon
          300: '#E8B0B4',     // Medium maroon
          400: '#D77B82',     // Bright maroon
          500: '#B94651',     // Vibrant maroon
          600: '#6E1E25',     // Primary - Deep maroon
          700: '#5A1820',     // Dark maroon
          800: '#47131A',     // Deeper maroon
          900: '#340E13',     // Darkest maroon
        },
        brandGold: { 
          DEFAULT: '#C9A227', // Warm gold accent
          50: '#FEFBF3',
          100: '#FDF6E3',
          200: '#FBEDC7',
          300: '#F7E0A0',
          400: '#F0CE69',
          500: '#E8BC3C',     // Bright gold
          600: '#C9A227',     // Secondary - Main gold
          700: '#A8861F',     // Dark gold
          800: '#876B19',     // Deeper gold
          900: '#6B5414',     // Darkest gold
        },
        brandBeige: {
          DEFAULT: '#F9F4EE', // Warm beige background
          50: '#FEFDFB',
          100: '#FDF9F5',
          200: '#F9F4EE',     // Main beige background
          300: '#F3EBE0',
          400: '#E8DCC9',
          500: '#DCCDAB',
          600: '#C9B388',
          700: '#A89165',
          800: '#85714F',
          900: '#65563D',
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