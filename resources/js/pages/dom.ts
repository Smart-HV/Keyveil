import { ApiError } from '../api/secrets';

export function byId<T extends Element = HTMLElement>(id: string): T {
    const element = document.getElementById(id);

    if (element === null) {
        throw new Error(`Missing element #${id}`);
    }

    return element as unknown as T;
}

/**
 * Show a message as plain text. Never use innerHTML for any content here.
 */
export function showMessage(element: HTMLElement, message: string): void {
    element.textContent = message;
    element.hidden = false;
}

export function hideMessage(element: HTMLElement): void {
    element.textContent = '';
    element.hidden = true;
}

/**
 * Copy only on explicit user action; no automatic clipboard access and no
 * promise of clipboard clearing.
 */
export function bindCopyButton(
    button: HTMLButtonElement,
    status: HTMLElement,
    text: () => string,
): void {
    let reset: ReturnType<typeof setTimeout> | undefined;

    button.addEventListener('click', () => {
        navigator.clipboard.writeText(text()).then(
            () => {
                status.textContent = 'In Zwischenablage kopiert.';
                button.classList.add('is-copied');
                setButtonLabel(button, 'Kopiert');
                clearTimeout(reset);
                reset = setTimeout(() => {
                    button.classList.remove('is-copied');
                    setButtonLabel(button, 'Kopieren');
                }, 2000);
            },
            () => {
                status.textContent =
                    'Kopieren nicht möglich. Bitte manuell markieren und kopieren.';
            },
        );
    });
}

/**
 * Change a button's visible label while keeping its icon.
 */
export function setButtonLabel(button: HTMLElement, label: string): void {
    const span = button.querySelector('span');

    if (span !== null) {
        span.textContent = label;
    } else {
        button.textContent = label;
    }
}

export type SealState = 'open' | 'sealed' | 'sealing' | 'unsealed';

/**
 * Switch the seal illustration state; the animation lives in app.css.
 */
export function setSealState(seal: Element, state: SealState): void {
    seal.classList.remove('is-open', 'is-sealed', 'is-sealing', 'is-unsealed');
    seal.classList.add(`is-${state}`);
}

export function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Remove the fragment (key material) from the visible address bar. Browser
 * history may still contain the previous URL.
 */
export function removeFragmentFromAddressBar(): void {
    history.replaceState(null, '', location.pathname);
}

/**
 * Pasting another link into the same tab only changes the fragment, which
 * does not reload the page. Reload so the new link is parsed from scratch.
 */
export function reloadOnFragmentChange(): void {
    window.addEventListener('hashchange', () => location.reload());
}

export function apiErrorMessage(error: unknown): string | null {
    if (!(error instanceof ApiError)) {
        return null;
    }

    switch (error.status) {
        case 404:
            return 'Dieses Secret ist nicht verfügbar. Es kann abgelaufen, bereits abgerufen oder gelöscht worden sein.';
        case 413:
            return 'Das Secret ist zu groß.';
        case 419:
        case 403:
            return 'Die Sitzung ist abgelaufen. Bitte lade die Seite neu.';
        case 422:
            return 'Die Eingaben sind ungültig.';
        case 429:
            return 'Zu viele Anfragen. Bitte warte einen Moment und versuche es erneut.';
        default:
            return 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.';
    }
}
