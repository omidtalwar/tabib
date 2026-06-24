/**
 * ui.js — small DOM helpers: toasts, modal/confirm, formatters, badge helpers.
 * No framework. Plain, active-voice copy; empty states tell the user what to do.
 */

/* ---------------- escaping ---------------- */
export function esc(s) {
  return String(s ?? "").replace(/[&<>"']/g, (c) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
  }[c]));
}

/* ---------------- el helper ---------------- */
export function el(tag, attrs = {}, children = []) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === "class") node.className = v;
    else if (k === "html") node.innerHTML = v;
    else if (k.startsWith("on") && typeof v === "function") node.addEventListener(k.slice(2), v);
    else if (v != null) node.setAttribute(k, v);
  }
  for (const c of [].concat(children)) {
    if (c == null) continue;
    node.append(c.nodeType ? c : document.createTextNode(c));
  }
  return node;
}

/* ---------------- toast ---------------- */
let _toastHost;
export function toast(message, { type = "info", timeout = 3500 } = {}) {
  if (!_toastHost) {
    _toastHost = el("div", { class: "fixed bottom-4 inset-x-0 z-50 flex flex-col items-center gap-2 px-4 pointer-events-none" });
    document.body.append(_toastHost);
  }
  const colors = { info: "bg-ink", ok: "bg-ok", warn: "bg-warn", error: "bg-danger" };
  const t = el("div", {
    class: `pointer-events-auto rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-lg ${colors[type] || colors.info}`,
    role: "status",
  }, message);
  _toastHost.append(t);
  setTimeout(() => t.remove(), timeout);
}

/* ---------------- modal ---------------- */
/** Open a modal. `render(close)` returns a DOM node. Returns a close() fn. */
export function modal(render, { onClose } = {}) {
  const close = () => { overlay.remove(); document.removeEventListener("keydown", onKey); onClose && onClose(); };
  const onKey = (e) => { if (e.key === "Escape") close(); };
  const panel = el("div", { class: "card w-full max-w-lg max-h-[90vh] overflow-auto", role: "dialog", "aria-modal": "true" });
  panel.append(render(close));
  const overlay = el("div", {
    class: "fixed inset-0 z-40 flex items-center justify-center bg-ink/40 p-4",
    onclick: (e) => { if (e.target === overlay) close(); },
  }, panel);
  document.body.append(overlay);
  document.addEventListener("keydown", onKey);
  return close;
}

/** Confirm dialog → Promise<boolean>. */
export function confirmDialog(message, { confirmLabel = "Confirm", danger = false } = {}) {
  return new Promise((resolve) => {
    modal(
      (close) => el("div", {}, [
        el("p", { class: "text-ink" }, message),
        el("div", { class: "mt-5 flex justify-end gap-2" }, [
          el("button", { class: "btn-ghost", onclick: () => { close(); resolve(false); } }, "Cancel"),
          el("button", { class: danger ? "btn-danger" : "btn-primary", onclick: () => { close(); resolve(true); } }, confirmLabel),
        ]),
      ]),
      { onClose: () => resolve(false) }
    );
  });
}

/* ---------------- formatters ---------------- */
export function money(n, currency = "AFN", locale = "en-US") {
  const v = Number(n) || 0;
  try {
    return new Intl.NumberFormat(locale, { style: "currency", currency, maximumFractionDigits: 2 }).format(v);
  } catch {
    return `${v.toFixed(2)} ${currency}`;
  }
}

export function fmtDate(date, locale = "en-US") {
  if (!date) return "—";
  const d = date instanceof Date ? date : new Date(date);
  if (isNaN(d.getTime())) return "—";
  return new Intl.DateTimeFormat(locale, { year: "numeric", month: "short", day: "numeric" }).format(d);
}

export function daysUntil(date) {
  if (!date) return null;
  const d = date instanceof Date ? date : new Date(date);
  if (isNaN(d.getTime())) return null;
  return Math.ceil((d.getTime() - Date.now()) / 86400000);
}

/* ---------------- stock / expiry status (safety-critical) ---------------- */
/** Returns { key, label } for a drug's stock state. Field names per REFERENCE. */
export function stockStatus({ stockQuantity = 0, reorderThreshold = 0 } = {}) {
  if (stockQuantity <= 0) return { key: "out", label: "Out of stock" };
  if (stockQuantity <= reorderThreshold) return { key: "low", label: "Low stock" };
  return { key: "ok", label: "In stock" };
}

export function expiryStatus(expiryDate) {
  const d = daysUntil(expiryDate);
  if (d == null) return { key: "none", label: "" };
  if (d < 0) return { key: "expired", label: "Expired" };
  if (d <= 30) return { key: "expiring", label: `Expires in ${d}d` };
  return { key: "ok", label: "" };
}

export function badge(text, kind = "muted") {
  const cls = { danger: "badge-danger", warn: "badge-warn", ok: "badge-ok", muted: "badge-muted" }[kind] || "badge-muted";
  return `<span class="${cls}">${esc(text)}</span>`;
}

/* ---------------- empty / error states ---------------- */
export function emptyState(title, hint) {
  return el("div", { class: "card text-center py-12" }, [
    el("p", { class: "text-base font-semibold text-ink" }, title),
    hint ? el("p", { class: "mt-1 text-sm text-soft" }, hint) : null,
  ]);
}

/* ---------------- data table ----------------
 * columns: [{ label, render(row)->(node|string), html?:bool }]
 * A render() returning a DOM node is appended; a string is set as text unless
 * the column sets html:true (used for badge markup). */
