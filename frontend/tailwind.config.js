/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Inter Variable"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['"JetBrains Mono Variable"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
      },
      colors: {
        // Brand scale tuned to match the Flycatcher mark (indigo → violet).
        brand: {
          50: '#f1f0fe',
          100: '#e5e3fd',
          200: '#cdc9fb',
          300: '#aca4f7',
          400: '#8b7cf3',
          500: '#7059ee',
          600: '#5f3fe0',
          700: '#4338ca',
          800: '#372fa3',
          900: '#2f2a82',
          950: '#1c1852',
        },
      },
      boxShadow: {
        soft: '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.06)',
        card: '0 1px 2px 0 rgb(0 0 0 / 0.03), 0 4px 12px -2px rgb(0 0 0 / 0.06)',
        lift: '0 8px 24px -4px rgb(0 0 0 / 0.12), 0 2px 8px -2px rgb(0 0 0 / 0.06)',
        glow: '0 0 0 1px rgb(99 102 241 / 0.15), 0 4px 20px -2px rgb(99 102 241 / 0.25)',
      },
      backgroundImage: {
        'brand-gradient': 'linear-gradient(135deg, #6366f1 0%, #4338ca 100%)',
        'brand-radial': 'radial-gradient(circle at 30% 20%, rgb(99 102 241 / 0.15), transparent 60%)',
      },
      animation: {
        'fade-in': 'fade-in 0.2s ease-out',
        'slide-up': 'slide-up 0.25s ease-out',
      },
      keyframes: {
        'fade-in': { from: { opacity: 0 }, to: { opacity: 1 } },
        'slide-up': { from: { opacity: 0, transform: 'translateY(6px)' }, to: { opacity: 1, transform: 'translateY(0)' } },
      },
    },
  },
  plugins: [],
}
