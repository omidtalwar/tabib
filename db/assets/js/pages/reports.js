/** Reports — Sales / Inventory / Expiry / Financial / Customers, with date-range
 * filter, KPIs, tables, CSV export and print. Client-side aggregation. */
import { readAll, toDate } from "../repo.js";
import { el, table, money, fmtDate, daysUntil, badge, loading, filterSelect, shamsiDate, downloadCSV, printContent, barChart, esc } from "../ui.js";
import { t } from "../i18n.js";

const TABS = ["sales", "inventory", "expiry", "purchases", "financial", "customers"];

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let drugs = null, sales = null, expenses = null;
  let tab = "sales", preset = "month", expiryWindow = 30;
  let fromPick = null, toPick = null;
  const current = {};

  const root = el("div", { class: "space-y-5" }, loading());
  outlet.append(root);

  Promise.all([readAll(pid, "drugs"), readAll(pid, "sales"), readAll(pid, "expenses")])
    .then(([d, s, e]) => { drugs = d; sales = s; expenses = e; paint(); })
    .catch(() => { drugs = []; sales = []; expenses = []; paint(); });

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
      const f = fromPick && fromPick.value(); const tv = toPick && toPick.value();
      from = f ? new Date(f) : new Date(2000, 0, 1);
      to = tv ? new Date(new Date(tv).setHours(23, 59, 59)) : to;
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

  function kpiRow(items) {
    return el("div", { class: "grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" },
      items.map(([label, value]) => el("div", { class: "kpi" }, [
        el("span", { class: "kpi-value" }, value), el("span", { class: "kpi-label" }, label),
      ])));
  }
  function sectionCard(title, node) {
    return el("div", { class: "card" }, [el("p", { class: "mb-3 font-semibold text-ink" }, title), node]);
  }

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
    const kpis = [[t("rep.grossSales"), money(gross)], [t("rep.netProfit"), money(profit)], [t("rep.margin"), margin], [t("rep.sales"), String(rs.length)], [t("rep.itemsSold"), String(units)]];
    const days = Object.keys(day).sort().map((k) => day[k]).slice(-31);
    const prodRows = Object.entries(prod).sort((a, b) => b[1].rev - a[1].rev);

    current.title = t("rep.title") + " — " + t("rep.tabSales"); current.kpis = kpis;
    current.csv = [[t("rep.product"), t("rep.qty"), t("rep.revenue"), t("rep.profit")], ...prodRows.map(([n, v]) => [n, v.qty, v.rev.toFixed(2), v.profit.toFixed(2)])];

    const prodTable = table([
      { label: t("rep.product"), render: (r) => r[0] },
      { label: t("rep.qty"), render: (r) => String(r[1].qty) },
      { label: t("rep.revenue"), render: (r) => money(r[1].rev) },
      { label: t("rep.profit"), render: (r) => money(r[1].profit) },
    ], prodRows, { empty: t("rep.noSalesRange") });

    const breakdown = (title, obj) => sectionCard(title, table(
      [{ label: title, render: (r) => r[0] }, { label: t("rep.revenue"), render: (r) => money(r[1]) }],
      Object.entries(obj).sort((a, b) => b[1] - a[1]), { empty: "—" }));

    return el("div", { class: "space-y-5" }, [
      kpiRow(kpis),
      days.length ? sectionCard(t("rep.salesOverTime"), barChart(days)) : null,
      sectionCard(t("rep.byProduct"), prodTable),
      el("div", { class: "grid gap-5 lg:grid-cols-3" }, [
        breakdown(t("rep.byCategory"), Object.fromEntries(Object.entries(cat).map(([k, v]) => [k, v.rev]))),
        breakdown(t("rep.byPayment"), pay),
        breakdown(t("rep.byCashier"), user),
      ]),
    ]);
  }

  function inventoryTab() {
    const active = drugs.filter((d) => d.isActive !== false);
    let units = 0, costVal = 0;
    for (const d of active) { const q = num(d.stockQuantity); units += q; costVal += q * num(d.unitPrice); }
    const low = active.filter((d) => num(d.stockQuantity) > 0 && num(d.stockQuantity) <= num(d.reorderThreshold));
    const out = active.filter((d) => num(d.stockQuantity) <= 0);
    const sold = {};
    for (const s of rangeSales()) for (const it of parseItems(s)) sold[it.drugId] = (sold[it.drugId] || 0) + num(it.quantity);
    const dead = active.filter((d) => !(sold[d.firestoreId || d.id]));

    const kpis = [[t("rep.skus"), String(active.length)], [t("rep.units"), String(units)], [t("rep.stockValueCost"), money(costVal)], [t("rep.lowStock"), String(low.length)], [t("rep.outOfStock"), String(out.length)]];
    current.title = t("rep.title") + " — " + t("rep.tabInventory"); current.kpis = kpis;
    current.csv = [[t("drugs.colName"), t("rep.category"), t("drugs.colStock"), t("inv.colReorder"), t("rep.stockValueCost")],
      ...active.map((d) => [d.name, d.category || "", d.stockQuantity ?? 0, d.reorderThreshold ?? 0, (num(d.stockQuantity) * num(d.unitPrice)).toFixed(2)])];

    const stockTable = (rows, empty) => table([
      { label: t("drugs.colName"), render: (d) => d.name || "—" },
      { label: t("rep.category"), render: (d) => d.category || "—" },
      { label: t("drugs.colStock"), render: (d) => String(d.stockQuantity ?? 0) },
      { label: t("rep.stockValueCost"), render: (d) => money(num(d.stockQuantity) * num(d.unitPrice)) },
    ], rows, { empty });

    return el("div", { class: "space-y-5" }, [
      kpiRow(kpis),
      sectionCard(t("rep.lowStock"), stockTable(low, t("inv.nothingLow"))),
      sectionCard(t("rep.outOfStock"), stockTable(out, t("inv.nothingOut"))),
      sectionCard(t("rep.deadStock"), stockTable(dead, t("rep.everythingMoved"))),
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

    const kpis = [[t("rep.expiringSoon"), String(expiring.length)], [t("status.expired"), String(expired.length)], [t("rep.expiringValue"), money(expiringVal)], [t("rep.expiredLoss"), money(expiredVal)]];
    current.title = t("rep.title") + " — " + t("rep.tabExpiry"); current.kpis = kpis;
    current.csv = [[t("drugs.colName"), t("rep.batch"), t("drugs.colExpiry"), t("rep.days"), t("drugs.colStock"), t("rep.lossValue")],
      ...[...expiring, ...expired].map((x) => [x.d.name, x.d.batchNumber || "", fmtDate(toDate(x.d.expiryDate)), x.n, x.d.stockQuantity ?? 0, val(x.d).toFixed(2)])];

    const win = filterSelect([30, 60, 90].map((w) => ({ value: String(w), label: t("rep.within", { n: w }) })), String(expiryWindow), (v) => { expiryWindow = +v; paint(); }, t("rep.window"));
    const exTable = (rows, empty) => table([
      { label: t("drugs.colName"), render: (x) => x.d.name || "—" },
      { label: t("rep.batch"), render: (x) => x.d.batchNumber || "—" },
      { label: t("drugs.colExpiry"), render: (x) => fmtDate(toDate(x.d.expiryDate)) },
      { label: t("rep.days"), html: true, render: (x) => x.n < 0 ? badge(t("status.expired"), "danger") : badge(`${x.n}d`, x.n <= 30 ? "warn" : "muted") },
      { label: t("drugs.colStock"), render: (x) => String(x.d.stockQuantity ?? 0) },
      { label: t("rep.lossValue"), render: (x) => money(val(x.d)) },
    ], rows, { empty });

    return el("div", { class: "space-y-5" }, [
      el("div", { class: "flex items-center gap-2" }, [el("span", { class: "text-sm text-soft" }, t("rep.window")), win]),
      kpiRow(kpis),
      sectionCard(t("rep.expiringSoon"), exTable(expiring, t("inv.nothingExpiring"))),
      sectionCard(t("rep.alreadyExpired"), exTable(expired, t("inv.nothingExpired"))),
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

    const kpis = [[t("rep.revenue"), money(revenue)], [t("rep.grossProfit"), money(gross)], [t("rep.expenses"), money(totalExp)], [t("rep.netProfit"), money(net)], [t("rep.netMargin"), margin]];
    current.title = t("rep.title") + " — " + t("rep.tabFinancial"); current.kpis = kpis;
    current.csv = [[t("rep.metric"), t("rep.amount")], [t("rep.revenue"), revenue.toFixed(2)], [t("rep.cogs"), cogs.toFixed(2)], [t("rep.grossProfit"), gross.toFixed(2)], [t("rep.expenses"), totalExp.toFixed(2)], [t("rep.netProfit"), net.toFixed(2)], [t("rep.receivables"), receivables.toFixed(2)], [t("rep.cashSalesIn"), cashSales.toFixed(2)], [t("rep.cashExpOut"), cashExp.toFixed(2)], [t("rep.netCash"), netCash.toFixed(2)]];

    const pl = table([{ label: t("rep.metric"), render: (r) => r[0] }, { label: t("rep.amount"), render: (r) => money(r[1]) }],
      [[t("rep.revenue"), revenue], [t("rep.cogs"), cogs], [t("rep.grossProfit"), gross], [t("rep.expenses"), -totalExp], [t("rep.netProfit"), net], [t("rep.receivables"), receivables]], {});
    const catRows = Object.entries(expByCat).sort((a, b) => b[1] - a[1]);
    const expTable = table([{ label: t("rep.category"), render: (r) => r[0] }, { label: t("rep.amount"), render: (r) => money(r[1]) }], catRows, { empty: t("rep.noSalesRange") });
    const cashTable = table([{ label: t("rep.cashRecon"), render: (r) => r[0] }, { label: t("rep.amount"), render: (r) => money(r[1]) }],
      [[t("rep.cashSalesIn"), cashSales], [t("rep.cashExpOut"), cashExp], [t("rep.netCash"), netCash]], {});

    return el("div", { class: "space-y-5" }, [
      kpiRow(kpis),
      sectionCard(t("rep.pl"), pl),
      el("div", { class: "grid gap-5 lg:grid-cols-2" }, [sectionCard(t("rep.expByCategory"), expTable), sectionCard(t("rep.cashRecon"), cashTable)]),
      el("div", { class: "rounded-xl border border-line bg-white p-4 text-xs text-soft" }, t("rep.cashNote")),
    ]);
  }

  function customersTab() {
    const rs = rangeSales();
    const by = {};
    for (const s of rs) {
      const name = s.patientName || t("dash.walkIn");
      by[name] = by[name] || { count: 0, spent: 0, credit: 0 };
      by[name].count += 1; by[name].spent += num(s.total);
      if ((s.paymentMethod || "") === "credit") by[name].credit += num(s.total);
    }
    const rows = Object.entries(by).sort((a, b) => b[1].spent - a[1].spent);
    const kpis = [[t("rep.customers"), String(rows.length)], [t("rep.sales"), String(rs.length)], [t("rep.revenue"), money(rows.reduce((a, r) => a + r[1].spent, 0))], [t("rep.receivables"), money(rows.reduce((a, r) => a + r[1].credit, 0))]];
    current.title = t("rep.title") + " — " + t("rep.tabCustomers"); current.kpis = kpis;
    current.csv = [[t("rep.customer"), t("rep.purchasesCount"), t("rep.totalSpent"), t("rep.credit")], ...rows.map(([n, v]) => [n, v.count, v.spent.toFixed(2), v.credit.toFixed(2)])];

    return el("div", { class: "space-y-5" }, [
      kpiRow(kpis),
      sectionCard(t("rep.topCustomers"), table([
        { label: t("rep.customer"), render: (r) => r[0] },
        { label: t("rep.purchasesCount"), render: (r) => String(r[1].count) },
        { label: t("rep.totalSpent"), render: (r) => money(r[1].spent) },
        { label: t("rep.credit"), render: (r) => money(r[1].credit) },
      ], rows, { empty: t("rep.noCustomerSales") })),
    ]);
  }

  function purchasesTab() {
    current.title = t("rep.tabPurchases"); current.kpis = []; current.csv = [];
    return el("div", { class: "card" }, [
      el("p", { class: "font-semibold text-ink" }, t("rep.purchases")),
      el("p", { class: "mt-1 text-sm text-soft" }, t("rep.purchasesNote")),
    ]);
  }

  const TAB_FNS = { sales: salesTab, inventory: inventoryTab, expiry: expiryTab, purchases: purchasesTab, financial: financialTab, customers: customersTab };
  const TAB_LABEL = { sales: "rep.tabSales", inventory: "rep.tabInventory", expiry: "rep.tabExpiry", purchases: "rep.tabPurchases", financial: "rep.tabFinancial", customers: "rep.tabCustomers" };
  const PRESETS = [
    { value: "today", label: t("rep.presetToday") }, { value: "week", label: t("rep.presetWeek") },
    { value: "month", label: t("rep.presetMonth") }, { value: "lastmonth", label: t("rep.presetLastMonth") },
    { value: "year", label: t("rep.presetYear") }, { value: "all", label: t("rep.presetAll") },
    { value: "custom", label: t("rep.presetCustom") },
  ];

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

  function header() {
    const presetSel = filterSelect(PRESETS, preset, (v) => { preset = v; paint(); }, t("rep.title"));
    const controls = [presetSel];
    if (preset === "custom") {
      fromPick = shamsiDate(fromPick && fromPick.value());
      toPick = shamsiDate(toPick && toPick.value());
      controls.push(el("div", { class: "flex flex-wrap items-center gap-2" }, [
        el("span", { class: "text-xs text-soft" }, t("rep.from")), fromPick.node,
        el("span", { class: "text-xs text-soft" }, t("rep.to")), toPick.node,
        el("button", { class: "btn-ghost", onclick: () => paint() }, t("common.apply")),
      ]));
    }
    const exportBtn = el("button", { class: "btn-ghost", onclick: doExport }, [
      el("span", { html: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>' }), t("common.export"),
    ]);
    const printBtn = el("button", { class: "btn-ghost", onclick: doPrint }, [
      el("span", { html: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6M6 18H4v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5h-2M8 14h8v7H8z"/></svg>' }), t("common.print"),
    ]);
    return el("div", { class: "flex flex-wrap items-center justify-between gap-3" }, [
      el("h2", { class: "text-lg font-bold text-ink" }, t("rep.title")),
      el("div", { class: "flex flex-wrap items-center gap-2" }, [...controls, exportBtn, printBtn]),
    ]);
  }
  function tabBar() {
    return el("div", { class: "flex flex-wrap gap-1 border-b border-line" }, TABS.map((key) => el("button", {
      class: "rounded-t-lg px-3 py-2 text-sm font-semibold transition " + (tab === key ? "border-b-2 border-brand-500 text-brand-700" : "text-soft hover:text-ink"),
      onclick: () => { tab = key; paint(); },
    }, t(TAB_LABEL[key]))));
  }

  function paint() {
    if (!drugs || !sales || !expenses) return;
    root.replaceChildren(header(), tabBar(), TAB_FNS[tab]());
  }
}
