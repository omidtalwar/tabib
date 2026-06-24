/** Purchases — record stock bought from suppliers. Adds stock + tracks payables.
 * commitPurchase increments each drug's stock and refreshes cost price atomically. */
import { watch, readAll, commitPurchase, uuid, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, money, fmtDate, badge, loading, toast, filterSelect, withButtonLoading } from "../ui.js";
import { t } from "../i18n.js";

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let purchases = null, drugs = [], suppliers = [];
  let mode = "history";
  const cart = []; // { drugId, drugName, quantity, costPrice }
  let supplierId = "", invoiceNumber = "", amountPaid = 0;

  const root = el("div", { class: "space-y-5" });
  outlet.append(root);

  readAll(pid, "suppliers").then((s) => { suppliers = s; if (mode === "new") paint(); }).catch(() => {});

  const num = (x) => Number(x) || 0;
  const total = () => cart.reduce((a, c) => a + num(c.costPrice) * num(c.quantity), 0);
  const statusOf = (paid, tot) => paid >= tot && tot > 0 ? "paid" : paid > 0 ? "partial" : "unpaid";
  const statusBadge = (s) => badge(t("pur.status" + s[0].toUpperCase() + s.slice(1)), s === "paid" ? "ok" : s === "partial" ? "warn" : "danger");

  async function confirmPurchase() {
    if (!cart.length) return;
    const tot = total();
    const items = cart.map((c) => ({ drugId: c.drugId, drugName: c.drugName, quantity: num(c.quantity), costPrice: num(c.costPrice), subtotal: num(c.costPrice) * num(c.quantity) }));
    const sup = suppliers.find((s) => (s.firestoreId || s.id) === supplierId);
    const id = uuid();
    const payload = {
      id: Date.now(), supplierId, supplierName: sup ? sup.name : "",
      itemsJson: JSON.stringify(items), subtotal: tot, total: tot,
      amountPaid: num(amountPaid), status: statusOf(num(amountPaid), tot),
      invoiceNumber, notes: "", date: new Date().toISOString(),
      createdBy: ctx.session.email || "", isActive: true, createdAt: new Date().toISOString(), isDirty: false,
    };
    try {
      await commitPurchase(pid, id, payload, items);
      toast(t("pur.recorded"), { type: "ok" });
      cart.length = 0; supplierId = ""; invoiceNumber = ""; amountPaid = 0;
      mode = "history"; paint();
    } catch (e) { toast(e.message || "Error", { type: "error" }); }
  }

  function newView() {
    const results = el("div", { class: "mt-2 grid gap-1" });
    const search = searchInput(t("pur.searchDrug"), (v) => {
      results.replaceChildren();
      if (!v) return;
      drugs.filter((d) => d.isActive !== false && (d.name || "").toLowerCase().includes(v)).slice(0, 8)
        .forEach((d) => results.append(el("button", {
          class: "flex items-center justify-between rounded-lg border border-line px-3 py-2 text-sm hover:bg-brand-50 text-start",
          onclick: () => addToCart(d),
        }, [el("span", { class: "font-semibold text-ink" }, d.name), el("span", { class: "text-xs text-soft" }, money(d.unitPrice))])));
    });
    const cartHost = el("div", {});
    const footHost = el("div", {});

    function addToCart(d) {
      const id = d.firestoreId || d.id;
      if (cart.find((c) => c.drugId === id)) return;
      cart.push({ drugId: id, drugName: d.name, quantity: 1, costPrice: num(d.unitPrice) });
      draw();
    }
    function draw() {
      if (!cart.length) { cartHost.replaceChildren(el("p", { class: "py-6 text-center text-sm text-soft" }, t("pur.cartEmpty"))); footHost.replaceChildren(); return; }
      cartHost.replaceChildren(el("table", { class: "table" }, [
        el("thead", {}, el("tr", {}, [t("pur.cDrug"), t("pur.cQty"), t("pur.cCost"), t("pur.cSubtotal"), ""].map((h) => el("th", {}, h)))),
        el("tbody", {}, cart.map((c, i) => el("tr", {}, [
          el("td", {}, c.drugName),
          el("td", {}, el("input", { type: "number", min: "1", value: String(c.quantity), class: "field w-20 py-1", onchange: (e) => { c.quantity = Math.max(1, num(e.target.value)); draw(); } })),
          el("td", {}, el("input", { type: "number", min: "0", step: "0.01", value: String(c.costPrice), class: "field w-24 py-1", onchange: (e) => { c.costPrice = num(e.target.value); draw(); } })),
          el("td", {}, money(num(c.costPrice) * num(c.quantity))),
          el("td", {}, el("button", { class: "btn-ghost px-2 py-1 text-xs", onclick: () => { cart.splice(i, 1); draw(); } }, "✕")),
        ]))),
      ]));
      const tot = total();
      footHost.replaceChildren(el("div", { class: "mt-4 grid gap-3 sm:grid-cols-2" }, [
        el("div", { class: "grid gap-2" }, [
          labeled(t("pur.fSupplier"), filterSelect([{ value: "", label: t("pur.selectSupplier") }, ...suppliers.map((s) => ({ value: s.firestoreId || s.id, label: s.name }))], supplierId, (v) => supplierId = v)),
          labeled(t("pur.fInvoice"), el("input", { class: "field", value: invoiceNumber, oninput: (e) => invoiceNumber = e.target.value })),
          labeled(t("pur.amountPaid"), el("input", { class: "field", type: "number", min: "0", value: String(amountPaid), oninput: (e) => { amountPaid = e.target.value; draw(); } })),
        ]),
        el("div", { class: "rounded-xl bg-brand-50 p-4 space-y-1.5 text-sm" }, [
          row(t("pur.subtotal"), money(tot)),
          row(t("pur.amountPaid"), money(num(amountPaid))),
          el("div", { class: "flex justify-between border-t border-brand-200 pt-2 text-base font-bold text-ink" }, [el("span", {}, t("pur.total")), el("span", {}, money(tot))]),
          el("p", { class: "text-xs text-soft" }, t("pur.payNote")),
          el("button", { class: "btn-primary w-full mt-1", onclick: (e) => withButtonLoading(e.currentTarget, confirmPurchase) }, t("pur.confirm")),
        ]),
      ]));
    }
    draw();
    return el("div", { class: "card" }, [el("p", { class: "font-semibold text-ink" }, t("pur.addTitle")), search, results, el("div", { class: "mt-4 overflow-x-auto" }, cartHost), footHost]);
  }

  function historyView() {
    const rows = (purchases || []).filter((p) => p.isActive !== false).sort((a, b) => (toDate(b.date)?.getTime() || 0) - (toDate(a.date)?.getTime() || 0));
    const totalP = rows.reduce((a, p) => a + num(p.total), 0);
    const payables = rows.reduce((a, p) => a + Math.max(0, num(p.total) - num(p.amountPaid)), 0);
    const kpis = el("div", { class: "grid grid-cols-3 gap-3" }, [
      [t("pur.kTotal"), money(totalP)], [t("pur.kPayables"), money(payables)], [t("pur.kEntries"), String(rows.length)],
    ].map(([l, v]) => el("div", { class: "kpi" }, [el("span", { class: "kpi-value" }, v), el("span", { class: "kpi-label" }, l)])));
    const tbl = table([
      { label: t("pur.colDate"), render: (p) => fmtDate(toDate(p.date)) },
      { label: t("pur.colSupplier"), render: (p) => p.supplierName || "—" },
      { label: t("pur.colItems"), render: (p) => { try { return String(JSON.parse(p.itemsJson || "[]").length); } catch { return "0"; } } },
      { label: t("pur.colTotal"), render: (p) => money(p.total) },
      { label: t("pur.colPaid"), render: (p) => money(p.amountPaid) },
      { label: t("pur.colStatus"), html: true, render: (p) => statusBadge(p.status || "unpaid") },
    ], rows, { empty: t("pur.empty"), emptyHint: t("pur.emptyHint") });
    return el("div", { class: "space-y-4" }, [kpis, tbl]);
  }

  function paint() {
    const toggle = mode === "new"
      ? el("button", { class: "btn-ghost", onclick: () => { mode = "history"; paint(); } }, t("sales.back"))
      : el("button", { class: "btn-primary", onclick: () => { mode = "new"; paint(); } }, "+ " + t("pur.add"));
    root.replaceChildren(toolbar(mode === "new" ? t("pur.add") : t("pur.title"), toggle),
      mode === "new" ? newView() : (purchases == null ? loading() : historyView()));
  }
  function labeled(l, n) { return el("label", { class: "block" }, [el("span", { class: "label" }, l), n]); }
  function row(l, r) { return el("div", { class: "flex justify-between text-soft" }, [el("span", {}, l), el("span", {}, r)]); }

  paint();
  const offD = watch(pid, "drugs", { onData: (d) => { drugs = d; }, onError: () => { drugs = []; } });
  const offP = watch(pid, "purchases", { onData: (d) => { purchases = d; if (mode === "history") paint(); }, onError: () => { purchases = []; if (mode === "history") paint(); } });
  return () => { offD(); offP(); };
}
