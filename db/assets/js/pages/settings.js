/** Settings — access info + reset-local-cache maintenance action. */
import { terminate, clearIndexedDbPersistence } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js";
import { db } from "../firebase.js";
import { el, confirmDialog, toast } from "../ui.js";
import { t } from "../i18n.js";

async function resetLocalCache() {
  const ok = await confirmDialog(t("set.resetConfirm"), { confirmLabel: t("set.resetBtn"), danger: true });
  if (!ok) return;
  try {
    if (window.caches) { const keys = await caches.keys(); await Promise.all(keys.map((k) => caches.delete(k))); }
    if (navigator.serviceWorker) { const regs = await navigator.serviceWorker.getRegistrations(); await Promise.all(regs.map((r) => r.unregister())); }
  } catch (e) { console.warn("cache clear", e); }
  try { await terminate(db); await clearIndexedDbPersistence(db); } catch (e) { console.warn("firestore cache clear", e); }
  toast(t("set.resetDone"), { type: "ok" });
  setTimeout(() => location.reload(), 400);
}

export default function render(outlet, ctx) {
  const isAdmin = ctx.session.role === "admin";

  const access = el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, t("set.access")),
    el("p", { class: "mt-1 text-sm text-soft" }, t("set.accessLine", { email: ctx.session.email || "—", role: ctx.session.role || "—", pid: ctx.pharmacyId })),
    el("p", { class: "mt-2 text-sm " + (isAdmin ? "text-ok" : "text-soft") }, isAdmin ? t("set.adminNote") : t("set.staffNote")),
  ]);

  const maintenance = el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, t("set.resetTitle")),
    el("p", { class: "mt-1 text-sm text-soft" }, t("set.resetBody")),
    el("div", { class: "mt-4" }, [el("button", { class: "btn-danger", onclick: resetLocalCache }, t("set.resetBtn"))]),
  ]);

  outlet.append(el("div", { class: "space-y-5" }, [access, maintenance]));
}
