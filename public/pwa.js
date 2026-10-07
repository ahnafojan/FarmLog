(() => {
    document.addEventListener("livewire:navigate", (event) => {
        if (!navigator.onLine) {
            event.preventDefault();
        }
    });

    if (!("serviceWorker" in navigator) || !window.isSecureContext) {
        return;
    }

    const registerServiceWorker = async () => {
        try {
            await navigator.serviceWorker.register("/service-worker.js", {
                scope: "/",
                updateViaCache: "none",
            });
        } catch (error) {
            console.error("Pendaftaran service worker gagal:", error);
        }
    };

    if (document.readyState === "complete") {
        void registerServiceWorker();
    } else {
        window.addEventListener("load", registerServiceWorker, {
            once: true,
        });
    }
})();
