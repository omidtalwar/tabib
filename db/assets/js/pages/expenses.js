/** Expenses — record and track pharmacy spending. CRUD + filters + KPIs + CSV.
 * Stored at pharmacies/{id}/expenses in the app's wire shape (ISO dates,
 * firestoreId, soft-delete via isActive). Feeds the Financial report. */
import { watch, create, update, softDelete, toIso, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, money, fmtDate, badge, loading, formModal, confirmDialog, toast, iconButton, ICON, filterSelect, downloadCSV } from "../ui.js";

const CATEGORIES = ["Rent", "Salaries & wages", "Utilities", "Transport & delivery", "Equipment & maintenance", "Licenses & fees", "Marketing", "Bank charges", "Petty cash", "Miscellaneous"];
const PAYMENTS = ["cash", "bank", "card"];

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let rows = null, q = "", category = "", payment = "";

  async function form(existing) {
    const ok = await formModal({
      title: existing ? "Edit expense" : "Add expense",
      values: existing ? { ...existing } : { date: new Date().toISOString(), paymentMethod: "cash", category: "Miscellaneous" },
      fields: [
        { name: "date", label: "Date", type: "jdate", required: true },
        { name: "amount", label: "Amount", type: "number", step: "0.01", min: "0", required: true },
        { name: "category", label: "Category", type: "combo", options: CATEGORIES, required: true, help: "Pick one or type your own" },
        { name: "paymentMethod", label: "Payment method", type: "select", options: PAYMENTS, default: "cash" },
        { name: "paidTo", label: "Paid to", placeholder: "Vendor / person" },
        { name: "referenceNo", label: "Reference / receipt no." },
        { name: "description", label: "Description", type: "textarea", full: true },
        { name: "recurring", label: "Recurring", type: "checkbox", help: "Repeats monthly (e.g. rent, salaries)" },
      ],
      onSubmit: async (d) => {
        const payload = {
          date: toIso(d.date), amount: d.amount ?? 0, category: d.category || "Miscellaneous",
          paymentMethod: d.paymentMethod || "cash", paidTo: d.paidTo || "", referenceNo: d.referenceNo || "",
          description: d.description || "", recurring: !!d.recurring, recordedBy: ctx.session.email || "",
        };
        if (existing) await update(pid, "expenses", existing.firestoreId || existing.id, payload);
        else await create(pid, "expenses", { ...payload, isActive: true, createdAt: new Date().toISOString() });
      },
    });
    if (ok) toast(existing ? "Expense updated" : "Expense added", { type: "ok" });
  }

  async function remove(e) {
    if (!(await confirmDialog(`Delete this ${money(e.amount)} ${e.category} expense? It will be hidden from totals.`, { confirmLabel: "Delete", danger: true }))) return;
    await softDelete(pid, "expenses", e.firestoreId || e.id);
    toast("Expense deleted", { type: "ok" });
  }

  const addBtn = el("button", { class: "btn-primary", onclick: () => form(null) }, [
    el("span", { html: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>' }), "Add expense",
  ]);
  const search = searchInput("Search vendor, ref, note…", (v) => { q = v; paint(); });
  const catFilter = filterSelect([{ value: "", label: "All categories" }, ...CATEGORIES.map((c) => ({ value: c, label: c }))], "", (v) => { category = v; paint(); }, "Category");
  const payFilter = filterSelect([{ value: "", label: "All payments" }, ...PAYMENTS.map((p) => ({ value: p, label: p }))], "", (v) => { payment = v; paint(); }, "Payment");
  const exportBtn = iconButton('<path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>', "Export CSV", doExport);

  const kpiHost = el("div", {});
  const listHost = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar("Expenses", el("div", { class: "flex flex-wrap items-center gap-2" }, [search, catFilter, payFilter, exportBtn, addBtn])),
    kpiHost, listHost,
  ]));

  function filtered() {
    return (rows || []).filter((e) => e.isActive !== false)
      .filter((e) => !q || [e.paidTo, e.referenceNo, e.description, e.category].some((x) => (x || "").toLowerCase().includes(q)))
      .filter((e) => !category || e.category === category)
      .filter((e) => !payment || e.paymentMethod === payment)
      .sort((a, b) => (toDate(b.date)?.getTime() || 0) - (toDate(a.date)?.getTime() || 0));
  }

  function doExport() {
    const f = filtered();
    if (!f.length) return;
    downloadCSV("expenses.csv", [["Date", "Category", "Paid to", "Amount", "Payment", "Reference", "Description"],
      ...f.map((e) => [fmtDate(toDate(e.date)), e.category || "", e.paidTo || "", Number(e.amount || 0).toFixed(2), e.paymentMethod || "", e.referenceNo || "", e.description || ""])]);
  }

  function paint() {
    if (!rows) return;
    const f = filtered();
    const total = f.reduce((a, e) => a + Number(e.amount || 0), 0);
    const cash = f.filter((e) => e.paymentMethod === "cash").reduce((a, e) => a + Number(e.amount || 0), 0);
    const now = new Date();
    const month = f.filter((e) => { const d = toDate(e.date); return d && d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth(); }).reduce((a, e) => a + Number(e.amount || 0), 0);

    kpiHost.replaceChildren(el("div", { class: "grid grid-cols-2 gap-3 sm:grid-cols-4" }, [
      ["Total (filtered)", money(total)], ["Entries", String(f.length)], ["This month", money(month)], ["Paid in cash", money(cash)],
    ].map(([l, v]) => el("div", { class: "kpi" }, [el("span", { class: "kpi-value" }, v), el("span", { class: "kpi-label" }, l)]))));

    listHost.replaceChildren(table([
      { label: "Date", render: (e) => fmtDate(toDate(e.date)) },
      { label: "Category", render: (e) => el("div", {}, [el("span", { class: "font-semibold text-ink" }, e.category || "—"), e.recurring ? el("span", { class: "ms-2", html: badge("Recurring", "muted") }) : null]) },
      { label: "Paid to", render: (e) => e.paidTo || "—" },
      { label: "Amount", render: (e) => el("span", { class: "font-semibold text-ink" }, money(e.amount)) },
      { label: "Payment", render: (e) => e.paymentMethod || "—" },
      { label: "Ref", render: (e) => e.referenceNo || "—" },
      { label: "", render: (e) => el("div", { class: "flex justify-end gap-1.5" }, [iconButton(ICON.edit, "Edit", () => form(e), { color: "blue" }), iconButton(ICON.remove, "Delete", () => remove(e), { color: "red" })]) },
    ], f, { empty: "No expenses yet", emptyHint: "Record your first expense with the button above." }));
  }

  return watch(pid, "expenses", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
