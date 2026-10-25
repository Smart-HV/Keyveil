import { ApiError } from '../api/secrets';

export function byId<T extends HTMLElement>(id: string): T {
    const element = document.getElementById(id);

    if (element === null) {
        throw new Error(`Missing element #${id}`);
    }

    return element as T;
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
    button.addEventListener('click', () => {
        navigator.clipboard.writeText(text()).then(
            () => {
                status.textContent = 'In Zwischenablage kopiert.';
            },
            () => {
                status.textContent =
                    'Kopieren nicht möglich. Bitte manuell markieren und kopieren.';
            },
        );
    });
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
