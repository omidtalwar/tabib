/* ===========================================================
   Tabib — i18n + UI interactions (lightweight, no deps)
   Languages: en (LTR), fa = Dari (RTL), ps = Pashto (RTL)
   =========================================================== */
(function () {
  "use strict";

  var RTL = { fa: true, ps: true };
  var HTML_LANG = { en: "en", fa: "fa-AF", ps: "ps" };

  var STR = {
    en: {
      "nav.features": "Features",
      "nav.communities": "Communities",
      "nav.providers": "For Providers",
      "nav.faq": "FAQ",
      "nav.support": "Support",
      "nav.getapp": "Get the app",
      "cta.play": "Get it on Google Play",
      "cta.seefeatures": "See features",

      "hero.eyebrow": "Health, in your language",
      "hero.h1": "Health help for everyone — <span class=\"hl\">in your language</span>.",
      "hero.sub": "Ask doctors and your community, get AI symptom help, and find clinics, pharmacies and labs nearby — all in English, Dari and Pashto.",
      "hero.float1.tag": "Quick question",
      "hero.float1.txt": "“I’ve had a headache for days — what should I do?”",
      "hero.float2.tag": "Verified answer",
      "hero.float2.txt": "A licensed doctor replied with a best answer.",
      "trust.free": "Free",
      "trust.offline": "Works offline",

      "strip.title": "Built for Afghanistan",
      "strip.note": "Full right-to-left support and instant language switching — no app restart needed.",

      "features.eyebrow": "Everything in one app",
      "features.title": "Six ways Tabib helps",
      "features.lead": "From trusted answers to finding nearby care — designed to be simple, fast and multilingual.",
      "f1.title": "Ask doctors & community",
      "f1.desc": "Public Q&A with verified professionals — add photos, upvote helpful replies, mark best answers, and read AI thread summaries.",
      "f2.title": "AI health tools",
      "f2.desc": "A Symptom Checker and the “Tabib” AI assistant in all three languages. Guidance to understand your health — not a diagnosis.",
      "f3.title": "Health communities",
      "f3.desc": "Topic rooms for diabetes, heart health and more — with voice notes, images, threads and helpful bots.",
      "f4.title": "Find care nearby",
      "f4.desc": "Verified doctors and clinics, pharmacies and labs near you, with reviews and ratings you can trust.",
      "f5.title": "Private messaging",
      "f5.desc": "Fast one-to-one chat with cached history and offline support, so conversations stay with you.",
      "f6.title": "Daily health tips",
      "f6.desc": "A curated health tip every day, plus a saved-tips library you can revisit any time.",

      "deepA.pill": "Q&A Community",
      "deepA.title": "Real answers from verified professionals",
      "deepA.desc": "Post a question publicly and get clear explanations. Verified doctors are marked with a badge, the community upvotes what helps, and AI summarizes long threads.",
      "deepA.li1": "Verified-professional badges",
      "deepA.li2": "Upvotes & best-answer marking",
      "deepA.li3": "AI thread summaries in all 3 languages",

      "deepB.pill": "AI Symptom Help",
      "deepB.title": "Understand symptoms, safely",
      "deepB.desc": "The Symptom Checker and Tabib AI assistant answer in English, Dari and Pashto, with simple next-step guidance — always reminding you it is guidance, not a diagnosis.",
      "deepB.li1": "Multilingual, easy to understand",
      "deepB.li2": "Suggests when to see a professional",
      "deepB.li3": "Guidance, not a diagnosis",

      "deepC.pill": "Topic Rooms",
      "deepC.title": "Communities for every health topic",
      "deepC.desc": "Join rooms on the topics that matter to you. Share voice notes and images, follow threaded conversations, read announcements and get help from community bots.",
      "deepC.li1": "Voice notes, images & threads",
      "deepC.li2": "Announcements & helpful bots",
      "deepC.li3": "150+ active topics and growing",

      "deepD.pill": "Chat & Connect",
      "deepD.title": "Stay connected, even offline",
      "deepD.desc": "Message people and rooms one-to-one. History is cached on your device, so your conversations load instantly and keep working on slow or offline connections.",
      "deepD.li1": "Fast 1-to-1 private chat",
      "deepD.li2": "Cached history & offline support",
      "deepD.li3": "Find and message verified providers",

      "providers.title": "Own a pharmacy, clinic or lab?",
      "providers.desc": "List your business in Tabib, reach patients near you, and manage drugs, sales, appointments and records right inside the app.",
      "providers.cta": "Learn more",

      "safety.eyebrow": "Trust & safety",
      "safety.title": "Built to keep you safe",
      "safety.lead": "Verification and moderation help keep the community trustworthy and respectful.",
      "s1.title": "Identity verification",
      "s1.desc": "Verified badges show who you can trust in the community.",
      "s2.title": "Licensed doctors",
      "s2.desc": "Professionals are reviewed for a valid license before verification.",
      "s3.title": "Moderation & reporting",
      "s3.desc": "Easy tools to report and remove harmful content.",
      "s4.title": "AI = guidance only",
      "s4.desc": "AI offers guidance to understand your health, never a diagnosis.",

      "gallery.title": "Take a look inside",
      "gallery.lead": "Real screens from the Tabib app.",

      "steps.title": "How it works",
      "step1.title": "Download & choose your language",
      "step1.desc": "Install free and pick English, Dari or Pashto in seconds.",
      "step2.title": "Ask or find care",
      "step2.desc": "Post a question or search for clinics, pharmacies and labs nearby.",
      "step3.title": "Join communities & learn",
      "step3.desc": "Follow topic rooms and get a helpful health tip every day.",

      "faq.title": "Frequently asked questions",
      "faq.q1": "Is Tabib free?",
      "faq.a1": "Yes. Tabib is free to download and use from Google Play.",
      "faq.q2": "Which languages does it support?",
      "faq.a2": "English, Dari (دری) and Pashto (پښتو), with full right-to-left support.",
      "faq.q3": "Does it work offline?",
      "faq.a3": "Chat history is cached on your device, so conversations keep working on slow or offline connections.",
      "faq.q4": "Is the AI a doctor?",
      "faq.a4": "No. The AI gives guidance to help you understand your health — it is not a diagnosis or a substitute for professional care.",
      "faq.q5": "How do I find verified doctors?",
      "faq.a5": "Use the Nearby feature to find verified doctors, clinics, pharmacies and labs, with reviews and ratings.",
      "faq.q6": "I own a pharmacy or clinic — how do I list it?",
      "faq.a6": "You can list and manage your business inside the app. See the For Providers page to learn more.",
      "faq.q7": "How is my data handled?",
      "faq.a7": "We take privacy seriously and never sell your data. Read our Privacy Policy for full details.",

      "finalcta.title": "Your health questions, answered.",
      "finalcta.sub": "Free to download. English · دری · پښتو.",

      "footer.tagline": "Health Q&A, AI guidance, communities and nearby care — built for Afghanistan, in your language.",
      "footer.product": "Product",
      "footer.legal": "Legal",
      "footer.privacy": "Privacy Policy",
      "footer.terms": "Terms of Service",
      "footer.deletion": "Account & Data Deletion",
      "footer.language": "Language",
      "footer.contact": "Contact",
      "footer.rights": "All rights reserved.",
      "footer.disclaimer": "Tabib provides health guidance, not medical diagnosis. In an emergency, contact local services."
    },

    fa: {
      "nav.features": "ویژگی‌ها",
      "nav.communities": "اجتماعات",
      "nav.providers": "برای ارائه‌دهندگان",
      "nav.faq": "پرسش‌ها",
      "nav.support": "پشتیبانی",
      "nav.getapp": "دریافت برنامه",
      "cta.play": "دریافت از Google Play",
      "cta.seefeatures": "دیدن ویژگی‌ها",

      "hero.eyebrow": "صحت، به زبان شما",
      "hero.h1": "کمک صحی برای همه — <span class=\"hl\">به زبان شما</span>.",
      "hero.sub": "از داکتران و اجتماع خود بپرسید، با هوش مصنوعی نشانه‌های مریضی را بسنجید و کلینیک‌ها، دواخانه‌ها و لابراتوارهای نزدیک را بیابید — همه به انگلیسی، دری و پشتو.",
      "hero.float1.tag": "پرسش سریع",
      "hero.float1.txt": "«چند روز است سردردی دارم — چه کنم؟»",
      "hero.float2.tag": "پاسخ تأییدشده",
      "hero.float2.txt": "یک داکتر مجاز بهترین پاسخ را داد.",
      "trust.free": "رایگان",
      "trust.offline": "بدون انترنت کار می‌کند",

      "strip.title": "ساخته‌شده برای افغانستان",
      "strip.note": "پشتیبانی کامل از راست‌به‌چپ و تعویض فوری زبان — بدون نیاز به راه‌اندازی دوباره برنامه.",

      "features.eyebrow": "همه‌چیز در یک برنامه",
      "features.title": "شش راهی که طبیب کمک می‌کند",
      "features.lead": "از پاسخ‌های قابل‌اعتماد تا یافتن خدمات نزدیک — ساده، سریع و چندزبانه.",
      "f1.title": "پرسش از داکتران و اجتماع",
      "f1.desc": "پرسش‌وپاسخ عمومی با متخصصان تأییدشده — افزودن عکس، رأی‌دادن به پاسخ‌های مفید، نشان‌کردن بهترین پاسخ و خلاصهٔ هوش مصنوعی.",
      "f2.title": "ابزارهای صحی هوش مصنوعی",
      "f2.desc": "سنجش نشانه‌های مریضی و دستیار «طبیب» به هر سه زبان. راهنمایی برای درک صحت شما — نه تشخیص.",
      "f3.title": "اجتماعات صحی",
      "f3.desc": "اتاق‌های موضوعی برای شکر، صحت قلب و بیشتر — با پیام صوتی، عکس، رشته‌ها و بات‌های کمک‌کننده.",
      "f4.title": "یافتن خدمات نزدیک",
      "f4.desc": "داکتران و کلینیک‌های تأییدشده، دواخانه‌ها و لابراتوارهای نزدیک شما با نظرات و امتیازها.",
      "f5.title": "پیام خصوصی",
      "f5.desc": "چت سریع یک‌به‌یک با تاریخچهٔ ذخیره‌شده و پشتیبانی آفلاین.",
      "f6.title": "نکات روزانهٔ صحی",
      "f6.desc": "هر روز یک نکتهٔ صحی، به‌همراه کتابخانهٔ نکات ذخیره‌شده.",

      "deepA.pill": "اجتماع پرسش‌وپاسخ",
      "deepA.title": "پاسخ‌های واقعی از متخصصان تأییدشده",
      "deepA.desc": "پرسش خود را به‌صورت عمومی مطرح کنید و توضیح روشن بگیرید. داکتران تأییدشده نشان دارند، اجتماع به پاسخ‌های مفید رأی می‌دهد و هوش مصنوعی رشته‌های طولانی را خلاصه می‌کند.",
      "deepA.li1": "نشان متخصص تأییدشده",
      "deepA.li2": "رأی‌دادن و نشان‌کردن بهترین پاسخ",
      "deepA.li3": "خلاصهٔ هوش مصنوعی به هر سه زبان",

      "deepB.pill": "کمک هوش مصنوعی",
      "deepB.title": "نشانه‌ها را با اطمینان درک کنید",
      "deepB.desc": "سنجش نشانه‌ها و دستیار طبیب به انگلیسی، دری و پشتو پاسخ می‌دهند و گام بعدی را پیشنهاد می‌کنند — همیشه یادآور می‌شوند که این راهنمایی است، نه تشخیص.",
      "deepB.li1": "چندزبانه و آسان‌فهم",
      "deepB.li2": "پیشنهاد زمان مراجعه به داکتر",
      "deepB.li3": "راهنمایی، نه تشخیص",

      "deepC.pill": "اتاق‌های موضوعی",
      "deepC.title": "اجتماع برای هر موضوع صحی",
      "deepC.desc": "به اتاق‌های موضوعِ موردنظر بپیوندید. پیام صوتی و عکس به‌اشتراک بگذارید، گفتگوهای رشته‌ای را دنبال کنید و از بات‌های اجتماع کمک بگیرید.",
      "deepC.li1": "پیام صوتی، عکس و رشته‌ها",
      "deepC.li2": "اعلان‌ها و بات‌های کمک‌کننده",
      "deepC.li3": "بیش از ۱۵۰ موضوع فعال",

      "deepD.pill": "چت و ارتباط",
      "deepD.title": "همیشه در ارتباط، حتی آفلاین",
      "deepD.desc": "با افراد و اتاق‌ها یک‌به‌یک پیام بفرستید. تاریخچه روی دستگاه شما ذخیره می‌شود تا گفتگوها فوری بارگذاری شوند و در انترنت کند یا آفلاین هم کار کنند.",
      "deepD.li1": "چت خصوصی سریع یک‌به‌یک",
      "deepD.li2": "تاریخچهٔ ذخیره‌شده و پشتیبانی آفلاین",
      "deepD.li3": "یافتن و پیام به ارائه‌دهندگان تأییدشده",

      "providers.title": "صاحب دواخانه، کلینیک یا لابراتوار هستید؟",
      "providers.desc": "کسب‌وکار خود را در طبیب ثبت کنید، به مریضان نزدیک خود برسید و دوا، فروش، نوبت‌ها و سوابق را داخل برنامه مدیریت کنید.",
      "providers.cta": "بیشتر بدانید",

      "safety.eyebrow": "اعتماد و امنیت",
      "safety.title": "ساخته‌شده برای امنیت شما",
      "safety.lead": "تأیید و نظارت به اعتماد و احترام در اجتماع کمک می‌کند.",
      "s1.title": "تأیید هویت",
      "s1.desc": "نشان‌های تأییدشده نشان می‌دهند به چه کسی می‌توان اعتماد کرد.",
      "s2.title": "داکتران مجاز",
      "s2.desc": "پیش از تأیید، جواز معتبر متخصصان بررسی می‌شود.",
      "s3.title": "نظارت و گزارش‌دهی",
      "s3.desc": "ابزارهای ساده برای گزارش و حذف محتوای مضر.",
      "s4.title": "هوش مصنوعی = فقط راهنمایی",
      "s4.desc": "هوش مصنوعی راهنمایی می‌دهد، نه تشخیص.",

      "gallery.title": "نگاهی به داخل برنامه",
      "gallery.lead": "تصاویر واقعی از برنامهٔ طبیب.",

      "steps.title": "چگونه کار می‌کند",
      "step1.title": "دانلود و انتخاب زبان",
      "step1.desc": "رایگان نصب کنید و در چند ثانیه انگلیسی، دری یا پشتو را انتخاب کنید.",
      "step2.title": "بپرسید یا خدمات بیابید",
      "step2.desc": "پرسش مطرح کنید یا کلینیک، دواخانه و لابراتوار نزدیک را جستجو کنید.",
      "step3.title": "به اجتماع بپیوندید و بیاموزید",
      "step3.desc": "اتاق‌های موضوعی را دنبال کنید و هر روز یک نکتهٔ صحی بگیرید.",

      "faq.title": "پرسش‌های پرتکرار",
      "faq.q1": "آیا طبیب رایگان است؟",
      "faq.a1": "بله. دانلود و استفاده از طبیب از Google Play رایگان است.",
      "faq.q2": "از چه زبان‌هایی پشتیبانی می‌کند؟",
      "faq.a2": "انگلیسی، دری و پشتو، با پشتیبانی کامل از راست‌به‌چپ.",
      "faq.q3": "آیا آفلاین کار می‌کند؟",
      "faq.a3": "تاریخچهٔ چت روی دستگاه ذخیره می‌شود و در انترنت کند یا آفلاین کار می‌کند.",
      "faq.q4": "آیا هوش مصنوعی داکتر است؟",
      "faq.a4": "نه. هوش مصنوعی راهنمایی می‌دهد تا صحت خود را بهتر درک کنید — جایگزین مراقبت تخصصی نیست.",
      "faq.q5": "چگونه داکتران تأییدشده را بیابم؟",
      "faq.a5": "از قابلیت «نزدیک من» برای یافتن داکتران، کلینیک‌ها، دواخانه‌ها و لابراتوارهای تأییدشده استفاده کنید.",
      "faq.q6": "صاحب دواخانه یا کلینیک هستم — چگونه ثبت کنم؟",
      "faq.a6": "می‌توانید کسب‌وکار خود را داخل برنامه ثبت و مدیریت کنید. صفحهٔ «برای ارائه‌دهندگان» را ببینید.",
      "faq.q7": "اطلاعات من چگونه مدیریت می‌شود؟",
      "faq.a7": "ما به حریم خصوصی اهمیت می‌دهیم و هرگز اطلاعات شما را نمی‌فروشیم. سیاست حفظ حریم خصوصی را بخوانید.",

      "finalcta.title": "پاسخ پرسش‌های صحی شما.",
      "finalcta.sub": "دانلود رایگان. English · دری · پښتو.",

      "footer.tagline": "پرسش‌وپاسخ صحی، راهنمایی هوش مصنوعی، اجتماعات و خدمات نزدیک — ساخته‌شده برای افغانستان، به زبان شما.",
      "footer.product": "محصول",
      "footer.legal": "حقوقی",
      "footer.privacy": "سیاست حریم خصوصی",
      "footer.terms": "شرایط استفاده",
      "footer.deletion": "حذف حساب و اطلاعات",
      "footer.language": "زبان",
      "footer.contact": "تماس",
      "footer.rights": "تمام حقوق محفوظ است.",
      "footer.disclaimer": "طبیب راهنمایی صحی ارائه می‌دهد، نه تشخیص طبی. در موارد عاجل با خدمات محلی تماس بگیرید."
    },

    ps: {
      "nav.features": "ځانګړنې",
      "nav.communities": "ټولنې",
      "nav.providers": "د چمتوکوونکو لپاره",
      "nav.faq": "پوښتنې",
      "nav.support": "ملاتړ",
      "nav.getapp": "اپ ترلاسه کړئ",
      "cta.play": "له Google Play ترلاسه کړئ",
      "cta.seefeatures": "ځانګړنې وګورئ",

      "hero.eyebrow": "روغتیا، ستاسو په ژبه",
      "hero.h1": "د ټولو لپاره روغتیایي مرسته — <span class=\"hl\">ستاسو په ژبه</span>.",
      "hero.sub": "له ډاکټرانو او خپلې ټولنې پوښتنه وکړئ، د مصنوعي هوش په مرسته د ناروغۍ نښې وڅیړئ، او نږدې کلینیکونه، درملتونونه او لابراتوارونه ومومئ — ټول په انګلیسي، دري او پښتو.",
      "hero.float1.tag": "چټکه پوښتنه",
      "hero.float1.txt": "«څو ورځې کیږي سرخوږی لرم — څه وکړم؟»",
      "hero.float2.tag": "تصدیق شوی ځواب",
      "hero.float2.txt": "یو جوازي ډاکټر تر ټولو غوره ځواب ورکړ.",
      "trust.free": "وړیا",
      "trust.offline": "آفلاین کار کوي",

      "strip.title": "د افغانستان لپاره جوړ شوی",
      "strip.note": "د ښي‌نه‌کیڼ بشپړ ملاتړ او د ژبې سمدستي بدلون — د اپ بیا پیلولو ته اړتیا نشته.",

      "features.eyebrow": "هرڅه په یوه اپ کې",
      "features.title": "شپږ لارې چې طبیب مرسته کوي",
      "features.lead": "له باوري ځوابونو تر نږدې خدماتو موندلو — ساده، چټک او څوژبیز.",
      "f1.title": "له ډاکټرانو او ټولنې پوښتنه",
      "f1.desc": "له تصدیق شوو متخصصینو سره عامه پوښتنه‌ځواب — انځور ورزیاتول، ګټورو ځوابونو ته رایه ورکول، غوره ځواب نښه کول او د مصنوعي هوش لنډیز.",
      "f2.title": "د مصنوعي هوش روغتیایي وسایل",
      "f2.desc": "د نښو څیړونکی او د «طبیب» مصنوعي هوش مرستیال په درې واړو ژبو. د روغتیا د پوهیدو لارښوونه — نه تشخیص.",
      "f3.title": "روغتیایي ټولنې",
      "f3.desc": "د شکر، زړه روغتیا او نورو موضوعاتو خونې — له غږیزو پیغامونو، انځورونو، مخینو او ګټورو باټانو سره.",
      "f4.title": "نږدې خدمات ومومئ",
      "f4.desc": "تصدیق شوي ډاکټران او کلینیکونه، درملتونونه او لابراتوارونه ستاسو نږدې، له نظرونو او درجو سره.",
      "f5.title": "خصوصي پیغام",
      "f5.desc": "چټک یو‌په‌یو چټ له خوندي شوې تاریخچې او آفلاین ملاتړ سره.",
      "f6.title": "ورځنۍ روغتیایي لارښوونې",
      "f6.desc": "هره ورځ یوه روغتیایي لارښوونه، له خوندي شوو لارښوونو کتابتون سره.",

      "deepA.pill": "د پوښتنه‌ځواب ټولنه",
      "deepA.title": "له تصدیق شوو متخصصینو ریښتیني ځوابونه",
      "deepA.desc": "خپله پوښتنه په عامه توګه مطرح کړئ او روښانه تشریح ترلاسه کړئ. تصدیق شوي ډاکټران نښه لري، ټولنه ګټورو ځوابونو ته رایه ورکوي، او مصنوعي هوش اوږدې مخینې لنډوي.",
      "deepA.li1": "د تصدیق شوي متخصص نښه",
      "deepA.li2": "رایه ورکول او د غوره ځواب نښه کول",
      "deepA.li3": "د مصنوعي هوش لنډیز په درې واړو ژبو",

      "deepB.pill": "د مصنوعي هوش مرسته",
      "deepB.title": "نښې په باور سره پوه شئ",
      "deepB.desc": "د نښو څیړونکی او د طبیب مرستیال په انګلیسي، دري او پښتو ځواب ورکوي او راتلونکی ګام وړاندیز کوي — تل یادونه کوي چې دا لارښوونه ده، نه تشخیص.",
      "deepB.li1": "څوژبیز او اسانه پوهیدونکی",
      "deepB.li2": "د ډاکټر ته د مراجعې وخت وړاندیز",
      "deepB.li3": "لارښوونه، نه تشخیص",

      "deepC.pill": "د موضوع خونې",
      "deepC.title": "د هرې روغتیایي موضوع لپاره ټولنه",
      "deepC.desc": "ستاسو د خوښې موضوع خونو ته ورشئ. غږیز پیغامونه او انځورونه شریک کړئ، د مخینې خبرې اترې تعقیب کړئ او د ټولنې له باټانو مرسته واخلئ.",
      "deepC.li1": "غږیز پیغامونه، انځورونه او مخینې",
      "deepC.li2": "اعلانونه او ګټور باټان",
      "deepC.li3": "تر ۱۵۰ زیاتې فعالې موضوعات",

      "deepD.pill": "چټ او اړیکه",
      "deepD.title": "تل اړیکه، حتی آفلاین",
      "deepD.desc": "له خلکو او خونو سره یو‌په‌یو پیغام ولیکئ. تاریخچه ستاسو په وسیله کې خوندي کیږي، نو خبرې اترې سمدستي بار کیږي او په کم انټرنېټ یا آفلاین هم کار کوي.",
      "deepD.li1": "چټک خصوصي یو‌په‌یو چټ",
      "deepD.li2": "خوندي تاریخچه او آفلاین ملاتړ",
      "deepD.li3": "تصدیق شوو چمتوکوونکو ته پیغام",

      "providers.title": "د درملتون، کلینیک یا لابراتوار خاوند یاست؟",
      "providers.desc": "خپل کاروبار په طبیب کې ثبت کړئ، خپلو نږدې ناروغانو ته ورسیږئ، او درمل، پلور، نوبتونه او ریکارډونه د اپ دننه اداره کړئ.",
      "providers.cta": "نور معلومات",

      "safety.eyebrow": "باور او خوندیتوب",
      "safety.title": "ستاسو د خوندیتوب لپاره جوړ شوی",
      "safety.lead": "تصدیق او څارنه د ټولنې باور او درناوی ساتي.",
      "s1.title": "د هویت تصدیق",
      "s1.desc": "تصدیق شوې نښې ښیي چې پر چا باور کولی شئ.",
      "s2.title": "جوازي ډاکټران",
      "s2.desc": "تر تصدیق مخکې د متخصصینو معتبر جواز کتل کیږي.",
      "s3.title": "څارنه او راپور",
      "s3.desc": "د زیانمن منځپانګې د راپور او لرې کولو اسانه وسایل.",
      "s4.title": "مصنوعي هوش = یوازې لارښوونه",
      "s4.desc": "مصنوعي هوش لارښوونه کوي، نه تشخیص.",

      "gallery.title": "د اپ دننه یوه کتنه",
      "gallery.lead": "د طبیب اپ ریښتیني انځورونه.",

      "steps.title": "څنګه کار کوي",
      "step1.title": "ډاونلوډ او د ژبې ټاکنه",
      "step1.desc": "وړیا یې نصب کړئ او په څو ثانیو کې انګلیسي، دري یا پښتو وټاکئ.",
      "step2.title": "پوښتنه وکړئ یا خدمات ومومئ",
      "step2.desc": "پوښتنه مطرح کړئ یا نږدې کلینیک، درملتون او لابراتوار ولټوئ.",
      "step3.title": "ټولنو ته ورشئ او زده کړئ",
      "step3.desc": "د موضوع خونې تعقیب کړئ او هره ورځ یوه روغتیایي لارښوونه ترلاسه کړئ.",

      "faq.title": "ډېرې پوښتل شوې پوښتنې",
      "faq.q1": "ایا طبیب وړیا دی؟",
      "faq.a1": "هو. د طبیب ډاونلوډ او کارول له Google Play وړیا دي.",
      "faq.q2": "کومې ژبې ملاتړ کوي؟",
      "faq.a2": "انګلیسي، دري او پښتو، د ښي‌نه‌کیڼ بشپړ ملاتړ سره.",
      "faq.q3": "ایا آفلاین کار کوي؟",
      "faq.a3": "د چټ تاریخچه پر وسیله خوندي کیږي او په کم انټرنېټ یا آفلاین کار کوي.",
      "faq.q4": "ایا مصنوعي هوش ډاکټر دی؟",
      "faq.a4": "نه. مصنوعي هوش لارښوونه کوي چې خپلې روغتیا ته پوه شئ — د مسلکي پاملرنې ځای نه نیسي.",
      "faq.q5": "تصدیق شوي ډاکټران څنګه ومومم؟",
      "faq.a5": "د «نږدې» ځانګړنه وکاروئ ترڅو تصدیق شوي ډاکټران، کلینیکونه، درملتونونه او لابراتوارونه ومومئ.",
      "faq.q6": "د درملتون یا کلینیک خاوند یم — څنګه یې ثبت کړم؟",
      "faq.a6": "خپل کاروبار د اپ دننه ثبت او اداره کولی شئ. د «چمتوکوونکو لپاره» مخ وګورئ.",
      "faq.q7": "زما معلومات څنګه اداره کیږي؟",
      "faq.a7": "موږ محرمیت ته ارزښت ورکوو او هیڅکله ستاسو معلومات نه پلوري. زموږ د محرمیت پالیسي ولولئ.",

      "finalcta.title": "ستاسو روغتیایي پوښتنو ته ځواب.",
      "finalcta.sub": "وړیا ډاونلوډ. English · دری · پښتو.",

      "footer.tagline": "روغتیایي پوښتنه‌ځواب، د مصنوعي هوش لارښوونه، ټولنې او نږدې خدمات — د افغانستان لپاره، ستاسو په ژبه.",
      "footer.product": "محصول",
      "footer.legal": "حقوقي",
      "footer.privacy": "د محرمیت پالیسي",
      "footer.terms": "د کارونې شرایط",
      "footer.deletion": "د حساب او معلوماتو حذف",
      "footer.language": "ژبه",
      "footer.contact": "اړیکه",
      "footer.rights": "ټول حقوق خوندي دي.",
      "footer.disclaimer": "طبیب روغتیایي لارښوونه کوي، نه طبي تشخیص. په بیړنیو حالاتو کې له محلي خدماتو سره اړیکه ونیسئ."
    }
  };

  function apply(lang) {
    var dict = STR[lang] || STR.en;
    document.documentElement.lang = HTML_LANG[lang] || "en";
    document.documentElement.dir = RTL[lang] ? "rtl" : "ltr";

    document.querySelectorAll("[data-i18n]").forEach(function (el) {
      var key = el.getAttribute("data-i18n");
      if (dict[key] == null) return;
      if (el.hasAttribute("data-i18n-html")) el.innerHTML = dict[key];
      else el.textContent = dict[key];
    });
    document.querySelectorAll("[data-i18n-aria]").forEach(function (el) {
      var key = el.getAttribute("data-i18n-aria");
      if (dict[key] != null) el.setAttribute("aria-label", dict[key]);
    });

    document.querySelectorAll(".lang-switch button, .footer-lang button").forEach(function (b) {
      b.classList.toggle("active", b.getAttribute("data-lang") === lang);
    });

    try { localStorage.setItem("tabib_lang", lang); } catch (e) {}
  }

  function initLang() {
    var saved;
    try { saved = localStorage.getItem("tabib_lang"); } catch (e) {}
    var lang = saved || (document.documentElement.getAttribute("data-default-lang")) || "en";
    if (!STR[lang]) lang = "en";
    apply(lang);

    document.querySelectorAll(".lang-switch button, .footer-lang button").forEach(function (b) {
      b.addEventListener("click", function () { apply(b.getAttribute("data-lang")); });
    });
  }

  function initMenu() {
    var toggle = document.querySelector(".menu-toggle");
    var nav = document.querySelector(".mobile-nav");
    if (!toggle || !nav) return;
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    nav.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () { nav.classList.remove("open"); });
    });
  }

  function initHeader() {
    var header = document.querySelector(".site-header");
    if (!header) return;
    var onScroll = function () { header.classList.toggle("scrolled", window.scrollY > 8); };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  function initFaq() {
    document.querySelectorAll(".faq-item").forEach(function (item) {
      var q = item.querySelector(".faq-q");
      var a = item.querySelector(".faq-a");
      if (!q || !a) return;
      q.addEventListener("click", function () {
        var open = item.classList.toggle("open");
        q.setAttribute("aria-expanded", open ? "true" : "false");
        a.style.maxHeight = open ? a.scrollHeight + "px" : null;
      });
    });
  }

  function initReveal() {
    var els = document.querySelectorAll(".reveal");
    if (!("IntersectionObserver" in window) || !els.length) {
      els.forEach(function (e) { e.classList.add("in"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    els.forEach(function (e) { io.observe(e); });
  }

  document.addEventListener("DOMContentLoaded", function () {
    initLang();
    initMenu();
    initHeader();
    initFaq();
    initReveal();
  });
})();
