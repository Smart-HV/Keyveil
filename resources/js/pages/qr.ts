import { encode } from 'uqr';

const SVG_NS = 'http://www.w3.org/2000/svg';

/**
 * Render a QR code fully client-side as an SVG built via DOM APIs. The link
 * (including the fragment) is never sent to any third party.
 */
export function renderQrCode(container: HTMLElement, text: string): void {
    const qr = encode(text, { ecc: 'M', border: 2 });

    let path = '';

    qr.data.forEach((row, y) => {
        row.forEach((dark, x) => {
            if (dark) {
                path += `M${x} ${y}h1v1h-1z`;
            }
        });
    });

    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${qr.size} ${qr.size}`);
    svg.setAttribute('shape-rendering', 'crispEdges');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'QR-Code des Share-Links');

    const background = document.createElementNS(SVG_NS, 'rect');
    background.setAttribute('width', String(qr.size));
    background.setAttribute('height', String(qr.size));
    background.setAttribute('fill', '#ffffff');

    const modules = document.createElementNS(SVG_NS, 'path');
    modules.setAttribute('d', path);
    modules.setAttribute('fill', '#000000');

    svg.append(background, modules);
    container.replaceChildren(svg);
}
