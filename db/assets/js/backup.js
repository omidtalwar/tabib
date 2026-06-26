/**
 * backup.js — full export / restore of one pharmacy's Firestore data.
 *
 * A backup is a single self-contained JSON file holding the pharmacy doc plus
 * every document of every known sub-collection under pharmacies/{pharmacyId}.
 * Importing it writes every document back by its own id, so the whole dataset
 * is rebuilt even after the data has been wiped.
 *
 * Firestore's client SDK can't enumerate sub-collections, so the set of
 * collections is listed explicitly below. Keep it in sync with repo.js / the
 * page modules when a new collection is added.
 */

import { db } from "./firebase.js";
import { colRef, docRef, pharmacyRef } from "./repo.js";
import {
  getDoc,
  getDocs,
  setDoc,
  writeBatch,
} from "https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js";

/** Every sub-collection under pharmacies/{pid}. Order is not significant. */
export const BACKUP_COLLECTIONS = [
  "drugs",
  "suppliers",
  "patients",
  "sales",
  "purchases",
  "returns",
  "stock_adjustments",
  "customer_payments",
  "prescriptions",
  "expenses",
  "staff",
];

const FORMAT = "tabib-backup";
const VERSION = 1;
const BATCH_LIMIT = 450; // Firestore caps a writeBatch at 500 ops; stay under.

/**
 * Read the whole pharmacy into a plain JS backup object.
 * onProgress({ phase, collection, done, total }) is called as it goes.
 */
export async function exportBackup(pharmacyId, onProgress = () => {}) {
  if (!pharmacyId) throw new Error("exportBackup: pharmacyId required");

  const backup = {
    format: FORMAT,
    version: VERSION,
    pharmacyId,
    exportedAt: new Date().toISOString(),
    pharmacy: null,
    collections: {},
  };

  // The pharmacy document itself (name, settings, ownerUid, …).
  try {
    const pSnap = await getDoc(pharmacyRef(pharmacyId));
    if (pSnap.exists()) backup.pharmacy = pSnap.data();
  } catch (e) {
    console.warn("exportBackup: pharmacy doc", e);
  }

  let done = 0;
  for (const sub of BACKUP_COLLECTIONS) {
    onProgress({ phase: "export", collection: sub, done, total: BACKUP_COLLECTIONS.length });
    const snap = await getDocs(colRef(pharmacyId, sub));
    backup.collections[sub] = snap.docs.map((d) => ({ __id: d.id, ...d.data() }));
    done++;
  }
  onProgress({ phase: "export", collection: null, done, total: BACKUP_COLLECTIONS.length });

  return backup;
}

/** Count total documents in a backup object. */
export function countDocs(backup) {
  let n = backup && backup.pharmacy ? 1 : 0;
  const cols = (backup && backup.collections) || {};
  for (const sub of Object.keys(cols)) n += (cols[sub] || []).length;
  return n;
}

/** Validate the shape of a parsed backup file; throws with a clear message. */
export function assertValidBackup(backup) {
  if (!backup || typeof backup !== "object") throw new Error("Not a valid backup file.");
  if (backup.format !== FORMAT) throw new Error("This file is not a Tabib backup.");
  if (!backup.collections || typeof backup.collections !== "object") {
    throw new Error("Backup file is missing its data.");
  }
}

/**
 * Restore every document from a backup object into the given pharmacy.
 * Documents are written by their original id (full overwrite), so an empty
 * database is rebuilt exactly. Existing docs with the same id are replaced.
 * Docs are committed in batches to stay within Firestore limits.
 *
 * onProgress({ phase, collection, done, total }) reports written-doc progress.
 * @returns {Promise<number>} number of documents written
 */
export async function restoreBackup(pharmacyId, backup, onProgress = () => {}) {
  if (!pharmacyId) throw new Error("restoreBackup: pharmacyId required");
  assertValidBackup(backup);

  const total = countDocs(backup);
  let done = 0;
  let batch = writeBatch(db);
  let ops = 0;

  const flush = async () => {
    if (ops > 0) { await batch.commit(); batch = writeBatch(db); ops = 0; }
  };

  // Pharmacy doc first (merge so we don't clobber fields the backup may omit).
  if (backup.pharmacy && typeof backup.pharmacy === "object") {
    await setDoc(pharmacyRef(pharmacyId), backup.pharmacy, { merge: true });
    done++;
    onProgress({ phase: "restore", collection: "pharmacy", done, total });
  }

  for (const sub of Object.keys(backup.collections)) {
    const rows = backup.collections[sub] || [];
    for (const row of rows) {
      const { __id, ...data } = row;
      const id = __id || data.firestoreId || data.id;
      if (id == null) continue; // skip a doc with no resolvable id
      batch.set(docRef(pharmacyId, sub, String(id)), data);
      ops++;
      done++;
      if (ops >= BATCH_LIMIT) {
        await flush();
        onProgress({ phase: "restore", collection: sub, done, total });
      }
    }
    onProgress({ phase: "restore", collection: sub, done, total });
  }

  await flush();
  return done;
}

/** Trigger a browser download of `obj` as a pretty-printed .json file. */
export function downloadJSON(filename, obj) {
  const blob = new Blob([JSON.stringify(obj, null, 2)], { type: "application/json;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** Convenience: export the pharmacy and download it as a timestamped file. */
export async function exportAndDownload(pharmacyId, onProgress) {
  const backup = await exportBackup(pharmacyId, onProgress);
  const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-");
  downloadJSON(`tabib-backup-${pharmacyId}-${stamp}.json`, backup);
  return backup;
}
