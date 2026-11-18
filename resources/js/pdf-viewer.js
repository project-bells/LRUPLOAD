import * as pdfjsLib from 'pdfjs-dist';

function resolveWorkerUrl() {
    const meta = document.querySelector('meta[name="pdf-worker-url"]');
    return meta && meta.content ? meta.content : '/js/pdf.worker.min.js';
}

pdfjsLib.GlobalWorkerOptions.workerSrc = resolveWorkerUrl();

/**
 * Alpine.js component for responsive, authenticated PDF.js preview viewer.
 * IMPORTANT: pdfDoc and renderTask are stored in plain closure variables (NOT on Alpine `this`)
 * because PDF.js v4/v5 uses ES private fields which break if wrapped in an Alpine reactive Proxy.
 */
export function aikcholPdfViewer(initialUrl = '') {
    let pdfDoc = null;
    let renderTask = null;

    return {
        url: initialUrl,
        pageNum: 1,
        pageCount: 0,
        scale: 1.15,
        rotation: 0,
        fitMode: 'width',
        loading: false,
        rendering: false,
        error: null,
        isFullscreen: false,

        async init() {
            pdfjsLib.GlobalWorkerOptions.workerSrc = resolveWorkerUrl();

            if (this.url) {
                await this.loadDocument(this.url);
            }

            document.addEventListener('fullscreenchange', () => {
                this.isFullscreen = Boolean(document.fullscreenElement);
            });
        },

        async loadDocument(url) {
            if (!url) return;
            this.url = url;
            this.loading = true;
            this.error = null;
            this.pageNum = 1;
            this.rotation = 0;

            try {
                if (renderTask) {
                    try {
                        renderTask.cancel();
                    } catch (_) {
                        // ignore cancellation
                    }
                    renderTask = null;
                }

                if (pdfDoc) {
                    await pdfDoc.destroy();
                    pdfDoc = null;
                }

                // Fetch authenticated PDF binary directly to avoid Range-request issues on PHP/Apache
                const response = await fetch(this.url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const buffer = await response.arrayBuffer();
                const loadingTask = pdfjsLib.getDocument({
                    data: new Uint8Array(buffer),
                });

                pdfDoc = await loadingTask.promise;
                this.pageCount = pdfDoc.numPages;
                this.loading = false;

                await this.$nextTick();
                await this.renderPage(this.pageNum);
            } catch (err) {
                console.error('PDF.js preview load error:', err);
                this.loading = false;
                this.error = 'ไม่สามารถแสดงตัวอย่างไฟล์ PDF ได้ในขณะนี้ กรุณาลองใหม่หรือตรวจสอบสิทธิ์การเข้าถึง';
            }
        },

        async renderPage(num) {
            if (!pdfDoc) return;

            if (renderTask) {
                try {
                    renderTask.cancel();
                } catch (_) {
                    // ignore
                }
                renderTask = null;
            }

            this.rendering = true;

            try {
                const page = await pdfDoc.getPage(num);
                const canvas = this.$refs.pdfCanvas;
                const container = this.$refs.canvasContainer;
                if (!canvas || !container) {
                    this.rendering = false;
                    return;
                }

                let effectiveScale = this.scale;
                if (this.fitMode === 'width') {
                    const baseViewport = page.getViewport({ scale: 1, rotation: this.rotation });
                    const availableWidth = Math.max(280, (container.clientWidth || 720) - 32);
                    effectiveScale = Math.min(2.5, Math.max(0.5, availableWidth / baseViewport.width));
                    this.scale = Number(effectiveScale.toFixed(2));
                }

                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                const viewport = page.getViewport({ scale: effectiveScale, rotation: this.rotation });

                canvas.width = Math.floor(viewport.width * dpr);
                canvas.height = Math.floor(viewport.height * dpr);
                canvas.style.width = `${Math.floor(viewport.width)}px`;
                canvas.style.height = `${Math.floor(viewport.height)}px`;

                const ctx = canvas.getContext('2d');
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                renderTask = page.render({
                    canvasContext: ctx,
                    viewport: viewport,
                });

                await renderTask.promise;
                renderTask = null;
            } catch (err) {
                if (err && err.name === 'RenderingCancelledException') {
                    return;
                }
                console.error('PDF.js render error:', err);
                this.error = 'เกิดข้อผิดพลาดในการเรนเดอร์หน้าเอกสาร';
            } finally {
                this.rendering = false;
            }
        },

        async prevPage() {
            if (this.pageNum <= 1) return;
            this.pageNum--;
            await this.renderPage(this.pageNum);
        },

        async nextPage() {
            if (this.pageNum >= this.pageCount) return;
            this.pageNum++;
            await this.renderPage(this.pageNum);
        },

        async goToPage(event) {
            const target = parseInt(event.target.value, 10);
            if (!Number.isNaN(target) && target >= 1 && target <= this.pageCount) {
                this.pageNum = target;
                await this.renderPage(this.pageNum);
            }
        },

        async zoomIn() {
            this.fitMode = 'custom';
            this.scale = Math.min(3.0, Number((this.scale + 0.2).toFixed(2)));
            await this.renderPage(this.pageNum);
        },

        async zoomOut() {
            this.fitMode = 'custom';
            this.scale = Math.max(0.4, Number((this.scale - 0.2).toFixed(2)));
            await this.renderPage(this.pageNum);
        },

        async fitWidth() {
            this.fitMode = 'width';
            await this.renderPage(this.pageNum);
        },

        async rotateClockwise() {
            this.rotation = (this.rotation + 90) % 360;
            await this.renderPage(this.pageNum);
        },

        async toggleFullscreen() {
            const root = this.$refs.viewerRoot;
            if (!root) return;

            if (!document.fullscreenElement) {
                if (root.requestFullscreen) {
                    await root.requestFullscreen();
                }
            } else if (document.exitFullscreen) {
                await document.exitFullscreen();
            }

            setTimeout(() => this.renderPage(this.pageNum), 150);
        },
    };
}
