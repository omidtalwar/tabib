/**
 * Firebase init — Auth + Firestore (persistent IndexedDB cache) + Functions.
 *
 * Uses the official Firebase JS SDK v10 ESM build from gstatic (no bundler needed
 * for static hosting). The persistent local cache makes the portal work offline:
 * the SDK serves reads from IndexedDB and queues writes, replaying them on
 * reconnect — do NOT build a custom offline queue.
 *
 * ┌───────────────────────────────────────────────────────────────────────┐
 * │  PLACEHOLDER CONFIG — replace `firebaseConfig` with your project's WEB  │
 * │  config object (Firebase console → Project settings → Your apps → Web). │
 * │  Then restrict the API key by HTTP referrer (tabib.af/*) in Google      │
 * │  Cloud console. Nothing connects to a live project until this is set.   │
 * └───────────────────────────────────────────────────────────────────────┘
 */

import { initializeApp } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-auth.js";
import {
  initializeFirestore,
  persistentLocalCache,
  persistentSingleTabManager,
} from "https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js";
import { getFunctions } from "https://www.gstatic.com/firebasejs/10.14.1/firebase-functions.js";

// Firebase WEB config for project tabib-01 (registered web app
// "Tabib Pharmacy Portal"). These values are not secret — security comes from
// Firestore rules + the API-key HTTP-referrer restriction (set tabib.af/* in
// Google Cloud console).
export const firebaseConfig = {
  apiKey: "AIzaSyCfP00f04fRBy832mC106UBOE6tGr5d2IQ",
  authDomain: "tabib-01.firebaseapp.com",
  projectId: "tabib-01",
  storageBucket: "tabib-01.firebasestorage.app",
  messagingSenderId: "1026232203696",
  appId: "1:1026232203696:web:59b2905bc6a43e6b94be75",
  measurementId: "G-3DVMBH3P7R",
};

export const isConfigured = !Object.values(firebaseConfig).some(
  (v) => typeof v === "string" && v.startsWith("REPLACE_ME")
);

export const app = initializeApp(firebaseConfig);

export const auth = getAuth(app);

// Persistent IndexedDB cache so the portal works fully offline (reads served
// from cache, writes queued + replayed on reconnect). Single-tab manager with
// forceOwnership: this tab always takes ownership of the cache — the multi-tab
// manager could leave a tab without offline data. Must be created via
// initializeFirestore before any getFirestore() call elsewhere.
export const db = initializeFirestore(app, {
  localCache: persistentLocalCache({
    tabManager: persistentSingleTabManager({ forceOwnership: true }),
  }),
});

// Region must match where the functions are deployed (default us-central1).
export const functions = getFunctions(app);
