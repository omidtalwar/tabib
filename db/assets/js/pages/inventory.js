/** Inventory — low-stock, out-of-stock, and expiry views over the drugs
 * collection (read-only; restock action is Phase 3). Logic per docs/REFERENCE.md:
 * low = 0 < stockQuantity <= reorderThreshold; expiring = expiryDate within 30d. */
import { watch, toDate } from "../repo.js";
import { el, table, badge, money, fmtDate, daysUntil, loading } from "../ui.js";

export default function render(outlet, ctx) {
  let drugs = null;
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-6" }, [host]));

  const drugCols = (extra) => [
    { label: "Name", render: (d) => el("div", {}, [
        el("div", { class: "font-semibold text-ink" }, d.name || "—"),
        d.genericName ? el("div", { class: "text-xs text-soft" }, d.genericName) : null,
      ]) },
    { label: "Stock", render: (d) => String(d.stockQuantity ?? 0) },
    { label: "Reorder ≤", render: (d) => String(d.reorderThreshold ?? 0) },
    { label: "Price", render: (d) => money(d.sellingPrice) },
    ...(extra || []),
  ];

  function section(title, rows, cols, kind, emptyText) {
    return el("div", { class: "space-y-3" }, [
      el("div", { class: "flex items-center gap-2" }, [
        el("h2", { class: "text-lg font-bold text-ink" }, title),
        el("span", { html: badge(String(rows.length), kind) }),
      ]),
      table(cols, rows, { empty: emptyText }),
    ]);
  }

  function paint() {
    if (!drugs) return;
    const active = drugs.filter((d) => d.isActive !== false);
    const out = active.filter((d) => (d.stockQuantity ?? 0) <= 0);
    const low = active.filter((d) => (d.stockQuantity ?? 0) > 0 && (d.stockQuantity ?? 0) <= (d.reorderThreshold ?? 0));
    const withDays = active.map((d) => ({ d, n: daysUntil(toDate(d.expiryDate)) }));
    const expired = withDays.filter((x) => x.n != null && x.n < 0).map((x) => x.d);
    const expiring = withDays.filter((x) => x.n != null && x.n >= 0 && x.n <= 30).map((x) => x.d);

    const expiryCol = [{ label: "Expiry", html: false, render: (d) => {
      const date = toDate(d.expiryDate); const n = daysUntil(date);
      const w = el("span", {}, fmtDate(date) + " ");
      if (n != null && n < 0) w.append(el("span", { html: badge("Expired", "danger") }));
      else if (n != null && n <= 30) w.append(el("span", { html: badge(`${n}d`, "warn") }));
      return w;
    } }];

    host.replaceChildren(el("div", { class: "space-y-8" }, [
      section("Out of stock", out, drugCols(), "danger", "Nothing out of stock."),
      section("Low stock", low, drugCols(), "warn", "Nothing low on stock."),
      section("Expired", expired, drugCols(expiryCol), "danger", "Nothing expired."),
      section("Expiring within 30 days", expiring, drugCols(expiryCol), "warn", "Nothing expiring soon."),
    ]));
  }

  return watch(ctx.pharmacyId, "drugs", { onData: (d) => { drugs = d; paint(); }, onError: () => { drugs = []; paint(); } });
}
