/** Drugs / Catalog — live list + create/edit/restock/soft-delete. */
import { watch, create, update, softDelete, adjustStock, recordAdjustment, uuid, readAll, toDate, toIso, commitLocal } from "../repo.js";
import { el, table, searchInput, toolbar, badge, money, fmtDate, fmtDateGreg, stockStatus, expiryStatus, loading, formModal, confirmDialog, toast, iconButton, ICON, filterSelect } from "../ui.js";
import { t } from "../i18n.js";

const CATEGORIES = ["Antibiotic", "Analgesic", "Antiseptic", "Vitamin", "Cardiac", "Diabetes", "Respiratory", "Other"];
const UNITS = ["Tablet", "Capsule", "Syrup", "Injection", "Cream", "Drops", "Sachet", "Other"];

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let drugs = null, q = "", category = "", status = "";

  async function drugForm(existing) {
    const suppliers = await readAll(pid, "suppliers").catch(() => []);
    const supplierOpts = [{ value: "", label: t("drugs.supNone") }, ...suppliers.map((s) => ({ value: s.firestoreId || s.id, label: s.name || "Unnamed" }))];
    const ok = await formModal({
      title: existing ? t("drugs.editTitle") : t("drugs.addTitle"),
      values: existing ? { ...existing } : { unit: "Tablet", category: "Other", reorderThreshold: 10, isActive: true },
      fields: [
        { name: "name", label: t("drugs.fName"), required: true },
        { name: "genericName", label: t("drugs.fGeneric") },
        { name: "brand", label: t("drugs.fBrand") },
        { name: "category", label: t("drugs.fCategory"), type: "select", options: CATEGORIES, default: "Other" },
        { name: "unit", label: t("drugs.fUnit"), type: "select", options: UNITS, default: "Tablet" },
        { name: "barcode", label: t("drugs.fBarcode") },
        { name: "stockQuantity", label: t("drugs.fStock"), type: "number", min: "0" },
        { name: "reorderThreshold", label: t("drugs.fReorder"), type: "number", min: "0" },
        { name: "unitPrice", label: t("drugs.fUnitPrice"), type: "number", step: "0.01", min: "0" },
        { name: "sellingPrice", label: t("drugs.fSellingPrice"), type: "number", step: "0.01", min: "0" },
        { name: "expiryDate", label: t("drugs.fExpiry"), type: "gdate" },
        { name: "batchNumber", label: t("drugs.fBatch") },
        { name: "supplierId", label: t("drugs.fSupplier"), type: "select", options: supplierOpts },
        { name: "description", label: t("drugs.fDescription"), type: "textarea", full: true },
        { name: "isControlled", label: t("drugs.fControlled"), type: "checkbox", help: t("drugs.fControlledHelp") },
      ],
      onSubmit: async (d) => {
        const payload = {
          name: d.name, genericName: d.genericName, brand: d.brand, category: d.category || "Other",
          barcode: d.barcode, unit: d.unit || "Tablet", description: d.description,
          stockQuantity: d.stockQuantity ?? 0, reorderThreshold: d.reorderThreshold ?? 10,
          unitPrice: d.unitPrice ?? 0, sellingPrice: d.sellingPrice ?? 0,
          expiryDate: toIso(d.expiryDate), batchNumber: d.batchNumber,
          supplierId: d.supplierId || "", isControlled: !!d.isControlled,
          lastSyncedAt: new Date().toISOString(),
        };
        if (existing) await commitLocal(update(pid, "drugs", existing.firestoreId || existing.id, payload));
        else await commitLocal(create(pid, "drugs", { ...payload, isActive: true }));
      },
    });
    if (ok) toast(existing ? t("drugs.updated") : t("drugs.added"), { type: "ok" });
  }

  async function restock(d) {
    const ok = await formModal({
      title: t("drugs.restockTitle", { name: d.name }),
      submitLabel: t("drugs.restockAdd"),
      values: { batchNumber: d.batchNumber, expiryDate: d.expiryDate },
      fields: [
        { name: "qty", label: t("drugs.restockQty"), type: "number", required: true, min: "1" },
        { name: "batchNumber", label: t("drugs.fBatch") },
        { name: "expiryDate", label: t("drugs.fExpiry"), type: "gdate" },
      ],
      onSubmit: async (v) => {
        const extra = { lastSyncedAt: new Date().toISOString() };
        if (v.batchNumber) extra.batchNumber = v.batchNumber;
        if (v.expiryDate) extra.expiryDate = toIso(v.expiryDate);
        await commitLocal(adjustStock(pid, d.firestoreId || d.id, Number(v.qty), extra));
      },
    });
    if (ok) toast(t("drugs.stockUpdated"), { type: "ok" });
  }

  async function remove(d) {
    if (!(await confirmDialog(t("drugs.removeConfirm", { name: d.name }), { confirmLabel: t("common.remove"), danger: true }))) return;
    await commitLocal(softDelete(pid, "drugs", d.firestoreId || d.id));
    toast(t("drugs.removed"), { type: "ok" });
  }

  async function adjust(d) {
    const ok = await formModal({
      title: t("adj.title", { name: d.name }),
      submitLabel: t("adj.save"),
      values: { type: "write_off", quantity: -1 },
      fields: [
        { name: "type", label: t("adj.type"), type: "select", options: [
          { value: "write_off", label: t("adj.typeWriteOff") },
          { value: "damage", label: t("adj.typeDamage") },
          { value: "expiry", label: t("adj.typeExpiry") },
          { value: "correction", label: t("adj.typeCorrection") },
          { value: "add", label: t("adj.typeAdd") },
        ] },
        { name: "quantity", label: t("adj.qty"), type: "number", required: true, help: t("adj.qtyHelp") },
        { name: "reason", label: t("adj.reason"), type: "textarea", full: true },
      ],
      onSubmit: async (v) => {
        await commitLocal(recordAdjustment(pid, uuid(), {
          id: Date.now(), drugId: d.firestoreId || d.id, drugName: d.name,
          type: v.type, quantity: Number(v.quantity) || 0, reason: v.reason || "",
          date: new Date().toISOString(), recordedBy: ctx.session.email || "",
          createdAt: new Date().toISOString(), isDirty: false,
        }));
      },
    });
    if (ok) toast(t("adj.recorded"), { type: "ok" });
  }

  const addBtn = el("button", { class: "btn-primary", onclick: () => drugForm(null) }, [
    el("span", { html: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>' }), t("drugs.add"),
  ]);
  const search = searchInput(t("drugs.searchPh"), (v) => { q = v; paint(); });
  const catFilter = filterSelect(
    [{ value: "", label: t("drugs.allCategories") }, ...CATEGORIES.map((c) => ({ value: c, label: c }))],
    "", (v) => { category = v; paint(); }, t("drugs.fCategory"));
  const statusFilter = filterSelect(
    [{ value: "", label: t("drugs.allStock") }, { value: "in", label: t("status.in") }, { value: "low", label: t("status.low") }, { value: "out", label: t("status.out") }, { value: "controlled", label: t("status.controlled") }],
    "", (v) => { status = v; paint(); }, t("drugs.colStock"));

  const controls = el("div", { class: "flex flex-wrap items-center gap-2" }, [search, catFilter, statusFilter, addBtn]);
  const listHost = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [toolbar(t("drugs.title"), controls), listHost]));

  function stockCell(d) {
    const st = stockStatus(d);
    const kind = st.key === "out" ? "danger" : st.key === "low" ? "warn" : "ok";
    return el("span", {}, [el("span", { class: "font-semibold text-ink" }, String(d.stockQuantity ?? 0)),
      el("span", { class: "ms-2", html: badge(st.label, kind) })]);
  }
  function expiryCell(d) {
    const date = toDate(d.expiryDate);
    if (!date) return "—";
    const ex = expiryStatus(d.expiryDate);
    const wrap = el("span", {}, fmtDateGreg(date) + " ");
    if (ex.key === "expired") wrap.append(el("span", { html: badge(t("status.expired"), "danger") }));
    else if (ex.key === "expiring") wrap.append(el("span", { html: badge(ex.label, "warn") }));
    return wrap;
  }
  function actions(d) {
    return el("div", { class: "flex justify-end gap-1.5" }, [
      iconButton(ICON.edit, t("common.edit"), () => drugForm(d), { color: "blue" }),
      iconButton(ICON.restock, t("drugs.restockAdd"), () => restock(d), { color: "teal" }),
      iconButton('<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>', t("adj.action"), () => adjust(d), { color: "amber" }),
      iconButton(ICON.remove, t("common.remove"), () => remove(d), { color: "red" }),
    ]);
  }

  function paint() {
    if (!drugs) return;
    const rows = drugs.filter((d) => d.isActive !== false)
      .filter((d) => !q || [d.name, d.genericName, d.brand, d.barcode].some((x) => (x || "").toLowerCase().includes(q)))
      .filter((d) => !category || d.category === category)
      .filter((d) => {
        if (!status) return true;
        if (status === "controlled") return !!d.isControlled;
        const k = stockStatus(d).key;
        return status === "in" ? k === "ok" : k === status;
      })
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""));
    listHost.replaceChildren(table([
      { label: t("drugs.colName"), render: (d) => el("div", {}, [
          el("div", { class: "font-semibold text-ink" }, d.name || "—"),
          d.genericName ? el("div", { class: "text-xs text-soft" }, d.genericName) : null]) },
      { label: t("drugs.colCategory"), render: (d) => d.category || "—" },
      { label: t("drugs.colStock"), render: stockCell },
      { label: t("drugs.colPrice"), render: (d) => money(d.sellingPrice) },
      { label: t("drugs.colExpiry"), render: expiryCell },
      { label: t("drugs.colFlags"), html: true, render: (d) => d.isControlled ? badge(t("status.controlled"), "warn") : "" },
      { label: "", render: actions },
    ], rows, { empty: t("drugs.empty"), emptyHint: t("drugs.emptyHint") }));
  }

  const off = watch(pid, "drugs", { onData: (d) => { drugs = d; paint(); }, onError: () => { drugs = []; paint(); } });
  return off;
}
