/** Support - send a problem report with contact and device details. */
import { commitLocal, put, uuid } from "../repo.js";
import { el, toast, withButtonLoading } from "../ui.js";
import { t, getLocale } from "../i18n.js";

const SUPPORT_EMAIL = "support@tabib.af";

function field(label, control, help) {
  return el("div", {}, [
    el("label", { class: "label" }, label),
    control,
    help ? el("p", { class: "mt-1 text-xs text-soft" }, help) : null,
  ]);
}

function deviceDetails(ctx) {
  return {
    pharmacyId: ctx.pharmacyId,
    userEmail: ctx.session.email || "",
    userRole: ctx.session.role || "",
    locale: getLocale(),
    url: location.href,
    online: navigator.onLine,
    userAgent: navigator.userAgent,
    platform: navigator.platform || "",
    screen: `${screen.width}x${screen.height}`,
    timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone || "",
  };
}

function buildMessage(data, ctx) {
  const d = deviceDetails(ctx);
  return [
    `Tabib Pharmacy support request`,
    ``,
    `Problem type: ${data.type || "-"}`,
    `Subject: ${data.subject || "-"}`,
    `Message: ${data.message || "-"}`,
    `Urgency: ${data.urgency || "-"}`,
    ``,
    `Contact name: ${data.contactName || "-"}`,
    `User WhatsApp: ${data.whatsapp || "-"}`,
    `User email: ${d.userEmail || "-"}`,
    ``,
    `Pharmacy ID: ${d.pharmacyId}`,
    `Role: ${d.userRole || "-"}`,
    `Current page: ${d.url}`,
    `Language: ${d.locale}`,
    `Online: ${d.online ? "yes" : "no"}`,
    `Device: ${d.platform || "-"} / ${d.screen}`,
    `Browser: ${d.userAgent}`,
  ].join("\n");
}

