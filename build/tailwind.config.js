/** @type {import('tailwindcss').Config} */
module.exports = {
  // Scan the portal source so unused utilities are purged from the built file.
  content: [
    "../db/index.html",
    "../db/app.html",
    "../db/assets/js/**/*.js",
  ],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        // One restrained accent (teal, matched to the Tabib brand).
        brand: {
          50: "#e6f7f5",
          100: "#c9efeb",
          200: "#9fe2db",
          300: "#5fcdc2",
          400: "#26b3a6",
          500: "#0EA59B",
          600: "#0A7F77",
          700: "#0a6660",
          800: "#0b524e",
          900: "#0c433f",
        },
        ink: "#13201F",
        soft: "#6C7B7A",
        line: "#E6EBEA",
        // Stock / expiry status colors — high contrast, they carry safety meaning.
        danger: "#D7263D",
        warn: "#E8870C",
        ok: "#1F9D55",
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', "system-ui", "sans-serif"],
        rtl: ['"Vazirmatn"', "system-ui", "sans-serif"],
      },
    },
  },
  plugins: [],
};
