/** Prescriptions — list + create, and "Dispense" which hands the items to the
 * POS (sales) to complete as a sale. */
import { watch, create, toDate, uuid } from "../repo.js";
import { el, table, searchInput, toolbar, badge, fmtDate, money, loading, toast } from "../ui.js";
import { t } from "../i18n.js";

const STATUS_KIND = { pending: "warn", dispensed: "ok", partially_dispensed: "warn", cancelled: "muted", expired: "danger" };
const num = (x) => Number(x) || 0;
const parseItems = (p) => { try { return JSON.parse(p.itemsJson || "[]"); } catch { return []; } };

export default function render(outlet, ctx) {
  const pid = ctx.pharmacyId;
  let rows = null, drugs = [], q = "", mode = "list";
  const cart = []; // { drugId, drugName, dosage, quantity }
  let patientName = "", doctorName = "", doctorPhone = "", notes = "";

  const root = el("div", { class: "space-y-5" }, loading());
  outlet.append(root);

  function dispense(p) {
    const items = parseItems(p).map((it) => ({ drugId: it.drugId, drugName: it.drugName, quantity: num(it.quantity) || 1 }));
    try { localStorage.setItem("tabib_dispense", JSON.stringify({ prescriptionId: p.firestoreId || p.id, patientName: p.patientName || "", items })); } catch {}
    toast(t("rx.dispensed"), { type: "ok" });
    location.hash = `#/p/${pid}/sales`;
  }

  async function save() {
    if (!cart.length) { toast(t("rx.cartEmpty"), { type: "warn" }); return; }
    const items = cart.map((c) => ({ drugId: c.drugId, drugName: c.drugName, dosage: c.dosage || "", frequency: "", durationDays: 0, quantity: num(c.quantity) || 1, refillsAllowed: 0, refillsUsed: 0 }));
    await create(pid, "prescriptions", {
      id: Date.now(), patientId: "", patientName, doctorName, doctorPhone,
      itemsJson: JSON.stringify(items), status: "pending",
      issuedDate: new Date().toISOString(), expiryDate: "", notes,
      dispensedAt: "", dispensedBy: "", isDirty: false, createdAt: new Date().toISOString(),
    });
    toast(t("rx.saved"), { type: "ok" });
    cart.length = 0; patientName = doctorName = doctorPhone = notes = "";
    mode = "list"; paint();
  }

  function newView() {
    const results = el("div", { class: "mt-2 grid gap-1" });
    const search = searchInput(t("rx.searchDrug"), (v) => {
      results.replaceChildren();
      if (!v) return;
      drugs.filter((d) => d.isActive !== false && (d.name || "").toLowerCase().includes(v)).slice(0, 8)
        .forEach((d) => results.append(el("button", { class: "rounded-lg border border-line px-3 py-2 text-start text-sm hover:bg-brand-50", onclick: () => { if (!cart.find((c) => c.drugId === (d.firestoreId || d.id))) { cart.push({ drugId: d.firestoreId || d.id, drugName: d.name, dosage: "", quantity: 1 }); draw(); } } }, el("span", { class: "font-semibold text-ink" }, d.name))));
    });
    const cartHost = el("div", {});
    function draw() {
      if (!cart.length) { cartHost.replaceChildren(el("p", { class: "py-6 text-center text-sm text-soft" }, t("rx.cartEmpty"))); return; }
      cartHost.replaceChildren(el("table", { class: "table" }, [
        el("thead", {}, el("tr", {}, [t("rx.cDrug"), t("rx.cDosage"), t("rx.cQty"), ""].map((h) => el("th", {}, h)))),
        el("tbody", {}, cart.map((c, i) => el("tr", {}, [
          el("td", {}, c.drugName),
          el("td", {}, el("input", { class: "field py-1", value: c.dosage, placeholder: "e.g. 1×3", oninput: (e) => c.dosage = e.target.value })),
          el("td", {}, el("input", { type: "number", min: "1", value: String(c.quantity), class: "field w-20 py-1", onchange: (e) => { c.quantity = Math.max(1, num(e.target.value)); } })),
          el("td", {}, el("button", { class: "btn-ghost px-2 py-1 text-xs", onclick: () => { cart.splice(i, 1); draw(); } }, "✕")),
        ]))),
      ]));
    }
    draw();
    return el("div", { class: "card space-y-3" }, [
      el("div", { class: "grid gap-3 sm:grid-cols-2" }, [
        labeled(t("rx.fPatient"), el("input", { class: "field", value: patientName, oninput: (e) => patientName = e.target.value })),
        labeled(t("rx.fDoctor"), el("input", { class: "field", value: doctorName, oninput: (e) => doctorName = e.target.value })),
        labeled(t("rx.fDoctorPhone"), el("input", { class: "field", type: "tel", value: doctorPhone, oninput: (e) => doctorPhone = e.target.value })),
        labeled(t("pat.fNotes"), el("input", { class: "field", value: notes, oninput: (e) => notes = e.target.value })),
      ]),
      search, results,
      el("div", { class: "overflow-x-auto" }, cartHost),
      el("button", { class: "btn-primary w-full", onclick: save }, t("rx.save")),
    ]);
  }

  function listView() {
    if (!rows) return loading();
    const filtered = rows
      .filter((p) => !q || [p.patientName, p.doctorName].some((x) => (x || "").toLowerCase().includes(q)))
      .sort((a, b) => (toDate(b.issuedDate)?.getTime() || 0) - (toDate(a.issuedDate)?.getTime() || 0));
    return table([
      { label: t("rx.colPatient"), render: (p) => p.patientName || "—" },
      { label: t("rx.colDoctor"), render: (p) => p.doctorName || "—" },
      { label: t("rx.colItems"), render: (p) => String(parseItems(p).length) },
      { label: t("rx.colStatus"), html: true, render: (p) => badge((p.status || "pending").replace("_", " "), STATUS_KIND[p.status] || "muted") },
      { label: t("rx.colIssued"), render: (p) => fmtDate(toDate(p.issuedDate)) },
      { label: "", render: (p) => (p.status === "dispensed" || p.status === "cancelled") ? "" : el("div", { class: "flex justify-end" }, el("button", { class: "btn-ghost px-2.5 py-1 text-xs", onclick: () => dispense(p) }, t("rx.dispense"))) },
    ], filtered, { empty: t("rx.empty"), emptyHint: t("rx.emptyHint") });
  }

  function paint() {
    const toggle = mode === "new"
      ? el("button", { class: "btn-ghost", onclick: () => { mode = "list"; paint(); } }, t("rx.back"))
      : el("div", { class: "flex flex-wrap gap-2" }, [searchInput(t("rx.searchPh"), (v) => { q = v; paint(); }), el("button", { class: "btn-primary", onclick: () => { mode = "new"; paint(); } }, "+ " + t("rx.add"))]);
    root.replaceChildren(toolbar(mode === "new" ? t("rx.addTitle") : t("rx.title"), toggle), mode === "new" ? newView() : listView());
  }
  function labeled(l, n) { return el("label", { class: "block" }, [el("span", { class: "label" }, l), n]); }

  paint();
  const offD = watch(pid, "drugs", { onData: (d) => { drugs = d; }, onError: () => { drugs = []; } });
  const offR = watch(pid, "prescriptions", { onData: (d) => { rows = d; if (mode === "list") paint(); }, onError: () => { rows = []; if (mode === "list") paint(); } });
  return () => { offD(); offR(); };
}
