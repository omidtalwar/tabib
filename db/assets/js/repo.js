/**
 * repo.js — generic Firestore access for one pharmacy.
 *
 * All data lives under pharmacies/{pharmacyId}/{sub}. These helpers are
 * field-shape AGNOSTIC on purpose: the per-collection field names live in the
 * page modules and must mirror the app's *_model.dart (see docs/REFERENCE.md).
 * What this layer owns: paths, real-time subscriptions, atomic writes, soft
 * delete, and defensive date coercion (Timestamp <-> ISO string).
 */

import { db } from "./firebase.js";
import {
  collection,
  doc,
  query,
  where,
  orderBy,
  limit as qlimit,
  onSnapshot,
  getDocs,
  addDoc,
  setDoc,
  updateDoc,
  serverTimestamp,
  runTransaction,
  writeBatch,
  increment,
  Timestamp,
} from "https://www.gstatic.com/firebasejs/10.14.1/firebase-firestore.js";

export { where, orderBy, qlimit as limit, serverTimestamp, increment, Timestamp };

/* ---------------- paths ---------------- */

export function colRef(pharmacyId, sub) {
  if (!pharmacyId) throw new Error("colRef: pharmacyId required");
  return collection(db, "pharmacies", pharmacyId, sub);
}

export function docRef(pharmacyId, sub, id) {
  return doc(db, "pharmacies", pharmacyId, sub, id);
}

export function pharmacyRef(pharmacyId) {
  return doc(db, "pharmacies", pharmacyId);
}

/* ---------------- multi-pharmacy (owner switcher) ---------------- */

/** List pharmacies owned by a user → [{ id, name }]. Rules allow owners to list. */
export async function listOwnedPharmacies(uid) {
  const snap = await getDocs(query(collection(db, "pharmacies"), where("ownerUid", "==", uid)));
  return snap.docs.map((d) => ({ id: d.id, name: d.data().name || d.data().pharmacyName || d.id }));
}

const ACTIVE_KEY = "tabib_active_pharmacy";
export function getActivePharmacy() {
  try { return localStorage.getItem(ACTIVE_KEY); } catch { return null; }
}
export function setActivePharmacy(id) {
  try { localStorage.setItem(ACTIVE_KEY, id); } catch {}
}

/* ---------------- date coercion (REFERENCE §5.3) ----------------
 * The app historically wrote ISO strings; new writes should be Timestamps.
 * Read defensively, write Timestamps. */

/** Coerce a Firestore value (Timestamp | ISO string | Date | millis) to a JS Date, or null. */
export function toDate(value) {
  if (value == null) return null;
  if (value instanceof Timestamp) return value.toDate();
  if (value instanceof Date) return value;
  if (typeof value === "number") return new Date(value);
  if (typeof value === "string") {
    const d = new Date(value);
    return isNaN(d.getTime()) ? null : d;
  }
  // Plain object that looks like a Timestamp ({seconds, nanoseconds}).
  if (typeof value === "object" && typeof value.seconds === "number") {
    return new Date(value.seconds * 1000);
  }
  return null;
}

/**
 * Coerce a JS Date | ISO string | millis to an ISO-8601 string for writing.
 * IMPORTANT: the Flutter app stores pharmacy dates as ISO strings (Isar
 * toMap → DateTime.toIso8601String()), NOT Firestore Timestamps, and reads them
 * back with DateTime.tryParse(value as String). Writing a Firestore Timestamp
 * here would make the app throw on read. So the portal writes ISO strings too.
 */
export function toIso(value) {
  const d = toDate(value);
  return d ? d.toISOString() : null;
}

/** RFC4122 v4 id — matches the app's Uuid().v4() document ids. */
export function uuid() {
  if (crypto.randomUUID) return crypto.randomUUID();
  return "10000000-1000-4000-8000-100000000000".replace(/[018]/g, (c) =>
    (c ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))).toString(16)
  );
}

/* ---------------- reads ---------------- */

/**
 * Subscribe to a collection in real time.
 *   watch(pharmacyId, "drugs", { constraints:[where("isActive","==",true)], onData, onError })
 * Returns an unsubscribe fn. Each doc is { id, ...data }.
 */
