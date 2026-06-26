/** Patients — list + create/edit, and a Credit (receivables) view where you
 * record customer credit-payments. Outstanding = credit sales − payments, by name. */
import { watch, create, update, readAll, toIso, toDate, uuid, commitLocal } from "../repo.js";
import { el, table, searchInput, toolbar, money, fmtDate, loading, formModal, toast, iconButton, ICON } from "../ui.js";
import { t } from "../i18n.js";

const GENDERS = ["Male", "Female", "Other"];
const BLOOD = ["", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"];
const PAYMENTS = ["cash", "bank", "card"];
const num = (x) => Number(x) || 0;

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  const isAdmin = ctx.session.role === "admin";
  let rows = null, q = "", mode = "list";

  const root = el("div", { class: "space-y-5" }, loading());
  outlet.append(root);

  /* ---------- patient form ---------- */
  async function form(existing) {
    const ok = await formModal({
      title: existing ? t("pat.editTitle") : t("pat.addTitle"),
      values: existing ? { ...existing, allergies: (existing.allergies || []).join(", ") } : { gender: "Other" },
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
        if (existing) await commitLocal(update(pid, "patients", existing.firestoreId || existing.id, payload));
        else await commitLocal(create(pid, "patients", { ...payload, createdAt: new Date().toISOString() }));
      },
    });
    if (ok) toast(existing ? t("pat.updated") : t("pat.added"), { type: "ok" });
  }

  /* ---------- shells ---------- */
  function tabs() {
    const tab = (key, label) => el("button", {
      class: "rounded-lg px-3 py-1.5 text-sm font-semibold transition " + (mode === key ? "bg-brand-50 text-brand-700" : "text-soft hover:text-ink"),
      onclick: () => { mode = key; paint(); },
    }, label);
    return el("div", { class: "inline-flex rounded-xl border border-line bg-white p-1" }, [tab("list", t("cr.tabPatients")), isAdmin ? tab("credit", t("cr.tabCredit")) : null]);
  }

  function listView() {
    const addBtn = el("button", { class: "btn-primary", onclick: () => form(null) }, t("pat.add"));
    const host = el("div", {}, loading());
    const wrap = el("div", { class: "space-y-5" }, [
      toolbar(t("pat.title"), el("div", { class: "flex flex-wrap items-center gap-2" }, [tabs(), searchInput(t("pat.searchPh"), (v) => { q = v; drawList(); }), addBtn])),
      host,
    ]);
    function drawList() {
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
    drawList();
    return wrap;
  }

  function creditView() {
    const host = el("div", {}, loading());
    const wrap = el("div", { class: "space-y-5" }, [
      toolbar(t("cr.title"), tabs()),
      host,
    ]);

    async function load() {
      const [sales, payments] = await Promise.all([readAll(pid, "sales"), readAll(pid, "customer_payments")]).catch(() => [[], []]);
      const by = {};
      for (const s of sales) {
        if ((s.paymentMethod || "") !== "credit") continue;
        const name = s.patientName || t("dash.walkIn");
        (by[name] = by[name] || { credit: 0, paid: 0 }).credit += num(s.total);
      }
      for (const p of payments) {
        const name = p.patientName || t("dash.walkIn");
        (by[name] = by[name] || { credit: 0, paid: 0 }).paid += num(p.amount);
      }
      const list = Object.entries(by).map(([name, v]) => ({ name, ...v, out: v.credit - v.paid }))
        .filter((r) => r.out > 0.0001).sort((a, b) => b.out - a.out);

      host.replaceChildren(table([
        { label: t("cr.colCustomer"), render: (r) => r.name },
        { label: t("cr.colSales"), render: (r) => money(r.credit) },
        { label: t("cr.colPaid"), render: (r) => money(r.paid) },
        { label: t("cr.colOutstanding"), render: (r) => el("span", { class: "font-semibold text-danger" }, money(r.out)) },
        { label: "", render: (r) => el("div", { class: "flex justify-end" }, el("button", { class: "btn-ghost px-2.5 py-1 text-xs", onclick: () => recordPayment(r.name, r.out) }, t("cr.record"))) },
      ], list, { empty: t("cr.none") }));
    }

    async function recordPayment(name, outstanding) {
      const ok = await formModal({
        title: t("cr.recordTitle", { name }),
        submitLabel: t("cr.record"),
        values: { amount: Math.round(outstanding * 100) / 100, paymentMethod: "cash" },
        fields: [
          { name: "amount", label: t("cr.amount"), type: "number", step: "0.01", min: "0", required: true },
          { name: "paymentMethod", label: t("cr.method"), type: "select", options: PAYMENTS, default: "cash" },
          { name: "note", label: t("cr.note"), type: "textarea", full: true },
        ],
        onSubmit: async (v) => {
          await commitLocal(create(pid, "customer_payments", {
            id: Date.now(), patientName: name, amount: num(v.amount), paymentMethod: v.paymentMethod || "cash",
            note: v.note || "", date: new Date().toISOString(), recordedBy: ctx.session.email || "",
            createdAt: new Date().toISOString(), isDirty: false,
          }));
        },
      });
      if (ok) { toast(t("cr.recorded"), { type: "ok" }); load(); }
    }

    load();
    return wrap;
  }

  function paint() {
    if (mode === "credit" && !isAdmin) mode = "list";
    root.replaceChildren(mode === "credit" ? creditView() : listView());
  }

  return watch(pid, "patients", { onData: (d) => { rows = d; paint(); }, onError: () => { rows = []; paint(); } });
}
