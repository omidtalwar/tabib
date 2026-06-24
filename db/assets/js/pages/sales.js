/** Sales — history + POS (atomic, offline-capable sale via commitSale). */
import { watch, commitSale, commitReturn, uuid, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, money, fmtDate, badge, loading, toast, confirmDialog, formModal, printContent, iconButton, esc, withButtonLoading } from "../ui.js";
import { t } from "../i18n.js";

const PAYMENTS = ["cash", "card", "insurance", "credit"];

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let sales = null, drugs = [];
  let mode = "history";
  const cart = [];
  let patientName = "", paymentMethod = "cash", discountPercent = 0, insuranceCoverage = 0;

  const root = el("div", { class: "space-y-5" });
  outlet.append(root);

  function totals() {
    const subtotal = cart.reduce((a, c) => a + c.unitPrice * c.quantity, 0);
    const discountAmount = subtotal * (Number(discountPercent) || 0) / 100;
    const total = Math.max(0, subtotal - discountAmount - (Number(insuranceCoverage) || 0));
    return { subtotal, discountAmount, total };
  }

  async function confirmSale() {
    if (!cart.length) return;
    for (const c of cart) {
      const d = drugs.find((x) => (x.firestoreId || x.id) === c.drugId);
      const stock = d ? (d.stockQuantity ?? 0) : 0;
      if (c.quantity > stock) return toast(t("sales.notEnough", { name: c.drugName, n: stock }), { type: "error" });
    }
    const controlled = cart.filter((c) => { const d = drugs.find((x) => (x.firestoreId || x.id) === c.drugId); return d && d.isControlled; });
    if (controlled.length && !(await confirmDialog(t("sales.controlledWarn", { names: controlled.map((c) => c.drugName).join(", ") }), { confirmLabel: t("sales.sell") }))) return;

    const { subtotal, discountAmount, total } = totals();
    const items = cart.map((c) => ({ drugId: c.drugId, drugName: c.drugName, quantity: c.quantity, unitPrice: c.unitPrice, subtotal: c.unitPrice * c.quantity }));
    const saleId = uuid();
    const payload = {
      id: Date.now(), prescriptionId: "", patientId: "", patientName,
      itemsJson: JSON.stringify(items),
      subtotal, discountPercent: Number(discountPercent) || 0, discountAmount,
      insuranceCoverage: Number(insuranceCoverage) || 0, total,
      paymentMethod, staffName: ctx.session.email || "",
      createdAt: new Date().toISOString(), receiptNumber: `RCP-${Date.now()}`, isDirty: false,
    };
    try {
      await commitSale(pid, saleId, payload, items);
      toast(t("sales.recorded", { total: money(total) }), { type: "ok" });
      printReceipt({ ...payload, firestoreId: saleId });
      cart.length = 0; patientName = ""; discountPercent = 0; insuranceCoverage = 0;
      mode = "history"; paint();
    } catch (e) {
      toast(e.message || t("sales.couldnt"), { type: "error" });
    }
  }

  function printReceipt(s) {
    let items = []; try { items = JSON.parse(s.itemsJson || "[]"); } catch {}
    const rows = items.map((it) => `<tr><td>${esc(it.drugName)}</td><td>${esc(it.quantity)}</td><td>${esc(money(it.unitPrice))}</td><td>${esc(money(it.subtotal))}</td></tr>`).join("");
    printContent(t("rcp.title"), `<h1>${t("rcp.title")} ${esc(s.receiptNumber || "")}</h1>
      <div class="sub">${t("rcp.date")}: ${esc(fmtDate(toDate(s.createdAt)))} · ${t("rcp.cashier")}: ${esc(s.staffName || "")}${s.patientName ? " · " + t("rcp.patient") + ": " + esc(s.patientName) : ""}</div>
      <table><tr><th>${t("rcp.item")}</th><th>${t("rcp.qty")}</th><th>${t("rcp.price")}</th><th>${t("rcp.amount")}</th></tr>${rows}</table>
      <div class="kpis" style="margin-top:10px">
        <span class="kpi"><b>${esc(money(s.subtotal))}</b><span>${t("rcp.subtotal")}</span></span>
        <span class="kpi"><b>${esc(money((s.discountAmount || 0) + (s.insuranceCoverage || 0)))}</b><span>${t("rcp.discount")}</span></span>
        <span class="kpi"><b>${esc(money(s.total))}</b><span>${t("rcp.total")}</span></span>
      </div>
      <p style="margin-top:16px;text-align:center">${t("rcp.thanks")}</p>`);
  }

  async function returnSale(s) {
    if (s.returned) { toast(t("ret.already"), { type: "warn" }); return; }
    let items = []; try { items = JSON.parse(s.itemsJson || "[]"); } catch {}
    const ok = await formModal({
      title: t("ret.title", { receipt: s.receiptNumber || "" }),
      submitLabel: t("ret.confirm"),
      fields: [
        ...items.map((it, i) => ({ name: "q" + i, label: `${it.drugName} (×${it.quantity})`, type: "number", min: "0", placeholder: "0" })),
        { name: "reason", label: t("ret.reason"), full: true },
      ],
      onSubmit: async (v) => {
        const retItems = items.map((it, i) => ({ drugId: it.drugId, drugName: it.drugName, quantity: Math.min(Number(v["q" + i]) || 0, it.quantity), unitPrice: it.unitPrice })).filter((x) => x.quantity > 0);
        if (!retItems.length) throw new Error(t("ret.none"));
        const totalRet = retItems.reduce((a, x) => a + x.unitPrice * x.quantity, 0);
        await commitReturn(pid, uuid(), {
          id: Date.now(), saleId: s.firestoreId || s.id, receiptNumber: s.receiptNumber || "",
          itemsJson: JSON.stringify(retItems), total: totalRet, reason: v.reason || "",
          date: new Date().toISOString(), recordedBy: ctx.session.email || "",
          createdAt: new Date().toISOString(), isDirty: false,
        }, retItems);
      },
    });
    if (ok) toast(t("ret.recorded"), { type: "ok" });
  }

  function posView() {
    const results = el("div", { class: "mt-2 grid gap-1" });
    const search = searchInput(t("sales.posSearch"), (v) => {
      results.replaceChildren();
      if (!v) return;
      drugs.filter((d) => d.isActive !== false && (d.name || "").toLowerCase().includes(v)).slice(0, 8)
        .forEach((d) => results.append(el("button", {
          class: "flex items-center justify-between rounded-lg border border-line px-3 py-2 text-sm hover:bg-brand-50 text-start",
          onclick: () => addToCart(d),
        }, [
          el("span", {}, [el("span", { class: "font-semibold text-ink" }, d.name), el("span", { class: "ms-2 text-soft" }, money(d.sellingPrice))]),
          el("span", { class: "text-xs text-soft" }, `${t("sales.stock")} ${d.stockQuantity ?? 0}`),
        ])));
    });

    const cartHost = el("div", {});
    const totalsHost = el("div", {});

    function addToCart(d) {
      const id = d.firestoreId || d.id;
      const existing = cart.find((c) => c.drugId === id);
      if (existing) existing.quantity += 1;
      else cart.push({ drugId: id, drugName: d.name, unitPrice: Number(d.sellingPrice) || 0, quantity: 1, stock: d.stockQuantity ?? 0 });
      drawCart();
    }
    function drawCart() {
      if (!cart.length) { cartHost.replaceChildren(el("p", { class: "text-sm text-soft py-6 text-center" }, t("sales.cartEmpty"))); totalsHost.replaceChildren(); return; }
      cartHost.replaceChildren(el("table", { class: "table" }, [
        el("thead", {}, el("tr", {}, [t("sales.cDrug"), t("sales.cQty"), t("sales.cPrice"), t("sales.cSubtotal"), ""].map((h) => el("th", {}, h)))),
        el("tbody", {}, cart.map((c, i) => el("tr", {}, [
          el("td", {}, c.drugName),
          el("td", {}, el("input", { type: "number", min: "1", value: String(c.quantity), class: "field w-20 py-1",
            onchange: (e) => { c.quantity = Math.max(1, Number(e.target.value) || 1); drawCart(); } })),
          el("td", {}, money(c.unitPrice)),
          el("td", {}, money(c.unitPrice * c.quantity)),
          el("td", {}, el("button", { class: "btn-ghost px-2 py-1 text-xs", onclick: () => { cart.splice(i, 1); drawCart(); } }, "✕")),
        ]))),
      ]));
      const tt = totals();
      totalsHost.replaceChildren(el("div", { class: "mt-4 grid gap-3 sm:grid-cols-2" }, [
        el("div", { class: "grid gap-2" }, [
          labeled(t("sales.patientOpt"), el("input", { class: "field", value: patientName, oninput: (e) => patientName = e.target.value })),
          labeled(t("sales.payment"), select(PAYMENTS, paymentMethod, (v) => paymentMethod = v)),
          labeled(t("sales.discountPct"), el("input", { class: "field", type: "number", min: "0", value: String(discountPercent), oninput: (e) => { discountPercent = e.target.value; drawCart(); } })),
          labeled(t("sales.insurance"), el("input", { class: "field", type: "number", min: "0", value: String(insuranceCoverage), oninput: (e) => { insuranceCoverage = e.target.value; drawCart(); } })),
        ]),
        el("div", { class: "rounded-xl bg-brand-50 p-4 space-y-1.5 text-sm" }, [
          row(t("sales.subtotal"), money(tt.subtotal)),
          row(t("sales.discount"), "− " + money(tt.discountAmount)),
          row(t("sales.insuranceRow"), "− " + money(Number(insuranceCoverage) || 0)),
          el("div", { class: "flex justify-between border-t border-brand-200 pt-2 text-base font-bold text-ink" }, [el("span", {}, t("sales.total")), el("span", {}, money(tt.total))]),
          el("button", { class: "btn-primary w-full mt-2", onclick: (e) => withButtonLoading(e.currentTarget, confirmSale) }, t("sales.confirm")),
        ]),
      ]));
    }
    drawCart();

    return el("div", { class: "card" }, [
      el("p", { class: "font-semibold text-ink" }, t("sales.posTitle")),
      search, results, el("div", { class: "mt-4 overflow-x-auto" }, cartHost), totalsHost,
    ]);
  }

  function historyView() {
    const rows = (sales || []).slice().sort((a, b) => (toDate(b.createdAt)?.getTime() || 0) - (toDate(a.createdAt)?.getTime() || 0));
    return table([
      { label: t("sales.colReceipt"), render: (s) => s.receiptNumber || (s.firestoreId || "").slice(0, 8) || "—" },
      { label: t("sales.colPatient"), render: (s) => s.patientName || "—" },
      { label: t("sales.colItems"), render: (s) => { try { return String(JSON.parse(s.itemsJson || "[]").length); } catch { return "0"; } } },
      { label: t("sales.colPayment"), render: (s) => s.paymentMethod || "—" },
      { label: t("sales.colTotal"), render: (s) => money(s.total) },
      { label: t("sales.colWhen"), render: (s) => fmtDate(toDate(s.createdAt)) },
      { label: "", render: (s) => el("div", { class: "flex justify-end gap-1.5" }, [
        iconButton('<path d="M6 9V3h12v6M6 18H4v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5h-2M8 14h8v7H8z"/>', t("rcp.print"), () => printReceipt(s), { color: "blue" }),
        s.returned ? el("span", { class: "self-center", html: badge(t("ret.returned"), "muted") }) : iconButton('<path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 5 5v3"/>', t("ret.action"), () => returnSale(s), { color: "red" }),
      ]) },
    ], rows, { empty: t("sales.empty"), emptyHint: t("sales.emptyHint") });
  }

  function paint() {
    const toggle = mode === "pos"
      ? el("button", { class: "btn-ghost", onclick: () => { mode = "history"; paint(); } }, t("sales.back"))
      : el("button", { class: "btn-primary", onclick: () => { mode = "pos"; paint(); } }, "+ " + t("sales.newSale"));
    root.replaceChildren(
      toolbar(mode === "pos" ? t("sales.posTitle") : t("sales.title"), toggle),
      mode === "pos" ? posView() : (sales == null ? loading() : historyView())
    );
  }

  function labeled(label, node) { return el("label", { class: "block" }, [el("span", { class: "label" }, label), node]); }
  function row(l, r) { return el("div", { class: "flex justify-between text-soft" }, [el("span", {}, l), el("span", {}, r)]); }
  function select(opts, val, on) {
    return el("select", { class: "field", onchange: (e) => on(e.target.value) }, opts.map((o) => {
      const opt = el("option", { value: o }, o); if (o === val) opt.selected = true; return opt;
    }));
  }

  paint();
  const offDrugs = watch(pid, "drugs", { onData: (d) => { drugs = d; }, onError: () => { drugs = []; } });
  const offSales = watch(pid, "sales", { onData: (d) => { sales = d; if (mode === "history") paint(); }, onError: () => { sales = []; if (mode === "history") paint(); } });
  return () => { offDrugs(); offSales(); };
}
