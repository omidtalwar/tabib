/**
 * router.js — hash router. Routes look like #/p/{pharmacyId}/{page}/{rest...}.
 * The pharmacyId segment is COSMETIC; access is enforced by Firestore rules
 * against the user's claim. The router ignores any pharmacyId that doesn't match
 * the session and rewrites it, so the URL can't be used to reach another
 * pharmacy's data.
 *
 * Each page module lives in ./pages/{page}.js and exports:
 *     export default function render(outlet, ctx) { ... return cleanup? }
 * where ctx = { pharmacyId, session, params:[...], setTitle(fn) }.
 */

import { t } from "./i18n.js";

const PAGES = [
  "dashboard", "drugs", "inventory", "sales",
  "prescriptions", "patients", "suppliers", "purchases", "expenses", "reports", "settings",
];
const DEFAULT_PAGE = "dashboard";

let _ctxBase = null;       // { session, pharmacyId }
let _outlet = null;
let _onNavigate = null;    // cb(page) for sidebar highlight + title
let _cleanup = null;

export function startRouter({ outlet, session, pharmacyId, onNavigate }) {
  _outlet = outlet;
  _ctxBase = { session, pharmacyId: pharmacyId || session.pharmacyId };
  _onNavigate = onNavigate;
  window.addEventListener("hashchange", handle);
  handle();
}

export function navigate(page, ...rest) {
  const pid = _ctxBase.pharmacyId;
  location.hash = `#/p/${pid}/${page}${rest.length ? "/" + rest.join("/") : ""}`;
}

function parseHash() {
  // #/p/{pid}/{page}/{...rest}
  const parts = location.hash.replace(/^#\/?/, "").split("/").filter(Boolean);
  if (parts[0] !== "p") return { page: DEFAULT_PAGE, params: [] };
  return { urlPid: parts[1], page: parts[2] || DEFAULT_PAGE, params: parts.slice(3) };
}

async function handle() {
  const { urlPid, page, params } = parseHash();
  const pid = _ctxBase.pharmacyId;

  // Normalize: enforce the active pharmacyId + a valid page.
  const safePage = PAGES.includes(page) ? page : DEFAULT_PAGE;
  if (urlPid !== pid || !PAGES.includes(page)) {
    location.replace(`#/p/${pid}/${safePage}`);
    return;
  }

  if (typeof _cleanup === "function") { try { _cleanup(); } catch {} _cleanup = null; }
  _outlet.replaceChildren();
  _onNavigate && _onNavigate(safePage);

  try {
    const mod = await import(`./pages/${safePage}.js`);
    const ctx = { pharmacyId: pid, session: _ctxBase.session, params };
    _cleanup = await mod.default(_outlet, ctx);
  } catch (err) {
    console.error(`route(${safePage})`, err);
    _outlet.replaceChildren();
    const div = document.createElement("div");
    div.className = "card";
    div.innerHTML = `<p class="font-semibold text-danger">${t("app.pageFailed")}</p>
      <p class="mt-1 text-sm text-soft">${t("app.pageFailedHint")}</p>`;
    _outlet.append(div);
  }
}

export { PAGES };
