const standaloneMode = window.matchMedia("(display-mode: standalone)");

let pendingInstallPrompt = null;
let installationInProgress = false;
let installationCompleted = false;
let statusMessage = "";

function getPlatform() {
    const userAgent = navigator.userAgent;

    const isIos =
        /iPhone|iPad|iPod/i.test(userAgent) ||
        (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);

    if (isIos) {
        return "ios";
    }

    return /Android/i.test(userAgent) ? "android" : "other";
}

function isStandalone() {
    return standaloneMode.matches || navigator.standalone === true;
}

function renderInstallCard() {
    const card = document.querySelector("[data-pwa-install]");

    if (!card) {
        return;
    }

    card.hidden = isStandalone() || installationCompleted;

    const canInstall = pendingInstallPrompt !== null;
    const platform = getPlatform();

    const action = card.querySelector("[data-pwa-install-action]");
    const button = card.querySelector("[data-pwa-install-button]");
    const status = card.querySelector("[data-pwa-install-status]");

    action.hidden = !canInstall && !installationInProgress;
    button.disabled = installationInProgress;
    button.textContent = installationInProgress
        ? "Menunggu pilihan Anda…"
        : "Instal aplikasi";

    status.textContent = statusMessage;

    card.querySelectorAll("[data-pwa-install-help]").forEach((help) => {
        help.hidden =
            canInstall ||
            installationInProgress ||
            help.dataset.pwaInstallHelp !== platform;
    });
}

async function requestInstallation() {
    if (!pendingInstallPrompt || installationInProgress) {
        return;
    }

    const installPrompt = pendingInstallPrompt;

    pendingInstallPrompt = null;
    installationInProgress = true;
    statusMessage = "";

    renderInstallCard();

    try {
        await installPrompt.prompt();

        const { outcome } = await installPrompt.userChoice;

        statusMessage =
            outcome === "accepted"
                ? "Permintaan instalasi diterima. Ikuti proses pada perangkat Anda."
                : "Instalasi dibatalkan. Anda tetap dapat menggunakan aplikasi di browser.";
    } catch (error) {
        statusMessage =
            "Instalasi belum dapat dibuka. Gunakan petunjuk di bawah.";

        console.error("Gagal membuka dialog instalasi:", error);
    } finally {
        installationInProgress = false;

        renderInstallCard();
    }
}

window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();

    pendingInstallPrompt = event;
    statusMessage = "";

    renderInstallCard();
});

window.addEventListener("appinstalled", () => {
    pendingInstallPrompt = null;
    installationCompleted = true;
    statusMessage = "";

    renderInstallCard();
});

document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    if (!event.target.closest("[data-pwa-install-button]")) {
        return;
    }

    event.preventDefault();

    void requestInstallation();
});

standaloneMode.addEventListener("change", renderInstallCard);
window.addEventListener("pageshow", renderInstallCard);
document.addEventListener("livewire:navigated", renderInstallCard);

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", renderInstallCard, {
        once: true,
    });
} else {
    renderInstallCard();
}
