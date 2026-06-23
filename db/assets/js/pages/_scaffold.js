/**
 * Shared helper for Phase-1 page scaffolds. The shell, routing, auth, rules,
 * and the generic data layer (repo.js) are all real. What's intentionally NOT
 * wired yet is per-collection field binding — that waits on docs/REFERENCE.md
 * so we mirror the app's *_model.dart exactly instead of inventing field names.
 */
import { el } from "../ui.js";

export function pendingNotice(title, { responsibility, fields = [], operations = [] }) {
  return el("div", { class: "space-y-5" }, [
    el("div", { class: "card" }, [
      el("p", { class: "text-sm text-soft" }, responsibility),
      el("div", { class: "mt-4 rounded-xl bg-brand-50 px-4 py-3" }, [
        el("p", { class: "text-sm font-semibold text-brand-800" }, "Scaffolded — data binding pending"),
        el("p", { class: "mt-1 text-sm text-brand-700" },
          "Routing, auth and the live data layer are in place. This page binds to Firestore once docs/REFERENCE.md confirms the exact field names from the app's *_model.dart."),
      ]),
      fields.length ? el("div", { class: "mt-4" }, [
        el("p", { class: "label" }, "Fields it will use (confirm against REFERENCE):"),
        el("div", { class: "flex flex-wrap gap-1.5" },
          fields.map((f) => el("code", { class: "rounded bg-line px-2 py-0.5 text-xs text-ink" }, f))),
      ]) : null,
      operations.length ? el("div", { class: "mt-4" }, [
        el("p", { class: "label" }, "Operations:"),
        el("ul", { class: "list-disc ps-5 text-sm text-soft space-y-1" },
          operations.map((o) => el("li", {}, o))),
      ]) : null,
    ]),
  ]);
}
