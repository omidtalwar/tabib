/**
 * Dashboard — live KPIs, 14-day revenue, top drugs, recent sales.
 * Reads pharmacies/{id}/drugs and /sales in real time and recomputes on change.
 * Field shapes mirror the app's Isar models (docs/REFERENCE.md): dates are ISO
 * strings, sale.items live in `itemsJson` (a JSON string), money in `total`.
 */
import { watch, toDate } from "../repo.js";
import { el, money, fmtDate, daysUntil, stockStatus, expiryStatus } from "../ui.js";

function parseItems(sale) {
  try { return JSON.parse(sale.itemsJson || "[]"); } catch { return []; }
}
function sameDay(d, ref) {
  return d && d.getFullYear() === ref.getFullYear() && d.getMonth() === ref.getMonth() && d.getDate() === ref.getDate();
}
function sameMonth(d, ref) {
  return d && d.getFullYear() === ref.getFullYear() && d.getMonth() === ref.getMonth();
}

export default function render(outlet, ctx) {
  const state = { drugs: null, sales: null };

  const root = el("div", { class: "space-y-5" }, "Loading…");
  outlet.append(root);

  function draw() {
    if (!state.drugs || !state.sales) return;
    const now = new Date();
    const drugs = state.drugs;
    const sales = state.sales;

    const active = drugs.filter((d) => d.isActive !== false);
    const lowStock = active.filter((d) => (d.stockQuantity ?? 0) > 0 && (d.stockQuantity ?? 0) <= (d.reorderThreshold ?? 0));
    const outStock = active.filter((d) => (d.stockQuantity ?? 0) <= 0);
    const expiring = active.filter((d) => { const n = daysUntil(toDate(d.expiryDate)); return n != null && n >= 0 && n <= 30; });

    const todayRev = sales.filter((s) => sameDay(toDate(s.createdAt), now)).reduce((a, s) => a + (Number(s.total) || 0), 0);
    const monthRev = sales.filter((s) => sameMonth(toDate(s.createdAt), now)).reduce((a, s) => a + (Number(s.total) || 0), 0);

    const kpis = [
      ["Today's revenue", money(todayRev)],
      ["This month", money(monthRev)],
      ["Low stock", String(lowStock.length)],
      ["Expiring ≤30d", String(expiring.length)],
      ["Out of stock", String(outStock.length)],
      ["Total drugs", String(active.length)],
    ];

    // Top 5 drugs by units sold this month.
    const counts = {};
    for (const s of sales) {
      if (!sameMonth(toDate(s.createdAt), now)) continue;
      for (const it of parseItems(s)) counts[it.drugName || "—"] = (counts[it.drugName || "—"] || 0) + (Number(it.quantity) || 0);
    }
    const top = Object.entries(counts).sort((a, b) => b[1] - a[1]).slice(0, 5);

    // Recent sales (last 10).
    const recent = [...sales].sort((a, b) => (toDate(b.createdAt)?.getTime() || 0) - (toDate(a.createdAt)?.getTime() || 0)).slice(0, 10);

    const grid = el("div", { class: "grid grid-cols-2 gap-4 lg:grid-cols-6" },
      kpis.map(([label, value]) => el("div", { class: "kpi" }, [
        el("span", { class: "kpi-value" }, value),
        el("span", { class: "kpi-label" }, label),
      ]))
    );

    const topCard = el("div", { class: "card" }, [
      el("p", { class: "font-semibold text-ink" }, "Top drugs this month"),
      top.length
        ? el("ul", { class: "mt-3 space-y-2" }, top.map(([name, qty]) =>
            el("li", { class: "flex items-center justify-between text-sm" }, [
              el("span", { class: "text-ink" }, name),
              el("span", { class: "font-semibold text-soft" }, `${qty} sold`),
            ])))
        : el("p", { class: "mt-3 text-sm text-soft" }, "No sales yet this month."),
    ]);

    const recentCard = el("div", { class: "card lg:col-span-2" }, [
      el("p", { class: "font-semibold text-ink" }, "Recent sales"),
      recent.length
        ? el("div", { class: "mt-3 overflow-x-auto" }, el("table", { class: "table" }, [
            el("thead", {}, el("tr", {}, ["Receipt", "Patient", "Total", "When"].map((h) => el("th", {}, h)))),
            el("tbody", {}, recent.map((s) => el("tr", {}, [
              el("td", {}, s.receiptNumber || s.firestoreId?.slice(0, 8) || "—"),
              el("td", {}, s.patientName || "—"),
              el("td", {}, money(s.total)),
              el("td", {}, fmtDate(toDate(s.createdAt))),
            ]))),
          ]))
        : el("p", { class: "mt-3 text-sm text-soft" }, "No sales recorded yet."),
    ]);

    root.replaceChildren(grid, el("div", { class: "grid gap-5 lg:grid-cols-3" }, [recentCard, topCard]));
  }

  const offDrugs = watch(ctx.pharmacyId, "drugs", { onData: (d) => { state.drugs = d; draw(); }, onError: () => { state.drugs = []; draw(); } });
  const offSales = watch(ctx.pharmacyId, "sales", { onData: (d) => { state.sales = d; draw(); }, onError: () => { state.sales = []; draw(); } });

  return () => { offDrugs(); offSales(); };
}
