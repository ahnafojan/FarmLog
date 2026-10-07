const CACHE_PREFIX = "pariwangi-offline-";
const CACHE_NAME = `${CACHE_PREFIX}v1`;
const OFFLINE_URL = "/offline.html";

self.addEventListener("install", (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);

            await cache.add(new Request(OFFLINE_URL, { cache: "reload" }));
        })(),
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        (async () => {
            const cacheNames = await caches.keys();

            await Promise.all(
                cacheNames
                    .filter((name) => {
                        return (
                            name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME
                        );
                    })
                    .map((name) => caches.delete(name)),
            );

            await self.clients.claim();
        })(),
    );
});

self.addEventListener("fetch", (event) => {
    const request = event.request;
    const url = new URL(request.url);

    const isAppPage =
        url.pathname === "/app" || url.pathname.startsWith("/app/");

    if (
        request.method !== "GET" ||
        request.mode !== "navigate" ||
        url.origin !== self.location.origin ||
        !isAppPage
    ) {
        return;
    }

    event.respondWith(
        (async () => {
            try {
                return await fetch(request, { cache: "no-store" });
            } catch {
                const cache = await caches.open(CACHE_NAME);
                const offlinePage = await cache.match(OFFLINE_URL);

                return (
                    offlinePage ??
                    new Response(
                        "Koneksi diperlukan. Periksa internet lalu muat ulang halaman.",
                        {
                            status: 503,
                            headers: {
                                "Content-Type": "text/plain; charset=utf-8",
                            },
                        },
                    )
                );
            }
        })(),
    );
});
