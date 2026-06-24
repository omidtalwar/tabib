/** Suppliers — live list + create/edit. Fields mirror supplier_isar.dart. */
import { watch, create, update } from "../repo.js";
import { el, table, searchInput, toolbar, loading, formModal, toast, iconButton, ICON } from "../ui.js";

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let rows = null, q = "";

  async function form(existing) {
    const ok = await formModal({
      title: existing ? "Edit supplier" : "Add supplier",
      values: existing ? { ...existing, itemsSupplied: (existing.itemsSupplied || []).join(", ") } : {},
      fields: [
        { name: "name", label: "Name", required: true },
        { name: "contactName", label: "Contact person" },
        { name: "phone", label: "Phone", type: "tel" },
        { name: "email", label: "Email", type: "email" },
        { name: "address", label: "Address", full: true },
        { name: "itemsSupplied", label: "Items supplied", help: "Comma-separated", full: true },
        { name: "notes", label: "Notes", type: "textarea", full: true },
      ],
      onSubmit: async (d) => {
        const payload = {
          name: d.name, contactName: d.contactName, phone: d.phone, email: d.email,
          address: d.address, notes: d.notes,
          itemsSupplied: (d.itemsSupplied || "").split(",").map((s) => s.trim()).filter(Boolean),
        };
        if (existing) await update(pid, "suppliers", existing.firestoreId || existing.id, payload);
        else await create(pid, "suppliers", { ...payload, createdAt: new Date().toISOString() });
      },
    });
    if (ok) toast(existing ? "Supplier updated" : "Supplier added", { type: "ok" });
  }

  const addBtn = el("button", { class: "btn-primary", onclick: () => form(null) }, "+ Add supplier");
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar("Suppliers", el("div", { class: "flex gap-2" }, [searchInput("Search name or contact…", (v) => { q = v; paint(); }), addBtn])),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((s) => !q || [s.name, s.contactName, s.phone].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""));
    host.replaceChildren(table([
      { label: "Name", render: (s) => s.name || "—" },
      { label: "Contact", render: (s) => s.contactName || "—" },
      { label: "Phone", render: (s) => s.phone || "—" },
      { label: "Email", render: (s) => s.email || "—" },
      { label: "Address", render: (s) => s.address || "—" },
      { label: "", render: (s) => el("div", { class: "flex justify-end" }, iconButton(ICON.edit, "Edit", () => form(s), { color: "blue" })) },
    ], filtered, { empty: "No suppliers yet", emptyHint: "Add your first supplier with the button above." }));
  }

  return watch(pid, "suppliers", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