export default function render(outlet, ctx) {
  const type = el("select", { class: "field" }, [
    el("option", { value: "Bug" }, t("suppt.typeBug")),
    el("option", { value: "Account" }, t("suppt.typeAccount")),
    el("option", { value: "Data" }, t("suppt.typeData")),
    el("option", { value: "Payment" }, t("suppt.typePayment")),
    el("option", { value: "Idea" }, t("suppt.typeIdea")),
    el("option", { value: "Other" }, t("suppt.typeOther")),
  ]);
  const urgency = el("select", { class: "field" }, [
    el("option", { value: "Normal" }, t("suppt.urgNormal")),
    el("option", { value: "High" }, t("suppt.urgHigh")),
    el("option", { value: "Critical" }, t("suppt.urgCritical")),
  ]);
  const subject = el("input", { class: "field", maxlength: "120", placeholder: t("suppt.subjectPh") });
  const message = el("textarea", { class: "field", rows: "7", maxlength: "3000", placeholder: t("suppt.messagePh") });
  const contactName = el("input", { class: "field", maxlength: "100", placeholder: t("suppt.namePh") });
  const whatsapp = el("input", { class: "field", type: "tel", maxlength: "40", placeholder: t("suppt.whatsappPh") });
  const status = el("p", { class: "hidden text-sm font-semibold text-danger" });

  const readData = () => ({
    type: type.value,
    urgency: urgency.value,
    subject: subject.value.trim(),
    message: message.value.trim(),
    contactName: contactName.value.trim(),
    whatsapp: whatsapp.value.trim(),
  });

  function updateLinks() {
    const data = readData();
    const text = buildMessage(data, ctx);
    whatsappBtn.href = `https://wa.me/?text=${encodeURIComponent(text)}`;
    emailBtn.href = `mailto:${SUPPORT_EMAIL}?subject=${encodeURIComponent(data.subject || "Tabib support request")}&body=${encodeURIComponent(text)}`;
  }

  async function submit(e) {
    const btn = e.currentTarget;
    const data = readData();
    if (!data.message) {
      status.textContent = t("suppt.messageRequired");
      status.classList.remove("hidden");
      message.focus();
      return;
    }
    status.classList.add("hidden");
    await withButtonLoading(btn, async () => {
      const id = uuid();
      const now = new Date().toISOString();
      const details = deviceDetails(ctx);
      const result = await commitLocal(put(ctx.pharmacyId, "support_requests", id, {
        ...data,
        status: "open",
        source: "pharmacy_portal",
        createdAt: now,
        updatedAt: now,
        createdByUid: ctx.session.uid || "",
        createdByEmail: ctx.session.email || "",
        device: details,
        preparedMessage: buildMessage(data, ctx),
      }));
      toast(result.synced ? t("suppt.sent") : t("suppt.savedOffline"), { type: "ok" });
      subject.value = "";
      message.value = "";
      updateLinks();
    });
  }

  const whatsappBtn = el("a", { class: "btn-ghost", href: "#", target: "_blank", rel: "noopener" }, [
    el("span", { html: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.4L3 21l2.1-5.1A8.5 8.5 0 1 1 21 11.5z"/><path d="M8.5 8.5c.4 3 2 5 5 6l1.4-1.4 2 .5"/></svg>' }),
    t("suppt.whatsappBtn"),
  ]);
  const emailBtn = el("a", { class: "btn-ghost", href: "#" }, [
    el("span", { html: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>' }),
    t("suppt.emailBtn"),
  ]);
  const copyBtn = el("button", {
    class: "btn-ghost",
    onclick: async () => {
      try {
        await navigator.clipboard.writeText(buildMessage(readData(), ctx));
        toast(t("suppt.copied"), { type: "ok" });
      } catch {
        toast(t("suppt.copyFailed"), { type: "warn" });
      }
    },
  }, t("suppt.copyBtn"));

  [type, urgency, subject, message, contactName, whatsapp].forEach((node) => {
    node.addEventListener("input", updateLinks);
    node.addEventListener("change", updateLinks);
  });

  const meta = deviceDetails(ctx);
  const details = el("div", { class: "card" }, [
    el("p", { class: "font-semibold text-ink" }, t("suppt.detailsTitle")),
    el("div", { class: "mt-3 grid gap-2 text-sm text-soft sm:grid-cols-2" }, [
      el("p", {}, `${t("suppt.detailUser")}: ${meta.userEmail || "-"}`),
      el("p", {}, `${t("suppt.detailPharmacy")}: ${meta.pharmacyId}`),
      el("p", {}, `${t("suppt.detailPage")}: ${meta.url}`),
      el("p", {}, `${t("suppt.detailDevice")}: ${meta.platform || "-"} / ${meta.screen}`),
    ]),
  ]);

  updateLinks();
  outlet.append(el("div", { class: "space-y-5" }, [
    el("div", { class: "grid gap-5 lg:grid-cols-3" }, [
      el("div", { class: "card lg:col-span-2" }, [
        el("div", { class: "mb-4" }, [
          el("p", { class: "font-semibold text-ink" }, t("suppt.title")),
          el("p", { class: "mt-1 text-sm text-soft" }, t("suppt.body")),
        ]),
        el("div", { class: "grid gap-3 sm:grid-cols-2" }, [
          field(t("suppt.type"), type),
          field(t("suppt.urgency"), urgency),
          field(t("suppt.subject"), subject),
          field(t("suppt.whatsapp"), whatsapp, t("suppt.whatsappHelp")),
          el("div", { class: "sm:col-span-2" }, field(t("suppt.message"), message)),
          field(t("suppt.contactName"), contactName),
        ]),
        status,
        el("div", { class: "mt-4 flex flex-wrap gap-2" }, [
          el("button", { class: "btn-primary", onclick: submit }, t("suppt.sendBtn")),
          whatsappBtn,
          emailBtn,
          copyBtn,
        ]),
      ]),
      el("div", { class: "card" }, [
        el("p", { class: "font-semibold text-ink" }, t("suppt.fastTitle")),
        el("p", { class: "mt-1 text-sm text-soft" }, t("suppt.fastBody")),
        el("div", { class: "mt-4 space-y-2 text-sm text-soft" }, [
          el("p", {}, t("suppt.tip1")),
          el("p", {}, t("suppt.tip2")),
          el("p", {}, t("suppt.tip3")),
        ]),
      ]),
    ]),
    details,
  ]));
}
