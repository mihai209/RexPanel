if (typeof globalThis.CustomEvent === 'undefined') {
    class NodeCustomEvent extends Event {
        constructor(type, params = {}) {
            super(type, params);
            this.detail = params.detail ?? null;
        }
    }

    globalThis.CustomEvent = NodeCustomEvent;
}

const viteBinUrl = new URL('../node_modules/vite/bin/vite.js', import.meta.url);

await import(viteBinUrl.href);
