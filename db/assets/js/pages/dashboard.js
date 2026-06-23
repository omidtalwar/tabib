/**
 * Dashboard — KPI cards, 14-day revenue, top drugs, recent sales. Real-time.
 * Layout is real; the numbers bind to live aggregates once REFERENCE.md confirms
 * sale + drug field shapes (revenue field, units sold, stock/expiry fields).
 */
import { el } from "../ui.js";

const KPIS = [
  ["Today's revenue", "today"],
  ["This month", "month"],
  ["Low stock", "low"],
  ["Expiring ≤30d", "expiring"],
  ["Out of stock", "out"],
  ["Total drugs", "total"],
];

export default function render(outlet) {
  const grid = el("div", { class: "grid grid-cols-2 gap-4 lg:grid-cols-6" },
    KPIS.map(([label]) =>
      el("div", { class: "kpi" }, [
        el("span", { class: "kpi-value text-soft" }, "—"),
        el("span", { class: "kpi-label" }, label),
      ])
    )
  );

  const lower = el("div", { class: "grid gap-5 lg:grid-cols-3" }, [
    el("div", { class: "card lg:col-span-2" }, [
      el("p", { class: "font-semibold text-ink" }, "Revenue — last 14 days"),
      el("div", { class: "mt-3 grid h-40 place-items-center rounded-xl bg-line/40 text-sm text-soft" },
        "Bar chart binds after REFERENCE.md (sale total + date fields)"),
    ]),
    el("div", { class: "card" }, [
      el("p", { class: "font-semibold text-ink" }, "Top 5 drugs this month"),
      el("p", { class: "mt-3 text-sm text-soft" }, "Ranked by units sold — pending sale line-item fields."),
    ]),
  ]);

  const recent = el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, "Recent sales"),
    el("p", { class: "mt-3 text-sm text-soft" }, "Last 10 sales via onSnapshot — pending sale field shapes."),
  ]);

  outlet.append(el("div", { class: "space-y-5" }, [grid, lower, recent]));
}
