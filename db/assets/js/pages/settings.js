/** Settings — access info + backup/restore + reset-local-cache maintenance. */
import { terminate, clearIndexedDbPersistence } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js";
import { db } from "../firebase.js";
import { watch, put, callFn } from "../repo.js";
import { el, table, confirmDialog, toast, formModal, badge, loadingSpinner, withButtonLoading } from "../ui.js";
import { t } from "../i18n.js";
import { exportAndDownload, restoreBackup, assertValidBackup, countDocs, downloadJSON } from "../backup.js";
import { onAutoBackup, isEnabled, setEnabled, runOnce, getLatestSnapshot, isRunning } from "../autobackup.js";

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

/** Read a File as text → Promise<string>. */
function readFileText(file) {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(String(r.result || ""));
    r.onerror = () => reject(new Error(t("bak.errRead")));
    r.readAsText(file);
  });
}

/** Backup & restore card (admin only). */
function backupCard(pid) {
  const status = el("p", { class: "mt-3 text-sm text-soft hidden" });
  const setStatus = (msg, hide = false) => {
    status.textContent = msg || "";
    status.classList.toggle("hidden", hide || !msg);
  };

  async function doBackup(e) {
    await withButtonLoading(e.currentTarget, async () => {
      try {
        setStatus(t("bak.working"));
        const backup = await exportAndDownload(pid, ({ collection, done, total }) =>
          setStatus(t("bak.exporting", { done, total, name: collection || "" }))
        );
        setStatus("", true);
        toast(t("bak.exportDone", { count: countDocs(backup) }), { type: "ok" });
      } catch (err) {
        console.error("backup", err);
        setStatus("", true);
        toast(t("bak.errExport"), { type: "error" });
      }
    });
  }

  // Hidden file input drives the restore flow.
  const fileInput = el("input", {
    type: "file", accept: "application/json,.json", class: "hidden",
    onchange: async (e) => {
      const file = e.target.files && e.target.files[0];
      e.target.value = ""; // allow re-selecting the same file later
      if (!file) return;
      let backup;
      try {
        backup = JSON.parse(await readFileText(file));
        assertValidBackup(backup);
      } catch (err) {
        toast(err.message || t("bak.errParse"), { type: "error" });
        return;
      }
      const total = countDocs(backup);
      const ok = await confirmDialog(t("bak.restoreConfirm", { count: total }), {
        confirmLabel: t("bak.restoreBtn"), danger: true,
      });
      if (!ok) return;
      try {
        setStatus(t("bak.working"));
        const written = await restoreBackup(pid, backup, ({ done, total: tt }) =>
          setStatus(t("bak.restoring", { done, total: tt }))
        );
        setStatus("", true);
        toast(t("bak.restoreDone", { count: written }), { type: "ok" });
      } catch (err) {
        console.error("restore", err);
        setStatus("", true);
        toast(t("bak.errRestore"), { type: "error" });
      }
    },
  });

  return el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, t("bak.title")),
    el("p", { class: "mt-1 text-sm text-soft" }, t("bak.body")),
    el("div", { class: "mt-4 flex flex-wrap gap-2" }, [
      el("button", { class: "btn-primary", onclick: doBackup }, t("bak.exportBtn")),
      el("button", { class: "btn-ghost", onclick: () => fileInput.click() }, t("bak.restoreBtn")),
      fileInput,
    ]),
    status,
  ]);
}

/**
 * Automatic local backup card (admin only). Shows live status of the on-device
 * snapshot and lets the admin back up now, download it, or restore from it.
 * Returns { node, cleanup }.
 */
function autoBackupCard(pid) {
  const statusLine = el("p", { class: "mt-1 text-sm text-soft" }, t("auto.never"));

  const fmtMeta = (meta) => {
    if (isRunning()) return t("auto.running");
    if (!meta || !meta.at) return t("auto.never");
    let when = meta.at;
    try { when = new Date(meta.at).toLocaleString(); } catch {}
    return t("auto.last", { when, count: meta.count ?? 0 });
  };

  const toggle = el("input", { type: "checkbox", class: "h-5 w-5 rounded border-line text-brand-500" });
  toggle.checked = isEnabled();
  toggle.addEventListener("change", () => {
    setEnabled(toggle.checked);
    toast(toggle.checked ? t("auto.on") : t("auto.off"), { type: "ok" });
  });

  async function backupNow(e) {
    await withButtonLoading(e.currentTarget, async () => {
      const snap = await runOnce(pid, { force: true });
      if (snap) toast(t("auto.savedNow", { count: countDocs(snap) }), { type: "ok" });
      else toast(t("auto.skipped"), { type: "warn" });
    });
  }

  async function downloadLatest(e) {
    await withButtonLoading(e.currentTarget, async () => {
      const snap = await getLatestSnapshot();
      if (!snap) { toast(t("auto.none"), { type: "warn" }); return; }
      const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
      downloadJSON(`tabib-backup-${pid}-${stamp}.json`, snap);
      toast(t("bak.exportDone", { count: countDocs(snap) }), { type: "ok" });
    });
  }

  async function restoreLatest(e) {
    const snap = await getLatestSnapshot();
    if (!snap) { toast(t("auto.none"), { type: "warn" }); return; }
    const total = countDocs(snap);
    const ok = await confirmDialog(t("bak.restoreConfirm", { count: total }), {
      confirmLabel: t("bak.restoreBtn"), danger: true,
    });
    if (!ok) return;
    await withButtonLoading(e.currentTarget, async () => {
      try {
        const written = await restoreBackup(pid, snap);
        toast(t("bak.restoreDone", { count: written }), { type: "ok" });
      } catch (err) {
        console.error("restore-latest", err);
        toast(t("bak.errRestore"), { type: "error" });
      }
    });
  }

  const node = el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, t("auto.title")),
    el("p", { class: "mt-1 text-sm text-soft" }, t("auto.body")),
    statusLine,
    el("label", { class: "mt-3 flex items-center gap-2 text-sm text-ink" }, [toggle, t("auto.enable")]),
    el("div", { class: "mt-4 flex flex-wrap gap-2" }, [
      el("button", { class: "btn-primary", onclick: backupNow }, t("auto.now")),
      el("button", { class: "btn-ghost", onclick: downloadLatest }, t("auto.download")),
      el("button", { class: "btn-ghost", onclick: restoreLatest }, t("auto.restore")),
    ]),
  ]);

  // Live status updates while this page is open.
  const unsub = onAutoBackup((meta) => { statusLine.textContent = fmtMeta(meta); });
  return { node, cleanup: unsub };
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
  let autoCleanup = null;

  // Staff management — admins only.
  if (isAdmin) {
    const pid = ctx.pharmacyId;
    const staffHost = el("div", {}, loadingSpinner());
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
    sections.push(backupCard(pid));
    const auto = autoBackupCard(pid);
    sections.push(auto.node);
    autoCleanup = auto.cleanup;

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

  // Compose cleanups: stop the staff listener and the auto-backup status sub.
  return () => { try { off && off(); } catch {} try { autoCleanup && autoCleanup(); } catch {} };
}
