/** Patients — live list + create/edit. */
import { watch, create, update, toIso, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, fmtDate, loading, formModal, toast, iconButton, ICON } from "../ui.js";
import { t } from "../i18n.js";

const GENDERS = ["Male", "Female", "Other"];
const BLOOD = ["", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let rows = null, q = "";

  async function form(existing) {
    const ok = await formModal({
      title: existing ? t("pat.editTitle") : t("pat.addTitle"),
      values: existing
        ? { ...existing, allergies: (existing.allergies || []).join(", ") }
        : { gender: "Other" },
      fields: [
        { name: "fullName", label: t("pat.fName"), required: true },
        { name: "phone", label: t("pat.fPhone"), type: "tel" },
        { name: "gender", label: t("pat.fGender"), type: "select", options: GENDERS, default: "Other" },
        { name: "dateOfBirth", label: t("pat.fDob"), type: "jdate" },
        { name: "bloodGroup", label: t("pat.fBlood"), type: "select", options: BLOOD.map((b) => ({ value: b, label: b || "—" })) },
        { name: "emergencyContact", label: t("pat.fEmergency"), type: "tel" },
        { name: "insuranceId", label: t("pat.fInsurance") },
        { name: "address", label: t("pat.fAddress"), full: true },
        { name: "allergies", label: t("pat.fAllergies"), help: t("pat.fAllergiesHelp"), full: true },
        { name: "notes", label: t("pat.fNotes"), type: "textarea", full: true },
      ],
      onSubmit: async (d) => {
        const payload = {
          fullName: d.fullName, phone: d.phone, gender: d.gender || "Other",
          dateOfBirth: toIso(d.dateOfBirth), address: d.address, bloodGroup: d.bloodGroup,
          emergencyContact: d.emergencyContact, insuranceId: d.insuranceId, notes: d.notes,
          allergies: (d.allergies || "").split(",").map((s) => s.trim()).filter(Boolean),
        };
        if (existing) await update(pid, "patients", existing.firestoreId || existing.id, payload);
        else await create(pid, "patients", { ...payload, createdAt: new Date().toISOString() });
      },
    });
    if (ok) toast(existing ? t("pat.updated") : t("pat.added"), { type: "ok" });
  }

  const addBtn = el("button", { class: "btn-primary", onclick: () => form(null) }, t("pat.add"));
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar(t("pat.title"), el("div", { class: "flex flex-wrap gap-2" }, [searchInput(t("pat.searchPh"), (v) => { q = v; paint(); }), addBtn])),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((p) => !q || [p.fullName, p.phone].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (a.fullName || "").localeCompare(b.fullName || ""));
    host.replaceChildren(table([
      { label: t("pat.colName"), render: (p) => p.fullName || "—" },
      { label: t("pat.colPhone"), render: (p) => p.phone || "—" },
      { label: t("pat.colGender"), render: (p) => p.gender || "—" },
      { label: t("pat.colBlood"), render: (p) => p.bloodGroup || "—" },
      { label: t("pat.colAllergies"), render: (p) => Array.isArray(p.allergies) && p.allergies.length ? p.allergies.join(", ") : "—" },
      { label: t("pat.colAdded"), render: (p) => fmtDate(toDate(p.createdAt)) },
      { label: "", render: (p) => el("div", { class: "flex justify-end" }, iconButton(ICON.edit, t("common.edit"), () => form(p), { color: "blue" })) },
    ], filtered, { empty: t("pat.empty"), emptyHint: t("pat.emptyHint") }));
  }

  return watch(pid, "patients", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
