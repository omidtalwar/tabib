/**
 * autobackup.js — automatic, always-on local backup of the whole pharmacy.
 *
 * Goal: a complete copy of every collection is kept ON THE DEVICE at all times,
 * refreshed whenever the device is online. If records are ever deleted in the
 * cloud, the last good full snapshot is still here and can be restored.
 *
 * Where it's stored: IndexedDB (database "tabib_autobackup", store "snapshots"),
 * which — unlike localStorage's ~5 MB cap — comfortably holds a full dataset.
 * A tiny metadata pointer (time + record count) is mirrored to localStorage so
 * the UI can show status instantly without opening IndexedDB.
 *
 * When it runs (admins only — a full backup needs read access to every section):
 *   - once at app start (if online),
 *   - again whenever the browser fires the "online" event (reconnect), and
 *   - on a periodic timer while online.
 *
 * It reuses exportBackup() from backup.js, so the on-device snapshot is the
 * exact same shape as a downloaded backup file and restores the same way.
 */

import { exportBackup, countDocs } from "./backup.js";

const IDB_NAME = "tabib_autobackup";
const IDB_STORE = "snapshots";
const LATEST_KEY = "latest";
const META_KEY = "tabib_autobackup_meta"; // localStorage: { at, count, pharmacyId }
const ENABLED_KEY = "tabib_autobackup_enabled";
const INTERVAL_MS = 15 * 60 * 1000; // re-snapshot every 15 min while online

/* ---------------- tiny IndexedDB wrapper ---------------- */

function openIdb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(IDB_NAME, 1);
    req.onupgradeneeded = () => {
      if (!req.result.objectStoreNames.contains(IDB_STORE)) {
        req.result.createObjectStore(IDB_STORE);
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

async function idbPut(key, value) {
  const db = await openIdb();
  try {
    await new Promise((resolve, reject) => {
      const tx = db.transaction(IDB_STORE, "readwrite");
      tx.objectStore(IDB_STORE).put(value, key);
      tx.oncomplete = resolve;
      tx.onerror = () => reject(tx.error);
      tx.onabort = () => reject(tx.error);
    });
  } finally {
    db.close();
  }
}

async function idbGet(key) {
  const db = await openIdb();
  try {
    return await new Promise((resolve, reject) => {
      const tx = db.transaction(IDB_STORE, "readonly");
      const r = tx.objectStore(IDB_STORE).get(key);
      r.onsuccess = () => resolve(r.result || null);
      r.onerror = () => reject(r.error);
    });
  } finally {
    db.close();
  }
}

/* ---------------- metadata + status ---------------- */

const _listeners = new Set();
function emit() { const m = getMeta(); for (const cb of _listeners) cb(m); }

/** Subscribe to auto-backup status changes. Fires immediately. Returns unsub. */
export function onAutoBackup(cb) {
  _listeners.add(cb);
  cb(getMeta());
  return () => _listeners.delete(cb);
}

/** Last-backup metadata: { at, count, pharmacyId } or null. */
export function getMeta() {
  try { return JSON.parse(localStorage.getItem(META_KEY)) || null; } catch { return null; }
}

function setMeta(meta) {
  try { localStorage.setItem(META_KEY, JSON.stringify(meta)); } catch {}
  emit();
}

export function isEnabled() {
  try { return localStorage.getItem(ENABLED_KEY) !== "0"; } catch { return true; }
}

export function setEnabled(on) {
  try { localStorage.setItem(ENABLED_KEY, on ? "1" : "0"); } catch {}
  if (on) { if (_pid) startAutoBackup(_pid); } // startAutoBackup also snapshots now
  else stopAutoBackup();
  emit();
}

/* ---------------- the snapshot job ---------------- */

let _pid = null;
let _timer = null;
let _running = false;
let _onlineHandler = null;

/**
 * Take one full snapshot now and store it on the device.
 * No-ops if disabled, already running, or offline.
 * @returns {Promise<object|null>} the snapshot taken, or null if skipped
 */
export async function runOnce(pid = _pid, { force = false } = {}) {
  if (!pid) return null;
  if (!force && (!isEnabled() || _running)) return null;
  if (!force && typeof navigator !== "undefined" && navigator.onLine === false) return null;

  _running = true;
  emit(); // lets the UI show a "backing up…" state via isRunning()
  try {
    const backup = await exportBackup(pid);
    await idbPut(LATEST_KEY, backup);
    setMeta({ at: backup.exportedAt, count: countDocs(backup), pharmacyId: pid });
    return backup;
  } catch (e) {
    console.warn("autobackup: snapshot failed", e);
    return null;
  } finally {
    _running = false;
    emit();
  }
}

export function isRunning() { return _running; }

/** Get the latest on-device snapshot object (or null). */
export function getLatestSnapshot() { return idbGet(LATEST_KEY); }

/**
 * Start automatic backups for a pharmacy: snapshot now, on reconnect, and on a
 * timer while online. Safe to call repeatedly (it resets cleanly). Admins only.
 */
export function startAutoBackup(pid) {
  _pid = pid;
  stopAutoBackup();
  if (!isEnabled()) return () => {};

  runOnce(pid); // initial snapshot at boot

  _timer = setInterval(() => runOnce(pid), INTERVAL_MS);

  _onlineHandler = () => runOnce(pid);
  window.addEventListener("online", _onlineHandler);

  return () => stopAutoBackup();
}

export function stopAutoBackup() {
  if (_timer) { clearInterval(_timer); _timer = null; }
  if (_onlineHandler) { window.removeEventListener("online", _onlineHandler); _onlineHandler = null; }
}
