/** Drugs / Catalog — live list + create/edit/restock/soft-delete.
 * Writes mirror drug_isar.dart wire shape (ISO dates, firestoreId, isDirty);
 * offline writes are queued by the Firestore SDK and replay on reconnect. */
import { watch, create, update, softDelete, adjustStock, readAll, toDate, toIso } from "../repo.js";
import { el, table, searchInput, toolbar, badge, money, fmtDate, stockStatus, expiryStatus, loading, formModal, confirmDialog, toast } from "../ui.js";

const CATEGORIES = ["Antibiotic", "Analgesic", "Antiseptic", "Vitamin", "Cardiac", "Diabetes", "Respiratory", "Other"];
const UNITS = ["Tablet", "Capsule", "Syrup", "Injection", "Cream", "Drops", "Sachet", "Other"];

function isoToDateInput(iso) {
  const d = toDate(iso);
  return d ? d.toISOString().slice(0, 10) : "";
}

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let drugs = null, q = "";

  async function drugForm(existing) {
    const suppliers = await readAll(pid, "suppliers").catch(() => []);
    const supplierOpts = [{ value: "", label: "— none —" }, ...suppliers.map((s) => ({ value: s.firestoreId || s.id, label: s.name || "Unnamed" }))];
    const ok = await formModal({
      title: existing ? "Edit drug" : "Add drug",
      values: existing ? { ...existing, expiryDate: isoToDateInput(existing.expiryDate) } : { unit: "Tablet", category: "Other", reorderThreshold: 10, isActive: true },
      fields: [
        { name: "name", label: "Name", required: true },
        { name: "genericName", label: "Generic name" },
        { name: "brand", label: "Brand" },
        { name: "category", label: "Category", type: "select", options: CATEGORIES, default: "Other" },
        { name: "unit", label: "Unit", type: "select", options: UNITS, default: "Tablet" },
        { name: "barcode", label: "Barcode" },
        { name: "stockQuantity", label: "Stock quantity", type: "number", min: "0" },
        { name: "reorderThreshold", label: "Reorder threshold", type: "number", min: "0" },
        { name: "unitPrice", label: "Unit (cost) price", type: "number", step: "0.01", min: "0" },
        { name: "sellingPrice", label: "Selling price", type: "number", step: "0.01", min: "0" },
        { name: "expiryDate", label: "Expiry date", type: "date" },
        { name: "batchNumber", label: "Batch number" },
        { name: "supplierId", label: "Supplier", type: "select", options: supplierOpts },
        { name: "description", label: "Description", type: "textarea", full: true },
        { name: "isControlled", label: "Controlled drug", type: "checkbox", help: "Requires extra care when selling" },
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
        if (existing) await update(pid, "drugs", existing.firestoreId || existing.id, payload);
        else await create(pid, "drugs", { ...payload, isActive: true });
      },
    });
    if (ok) toast(existing ? "Drug updated" : "Drug added", { type: "ok" });
  }

  async function restock(d) {
    const ok = await formModal({
      title: `Restock — ${d.name}`,
      submitLabel: "Add stock",
      values: { batchNumber: d.batchNumber, expiryDate: isoToDateInput(d.expiryDate) },
      fields: [
        { name: "qty", label: "Quantity to add", type: "number", required: true, min: "1" },
        { name: "batchNumber", label: "Batch number" },
        { name: "expiryDate", label: "Expiry date", type: "date" },
      ],
      onSubmit: async (v) => {
        const extra = {};
        if (v.batchNumber) extra.batchNumber = v.batchNumber;
        if (v.expiryDate) extra.expiryDate = toIso(v.expiryDate);
        extra.lastSyncedAt = new Date().toISOString();
        await adjustStock(pid, d.firestoreId || d.id, Number(v.qty), extra);
      },
    });
    if (ok) toast("Stock updated", { type: "ok" });
  }

  async function remove(d) {
    if (!(await confirmDialog(`Remove “${d.name}” from the catalog? It will be hidden, not deleted.`, { confirmLabel: "Remove", danger: true }))) return;
    await softDelete(pid, "drugs", d.firestoreId || d.id);
    toast("Drug removed", { type: "ok" });
  }

  const addBtn = el("button", { class: "btn-primary", onclick: () => drugForm(null) }, "+ Add drug");
  const search = searchInput("Search name, generic, brand, barcode…", (v) => { q = v; paint(); });
  const listHost = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar("Drugs", el("div", { class: "flex gap-2" }, [search, addBtn])),
    listHost,
  ]));

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
    const wrap = el("span", {}, fmtDate(date) + " ");
    if (ex.key === "expired") wrap.append(el("span", { html: badge("Expired", "danger") }));
    else if (ex.key === "expiring") wrap.append(el("span", { html: badge(ex.label, "warn") }));
    return wrap;
  }
  function actions(d) {
    return el("div", { class: "flex gap-1.5 justify-end" }, [
      el("button", { class: "btn-ghost px-2.5 py-1 text-xs", onclick: () => drugForm(d) }, "Edit"),
      el("button", { class: "btn-ghost px-2.5 py-1 text-xs", onclick: () => restock(d) }, "Restock"),
      el("button", { class: "btn-ghost px-2.5 py-1 text-xs", onclick: () => remove(d) }, "Remove"),
    ]);
  }

  function paint() {
    if (!drugs) return;
    const rows = drugs.filter((d) => d.isActive !== false)
      .filter((d) => !q || [d.name, d.genericName, d.brand, d.barcode].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""));
    listHost.replaceChildren(table([
      { label: "Name", render: (d) => el("div", {}, [
          el("div", { class: "font-semibold text-ink" }, d.name || "—"),
          d.genericName ? el("div", { class: "text-xs text-soft" }, d.genericName) : null]) },
      { label: "Category", render: (d) => d.category || "—" },
      { label: "Stock", render: stockCell },
      { label: "Price", render: (d) => money(d.sellingPrice) },
      { label: "Expiry", render: expiryCell },
      { label: "Flags", html: true, render: (d) => d.isControlled ? badge("Controlled", "warn") : "" },
      { label: "", render: actions },
    ], rows, { empty: "No drugs yet", emptyHint: "Add your first drug with the button above." }));
  }

  const off = watch(pid, "drugs", { onData: (d) => { drugs = d; paint(); }, onError: () => { drugs = []; paint(); } });
  return off;
}
