/** Prescriptions — live read-only list. Create + dispense-to-POS is Phase 3.
 * Fields mirror prescription_isar.dart: items in `itemsJson`, status enum,
 * dates ISO strings (docs/REFERENCE.md). */
import { watch, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, badge, fmtDate, loading } from "../ui.js";

const STATUS_KIND = {
  pending: "warn", dispensed: "ok", partially_dispensed: "warn",
  cancelled: "muted", expired: "danger",
};
function itemCount(p) {
  try { return JSON.parse(p.itemsJson || "[]").length; } catch { return 0; }
}

export default function render(outlet, ctx) {
  let rows = null, q = "";
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar("Prescriptions", searchInput("Search patient or doctor…", (v) => { q = v; paint(); })),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((p) => !q || [p.patientName, p.doctorName].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (toDate(b.issuedDate)?.getTime() || 0) - (toDate(a.issuedDate)?.getTime() || 0));
    host.replaceChildren(table([
      { label: "Patient", render: (p) => p.patientName || "—" },
      { label: "Doctor", render: (p) => p.doctorName || "—" },
      { label: "Items", render: (p) => String(itemCount(p)) },
      { label: "Status", html: true, render: (p) => badge((p.status || "pending").replace("_", " "), STATUS_KIND[p.status] || "muted") },
      { label: "Issued", render: (p) => fmtDate(toDate(p.issuedDate)) },
      { label: "Expires", render: (p) => fmtDate(toDate(p.expiryDate)) },
    ], filtered, { empty: "No prescriptions yet", emptyHint: "Prescriptions added in the app appear here." }));
  }

  return watch(ctx.pharmacyId, "prescriptions", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
