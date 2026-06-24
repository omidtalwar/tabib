/**
 * ui.js — small DOM helpers: toasts, modal/confirm, formatters, badge helpers.
 * No framework. Plain, active-voice copy; empty states tell the user what to do.
 */

import { formatJalali, gregToJalali, jalaliToDate, todayJalali, jMonthLength, JMONTHS } from "./jalali.js";
import { t } from "./i18n.js";

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
export function confirmDialog(message, { confirmLabel = t("common.confirm"), danger = false } = {}) {
  return new Promise((resolve) => {
    modal(
      (close) => el("div", {}, [
        el("p", { class: "text-ink" }, message),
        el("div", { class: "mt-5 flex justify-end gap-2" }, [
          el("button", { class: "btn-ghost", onclick: () => { close(); resolve(false); } }, t("common.cancel")),
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

/** Dates display in Shamsi (Afghan calendar): "1403/01/15". */
export function fmtDate(date) {
  if (!date) return "—";
  const d = date instanceof Date ? date : new Date(date);
  if (isNaN(d.getTime())) return "—";
  return formatJalali(d);
}

/** Shamsi with month name: "15 Hamal 1403". */
export function fmtDateLong(date) {
  if (!date) return "—";
  const d = date instanceof Date ? date : new Date(date);
  if (isNaN(d.getTime())) return "—";
  return formatJalali(d, { withMonthName: true });
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
  if (stockQuantity <= 0) return { key: "out", label: t("status.out") };
  if (stockQuantity <= reorderThreshold) return { key: "low", label: t("status.low") };
  return { key: "ok", label: t("status.in") };
}

export function expiryStatus(expiryDate) {
  const d = daysUntil(expiryDate);
  if (d == null) return { key: "none", label: "" };
  if (d < 0) return { key: "expired", label: t("status.expired") };
  if (d <= 30) return { key: "expiring", label: `${d}d` };
  return { key: "ok", label: "" };
}

export function badge(text, kind = "muted") {
  const cls = { danger: "badge-danger", warn: "badge-warn", ok: "badge-ok", muted: "badge-muted" }[kind] || "badge-muted";
  return `<span class="${cls}">${esc(text)}</span>`;
}

/* ---------------- Shamsi date picker (standalone) ----------------
 * Returns { node, value() } where value() yields an ISO string or null. */
export function shamsiDate(initialISO) {
  const init = initialISO ? gregToJalali(new Date(initialISO)) : null;
  const today = todayJalali();
  const ySel = el("select", { class: "field" });
  ySel.append(el("option", { value: "" }, "Year"));
  for (let y = today.jy + 5; y >= today.jy - 90; y--) {
    const o = el("option", { value: String(y) }, String(y));
    if (init && init.jy === y) o.selected = true;
    ySel.append(o);
  }
  const mSel = el("select", { class: "field" });
  mSel.append(el("option", { value: "" }, "Month"));
  JMONTHS.forEach((nm, i) => {
    const o = el("option", { value: String(i + 1) }, `${String(i + 1).padStart(2, "0")} · ${nm}`);
    if (init && init.jm === i + 1) o.selected = true;
    mSel.append(o);
  });
  const dSel = el("select", { class: "field" });
  const rebuild = () => {
    const yy = +ySel.value, mm = +mSel.value;
    const max = (yy && mm) ? jMonthLength(yy, mm) : 31;
    const keep = dSel.value;
    dSel.replaceChildren(el("option", { value: "" }, "Day"));
    for (let dd = 1; dd <= max; dd++) dSel.append(el("option", { value: String(dd) }, String(dd)));
    if (keep && +keep <= max) dSel.value = keep;
  };
  rebuild();
  if (init) dSel.value = String(init.jd);
  ySel.addEventListener("change", rebuild);
  mSel.addEventListener("change", rebuild);
  const node = el("div", { class: "grid grid-cols-3 gap-2" }, [ySel, mSel, dSel]);
  return {
    node,
    value: () => { const y = +ySel.value, m = +mSel.value, d = +dSel.value; return (y && m && d) ? jalaliToDate(y, m, d).toISOString() : null; },
  };
}

/* ---------------- export / print / charts ---------------- */

/** Download rows (array of arrays) as a UTF-8 CSV file. */
export function downloadCSV(filename, rows) {
  const cell = (s) => { s = String(s ?? ""); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
  const csv = rows.map((r) => r.map(cell).join(",")).join("\r\n");
  const blob = new Blob(["﻿" + csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = el("a", { href: url, download: filename });
  document.body.append(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** Open a print-formatted window with the given title + inner HTML. */
export function printContent(title, innerHTML) {
  const w = window.open("", "_blank");
  if (!w) { toast("Allow popups to print", { type: "warn" }); return; }
  w.document.write(
    '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(title) + '</title><style>' +
    'body{font-family:system-ui,Segoe UI,Arial,sans-serif;padding:24px;color:#13201F}' +
    'h1{font-size:18px;margin:0 0 2px}.sub{color:#6C7B7A;font-size:12px;margin-bottom:16px}' +
    'table{width:100%;border-collapse:collapse;font-size:12px;margin-top:8px}' +
    'th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f3f4f4}' +
    '.kpis{margin:8px 0 4px}.kpi{display:inline-block;margin:0 18px 8px 0}.kpi b{display:block;font-size:16px}' +
    '.kpi span{color:#6C7B7A;font-size:11px}</style></head><body>' + innerHTML +
    '<script>window.onload=function(){window.print()}<\/script></body></html>'
  );
  w.document.close();
}

/** Tiny inline sparkline from an array of numbers. */
export function sparkline(values, { color = "#0EA59B", width = 86, height = 26 } = {}) {
  const span = el("span", { class: "inline-block align-middle" });
  if (!values || values.length < 2) { span.innerHTML = ""; return span; }
  const max = Math.max(...values), min = Math.min(0, ...values), range = (max - min) || 1;
  const pts = values.map((v, i) => {
    const x = (i / (values.length - 1)) * width;
    const y = height - 2 - ((v - min) / range) * (height - 4);
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  }).join(" ");
  span.innerHTML = `<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none"><polyline points="${pts}" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
  return span;
}

/** Lightweight SVG-free bar chart. data: [{label,value}]. */
export function barChart(data, { height = 150, color = "#26b3a6" } = {}) {
  const max = Math.max(1, ...data.map((d) => d.value));
  const wrap = el("div", { class: "flex items-stretch gap-1", style: `height:${height}px` });
  wrap.innerHTML = data.map((d) => {
    const h = Math.max(2, Math.round((d.value / max) * (height - 26)));
    return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;min-width:0">
      <div style="flex:1;display:flex;align-items:flex-end;width:100%">
        <div style="width:100%;height:${h}px;border-radius:5px 5px 0 0;background:${color}" title="${esc(d.label)}: ${esc(d.value)}"></div>
      </div>
      <span style="font-size:10px;color:#6C7B7A;margin-top:4px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(d.label)}</span>
    </div>`;
  }).join("");
  return wrap;
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
export function formModal({ title, fields, values = {}, submitLabel = t("common.save"), onSubmit }) {
  return new Promise((resolve) => {
    const inputs = {};
    const err = el("p", { class: "hidden text-sm font-semibold text-danger" });

    const body = el("div", { class: "grid gap-3 sm:grid-cols-2" }, fields.map((f) => {
      const id = `f_${f.name}`;
      let input, jsel = null, datalistNode = null;
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
      } else if (f.type === "jdate") {
        const sd = shamsiDate(v);
        input = sd.node;
        jsel = sd;
      } else if (f.type === "combo") {
        // free text with suggestions (editable categories etc.)
        const listId = id + "_list";
        input = el("input", { id, class: "field", placeholder: f.placeholder || "", value: v != null ? String(v) : "", list: listId, autocomplete: "off" });
        datalistNode = el("datalist", { id: listId }, (f.options || []).map((o) => el("option", { value: o.value ?? o })));
      } else {
        input = el("input", {
          id, type: f.type || "text", class: "field",
          placeholder: f.placeholder || "", step: f.step, min: f.min,
          value: v != null ? String(v) : "",
        });
      }
      inputs[f.name] = { input, f, jsel };
      const isWide = f.full || f.type === "textarea" || f.type === "jdate";
      const wrap = el("div", { class: isWide ? "sm:col-span-2" : "" }, [
        el("label", { class: "label", for: id }, f.label + (f.required ? " *" : "") + (f.type === "jdate" ? " (Shamsi)" : "")),
        f.type === "checkbox"
          ? el("label", { class: "flex items-center gap-2 text-sm text-ink" }, [input, f.help || ""])
          : input,
        datalistNode,
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
          el("button", { type: "button", class: "btn-ghost", onclick: () => { closeFn(); resolve(false); } }, t("common.cancel")),
          el("button", { type: "button", class: "btn-primary", onclick: submit }, submitLabel),
        ]),
      ]);
      async function submit() {
        const data = {};
        for (const [name, meta] of Object.entries(inputs)) {
          const { input, f, jsel } = meta;
          let val;
          if (f.type === "checkbox") val = input.checked;
          else if (f.type === "jdate") val = jsel.value();
          else if (f.type === "number") val = input.value === "" ? null : Number(input.value);
          else val = input.value.trim();
          if (f.required && (val === "" || val == null)) {
            err.textContent = t("common.required", { label: f.label });
            err.classList.remove("hidden");
            if (input.focus) input.focus();
            return;
          }
          data[name] = val;
        }
        err.classList.add("hidden");
        const btn = panel.querySelector(".btn-primary");
        btn.disabled = true; btn.textContent = t("common.saving");
        try {
          await onSubmit(data);
          closeFn(); resolve(true);
        } catch (e) {
          err.textContent = e.message || t("common.couldntSave");
          err.classList.remove("hidden");
          btn.disabled = false; btn.textContent = submitLabel;
        }
      }
      return panel;
    }, { onClose: () => resolve(false) });
  });
}

/* A search box with a leading magnifier icon; calls onInput(lowercased value). */
export function searchInput(placeholder, onInput) {
  const input = el("input", {
    type: "search", placeholder,
    oninput: (e) => onInput(e.target.value.trim().toLowerCase()),
  });
  const wrap = el("div", { class: "search w-full sm:w-64" });
  wrap.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>';
  wrap.append(input);
  return wrap;
}

/* Icon-only action button. `path` is inner SVG markup (24x24, stroke).
 * opts.color: "blue" | "teal" | "green" | "amber" | "red" for a tinted look. */
export function iconButton(path, title, onClick, { danger = false, color = "" } = {}) {
  const cls = "icon-btn" + (color ? " c-" + color : "") + (danger ? " danger" : "");
  const b = el("button", { class: cls, title, "aria-label": title, onclick: onClick });
  b.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
  return b;
}

/* Common action-icon paths. */
export const ICON = {
  edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>',
  restock: '<path d="M12 3v10m0 0 3.5-3.5M12 13 8.5 9.5"/><path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
  remove: '<path d="M4 7h16M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M18 7l-1 13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 7"/>',
};

/* A styled filter dropdown. options: [{value,label}]. Calls onChange(value). */
export function filterSelect(options, value, onChange, ariaLabel = "Filter") {
  return el("select", {
    class: "filter-select", "aria-label": ariaLabel,
    onchange: (e) => onChange(e.target.value),
  }, options.map((o) => {
    const opt = el("option", { value: o.value }, o.label);
    if (String(o.value) === String(value)) opt.selected = true;
    return opt;
  }));
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
