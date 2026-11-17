function safeFilename(value, fallback = 'simpeg-export') {
    const normalized = String(value || fallback)
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase();

    return normalized || fallback;
}

function stripInvalidXmlCharacters(value) {
    return String(value ?? '')
        .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\uFFFE\uFFFF]/g, '')
        .replace(/\u00A0/g, ' ');
}

function escapeXml(value) {
    return stripInvalidXmlCharacters(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

function sanitizeSerializedMarkup(value) {
    return stripInvalidXmlCharacters(value).replace(
        /&(?!amp;|lt;|gt;|quot;|apos;|#(?:x[0-9a-f]+|\d+);)/gi,
        '&amp;',
    );
}

function asXmlCdata(value) {
    return stripInvalidXmlCharacters(value).replace(/\]\]>/g, ']]]]><![CDATA[>');
}

function buildSvgDocument({ title, width, height, styles, markup }) {
    return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
  <title>${escapeXml(title || 'SIMPEG')}</title>
  <foreignObject width="100%" height="100%">
    <div xmlns="http://www.w3.org/1999/xhtml" class="simpeg-export-document">
      <style type="text/css"><![CDATA[${asXmlCdata(styles)}]]></style>
      ${sanitizeSerializedMarkup(markup)}
    </div>
  </foreignObject>
</svg>`;
}

function isValidSvgDocument(svg) {
    if (typeof DOMParser === 'undefined') {
        return true;
    }

    const document = new DOMParser().parseFromString(svg, 'image/svg+xml');

    return !document.querySelector('parsererror');
}

function collectStyles() {
    let css = '';

    for (const sheet of Array.from(document.styleSheets)) {
        try {
            css += Array.from(sheet.cssRules || []).map((rule) => rule.cssText).join('\n');
        } catch {
            // Cross-origin stylesheets cannot be inspected. The app stylesheets
            // are same-origin, so an inaccessible third-party sheet is safe to skip.
        }
    }

    return css;
}

function getExportRoot() {
    return document.querySelector('[data-simpeg-export-root]') || document.querySelector('main');
}

function cloneExportRoot() {
    const root = getExportRoot();

    if (!root) {
        return null;
    }

    const clone = root.cloneNode(true);
    clone.removeAttribute('data-simpeg-export-root');

    clone.querySelectorAll('.simpeg-export-dropdown, form.simpeg-filter-toolbar, .simpeg-filter-toolbar, [data-export-hide]').forEach((element) => element.remove());
    clone.querySelectorAll('button, input, select, textarea').forEach((element) => element.remove());

    return { clone, root };
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 30000);
}

export function exportPageAsSvg() {
    const exportData = cloneExportRoot();

    if (!exportData) {
        return;
    }

    const { clone, root } = exportData;
    const bounds = root.getBoundingClientRect();
    const width = Math.max(Math.ceil(bounds.width), 640);
    const height = Math.max(Math.ceil(root.scrollHeight), Math.ceil(bounds.height), 480);
    const serializedMarkup = new XMLSerializer().serializeToString(clone);
    const svg = buildSvgDocument({
        title: document.title,
        width,
        height,
        styles: collectStyles(),
        markup: serializedMarkup,
    });

    if (!isValidSvgDocument(svg)) {
        console.error('SIMPEG SVG export menghasilkan XML yang tidak valid.');
        window.alert('Export SVG gagal karena isi halaman mengandung karakter XML yang tidak valid.');
        return;
    }

    downloadBlob(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }), `${safeFilename(document.title)}.svg`);
}

export function printPageAsPdf() {
    const exportData = cloneExportRoot();

    if (!exportData) {
        window.print();
        return;
    }

    const { clone } = exportData;
    const printWindow = window.open('', '_blank');

    if (!printWindow) {
        window.print();
        return;
    }

    printWindow.document.open();
    printWindow.document.write(`<!doctype html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>${escapeXml(document.title || 'SIMPEG')}</title>
        <style>
            ${collectStyles()}
            @page { size: auto; margin: 12mm; }
            html, body { background: #FFFFFF !important; color: #0B1F4B !important; }
            body { margin: 0; min-width: 0 !important; }
            .simpeg-export-document { width: 100%; }
            .simpeg-export-document * { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            @media print {
                .simpeg-export-document { break-inside: avoid; }
                table { break-inside: auto; }
                tr { break-inside: avoid; }
            }
        </style>
    </head>
    <body><div class="simpeg-export-document">${clone.outerHTML}</div></body>
</html>`);
    printWindow.document.close();
    printWindow.setTimeout(async () => {
        try {
            if (printWindow.document.fonts?.ready) {
                await printWindow.document.fonts.ready;
            }

            await Promise.all(Array.from(printWindow.document.images || []).map((image) => {
                if (image.complete) return Promise.resolve();
                return new Promise((resolve) => {
                    image.addEventListener('load', resolve, { once: true });
                    image.addEventListener('error', resolve, { once: true });
                });
            }));
        } finally {
            printWindow.focus();
            printWindow.print();
        }
    }, 150);
}
