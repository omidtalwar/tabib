/** Sales — live read-only history. Full POS (cart + atomic stock decrement) is
 * Phase 3. Fields mirror sale_isar.dart: items in `itemsJson` (JSON string),
 * dates ISO strings, money in `total` (docs/REFERENCE.md). */
import { watch, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, money, fmtDate, loading } from "../ui.js";

function itemCount(sale) {
  try { return JSON.parse(sale.itemsJson || "[]").length; } catch { return 0; }
}

export default function render(outlet, ctx) {
  let rows = null, q = "";
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar("Sales (POS)", searchInput("Search receipt or patient…", (v) => { q = v; paint(); })),
    el("p", { class: "text-sm text-soft" }, "Read-only history. New-sale POS with atomic stock decrement is Phase 3."),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((s) => !q || [s.receiptNumber, s.patientName].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (toDate(b.createdAt)?.getTime() || 0) - (toDate(a.createdAt)?.getTime() || 0));
    host.replaceChildren(table([
      { label: "Receipt", render: (s) => s.receiptNumber || s.firestoreId?.slice(0, 8) || "—" },
      { label: "Patient", render: (s) => s.patientName || "—" },
      { label: "Items", render: (s) => String(itemCount(s)) },
      { label: "Payment", render: (s) => s.paymentMethod || "—" },
      { label: "Total", render: (s) => money(s.total) },
      { label: "When", render: (s) => fmtDate(toDate(s.createdAt)) },
    ], filtered, { empty: "No sales yet", emptyHint: "Sales recorded in the app appear here." }));
  }

  return watch(ctx.pharmacyId, "sales", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
