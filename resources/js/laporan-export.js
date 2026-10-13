export default function laporanExport() {
    return {
        activeExport: null,
        exportError: "",

        get isExporting() {
            return this.activeExport !== null;
        },

        async exportReport(exportType, resolveUrl) {
            if (this.isExporting) {
                return;
            }

            this.activeExport = exportType;
            this.exportError = "";

            try {
                const url = await resolveUrl();

                if (!url) {
                    return;
                }

                const response = await fetch(url, {
                    credentials: "same-origin",
                    headers: { Accept: "application/json" },
                });

                if (!response.ok) {
                    if (response.status === 429) {
                        const message = response.headers.get("Content-Type")?.includes("application/json")
                            ? (await response.json()).message
                            : await response.text();

                        this.exportError = message;
                    } else if (response.status === 401 || response.status === 419) {
                        this.exportError = "Sesi Anda telah berakhir. Muat ulang halaman dan masuk kembali.";
                    }

                    throw new Error("Export request failed");
                }

                const contentType = response.headers.get("Content-Type")?.split(";")[0];

                if (!["application/pdf", "application/zip"].includes(contentType)) {
                    throw new Error("Unexpected export response");
                }

                const file = await response.blob();
                const objectUrl = URL.createObjectURL(file);
                const link = document.createElement("a");
                const disposition = response.headers.get("Content-Disposition") ?? "";
                const filename = disposition.match(/filename="([^"]+)"|filename=([^;]+)/);

                link.href = objectUrl;
                link.download = filename?.[1] ?? filename?.[2] ?? `laporan.${contentType === "application/pdf" ? "pdf" : "zip"}`;
                document.body.append(link);

                try {
                    link.click();
                } finally {
                    link.remove();
                    setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
                }
            } catch {
                this.exportError ||= "Laporan gagal diunduh. Silakan coba lagi.";
            } finally {
                this.activeExport = null;
            }
        },
    };
}
