/**
 * i18n — three locales: en, fa (Dari), ps (Pashto). Strings live in editable
 * JSON files at assets/i18n/{locale}.json so they're easy to correct.
 *
 * Usage:
 *   await initI18n();                 // call once before rendering the app
 *   import { t } from "./i18n.js";    // t("nav.drugs") -> translated string
 *   applyTranslations(root);          // fills [data-i18n] / [data-i18n-ph] in static HTML
 *   setLocale("fa");                  // persists + reloads (also flips RTL)
 *
 * Language drives direction: fa/ps are RTL, en is LTR.
 */

const LOCALES = ["en", "fa", "ps"];
const RTL = { fa: true, ps: true };
const HTML_LANG = { en: "en", fa: "fa-AF", ps: "ps" };
const KEY = "tabib_db_lang";

let _dict = {};      // active locale strings
let _fallback = {};  // english fallback
let _locale = "en";

function pickSaved() {
  let s; try { s = localStorage.getItem(KEY); } catch (e) {}
  return LOCALES.includes(s) ? s : "en";
}

async function fetchJSON(loc) {
  try {
    const r = await fetch(`./assets/i18n/${loc}.json`, { cache: "no-store" });
    return r.ok ? await r.json() : {};
  } catch (e) { return {}; }
}

export function getLocale() { return _locale; }
export function isRTL() { return !!RTL[_locale]; }
export const LOCALE_LABELS = { en: "English", fa: "دری", ps: "پښتو" };

/** Load the saved locale (+ english fallback) and apply <html> dir/lang. */
export async function initI18n() {
  _locale = pickSaved();
  _fallback = await fetchJSON("en");
  _dict = _locale === "en" ? _fallback : await fetchJSON(_locale);
  document.documentElement.lang = HTML_LANG[_locale] || "en";
  document.documentElement.dir = RTL[_locale] ? "rtl" : "ltr";
  return _locale;
}

/** Translate a key; {param} placeholders replaced from `params`. Falls back en→key. */
export function t(key, params) {
  let s = _dict[key];
  if (s == null) s = _fallback[key];
  if (s == null) s = key;
  if (params) for (const k in params) s = String(s).replace(new RegExp(`\\{${k}\\}`, "g"), params[k]);
  return s;
}

/** Set locale, persist, and reload so every screen re-renders in the new language. */
export function setLocale(loc) {
  if (!LOCALES.includes(loc) || loc === _locale) return;
  try { localStorage.setItem(KEY, loc); } catch (e) {}
  location.reload();
}

/** Fill static markup: [data-i18n] -> textContent, [data-i18n-ph] -> placeholder. */
export function applyTranslations(root = document) {
  root.querySelectorAll("[data-i18n]").forEach((el) => { el.textContent = t(el.getAttribute("data-i18n")); });
  root.querySelectorAll("[data-i18n-ph]").forEach((el) => { el.setAttribute("placeholder", t(el.getAttribute("data-i18n-ph"))); });
}
