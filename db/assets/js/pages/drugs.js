/** Drugs / Catalog — live list with search and stock/expiry badges (read-only;
 * create/edit is Phase 3). Fields mirror drug_isar.dart (docs/REFERENCE.md). */
import { watch, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, badge, money, fmtDate, stockStatus, expiryStatus, loading } from "../ui.js";

export default function render(outlet, ctx) {
  let drugs = null;
  let q = "";

  const listHost = el("div", {}, loading());
  const search = searchInput("Search name, generic, brand, barcode…", (v) => { q = v; paint(); });
  outlet.append(el("div", { class: "space-y-5" }, [toolbar("Drugs", search), listHost]));

  function stockCell(d) {
    const st = stockStatus(d);
    const kind = st.key === "out" ? "danger" : st.key === "low" ? "warn" : "ok";
    return el("span", {}, [
      el("span", { class: "font-semibold text-ink" }, String(d.stockQuantity ?? 0)),
      el("span", { class: "ms-2", html: badge(st.label, kind) }),
    ]);
  }
  function expiryCell(d) {
    const date = toDate(d.expiryDate);
    if (!date) return "—";
    const ex = expiryStatus(d.expiryDate);
    const wrap = el("span", {}, fmtDate(date) + " ");
    if (ex.key === "expired") wrap.append(el("span", { html: badge("Expired", "danger") }));
    else if (ex.key === "expiring") wrap.append(el("span", { html: badge(ex.label, "warn") }));
    return wrap;
  }

  function paint() {
    if (!drugs) return;
    const rows = drugs
      .filter((d) => d.isActive !== false)
      .filter((d) => {
        if (!q) return true;
        return [d.name, d.genericName, d.brand, d.barcode].some((x) => (x || "").toLowerCase().includes(q));
      })
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""));

    listHost.replaceChildren(table([
      { label: "Name", render: (d) => el("div", {}, [
          el("div", { class: "font-semibold text-ink" }, d.name || "—"),
          d.genericName ? el("div", { class: "text-xs text-soft" }, d.genericName) : null,
        ]) },
      { label: "Category", render: (d) => d.category || "—" },
      { label: "Stock", render: stockCell },
      { label: "Price", render: (d) => money(d.sellingPrice) },
      { label: "Expiry", render: expiryCell },
      { label: "Flags", html: true, render: (d) => d.isControlled ? badge("Controlled", "warn") : "" },
    ], rows, { empty: "No drugs yet", emptyHint: "Drugs added in the app appear here." }));
  }

  const off = watch(ctx.pharmacyId, "drugs", {
    onData: (d) => { drugs = d; paint(); },
    onError: () => { drugs = []; paint(); },
  });
  return off;
}
