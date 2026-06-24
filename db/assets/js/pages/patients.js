/** Patients — live list with search (read-only; CRUD is Phase 3).
 * Fields mirror patient_isar.dart (docs/REFERENCE.md). */
import { watch, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, fmtDate, loading } from "../ui.js";

export default function render(outlet, ctx) {
  let rows = null, q = "";
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar("Patients", searchInput("Search name or phone…", (v) => { q = v; paint(); })),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((p) => !q || [p.fullName, p.phone].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (a.fullName || "").localeCompare(b.fullName || ""));
    host.replaceChildren(table([
      { label: "Name", render: (p) => p.fullName || "—" },
      { label: "Phone", render: (p) => p.phone || "—" },
      { label: "Gender", render: (p) => p.gender || "—" },
      { label: "Blood", render: (p) => p.bloodGroup || "—" },
      { label: "Allergies", render: (p) => Array.isArray(p.allergies) && p.allergies.length ? p.allergies.join(", ") : "—" },
      { label: "Added", render: (p) => fmtDate(toDate(p.createdAt)) },
    ], filtered, { empty: "No patients yet", emptyHint: "Patients added in the app appear here." }));
  }

  return watch(ctx.pharmacyId, "patients", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
