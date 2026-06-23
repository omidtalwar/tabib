/**
 * Settings — pharmacy profile (read-only mirror of pharmacies/{id}), staff
 * management (admin only → calls createPharmacyStaff / setPharmacyClaim Cloud
 * Functions), and currency/locale/RTL.
 */
import { el } from "../ui.js";
import { pendingNotice } from "./_scaffold.js";

export default function render(outlet, ctx) {
  const isAdmin = ctx.session.role === "admin";

  const roleNote = el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, "Your access"),
    el("p", { class: "mt-1 text-sm text-soft" },
      `Signed in as ${ctx.session.email || "—"} · role: ${ctx.session.role || "unknown"} · pharmacy: ${ctx.pharmacyId}`),
    el("p", { class: "mt-2 text-sm " + (isAdmin ? "text-ok" : "text-soft") },
      isAdmin ? "Admin — staff management will be enabled here." : "Staff — staff management is admin-only."),
  ]);

  outlet.append(el("div", { class: "space-y-5" }, [
    roleNote,
    pendingNotice("Settings", {
      responsibility:
        "Pharmacy profile (read-only mirror of pharmacies/{id}). Staff management for admins: invite staff = createPharmacyStaff(email, password, pharmacyId, role) Cloud Function. Currency / locale / RTL preferences (RTL toggle is already live in the header).",
      fields: ["pharmacies/{id}.name", "pharmacies/{id}.mode", "user_practices/{uid}"],
      operations: [
        "read pharmacy doc (rules: members only)",
        "admin: httpsCallable('createPharmacyStaff') / ('setPharmacyClaim')",
        "store currency/locale in pharmacy_settings/{uid}",
      ],
    }),
  ]));
}
