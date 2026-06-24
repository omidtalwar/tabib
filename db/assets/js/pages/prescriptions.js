/** Prescriptions — live read-only list. */
import { watch, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, badge, fmtDate, loading } from "../ui.js";
import { t } from "../i18n.js";

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
    toolbar(t("rx.title"), searchInput(t("rx.searchPh"), (v) => { q = v; paint(); })),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((p) => !q || [p.patientName, p.doctorName].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (toDate(b.issuedDate)?.getTime() || 0) - (toDate(a.issuedDate)?.getTime() || 0));
    host.replaceChildren(table([
      { label: t("rx.colPatient"), render: (p) => p.patientName || "—" },
      { label: t("rx.colDoctor"), render: (p) => p.doctorName || "—" },
      { label: t("rx.colItems"), render: (p) => String(itemCount(p)) },
      { label: t("rx.colStatus"), html: true, render: (p) => badge((p.status || "pending").replace("_", " "), STATUS_KIND[p.status] || "muted") },
      { label: t("rx.colIssued"), render: (p) => fmtDate(toDate(p.issuedDate)) },
      { label: t("rx.colExpires"), render: (p) => fmtDate(toDate(p.expiryDate)) },
    ], filtered, { empty: t("rx.empty"), emptyHint: t("rx.emptyHint") }));
  }

  return watch(ctx.pharmacyId, "prescriptions", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