export function table(columns, rows, { empty = "Nothing here yet", emptyHint = "" } = {}) {
  if (!rows || !rows.length) return emptyState(empty, emptyHint);
  const head = el("tr", {}, columns.map((c) => el("th", {}, c.label)));
  const body = rows.map((r) =>
    el("tr", {}, columns.map((c) => {
      const td = el("td", {});
      const v = c.render ? c.render(r) : "";
      if (v == null || v === "") td.textContent = "—";
      else if (v.nodeType) td.append(v);
      else if (c.html) td.innerHTML = v;
      else td.textContent = String(v);
      return td;
    }))
  );
  return el("div", { class: "card overflow-x-auto" }, [
    el("table", { class: "table" }, [el("thead", {}, head), el("tbody", {}, body)]),
  ]);
}

/* ---------------- form modal ----------------
 * fields: [{ name, label, type, options?, required?, step?, min?, placeholder?, help? }]
 *   type: text | number | date | select | checkbox | textarea | tel | email
 * values: initial values (edit mode). onSubmit(data) where data is typed:
 *   number->Number, checkbox->bool, date->"YYYY-MM-DD" string, else string.
 * Return a Promise that resolves true on save, false on cancel. */
export function formModal({ title, fields, values = {}, submitLabel = "Save", onSubmit }) {
  return new Promise((resolve) => {
    const inputs = {};
    const err = el("p", { class: "hidden text-sm font-semibold text-danger" });

    const body = el("div", { class: "grid gap-3 sm:grid-cols-2" }, fields.map((f) => {
      const id = `f_${f.name}`;
      let input;
      const v = values[f.name];
      if (f.type === "select") {
        input = el("select", { id, class: "field" }, (f.options || []).map((o) => {
          const opt = el("option", { value: o.value ?? o }, o.label ?? o);
          if (String(v ?? f.default ?? "") === String(o.value ?? o)) opt.selected = true;
          return opt;
        }));
      } else if (f.type === "checkbox") {
        input = el("input", { id, type: "checkbox", class: "h-5 w-5 rounded border-line text-brand-500" });
        if (v) input.checked = true;
      } else if (f.type === "textarea") {
        input = el("textarea", { id, class: "field", rows: "2", placeholder: f.placeholder || "" }, v ?? "");
      } else {
        input = el("input", {
          id, type: f.type || "text", class: "field",
          placeholder: f.placeholder || "", step: f.step, min: f.min,
          value: v != null ? String(v) : "",
        });
      }
      inputs[f.name] = { input, f };
      const wrap = el("div", { class: f.full || f.type === "textarea" ? "sm:col-span-2" : "" }, [
        el("label", { class: "label", for: id }, f.label + (f.required ? " *" : "")),
        f.type === "checkbox"
          ? el("label", { class: "flex items-center gap-2 text-sm text-ink" }, [input, f.help || ""])
          : input,
        f.help && f.type !== "checkbox" ? el("p", { class: "mt-1 text-xs text-soft" }, f.help) : null,
      ]);
      return wrap;
    }));

    const close = modal((closeFn) => {
      const panel = el("div", {}, [
        el("h3", { class: "text-lg font-bold text-ink" }, title),
        el("div", { class: "mt-4" }, body),
        err,
        el("div", { class: "mt-5 flex justify-end gap-2" }, [
          el("button", { type: "button", class: "btn-ghost", onclick: () => { closeFn(); resolve(false); } }, "Cancel"),
          el("button", { type: "button", class: "btn-primary", onclick: submit }, submitLabel),
        ]),
      ]);
      async function submit() {
        const data = {};
        for (const [name, { input, f }] of Object.entries(inputs)) {
          let val;
          if (f.type === "checkbox") val = input.checked;
          else if (f.type === "number") val = input.value === "" ? null : Number(input.value);
          else val = input.value.trim();
          if (f.required && (val === "" || val == null)) {
            err.textContent = `${f.label} is required.`;
            err.classList.remove("hidden");
            input.focus();
            return;
          }
          data[name] = val;
        }
        err.classList.add("hidden");
        const btn = panel.querySelector(".btn-primary");
        btn.disabled = true; btn.textContent = "Saving…";
        try {
          await onSubmit(data);
          closeFn(); resolve(true);
        } catch (e) {
          err.textContent = e.message || "Couldn't save. Try again.";
          err.classList.remove("hidden");
          btn.disabled = false; btn.textContent = submitLabel;
        }
      }
      return panel;
    }, { onClose: () => resolve(false) });
  });
}

/* A search input that filters as you type; calls onInput(value). */
export function searchInput(placeholder, onInput) {
  return el("input", {
    class: "field max-w-sm", type: "search", placeholder,
    oninput: (e) => onInput(e.target.value.trim().toLowerCase()),
  });
}

/* Section header row with a title and optional right-side node. */
export function toolbar(title, right) {
  return el("div", { class: "flex flex-wrap items-center justify-between gap-3" }, [
    el("h2", { class: "text-lg font-bold text-ink" }, title),
    right || null,
  ]);
}

/* Loading + error helpers for live views. */
export function loading(text = "Loading…") {
  return el("div", { class: "card text-center py-10 text-sm text-soft" }, text);
}
export function errorCard(text) {
  return el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-danger" }, "Couldn't load this data."),
    el("p", { class: "mt-1 text-sm text-soft" }, text || "Check your connection and try again."),
  ]);
}
