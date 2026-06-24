/** Inventory — low-stock, out-of-stock, and expiry views (read-only). */
import { watch, toDate } from "../repo.js";
import { el, table, badge, money, fmtDate, daysUntil, loading } from "../ui.js";
import { t } from "../i18n.js";

export default function render(outlet, ctx) {
  let drugs = null;
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-6" }, [host]));

  const drugCols = (extra) => [
    { label: t("inv.colName"), render: (d) => el("div", {}, [
        el("div", { class: "font-semibold text-ink" }, d.name || "—"),
        d.genericName ? el("div", { class: "text-xs text-soft" }, d.genericName) : null,
      ]) },
    { label: t("inv.colStock"), render: (d) => String(d.stockQuantity ?? 0) },
    { label: t("inv.colReorder"), render: (d) => String(d.reorderThreshold ?? 0) },
    { label: t("inv.colPrice"), render: (d) => money(d.sellingPrice) },
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

    const expiryCol = [{ label: t("inv.colExpiry"), html: false, render: (d) => {
      const date = toDate(d.expiryDate); const n = daysUntil(date);
      const w = el("span", {}, fmtDate(date) + " ");
      if (n != null && n < 0) w.append(el("span", { html: badge(t("status.expired"), "danger") }));
      else if (n != null && n <= 30) w.append(el("span", { html: badge(`${n}d`, "warn") }));
      return w;
    } }];

    host.replaceChildren(el("div", { class: "space-y-8" }, [
      section(t("inv.outTitle"), out, drugCols(), "danger", t("inv.nothingOut")),
      section(t("inv.lowTitle"), low, drugCols(), "warn", t("inv.nothingLow")),
      section(t("inv.expiredTitle"), expired, drugCols(expiryCol), "danger", t("inv.nothingExpired")),
      section(t("inv.expiringTitle"), expiring, drugCols(expiryCol), "warn", t("inv.nothingExpiring")),
    ]));
  }

  return watch(ctx.pharmacyId, "drugs", { onData: (d) => { drugs = d; paint(); }, onError: () => { drugs = []; paint(); } });
}
