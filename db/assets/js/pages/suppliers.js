/** Suppliers — live list (read-only; CRUD is Phase 3).
 * Fields mirror supplier_isar.dart (docs/REFERENCE.md). */
import { watch } from "../repo.js";
import { el, table, searchInput, toolbar, loading } from "../ui.js";

export default function render(outlet, ctx) {
  let rows = null, q = "";
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar("Suppliers", searchInput("Search name or contact…", (v) => { q = v; paint(); })),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((s) => !q || [s.name, s.contactName, s.phone].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""));
    host.replaceChildren(table([
      { label: "Name", render: (s) => s.name || "—" },
      { label: "Contact", render: (s) => s.contactName || "—" },
      { label: "Phone", render: (s) => s.phone || "—" },
      { label: "Email", render: (s) => s.email || "—" },
      { label: "Address", render: (s) => s.address || "—" },
    ], filtered, { empty: "No suppliers yet", emptyHint: "Suppliers added in the app appear here." }));
  }

  return watch(ctx.pharmacyId, "suppliers", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
