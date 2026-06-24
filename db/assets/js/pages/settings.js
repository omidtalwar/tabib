/**
 * Settings — access info + a "reset local cache" maintenance action.
 *
 * Reset clears the two local cache layers (Firestore offline IndexedDB cache and
 * the service-worker asset caches) and reloads, forcing a fresh download from
 * the server. It does NOT sign you out — Firebase Auth uses a separate store, so
 * your account/session is untouched.
 */
import { terminate, clearIndexedDbPersistence } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js";
import { db } from "../firebase.js";
import { el, confirmDialog, toast } from "../ui.js";

async function resetLocalCache() {
  const ok = await confirmDialog(
    "Clear all locally cached data and reload? You stay signed in — the app just re-downloads everything from the server.",
    { confirmLabel: "Reset cache", danger: true }
  );
  if (!ok) return;

  // 1. Service-worker asset caches + registrations.
  try {
    if (window.caches) {
      const keys = await caches.keys();
      await Promise.all(keys.map((k) => caches.delete(k)));
    }
    if (navigator.serviceWorker) {
      const regs = await navigator.serviceWorker.getRegistrations();
      await Promise.all(regs.map((r) => r.unregister()));
    }
  } catch (e) { console.warn("cache clear", e); }

  // 2. Firestore offline cache (IndexedDB). Terminate first so it can be cleared.
  try {
    await terminate(db);
    await clearIndexedDbPersistence(db);
  } catch (e) {
    // Often "failed-precondition" if another tab holds the cache — reload anyway.
    console.warn("firestore cache clear", e);
  }

  toast("Cache cleared — reloading…", { type: "ok" });
  setTimeout(() => location.reload(), 400);
}

export default function render(outlet, ctx) {
  const isAdmin = ctx.session.role === "admin";

  const access = el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, "Your access"),
    el("p", { class: "mt-1 text-sm text-soft" },
      `${ctx.session.email || "—"} · role: ${ctx.session.role || "unknown"} · pharmacy: ${ctx.pharmacyId}`),
    el("p", { class: "mt-2 text-sm " + (isAdmin ? "text-ok" : "text-soft") },
      isAdmin ? "Admin — staff management will live here (coming soon)." : "Staff access."),
  ]);

  const maintenance = el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, "Reset local cache"),
    el("p", { class: "mt-1 text-sm text-soft" },
      "Clears this device's cached data and app files, then reloads with a fresh copy from the server. " +
      "Use this if something looks out of date. Your account and login are not affected, and your data on the server is not deleted."),
    el("div", { class: "mt-4" }, [
      el("button", { class: "btn-danger", onclick: resetLocalCache }, "Reset local cache"),
    ]),
  ]);

  outlet.append(el("div", { class: "space-y-5" }, [access, maintenance]));
}
