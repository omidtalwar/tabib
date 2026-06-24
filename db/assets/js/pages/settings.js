/** Settings — access info + reset-local-cache maintenance action. */
import { terminate, clearIndexedDbPersistence } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js";
import { db } from "../firebase.js";
import { watch, put, callFn } from "../repo.js";
import { el, table, confirmDialog, toast, formModal, badge, loading } from "../ui.js";
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

  const sections = [access];
  let off = null;

  // Staff management — admins only.
  if (isAdmin) {
    const pid = ctx.pharmacyId;
    const staffHost = el("div", {}, loading());
    async function invite() {
      const ok = await formModal({
        title: t("stf.invite"),
        submitLabel: t("stf.invite"),
        values: { role: "staff" },
        fields: [
          { name: "email", label: t("stf.email"), type: "email", required: true },
          { name: "password", label: t("stf.password"), required: true, help: t("stf.note") },
          { name: "role", label: t("stf.role"), type: "select", options: [{ value: "staff", label: t("stf.roleStaff") }, { value: "admin", label: t("stf.roleAdmin") }] },
        ],
        onSubmit: async (d) => {
          let res;
          try {
            res = await callFn("createPharmacyStaff", { email: d.email, password: d.password, pharmacyId: pid, role: d.role || "staff" });
          } catch (e) {
            const code = (e && e.code) || "";
            if (code.includes("already-exists")) throw new Error(t("stf.errExists"));
            if (code.includes("permission-denied")) throw new Error(t("stf.errPerm"));
            throw new Error(t("stf.errGeneric"));
          }
          // Mirror to pharmacies/{id}/staff so members can list staff.
          if (res && res.uid) await put(pid, "staff", res.uid, { email: d.email, role: d.role || "staff", isActive: true, createdAt: new Date().toISOString() });
        },
      });
      if (ok) toast(t("stf.invited"), { type: "ok" });
    }

    const staff = el("div", { class: "card" }, [
      el("div", { class: "mb-3 flex items-center justify-between" }, [
        el("p", { class: "font-semibold text-ink" }, t("stf.title")),
        el("button", { class: "btn-primary", onclick: invite }, "+ " + t("stf.invite")),
      ]),
      staffHost,
    ]);
    sections.push(staff);

    off = watch(pid, "staff", {
      onData: (list) => staffHost.replaceChildren(table([
        { label: t("stf.colEmail"), render: (s) => s.email || "—" },
        { label: t("stf.colRole"), html: true, render: (s) => badge(s.role === "admin" ? t("stf.roleAdmin") : t("stf.roleStaff"), s.role === "admin" ? "ok" : "muted") },
      ], (list || []).filter((s) => s.isActive !== false), { empty: t("stf.empty") })),
      onError: () => staffHost.replaceChildren(table([], [], { empty: t("stf.empty") })),
    });
  }

  sections.push(maintenance);
  outlet.append(el("div", { class: "space-y-5" }, sections));
  return off || undefined;
}
