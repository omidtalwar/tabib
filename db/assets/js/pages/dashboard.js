/**
 * Dashboard — "what needs my attention today?" Live (onSnapshot) over drugs,
 * sales and expenses: greeting + summary, KPI cards with day-over-day trend +
 * sparklines, a clickable alert strip, two charts, three lists, quick actions.
 * Every card/alert deep-links to the relevant page.
 */
import { watch, toDate } from "../repo.js";
import { el, money, fmtDate, daysUntil, sparkline, barChart, loading } from "../ui.js";
import { t } from "../i18n.js";

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  const state = { drugs: null, sales: null, expenses: null, returns: null, payments: null };
  const go = (page) => { location.hash = `#/p/${pid}/${page}`; };

  const root = el("div", { class: "space-y-5" }, loading());
  outlet.append(root);

  /* ---------- helpers ---------- */
  const num = (x) => Number(x) || 0;
  const items = (s) => { try { return JSON.parse(s.itemsJson || "[]"); } catch { return []; } };
  const startOf = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
  const drugMap = () => Object.fromEntries((state.drugs || []).map((d) => [d.firestoreId || d.id, d]));

  function salesStats(list, dm) {
    let revenue = 0, profit = 0, cash = 0;
    for (const s of list) {
      revenue += num(s.total);
      if ((s.paymentMethod || "") === "cash") cash += num(s.total);
      for (const it of items(s)) { const dr = dm[it.drugId]; profit += num(it.subtotal) - (dr ? num(dr.unitPrice) : 0) * num(it.quantity); }
    }
    return { revenue, profit, cash, count: list.length };
  }
  // Returns reverse revenue/profit/units — netted into every sales figure so
  // totals, profit and charts stay consistent after a refund.
  function returnStats(list, dm) {
    let revenue = 0, profit = 0, units = 0;
    for (const r of list) {
      revenue += num(r.total);
      for (const it of items(r)) { const q = num(it.quantity), rev = num(it.unitPrice) * q; units += q; const dr = dm[it.drugId]; profit += rev - (dr ? num(dr.unitPrice) : 0) * q; }
    }
    return { revenue, profit, units };
  }
  const pct = (now, prev) => (prev > 0 ? Math.round(((now - prev) / prev) * 100) : (now > 0 ? 100 : 0));

  function kpiCard({ label, value, change, goodWhenUp = true, spark, sparkColor }) {
    const up = change > 0, flat = change === 0;
    const good = goodWhenUp ? up : !up;
    const arrow = flat ? "" : up ? "▲" : "▼";
    const trend = change == null ? null : el("span", {
      class: "inline-flex items-center gap-1 text-xs font-semibold " + (flat ? "text-soft" : good ? "text-ok" : "text-danger"),
    }, `${arrow} ${Math.abs(change)}% ${t("dash.vsYest")}`);
    return el("div", { class: "kpi" }, [
      el("span", { class: "kpi-label" }, label),
      el("div", { class: "flex items-end justify-between gap-2" }, [
        el("span", { class: "kpi-value" }, value),
        spark ? sparkline(spark, { color: sparkColor || "#0EA59B" }) : null,
      ]),
      trend,
    ]);
  }

  function alertPill(count, label, kind, page) {
    const dot = { red: "bg-danger", orange: "bg-warn", amber: "bg-warn", purple: "bg-[#7C5CFC]", green: "bg-ok" }[kind] || "bg-soft";
    const b = el("button", {
      class: "inline-flex items-center gap-2 rounded-full border border-line bg-white px-3 py-1.5 text-sm font-semibold text-ink transition hover:border-brand-400 hover:bg-brand-50",
      onclick: () => go(page),
    }, [
      el("span", { class: `h-2.5 w-2.5 flex-none rounded-full ${dot}` }),
      el("span", { class: "tabular-nums" }, String(count)),
      el("span", { class: "text-soft" }, label),
    ]);
    return b;
  }

  function listCard(title, rows, { empty, action } = {}) {
    return el("div", { class: "card" }, [
      el("div", { class: "mb-2 flex items-center justify-between" }, [
        el("p", { class: "font-semibold text-ink" }, title),
        action ? el("button", { class: "text-xs font-semibold text-brand-700 hover:text-brand-800", onclick: action.onClick }, action.label) : null,
      ]),
      rows && rows.length ? el("div", { class: "divide-y divide-line" }, rows) : el("p", { class: "py-6 text-center text-sm text-soft" }, empty || "Nothing yet."),
    ]);
  }
  const rowEl = (left, right, sub) => el("div", { class: "flex items-center justify-between gap-3 py-2" }, [
    el("div", { class: "min-w-0" }, [el("p", { class: "truncate text-sm font-medium text-ink" }, left), sub ? el("p", { class: "truncate text-xs text-soft" }, sub) : null]),
    el("span", { class: "flex-none text-sm font-semibold text-ink" }, right),
  ]);

  function quickBtn(label, page, path, color) {
    return el("button", { class: "btn-primary", style: `background:${color}`, onclick: () => go(page) }, [
      el("span", { html: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>` }), label,
    ]);
  }

  /* ---------- draw ---------- */
  function draw() {
    if (!state.drugs || !state.sales || !state.expenses || !state.returns || !state.payments) return;
    const dm = drugMap();
    const now = new Date();
    const today0 = startOf(now);
    const yest0 = new Date(today0); yest0.setDate(yest0.getDate() - 1);
    const inToday = (s) => { const d = toDate(s.createdAt); return d && d >= today0; };
    const inYest = (s) => { const d = toDate(s.createdAt); return d && d >= yest0 && d < today0; };

    const todayS = salesStats(state.sales.filter(inToday), dm);
    const yestS = salesStats(state.sales.filter(inYest), dm);

    // Net returns out of revenue/profit so the numbers stay correct after refunds.
    const inDateR = (r, from, to) => { const d = toDate(r.date); return d && d >= from && (!to || d < to); };
    const todayRet = returnStats(state.returns.filter((r) => inDateR(r, today0)), dm);
    const yestRet = returnStats(state.returns.filter((r) => inDateR(r, yest0, today0)), dm);
    const todayRevenue = todayS.revenue - todayRet.revenue;
    const todayProfit = todayS.profit - todayRet.profit;
    const yestRevenue = yestS.revenue - yestRet.revenue;
    const yestProfit = yestS.profit - yestRet.profit;

    // Expenses today/yesterday
    const expSum = (pred) => state.expenses.filter((e) => e.isActive !== false && pred(e)).reduce((a, e) => a + num(e.amount), 0);
    const expToday = expSum((e) => { const d = toDate(e.date); return d && d >= today0; });
    const expYest = expSum((e) => { const d = toDate(e.date); return d && d >= yest0 && d < today0; });

    // Cash drawer (net): all cash sales − all cash expenses
    const cashSalesAll = state.sales.reduce((a, s) => a + ((s.paymentMethod || "") === "cash" ? num(s.total) : 0), 0);
    const cashExpAll = state.expenses.filter((e) => e.isActive !== false && (e.paymentMethod || "") === "cash").reduce((a, e) => a + num(e.amount), 0);
    const cashDrawer = cashSalesAll - cashExpAll;

    // Stock value (cost) + alerts
    const active = state.drugs.filter((d) => d.isActive !== false);
    const stockValue = active.reduce((a, d) => a + num(d.stockQuantity) * num(d.unitPrice), 0);
    const low = active.filter((d) => num(d.stockQuantity) > 0 && num(d.stockQuantity) <= num(d.reorderThreshold));
    const out = active.filter((d) => num(d.stockQuantity) <= 0);
    const withDays = active.map((d) => ({ d, n: daysUntil(toDate(d.expiryDate)) })).filter((x) => x.n != null);
    const expiring = withDays.filter((x) => x.n >= 0 && x.n <= 30).sort((a, b) => a.n - b.n);
    const expired = withDays.filter((x) => x.n < 0);
    const expWeek = withDays.filter((x) => x.n >= 0 && x.n <= 7).length;
    const creditPaid = state.payments.reduce((a, p) => a + num(p.amount), 0);
    const creditOutstanding = state.sales.reduce((a, s) => a + ((s.paymentMethod || "") === "credit" ? num(s.total) : 0), 0) - creditPaid;

    // 7-day sparklines / trend chart
    const days7 = [], chart7 = [];
    for (let i = 6; i >= 0; i--) {
      const d0 = new Date(today0); d0.setDate(d0.getDate() - i);
      const d1 = new Date(d0); d1.setDate(d1.getDate() + 1);
      const dayS = state.sales.filter((s) => { const t = toDate(s.createdAt); return t && t >= d0 && t < d1; });
      const st = salesStats(dayS, dm);
      const dr = returnStats(state.returns.filter((r) => { const t = toDate(r.date); return t && t >= d0 && t < d1; }), dm);
      days7.push({ revenue: st.revenue - dr.revenue, profit: st.profit - dr.profit });
      chart7.push({ label: fmtDate(d0).slice(5), value: Math.round(st.revenue - dr.revenue) });
    }
    const expDays7 = [];
    for (let i = 6; i >= 0; i--) {
      const d0 = new Date(today0); d0.setDate(d0.getDate() - i);
      const d1 = new Date(d0); d1.setDate(d1.getDate() + 1);
      expDays7.push(expSum((e) => { const t = toDate(e.date); return t && t >= d0 && t < d1; }));
    }

    // Top products (last 7 days) by qty
    const prodQty = {};
    const weekStart = new Date(today0); weekStart.setDate(weekStart.getDate() - 6);
    for (const s of state.sales) { const t = toDate(s.createdAt); if (!t || t < weekStart) continue; for (const it of items(s)) prodQty[it.drugName || "—"] = (prodQty[it.drugName || "—"] || 0) + num(it.quantity); }
    const topProducts = Object.entries(prodQty).sort((a, b) => b[1] - a[1]).slice(0, 6);

    /* ----- build sections ----- */
    const name = (ctx.session.displayName || (ctx.session.email || "").split("@")[0] || "there").split(" ")[0];
    const greet = now.getHours() < 12 ? t("dash.greetMorning") : now.getHours() < 17 ? t("dash.greetAfternoon") : t("dash.greetEvening");
    const summaryBits = [];
    if (expWeek) summaryBits.push(t("dash.bitExpiring", { n: expWeek }));
    if (out.length) summaryBits.push(t("dash.bitOut", { n: out.length }));
    if (low.length) summaryBits.push(t("dash.bitLow", { n: low.length }));
    const summaryLine = summaryBits.length ? t("dash.youHave", { x: summaryBits.join(" · ") }) : t("dash.allHealthy");

    const greeting = el("div", {}, [
      el("h1", { class: "text-xl font-bold text-ink" }, `${greet}, ${name[0].toUpperCase() + name.slice(1)}`),
      el("p", { class: "mt-0.5 text-sm text-soft" }, summaryLine),
    ]);

    const kpis = el("div", { class: "grid grid-cols-2 gap-3 lg:grid-cols-6" }, [
      kpiCard({ label: t("dash.kpiSales"), value: money(todayRevenue), change: pct(todayRevenue, yestRevenue), spark: days7.map((x) => x.revenue), sparkColor: "#0EA59B" }),
      kpiCard({ label: t("dash.kpiProfit"), value: money(todayProfit), change: pct(todayProfit, yestProfit), spark: days7.map((x) => x.profit), sparkColor: "#1F9D55" }),
      kpiCard({ label: t("dash.kpiInvoices"), value: String(todayS.count), change: pct(todayS.count, yestS.count) }),
      kpiCard({ label: t("dash.kpiCash"), value: money(cashDrawer) }),
      kpiCard({ label: t("dash.kpiStock"), value: money(stockValue) }),
      kpiCard({ label: t("dash.kpiExpenses"), value: money(expToday), change: pct(expToday, expYest), goodWhenUp: false, spark: expDays7, sparkColor: "#E8554E" }),
    ]);

    const alerts = el("div", { class: "flex flex-wrap items-center gap-2" }, [
      el("span", { class: "text-xs font-semibold uppercase tracking-wide text-soft" }, t("dash.needsAttention")),
      alertPill(expiring.length, t("dash.alertExpiring"), "orange", "inventory"),
      alertPill(low.length, t("dash.alertLow"), "amber", "inventory"),
      alertPill(out.length, t("dash.alertOut"), "red", "inventory"),
      alertPill(expired.length, t("dash.alertExpired"), "red", "inventory"),
      creditOutstanding > 0 ? alertPill(Math.round(creditOutstanding), t("dash.alertCredit"), "purple", "reports") : null,
    ]);

    const charts = el("div", { class: "grid gap-5 lg:grid-cols-2" }, [
      el("div", { class: "card" }, [el("p", { class: "mb-3 font-semibold text-ink" }, t("dash.sales7")), barChart(chart7)]),
      el("div", { class: "card" }, [el("p", { class: "mb-3 font-semibold text-ink" }, t("dash.topProducts")),
        topProducts.length ? barChart(topProducts.map(([l, v]) => ({ label: l.slice(0, 8), value: v })), { color: "#7C5CFC" }) : el("p", { class: "text-sm text-soft" }, t("dash.noSalesWeek"))]),
    ]);

    const recent = [...state.sales].sort((a, b) => (toDate(b.createdAt)?.getTime() || 0) - (toDate(a.createdAt)?.getTime() || 0)).slice(0, 6)
      .map((s) => rowEl(s.patientName || t("dash.walkIn"), money(s.total), `${s.receiptNumber || ""} · ${s.staffName || ""}`.replace(/^ · | · $/g, "")));
    const topList = topProducts.slice(0, 6).map(([n, q]) => rowEl(n, t("dash.sold", { n: q })));
    const expSoon = expiring.slice(0, 6).map((x) => rowEl(x.d.name || "—", `${x.n}d`, fmtDate(toDate(x.d.expiryDate))));

    const lists = el("div", { class: "grid gap-5 lg:grid-cols-3" }, [
      listCard(t("dash.recentSales"), recent, { empty: t("dash.noSales"), action: { label: t("dash.viewAll"), onClick: () => go("sales") } }),
      listCard(t("dash.topSelling"), topList, { empty: t("dash.noSalesWeek") }),
      listCard(t("dash.expiringSoon"), expSoon, { empty: t("dash.nothingExpiring"), action: { label: t("nav.inventory"), onClick: () => go("inventory") } }),
    ]);

    const quick = el("div", { class: "card flex flex-wrap gap-2" }, [
      quickBtn(t("dash.quickNewSale"), "sales", '<path d="M12 5v14M5 12h14"/>', "#0EA59B"),
      quickBtn(t("dash.quickAddStock"), "drugs", '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>', "#2F6FED"),
      quickBtn(t("dash.quickAddExpense"), "expenses", '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>', "#E8554E"),
      quickBtn(t("dash.quickReports"), "reports", '<path d="M5 20V10M12 20V4M19 20v-7"/>', "#6C7B7A"),
    ]);

    root.replaceChildren(greeting, kpis, alerts, charts, lists, quick);
  }

  const offD = watch(pid, "drugs", { onData: (d) => { state.drugs = d; draw(); }, onError: () => { state.drugs = []; draw(); } });
  const offS = watch(pid, "sales", { onData: (d) => { state.sales = d; draw(); }, onError: () => { state.sales = []; draw(); } });
  const offE = watch(pid, "expenses", { onData: (d) => { state.expenses = d; draw(); }, onError: () => { state.expenses = []; draw(); } });
  const offR = watch(pid, "returns", { onData: (d) => { state.returns = d; draw(); }, onError: () => { state.returns = []; draw(); } });
  const offP = watch(pid, "customer_payments", { onData: (d) => { state.payments = d; draw(); }, onError: () => { state.payments = []; draw(); } });
  return () => { offD(); offS(); offE(); offR(); offP(); };
}
