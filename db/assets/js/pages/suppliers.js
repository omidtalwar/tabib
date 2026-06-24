/** Suppliers — live list + create/edit. */
import { watch, create, update } from "../repo.js";
import { el, table, searchInput, toolbar, loading, formModal, toast, iconButton, ICON } from "../ui.js";
import { t } from "../i18n.js";

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let rows = null, q = "";

  async function form(existing) {
    const ok = await formModal({
      title: existing ? t("sup.editTitle") : t("sup.addTitle"),
      values: existing ? { ...existing, itemsSupplied: (existing.itemsSupplied || []).join(", ") } : {},
      fields: [
        { name: "name", label: t("sup.fName"), required: true },
        { name: "contactName", label: t("sup.fContact") },
        { name: "phone", label: t("sup.fPhone"), type: "tel" },
        { name: "email", label: t("sup.fEmail"), type: "email" },
        { name: "address", label: t("sup.fAddress"), full: true },
        { name: "itemsSupplied", label: t("sup.fItems"), help: t("sup.fItemsHelp"), full: true },
        { name: "notes", label: t("sup.fNotes"), type: "textarea", full: true },
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
    if (ok) toast(existing ? t("sup.updated") : t("sup.added"), { type: "ok" });
  }

  const addBtn = el("button", { class: "btn-primary", onclick: () => form(null) }, t("sup.add"));
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar(t("sup.title"), el("div", { class: "flex flex-wrap gap-2" }, [searchInput(t("sup.searchPh"), (v) => { q = v; paint(); }), addBtn])),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((s) => !q || [s.name, s.contactName, s.phone].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""));
    host.replaceChildren(table([
      { label: t("sup.colName"), render: (s) => s.name || "—" },
      { label: t("sup.colContact"), render: (s) => s.contactName || "—" },
      { label: t("sup.colPhone"), render: (s) => s.phone || "—" },
      { label: t("sup.colEmail"), render: (s) => s.email || "—" },
      { label: t("sup.colAddress"), render: (s) => s.address || "—" },
      { label: "", render: (s) => el("div", { class: "flex justify-end" }, iconButton(ICON.edit, t("common.edit"), () => form(s), { color: "blue" })) },
    ], filtered, { empty: t("sup.empty"), emptyHint: t("sup.emptyHint") }));
  }

  return watch(pid, "suppliers", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
