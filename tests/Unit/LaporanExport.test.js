import assert from "node:assert/strict";
import { afterEach, mock, test } from "node:test";
import laporanExport from "../../resources/js/laporan-export.js";

afterEach(() => mock.restoreAll());

for (const [contentType, filename, exportType, otherExportType] of [
    ["application/pdf", "laporan.pdf", "penjualan", "pengeluaran"],
    ["application/zip", "laporan.zip", "pengeluaran", "penjualan"],
]) {
    test(`blocks repeated clicks until the ${filename} file is ready, then downloads it and unlocks`, async () => {
        const state = laporanExport();
        let finishUrl;
        let finishFile;
        const fileReady = new Promise((resolve) => { finishFile = resolve; });
        const resolveUrl = mock.fn(() => new Promise((resolve) => { finishUrl = resolve; }));
        const fetchMock = mock.method(globalThis, "fetch", async () => ({
            ok: true,
            headers: new Headers({ "Content-Type": contentType, "Content-Disposition": `attachment; filename="${filename}"` }),
            blob: () => fileReady,
        }));
        const link = { click: mock.fn(), remove: mock.fn() };
        const originalDocument = Object.getOwnPropertyDescriptor(globalThis, "document");
        Object.defineProperty(globalThis, "document", {
            configurable: true,
            value: { createElement: () => link, body: { append: mock.fn() } },
        });
        const createUrl = mock.method(URL, "createObjectURL", () => "blob:laporan");
        const revokeUrl = mock.method(URL, "revokeObjectURL", () => {});
        mock.method(globalThis, "setTimeout", (callback) => callback());

        try {
            const pending = state.exportReport(exportType, resolveUrl);
            assert.equal(state.isExporting, true);
            assert.equal(state.activeExport, exportType);
            await state.exportReport(exportType, resolveUrl);
            assert.equal(resolveUrl.mock.callCount(), 1);
            finishUrl("/laporan/download");
            await Promise.resolve();
            await Promise.resolve();
            assert.equal(state.isExporting, true);
            assert.equal(link.click.mock.callCount(), 0);
            await state.exportReport(otherExportType, resolveUrl);
            assert.equal(fetchMock.mock.callCount(), 1);
            assert.equal(state.activeExport, exportType);
            assert.equal(state.isExporting, true);

            const file = new Blob(["report"], { type: contentType });
            finishFile(file);
            await pending;

            assert.equal(state.isExporting, false);
            assert.equal(state.activeExport, null);
            assert.equal(state.exportError, "");
            assert.equal(link.download, filename);
            assert.equal(link.href, "blob:laporan");
            assert.equal(link.click.mock.callCount(), 1);
            assert.equal(link.remove.mock.callCount(), 1);
            assert.equal(createUrl.mock.calls[0].arguments[0], file);
            assert.equal(revokeUrl.mock.calls[0].arguments[0], "blob:laporan");
        } finally {
            if (originalDocument) {
                Object.defineProperty(globalThis, "document", originalDocument);
            } else {
                delete globalThis.document;
            }
        }
    });
}

for (const response of [
    new Response("Ekspor Anda masih diproses.", { status: 429 }),
    Response.json({ message: "Ekspor Anda masih diproses." }, { status: 429 }),
]) {
    test(`unlocks and displays a 429 ${response.headers.get("Content-Type")} message`, async () => {
        mock.method(globalThis, "fetch", async () => response);
        const state = laporanExport();

        await state.exportReport("pengeluaran", () => "/laporan/download");

        assert.equal(state.isExporting, false);
        assert.equal(state.exportError, "Ekspor Anda masih diproses.");
        assert.equal(state.activeExport, null);
    });
}

for (const status of [401, 419]) {
    test(`unlocks with an Indonesian session message on HTTP ${status}`, async () => {
        mock.method(globalThis, "fetch", async () => new Response("", { status }));
        const state = laporanExport();

        await state.exportReport("pengeluaran", () => "/laporan/download");

        assert.equal(state.isExporting, false);
        assert.match(state.exportError, /Sesi Anda telah berakhir/);
        assert.equal(state.activeExport, null);
    });
}

for (const [name, request] of [
    ["network error", async () => { throw new TypeError("offline"); }],
    ["server error", async () => new Response("private exception", { status: 500 })],
    ["HTML instead of a download", async () => new Response("login", { headers: { "Content-Type": "text/html" } })],
    ["interrupted file", async () => ({ ok: true, headers: new Headers({ "Content-Type": "application/pdf" }), blob: async () => { throw new Error("interrupted"); } })],
]) {
    test(`unlocks after ${name} and allows another attempt`, async () => {
        const fetchMock = mock.method(globalThis, "fetch", request);
        const state = laporanExport();

        await state.exportReport("pengeluaran", () => "/laporan/download");

        assert.equal(state.isExporting, false);
        assert.equal(state.exportError, "Laporan gagal diunduh. Silakan coba lagi.");
        assert.equal(state.activeExport, null);
        await state.exportReport("penjualan", () => "/laporan/download");
        assert.equal(fetchMock.mock.callCount(), 2);
    });
}

test("unlocks without downloading when form validation does not return a URL", async () => {
    const fetchMock = mock.method(globalThis, "fetch", async () => {});
    const state = laporanExport();

    await state.exportReport("pengeluaran", () => null);

    assert.equal(state.isExporting, false);
    assert.equal(state.exportError, "");
    assert.equal(fetchMock.mock.callCount(), 0);
    assert.equal(state.activeExport, null);
});

test("unlocks when the Livewire action fails and clears the old error on retry", async () => {
    const state = laporanExport();

    await state.exportReport("pengeluaran", () => { throw new Error("Livewire failed"); });

    assert.equal(state.isExporting, false);
    assert.equal(state.exportError, "Laporan gagal diunduh. Silakan coba lagi.");
    assert.equal(state.activeExport, null);
    await state.exportReport("pengeluaran", () => null);
    assert.equal(state.exportError, "");
});
