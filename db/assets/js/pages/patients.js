/** Patients — live list + create/edit. Fields mirror patient_isar.dart. */
import { watch, create, update, toIso, toDate } from "../repo.js";
import { el, table, searchInput, toolbar, fmtDate, loading, formModal, toast, iconButton, ICON } from "../ui.js";

const GENDERS = ["Male", "Female", "Other"];
const BLOOD = ["", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];

function isoToDateInput(iso) { const d = toDate(iso); return d ? d.toISOString().slice(0, 10) : ""; }

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let rows = null, q = "";

  async function form(existing) {
    const ok = await formModal({
      title: existing ? "Edit patient" : "Add patient",
      values: existing
        ? { ...existing, dateOfBirth: isoToDateInput(existing.dateOfBirth), allergies: (existing.allergies || []).join(", ") }
        : { gender: "Other" },
      fields: [
        { name: "fullName", label: "Full name", required: true },
        { name: "phone", label: "Phone", type: "tel" },
        { name: "gender", label: "Gender", type: "select", options: GENDERS, default: "Other" },
        { name: "dateOfBirth", label: "Date of birth", type: "date" },
        { name: "bloodGroup", label: "Blood group", type: "select", options: BLOOD.map((b) => ({ value: b, label: b || "—" })) },
        { name: "emergencyContact", label: "Emergency contact", type: "tel" },
        { name: "insuranceId", label: "Insurance ID" },
        { name: "address", label: "Address", full: true },
        { name: "allergies", label: "Allergies", help: "Comma-separated", full: true },
        { name: "notes", label: "Notes", type: "textarea", full: true },
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
    if (ok) toast(existing ? "Patient updated" : "Patient added", { type: "ok" });
  }

  const addBtn = el("button", { class: "btn-primary", onclick: () => form(null) }, "+ Add patient");
  const host = el("div", {}, loading());
  outlet.append(el("div", { class: "space-y-5" }, [
    toolbar("Patients", el("div", { class: "flex gap-2" }, [searchInput("Search name or phone…", (v) => { q = v; paint(); }), addBtn])),
    host,
  ]));

  function paint() {
    if (!rows) return;
    const filtered = rows
      .filter((p) => !q || [p.fullName, p.phone].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (a.fullName || "").localeCompare(b.fullName || ""));
    host.replaceChildren(table([
      { label: "Name", render: (p) => p.fullName || "—" },
      { label: "Phone", render: (p) => p.phone || "—" },
      { label: "Gender", render: (p) => p.gender || "—" },
      { label: "Blood", render: (p) => p.bloodGroup || "—" },
      { label: "Allergies", render: (p) => Array.isArray(p.allergies) && p.allergies.length ? p.allergies.join(", ") : "—" },
      { label: "Added", render: (p) => fmtDate(toDate(p.createdAt)) },
      { label: "", render: (p) => el("div", { class: "flex justify-end" }, iconButton(ICON.edit, "Edit", () => form(p))) },
    ], filtered, { empty: "No patients yet", emptyHint: "Add your first patient with the button above." }));
  }

  return watch(pid, "patients", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
