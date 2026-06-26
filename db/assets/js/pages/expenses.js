/** Expenses — record and track pharmacy spending. CRUD + filters + KPIs + CSV. */
import { watch, create, update, softDelete, toIso, toDate, commitLocal } from "../repo.js";
import { el, table, searchInput, toolbar, money, fmtDate, badge, loading, formModal, confirmDialog, toast, iconButton, ICON, filterSelect, downloadCSV } from "../ui.js";
import { t } from "../i18n.js";

const CATEGORIES = ["Rent", "Salaries & wages", "Utilities", "Transport & delivery", "Equipment & maintenance", "Licenses & fees", "Marketing", "Bank charges", "Petty cash", "Miscellaneous"];
const PAYMENTS = ["cash", "bank", "card"];

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let rows = null, q = "", category = "", payment = "";

  async function form(existing) {
    const ok = await formModal({
      title: existing ? t("exp.editTitle") : t("exp.addTitle"),
      values: existing ? { ...existing } : { date: new Date().toISOString(), paymentMethod: "cash", category: "Miscellaneous" },
      fields: [
        { name: "date", label: t("exp.fDate"), type: "jdate", required: true },
        { name: "amount", label: t("exp.fAmount"), type: "number", step: "0.01", min: "0", required: true },
        { name: "category", label: t("exp.fCategory"), type: "combo", options: CATEGORIES, required: true, help: t("exp.fCategoryHelp") },
        { name: "paymentMethod", label: t("exp.fPayment"), type: "select", options: PAYMENTS, default: "cash" },
        { name: "paidTo", label: t("exp.fPaidTo"), placeholder: t("exp.fPaidToPh") },
        { name: "referenceNo", label: t("exp.fRef") },
        { name: "description", label: t("exp.fDescription"), type: "textarea", full: true },
        { name: "recurring", label: t("exp.fRecurring"), type: "checkbox", help: t("exp.fRecurringHelp") },
      ],
      onSubmit: async (d) => {
        const payload = {
          date: toIso(d.date), amount: d.amount ?? 0, category: d.category || "Miscellaneous",
          paymentMethod: d.paymentMethod || "cash", paidTo: d.paidTo || "", referenceNo: d.referenceNo || "",
          description: d.description || "", recurring: !!d.recurring, recordedBy: ctx.session.email || "",
        };
        if (existing) await commitLocal(update(pid, "expenses", existing.firestoreId || existing.id, payload));
        else await commitLocal(create(pid, "expenses", { ...payload, isActive: true, createdAt: new Date().toISOString() }));
      },
    });
    if (ok) toast(existing ? t("exp.updated") : t("exp.added"), { type: "ok" });
  }

  async function remove(e) {
    if (!(await confirmDialog(t("exp.deleteConfirm", { amount: money(e.amount), category: e.category }), { confirmLabel: t("common.delete"), danger: true }))) return;
    await commitLocal(softDelete(pid, "expenses", e.firestoreId || e.id));
    toast(t("exp.deleted"), { type: "ok" });
  }

  const addBtn = el("button", { class: "btn-primary", onclick: () => form(null) }, [
    el("span", { html: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>' }), t("exp.add"),
  ]);
  const search = searchInput(t("exp.searchPh"), (v) => { q = v; paint(); });
  const catFilter = filterSelect([{ value: "", label: t("exp.allCategories") }, ...CATEGORIES.map((c) => ({ value: c, label: c }))], "", (v) => { category = v; paint(); }, t("exp.colCategory"));
  const payFilter = filterSelect([{ value: "", label: t("exp.allPayments") }, ...PAYMENTS.map((p) => ({ value: p, label: p }))], "", (v) => { payment = v; paint(); }, t("exp.colPayment"));
  const exportBtn = iconButton('<path d="M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>', t("common.export"), doExport);

  const kpiHost = el("div", {});
  const listHost = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar(t("exp.title"), el("div", { class: "flex flex-wrap items-center gap-2" }, [search, catFilter, payFilter, exportBtn, addBtn])),
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
    downloadCSV("expenses.csv", [[t("exp.colDate"), t("exp.colCategory"), t("exp.colPaidTo"), t("exp.colAmount"), t("exp.colPayment"), t("exp.colRef"), t("exp.fDescription")],
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
      [t("exp.kTotal"), money(total)], [t("exp.kEntries"), String(f.length)], [t("exp.kMonth"), money(month)], [t("exp.kCash"), money(cash)],
    ].map(([l, v]) => el("div", { class: "kpi" }, [el("span", { class: "kpi-value" }, v), el("span", { class: "kpi-label" }, l)]))));

    listHost.replaceChildren(table([
      { label: t("exp.colDate"), render: (e) => fmtDate(toDate(e.date)) },
      { label: t("exp.colCategory"), render: (e) => el("div", {}, [el("span", { class: "font-semibold text-ink" }, e.category || "—"), e.recurring ? el("span", { class: "ms-2", html: badge(t("exp.recurring"), "muted") }) : null]) },
      { label: t("exp.colPaidTo"), render: (e) => e.paidTo || "—" },
      { label: t("exp.colAmount"), render: (e) => el("span", { class: "font-semibold text-ink" }, money(e.amount)) },
      { label: t("exp.colPayment"), render: (e) => e.paymentMethod || "—" },
      { label: t("exp.colRef"), render: (e) => e.referenceNo || "—" },
      { label: "", render: (e) => el("div", { class: "flex justify-end gap-1.5" }, [iconButton(ICON.edit, t("common.edit"), () => form(e), { color: "blue" }), iconButton(ICON.remove, t("common.delete"), () => remove(e), { color: "red" })]) },
    ], f, { empty: t("exp.empty"), emptyHint: t("exp.emptyHint") }));
  }

  return watch(pid, "expenses", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