export function watch(pharmacyId, sub, { constraints = [], onData, onError } = {}) {
  const q = constraints.length
    ? query(colRef(pharmacyId, sub), ...constraints)
    : colRef(pharmacyId, sub);
  return onSnapshot(
    q,
    (snap) => onData && onData(snap.docs.map((d) => ({ id: d.id, ...d.data() }))),
    (err) => (onError ? onError(err) : console.error(`watch(${sub})`, err))
  );
}

/** One-shot read of a collection (e.g. for reports). */
export async function readAll(pharmacyId, sub, constraints = []) {
  const q = constraints.length
    ? query(colRef(pharmacyId, sub), ...constraints)
    : colRef(pharmacyId, sub);
  const snap = await getDocs(q);
  return snap.docs.map((d) => ({ id: d.id, ...d.data() }));
}

/* ---------------- writes ----------------
 * Documents mirror the app's Isar toMap() wire shape: an explicit string doc id
 * that is ALSO stored in a `firestoreId` field, a local int `id`, ISO date
 * strings, and an `isDirty` flag. Page modules build the per-collection map
 * (mirroring *_isar.dart); this layer just adds id plumbing and writes it.
 * Dates must already be ISO strings — use toIso() when building `data`. */

/**
 * Create a doc with a generated uuid id. Returns the id. Stamps the matching
 * `firestoreId` field; `isDirty:false` because a direct web write is already
 * "synced" (the app's queue uses isDirty for its own pending state).
 */
export async function create(pharmacyId, sub, data) {
  const id = data.firestoreId || uuid();
  await setDoc(docRef(pharmacyId, sub, id), {
    id: data.id ?? Date.now(),
    ...data,
    firestoreId: id,
    isDirty: false,
  });
  return id;
}

/** Create/replace a doc at a known id (keeps firestoreId in sync). */
export function put(pharmacyId, sub, id, data, { merge = true } = {}) {
  return setDoc(docRef(pharmacyId, sub, id), { ...data, firestoreId: id }, { merge });
}

/** Patch fields on a doc. Caller passes ISO strings for any dates. */
export function update(pharmacyId, sub, id, patch) {
  return updateDoc(docRef(pharmacyId, sub, id), patch);
}

/** Soft delete — never hard delete (set isActive=false). Drugs only. */
export function softDelete(pharmacyId, sub, id) {
  return updateDoc(docRef(pharmacyId, sub, id), { isActive: false });
}

/** Atomic stock change via FieldValue.increment (e.g. restock). */
export function adjustStock(pharmacyId, drugId, delta, extra = {}) {
  return updateDoc(docRef(pharmacyId, "drugs", drugId), {
    stockQuantity: increment(delta),
    ...extra,
  });
}

/**
 * Run an atomic transaction. Used by POS so the sale write AND every line-item
 * stock decrement commit together, with oversell protection (REFERENCE §5.6).
 * `fn` receives the Firestore `transaction` object and helper refs.
 * NOTE: transactions require connectivity (they round-trip). For offline POS we
 * use commitSale() instead.
 */
export function txn(fn) {
  return runTransaction(db, (transaction) => fn(transaction, { docRef, colRef, pharmacyRef }));
}

/**
 * Commit a sale + decrement each line's drug stock as ONE atomic writeBatch.
 * A writeBatch is all-or-nothing AND works offline (queued, replayed atomically),
 * so sales can be entered offline. Oversell is checked against cached stock in
 * the page before calling this (a batch can't read). Stock uses FieldValue
 * .increment so concurrent decrements from app/web compose correctly.
 *   items: [{ drugId, quantity }, ...]
 */
export function commitSale(pharmacyId, saleId, salePayload, items) {
  const b = writeBatch(db);
  b.set(docRef(pharmacyId, "sales", saleId), { ...salePayload, firestoreId: saleId });
  const now = new Date().toISOString();
  for (const it of items) {
    if (!it.drugId) continue;
    b.update(docRef(pharmacyId, "drugs", it.drugId), {
      stockQuantity: increment(-Math.abs(it.quantity || 0)),
      lastSyncedAt: now,
    });
  }
  return b.commit();
}
