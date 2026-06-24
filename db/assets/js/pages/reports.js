/**
 * Reports — Sales / Inventory / Expiry / Financial / Customers, each with a
 * date-range filter, KPI summary, detail table, CSV export and print.
 * Aggregation is client-side over the pharmacy's drugs + sales (fine for a
 * single pharmacy; server-side aggregation would be added for very large data).
 * Purchases/payables/cash-reconciliation need data the app doesn't capture yet.
 */
import { readAll, toDate } from "../repo.js";
import { el, table, money, fmtDate, daysUntil, badge, loading, toolbar, filterSelect, shamsiDate, downloadCSV, printContent, barChart, esc } from "../ui.js";

const TABS = [
  ["sales", "Sales"], ["inventory", "Inventory"], ["expiry", "Expiry"],
  ["purchases", "Purchases"], ["financial", "Financial"], ["customers", "Customers"],
];
const PRESETS = [
  { value: "today", label: "Today" }, { value: "week", label: "Last 7 days" },
  { value: "month", label: "This month" }, { value: "lastmonth", label: "Last month" },
  { value: "year", label: "This year" }, { value: "all", label: "All time" },
  { value: "custom", label: "Custom range" },
];

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let drugs = null, sales = null, expenses = null;
  let tab = "sales", preset = "month", expiryWindow = 30;
  let fromPick = null, toPick = null; // shamsiDate pickers when custom
  const current = {}; // { title, kpis:[[label,value]], csv:[[...]] } for export/print

  const root = el("div", { class: "space-y-5" }, loading("Loading reports…"));
  outlet.append(root);

  Promise.all([readAll(pid, "drugs"), readAll(pid, "sales"), readAll(pid, "expenses")])
    .then(([d, s, e]) => { drugs = d; sales = s; expenses = e; paint(); })
    .catch(() => { drugs = []; sales = []; expenses = []; paint(); });

  /* ---------- date range ---------- */
  function range() {
    const now = new Date();
    const sod = (dt) => new Date(dt.getFullYear(), dt.getMonth(), dt.getDate());
    let from, to = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59);
    if (preset === "today") from = sod(now);
    else if (preset === "week") { from = sod(now); from.setDate(from.getDate() - 6); }
    else if (preset === "month") from = new Date(now.getFullYear(), now.getMonth(), 1);
    else if (preset === "lastmonth") { from = new Date(now.getFullYear(), now.getMonth() - 1, 1); to = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59); }
    else if (preset === "year") from = new Date(now.getFullYear(), 0, 1);
    else if (preset === "custom") {
      const f = fromPick && fromPick.value(); const t = toPick && toPick.value();
      from = f ? new Date(f) : new Date(2000, 0, 1);
      to = t ? new Date(new Date(t).setHours(23, 59, 59)) : to;
    } else from = new Date(2000, 0, 1);
    return { from, to };
  }
  const inRange = (s) => { const d = toDate(s.createdAt); if (!d) return false; const { from, to } = range(); return d >= from && d <= to; };
  const rangeSales = () => sales.filter(inRange);
  const inRangeE = (e) => { const d = toDate(e.date); if (!d) return false; const { from, to } = range(); return d >= from && d <= to; };
  const rangeExpenses = () => expenses.filter((e) => e.isActive !== false && inRangeE(e));
  const parseItems = (s) => { try { return JSON.parse(s.itemsJson || "[]"); } catch { return []; } };
  const drugMap = () => Object.fromEntries(drugs.map((d) => [d.firestoreId || d.id, d]));
  const num = (x) => Number(x) || 0;

  /* ---------- shared UI ---------- */
  function kpiRow(items) {
    return el("div", { class: "grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" },
      items.map(([label, value]) => el("div", { class: "kpi" }, [
        el("span", { class: "kpi-value" }, value), el("span", { class: "kpi-label" }, label),
      ])));
  }
  function sectionCard(title, node) {
    return el("div", { class: "card" }, [el("p", { class: "mb-3 font-semibold text-ink" }, title), node]);
  }

  /* ---------- TABS ---------- */
  function salesTab() {
    const rs = rangeSales(), dm = drugMap();
    let gross = 0, profit = 0, units = 0;
    const prod = {}, cat = {}, pay = {}, user = {}, day = {};
    for (const s of rs) {
      const tot = num(s.total); gross += tot;
      pay[s.paymentMethod || "—"] = (pay[s.paymentMethod || "—"] || 0) + tot;
      user[s.staffName || "—"] = (user[s.staffName || "—"] || 0) + tot;
      const d = toDate(s.createdAt); const key = d ? d.toISOString().slice(0, 10) : "—";
      day[key] = day[key] || { label: fmtDate(d), value: 0 }; day[key].value += tot;
      for (const it of parseItems(s)) {
        const q = num(it.quantity), rev = num(it.subtotal) || num(it.unitPrice) * q; units += q;
        const dr = dm[it.drugId]; const cost = (dr ? num(dr.unitPrice) : 0) * q; profit += rev - cost;
        const pn = it.drugName || "—"; prod[pn] = prod[pn] || { qty: 0, rev: 0, profit: 0 };
        prod[pn].qty += q; prod[pn].rev += rev; prod[pn].profit += rev - cost;
        const cn = dr ? dr.category || "Other" : "Other"; cat[cn] = cat[cn] || { qty: 0, rev: 0 };
        cat[cn].qty += q; cat[cn].rev += rev;
      }
    }
    const margin = gross ? Math.round((profit / gross) * 100) + "%" : "—";
    const kpis = [["Gross sales", money(gross)], ["Net profit", money(profit)], ["Margin", margin], ["Sales", String(rs.length)], ["Items sold", String(units)]];

    const days = Object.keys(day).sort().map((k) => day[k]).slice(-31);
    const prodRows = Object.entries(prod).sort((a, b) => b[1].rev - a[1].rev);

    current.title = "Sales report";
    current.kpis = kpis;
    current.csv = [["Product", "Qty sold", "Revenue", "Profit"], ...prodRows.map(([n, v]) => [n, v.qty, v.rev.toFixed(2), v.profit.toFixed(2)])];

    const prodTable = table([
      { label: "Product", render: (r) => r[0] },
      { label: "Qty", render: (r) => String(r[1].qty) },
      { label: "Revenue", render: (r) => money(r[1].rev) },
      { label: "Profit", render: (r) => money(r[1].profit) },
    ], prodRows, { empty: "No sales in this range" });

    const breakdown = (title, obj) => sectionCard(title, table(
      [{ label: title, render: (r) => r[0] }, { label: "Revenue", render: (r) => money(r[1]) }],
      Object.entries(obj).sort((a, b) => b[1] - a[1]), { empty: "—" }));

    return el("div", { class: "space-y-5" }, [
      kpiRow(kpis),
      days.length ? sectionCard("Sales over time", barChart(days)) : null,
      sectionCard("By product", prodTable),
      el("div", { class: "grid gap-5 lg:grid-cols-3" }, [
        breakdown("By category", Object.fromEntries(Object.entries(cat).map(([k, v]) => [k, v.rev]))),
        breakdown("By payment method", pay),
        breakdown("By cashier", user),
      ]),
    ]);
  }

  function inventoryTab() {
    const active = drugs.filter((d) => d.isActive !== false);
    let units = 0, costVal = 0, retailVal = 0;
    for (const d of active) { const q = num(d.stockQuantity); units += q; costVal += q * num(d.unitPrice); retailVal += q * num(d.sellingPrice); }
    const low = active.filter((d) => num(d.stockQuantity) > 0 && num(d.stockQuantity) <= num(d.reorderThreshold));
    const out = active.filter((d) => num(d.stockQuantity) <= 0);

    // Dead stock = active drugs with no units sold in the selected range.
    const sold = {};
    for (const s of rangeSales()) for (const it of parseItems(s)) sold[it.drugId] = (sold[it.drugId] || 0) + num(it.quantity);
    const dead = active.filter((d) => !(sold[d.firestoreId || d.id]));

    const kpis = [["SKUs", String(active.length)], ["Units", String(units)], ["Stock value (cost)", money(costVal)], ["Low stock", String(low.length)], ["Out of stock", String(out.length)]];
    current.title = "Inventory report"; current.kpis = kpis;
    current.csv = [["Drug", "Category", "Stock", "Reorder ≤", "Cost value", "Retail value"],
      ...active.map((d) => [d.name, d.category || "", d.stockQuantity ?? 0, d.reorderThreshold ?? 0, (num(d.stockQuantity) * num(d.unitPrice)).toFixed(2), (num(d.stockQuantity) * num(d.sellingPrice)).toFixed(2)])];

    const stockTable = (rows, empty) => table([
      { label: "Drug", render: (d) => d.name || "—" },
      { label: "Category", render: (d) => d.category || "—" },
      { label: "Stock", render: (d) => String(d.stockQuantity ?? 0) },
      { label: "Cost value", render: (d) => money(num(d.stockQuantity) * num(d.unitPrice)) },
    ], rows, { empty });

    return el("div", { class: "space-y-5" }, [
      kpiRow(kpis),
      sectionCard("Low stock (reorder)", stockTable(low, "Nothing low.")),
      sectionCard("Out of stock", stockTable(out, "Nothing out of stock.")),
      sectionCard(`Dead stock (no sales in range)`, stockTable(dead, "Everything moved in this range.")),
    ]);
  }

  function expiryTab() {
    const active = drugs.filter((d) => d.isActive !== false);
    const withDays = active.map((d) => ({ d, n: daysUntil(toDate(d.expiryDate)) })).filter((x) => x.n != null);
    const expiring = withDays.filter((x) => x.n >= 0 && x.n <= expiryWindow).sort((a, b) => a.n - b.n);
    const expired = withDays.filter((x) => x.n < 0).sort((a, b) => a.n - b.n);
    const val = (d) => num(d.stockQuantity) * num(d.unitPrice);
    const expiringVal = expiring.reduce((a, x) => a + val(x.d), 0);
    const expiredVal = expired.reduce((a, x) => a + val(x.d), 0);

    const kpis = [[`Expiring ≤${expiryWindow}d`, String(expiring.length)], ["Expired", String(expired.length)], ["Expiring value", money(expiringVal)], ["Expired loss", money(expiredVal)]];
    current.title = "Expiry report"; current.kpis = kpis;
    current.csv = [["Drug", "Batch", "Expiry (Shamsi)", "Days", "Stock", "Loss value"],
      ...[...expiring, ...expired].map((x) => [x.d.name, x.d.batchNumber || "", fmtDate(toDate(x.d.expiryDate)), x.n, x.d.stockQuantity ?? 0, val(x.d).toFixed(2)])];

    const win = filterSelect([30, 60, 90].map((w) => ({ value: String(w), label: `Within ${w} days` })), String(expiryWindow), (v) => { expiryWindow = +v; paint(); }, "Expiry window");
    const exTable = (rows, empty) => table([
      { label: "Drug", render: (x) => x.d.name || "—" },
      { label: "Batch", render: (x) => x.d.batchNumber || "—" },
      { label: "Expiry", render: (x) => fmtDate(toDate(x.d.expiryDate)) },
      { label: "Days", html: true, render: (x) => x.n < 0 ? badge("Expired", "danger") : badge(`${x.n}d`, x.n <= 30 ? "warn" : "muted") },
      { label: "Stock", render: (x) => String(x.d.stockQuantity ?? 0) },
      { label: "Loss value", render: (x) => money(val(x.d)) },
    ], rows, { empty });

    return el("div", { class: "space-y-5" }, [
      el("div", { class: "flex items-center gap-2" }, [el("span", { class: "text-sm text-soft" }, "Window:"), win]),
      kpiRow(kpis),
      sectionCard("Expiring soon", exTable(expiring, "Nothing expiring in this window.")),
      sectionCard("Already expired (remove from shelf)", exTable(expired, "Nothing expired.")),
    ]);
  }

  function financialTab() {
    const rs = rangeSales(), dm = drugMap();
    let revenue = 0, cogs = 0, receivables = 0, cashSales = 0;
    for (const s of rs) {
      revenue += num(s.total);
      if ((s.paymentMethod || "") === "credit") receivables += num(s.total);
      if ((s.paymentMethod || "") === "cash") cashSales += num(s.total);
      for (const it of parseItems(s)) { const dr = dm[it.drugId]; cogs += (dr ? num(dr.unitPrice) : 0) * num(it.quantity); }
    }
    const gross = revenue - cogs;
    const exps = rangeExpenses();
    let totalExp = 0, cashExp = 0; const expByCat = {};
    for (const e of exps) { const a = num(e.amount); totalExp += a; if ((e.paymentMethod || "") === "cash") cashExp += a; expByCat[e.category || "Miscellaneous"] = (expByCat[e.category || "Miscellaneous"] || 0) + a; }
    const net = gross - totalExp;
    const margin = revenue ? Math.round((net / revenue) * 100) + "%" : "—";
    const netCash = cashSales - cashExp;

    const kpis = [["Revenue", money(revenue)], ["Gross profit", money(gross)], ["Expenses", money(totalExp)], ["Net profit", money(net)], ["Net margin", margin]];
    current.title = "Financial report (P&L)"; current.kpis = kpis;
    current.csv = [["Metric", "Amount"], ["Revenue", revenue.toFixed(2)], ["Cost of goods sold", cogs.toFixed(2)], ["Gross profit", gross.toFixed(2)], ["Total expenses", totalExp.toFixed(2)], ["Net profit", net.toFixed(2)], ["Outstanding credit (receivables)", receivables.toFixed(2)], ["Cash sales", cashSales.toFixed(2)], ["Cash expenses", cashExp.toFixed(2)], ["Net cash movement", netCash.toFixed(2)]];

    const pl = table([{ label: "Metric", render: (r) => r[0] }, { label: "Amount", render: (r) => money(r[1]) }],
      [["Revenue", revenue], ["Cost of goods sold", cogs], ["Gross profit", gross], ["Total expenses", -totalExp], ["Net profit", net], ["Outstanding credit", receivables]], {});
    const catRows = Object.entries(expByCat).sort((a, b) => b[1] - a[1]);
    const expTable = table([{ label: "Category", render: (r) => r[0] }, { label: "Amount", render: (r) => money(r[1]) }], catRows, { empty: "No expenses in range." });
    const cashTable = table([{ label: "Cash drawer", render: (r) => r[0] }, { label: "Amount", render: (r) => money(r[1]) }],
      [["Cash sales (in)", cashSales], ["Cash expenses (out)", cashExp], ["Net cash movement", netCash]], {});

    return el("div", { class: "space-y-5" }, [
      kpiRow(kpis),
      sectionCard("Profit & Loss", pl),
      el("div", { class: "grid gap-5 lg:grid-cols-2" }, [sectionCard("Expenses by category", expTable), sectionCard("Cash reconciliation", cashTable)]),
      el("div", { class: "rounded-xl border border-line bg-white p-4 text-xs text-soft" }, "Cash reconciliation excludes opening balance and refunds (not tracked yet). Net cash movement = cash sales − cash expenses."),
    ]);
  }

  function customersTab() {
    const rs = rangeSales();
    const by = {};
    for (const s of rs) {
      const name = s.patientName || "Walk-in";
      by[name] = by[name] || { count: 0, spent: 0, credit: 0 };
      by[name].count += 1; by[name].spent += num(s.total);
      if ((s.paymentMethod || "") === "credit") by[name].credit += num(s.total);
    }
    const rows = Object.entries(by).sort((a, b) => b[1].spent - a[1].spent);
    const kpis = [["Customers", String(rows.length)], ["Sales", String(rs.length)], ["Revenue", money(rows.reduce((a, r) => a + r[1].spent, 0))], ["Outstanding credit", money(rows.reduce((a, r) => a + r[1].credit, 0))]];
    current.title = "Customers report"; current.kpis = kpis;
    current.csv = [["Customer", "Purchases", "Total spent", "Outstanding credit"], ...rows.map(([n, v]) => [n, v.count, v.spent.toFixed(2), v.credit.toFixed(2)])];

    return el("div", { class: "space-y-5" }, [
      kpiRow(kpis),
      sectionCard("Top customers", table([
        { label: "Customer", render: (r) => r[0] },
        { label: "Purchases", render: (r) => String(r[1].count) },
        { label: "Total spent", render: (r) => money(r[1].spent) },
        { label: "Credit", render: (r) => money(r[1].credit) },
      ], rows, { empty: "No customer sales in this range." })),
    ]);
  }

  function purchasesTab() {
    current.title = "Purchases"; current.kpis = []; current.csv = [];
    return el("div", { class: "card" }, [
      el("p", { class: "font-semibold text-ink" }, "Purchases & supplier payables"),
      el("p", { class: "mt-1 text-sm text-soft" }, "This needs purchase-order and supplier-payment data the app doesn't capture yet. Once purchase recording is added (received stock, supplier invoices, payments), this report will show purchase history by supplier, per-product purchase trends, and a payables ledger."),
    ]);
  }

  const TAB_FNS = { sales: salesTab, inventory: inventoryTab, expiry: expiryTab, purchases: purchasesTab, financial: financialTab, customers: customersTab };

  /* ---------- export / print ---------- */
  function doExport() {
    if (!current.csv || current.csv.length <= 1) return;
    downloadCSV(`${(current.title || "report").toLowerCase().replace(/\s+/g, "-")}.csv`, current.csv);
  }
  function doPrint() {
    const { from, to } = range();
    const sub = `${fmtDate(from)} — ${fmtDate(to)}`;
    const kpiHtml = '<div class="kpis">' + (current.kpis || []).map(([l, v]) => `<span class="kpi"><b>${esc(v)}</b><span>${esc(l)}</span></span>`).join("") + "</div>";
    const rows = current.csv || [];
    const thead = rows[0] ? `<tr>${rows[0].map((h) => `<th>${esc(h)}</th>`).join("")}</tr>` : "";
    const tbody = rows.slice(1).map((r) => `<tr>${r.map((c) => `<td>${esc(c)}</td>`).join("")}</tr>`).join("");
    printContent(current.title || "Report", `<h1>${esc(current.title || "Report")}</h1><div class="sub">${esc(sub)}</div>${kpiHtml}<table>${thead}${tbody}</table>`);
  }

  /* ---------- shell ---------- */
  function header() {
    const presetSel = filterSelect(PRESETS, preset, (v) => { preset = v; paint(); }, "Date range");
    const controls = [presetSel];
    if (preset === "custom") {
      fromPick = shamsiDate(fromPick && fromPick.value());
      toPick = shamsiDate(toPick && toPick.value());
      controls.push(el("div", { class: "flex flex-wrap items-center gap-2" }, [
        el("span", { class: "text-xs text-soft" }, "From"), fromPick.node,
        el("span", { class: "text-xs text-soft" }, "To"), toPick.node,
        el("button", { class: "btn-ghost", onclick: () => paint() }, "Apply"),
      ]));
    }
    const exportBtn = el("button", { class: "btn-ghost", onclick: doExport }, [
      el("span", { html: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>' }), "CSV",
    ]);
    const printBtn = el("button", { class: "btn-ghost", onclick: doPrint }, [
      el("span", { html: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5h-2M8 14h8v7H8z"/></svg>' }), "Print / PDF",
    ]);
    return el("div", { class: "flex flex-wrap items-center justify-between gap-3" }, [
      el("h2", { class: "text-lg font-bold text-ink" }, "Reports"),
      el("div", { class: "flex flex-wrap items-center gap-2" }, [...controls, exportBtn, printBtn]),
    ]);
  }
  function tabBar() {
    return el("div", { class: "flex flex-wrap gap-1 border-b border-line" }, TABS.map(([key, label]) => {
      const b = el("button", {
        class: "rounded-t-lg px-3 py-2 text-sm font-semibold transition " + (tab === key ? "border-b-2 border-brand-500 text-brand-700" : "text-soft hover:text-ink"),
        onclick: () => { tab = key; paint(); },
      }, label);
      return b;
    }));
  }

  function paint() {
    if (!drugs || !sales) return;
    const body = TAB_FNS[tab]();
    root.replaceChildren(header(), tabBar(), body);
  }
}
