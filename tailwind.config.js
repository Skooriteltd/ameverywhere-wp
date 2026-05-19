/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/assets/js/**/*.{js,jsx,ts,tsx}",
    "./src/Modules/**/*.php",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f4f7fa',
          100: '#e5ecf3',
          500: '#4361ee', // Primary brand color
          600: '#3a0ca3',
          900: '#0f172a', // Dark text
        }
      }
    },
  },
  plugins: [],
  corePlugins: {
    preflight: false, // Prevent tailwind from overriding WP admin styles heavily
  }
}
