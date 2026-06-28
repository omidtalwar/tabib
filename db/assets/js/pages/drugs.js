/** Drugs / Catalog — live list + create/edit/restock/soft-delete. */
import { watch, create, update, softDelete, adjustStock, recordAdjustment, uuid, readAll, toDate, toIso, commitLocal } from "../repo.js";
import { el, table, searchInput, toolbar, badge, money, fmtDate, fmtDateGreg, stockStatus, expiryStatus, loading, formModal, confirmDialog, toast, iconButton, ICON, filterSelect } from "../ui.js";
import { t } from "../i18n.js";

// Full therapeutic category list. Keep these strings identical to the Flutter
// app (lib/features/pharmacy/screens/drugs/add_edit_drug_screen.dart) so the
// `category` field stays consistent across web + mobile. Change log:
// Flutter app lib/pages/manage/DRUG_CATEGORIES.md.
const CATEGORIES = [
  "Analgesics & Antipyretics",
  "NSAIDs & Anti-inflammatory",
  "Opioid Analgesics",
  "Antibiotics",
  "Antivirals",
  "Antifungals",
  "Antiparasitic & Antimalarial",
  "Antitubercular",
  "Cardiovascular (Hypertension, Heart)",
  "Lipid-Lowering (Statins)",
  "Anticoagulants & Antiplatelets",
  "Respiratory (Asthma, COPD, Cough & Cold)",
  "Antihistamines & Anti-allergic",
  "Gastrointestinal (Antacids, PPIs, Antiemetics)",
  "Laxatives & Antidiarrheals",
  "Antidiabetics",
  "Thyroid & Hormonal",
  "Corticosteroids",
  "Contraceptives & Reproductive Health",
  "CNS & Psychiatric (Antidepressants, Antipsychotics)",
  "Anticonvulsants & Antiepileptics",
  "Sedatives & Anxiolytics",
  "Muscle Relaxants",
  "Vitamins, Minerals & Supplements",
  "Hematinics (Iron, B12, Folic Acid)",
  "Dermatological (Topical)",
  "Ophthalmic (Eye)",
  "ENT (Ear, Nose, Throat)",
  "Urological & Renal",
  "Vaccines & Immunologicals",
  "Oncology / Cytotoxic",
  "IV Fluids & Electrolytes",
  "Other / Miscellaneous",
];
const DEFAULT_CATEGORY = "Other / Miscellaneous";
// Dosage forms / units. Keep identical to the Flutter app (_kUnits in
// add_edit_drug_screen.dart) so the `unit` field matches across web + mobile.
const UNITS = [
  "Tablet", "Capsule", "Syrup", "Injection", "Cream", "Ointment", "Drops",
  "Inhaler", "Spray", "Gel", "Lotion", "Suppository", "Sachet", "Powder",
  "Solution", "Patch", "Other",
];

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let drugs = null, q = "", category = "", status = "";

  async function drugForm(existing) {
    const suppliers = await readAll(pid, "suppliers").catch(() => []);
    const supplierOpts = [{ value: "", label: t("drugs.supNone") }, ...suppliers.map((s) => ({ value: s.firestoreId || s.id, label: s.name || "Unnamed" }))];
    // Pre-open an optional section in edit mode when it already holds data.
    const hasMore = !!(existing && (existing.genericName || existing.brand || existing.barcode ||
      existing.unitPrice || existing.description ||
      (existing.reorderThreshold != null && existing.reorderThreshold !== 10)));
    const hasBatch = !!(existing && (existing.expiryDate || existing.batchNumber || existing.supplierId || existing.isControlled));
    const ok = await formModal({
      title: existing ? t("drugs.editTitle") : t("drugs.addTitle"),
      wide: true,
      values: existing ? { ...existing } : { unit: "Tablet", category: DEFAULT_CATEGORY, reorderThreshold: 10, isActive: true },
      fields: [
        // Essentials — always visible.
        { name: "name", label: t("drugs.fName"), required: true },
        { name: "category", label: t("drugs.fCategory"), type: "combo", options: CATEGORIES, placeholder: t("drugs.categoryPh") },
        { name: "unit", label: t("drugs.fUnit"), type: "select", options: UNITS, default: "Tablet" },
        { name: "stockQuantity", label: t("drugs.fStock"), type: "number", min: "0" },
        { name: "sellingPrice", label: t("drugs.fSellingPrice"), type: "number", step: "0.01", min: "0" },

        // Optional — revealed by a switch so the form stays short.
        { name: "_more", type: "section", label: t("drugs.secMore"), hint: t("common.optional"), default: hasMore,
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>' },
        { name: "genericName", label: t("drugs.fGeneric"), section: "_more" },
        { name: "brand", label: t("drugs.fBrand"), section: "_more" },
        { name: "barcode", label: t("drugs.fBarcode"), section: "_more" },
        { name: "unitPrice", label: t("drugs.fUnitPrice"), type: "number", step: "0.01", min: "0", section: "_more" },
        { name: "reorderThreshold", label: t("drugs.fReorder"), type: "number", min: "0", section: "_more" },
        { name: "description", label: t("drugs.fDescription"), type: "textarea", full: true, section: "_more" },

        { name: "_batch", type: "section", label: t("drugs.secBatch"), hint: t("common.optional"), default: hasBatch,
          icon: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>' },
        { name: "expiryDate", label: t("drugs.fExpiry"), type: "gdate", section: "_batch" },
        { name: "batchNumber", label: t("drugs.fBatch"), section: "_batch" },
        { name: "supplierId", label: t("drugs.fSupplier"), type: "select", options: supplierOpts, section: "_batch" },
        { name: "isControlled", label: t("drugs.fControlled"), type: "checkbox", help: t("drugs.fControlledHelp"), section: "_batch" },
      ],
      onSubmit: async (d) => {
        const payload = {
          name: d.name, genericName: d.genericName, brand: d.brand, category: d.category || DEFAULT_CATEGORY,
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
