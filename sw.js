const CACHE_NAME = "househub-static-v3";
const API_CACHE_NAME = "househub-api-v1";
const ASSETS_TO_CACHE = [
  "/offline.php",
  "/global.css",
  "/dark-mode.css",
  "/favicon.png",
];

// --- Gestion de la file d'attente (IndexedDB) pour les actions hors ligne ---
function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open("househub-offline-sync", 1);
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains("sync-queue")) {
        db.createObjectStore("sync-queue", { autoIncrement: true });
      }
    };
    request.onsuccess = (event) => resolve(event.target.result);
    request.onerror = (event) => reject(event.target.error);
  });
}

async function dequeueSyncActions() {
  try {
    const db = await openDB();
    const transaction = db.transaction("sync-queue", "readwrite");
    const store = transaction.objectStore("sync-queue");
    const request = store.getAll();

    request.onsuccess = async () => {
      const actions = request.result;
      for (const action of actions) {
        try {
          await fetch(action.url, {
            method: action.method,
            headers: action.headers,
            body: action.body,
          });

          // Suppression si succès
          const delTx = db.transaction("sync-queue", "readwrite");
          delTx.objectStore("sync-queue").delete(action.id);
        } catch (err) {
          console.error("Erreur de synchronisation en arrière-plan :", err);
          break;
        }
      }
    };
  } catch (e) {
    console.error("Erreur ouverture DB dans le Service Worker", e);
  }
}

// Background Sync exclusif à Chromium
self.addEventListener("sync", (event) => {
  if (event.tag === "househub-sync") {
    event.waitUntil(dequeueSyncActions());
  }
});

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Utilise catch pour éviter qu'un fichier manquant (ex: favicon) fasse crasher l'installation
      return cache
        .addAll(ASSETS_TO_CACHE)
        .catch((err) => console.error("Erreur de cache initial", err));
    }),
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== API_CACHE_NAME) {
            return caches.delete(cacheName);
          }
        }),
      );
    }),
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const requestUrl = new URL(event.request.url);

  if (
    requestUrl.pathname.includes("/api/") ||
    requestUrl.pathname.includes("api.php")
  ) {
    if (event.request.method === "GET") {
      event.respondWith(
        fetch(event.request)
          .then((response) => {
            const responseClone = response.clone();
            caches
              .open(API_CACHE_NAME)
              .then((cache) => cache.put(event.request, responseClone));
            return response;
          })
          .catch(() => caches.match(event.request)),
      );
    }
    return;
  }

  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          const responseClone = response.clone();
          caches
            .open(CACHE_NAME)
            .then((cache) => cache.put(event.request, responseClone));
          return response;
        })
        .catch(() => {
          return caches.match(event.request).then((cachedResponse) => {
            return cachedResponse || caches.match("/offline.php");
          });
        }),
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then((response) => {
      if (response) return response;
      return fetch(event.request)
        .then((networkResponse) => {
          if (
            networkResponse &&
            networkResponse.status === 200 &&
            (networkResponse.type === "basic" ||
              networkResponse.type === "cors")
          ) {
            const responseToCache = networkResponse.clone();
            caches
              .open(CACHE_NAME)
              .then((cache) => cache.put(event.request, responseToCache));
          }
          return networkResponse;
        })
        .catch(() => null);
    }),
  );
});
