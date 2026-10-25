import { createSecret } from '../api/secrets';
import { CryptoError } from '../crypto/errors';
import {
    DELETE_TOKEN_BYTES,
    encryptV1,
    hashDeleteToken,
    MAX_PLAINTEXT_BYTES,
} from '../crypto/protocol-v1';
import { randomBytes } from '../crypto/random';
import { buildDeleteFragment, buildShareFragment } from '../security/fragment';
import {
    apiErrorMessage,
    bindCopyButton,
    byId,
    hideMessage,
    prefersReducedMotion,
    setButtonLabel,
    setSealState,
    showMessage,
} from './dom';
import { renderQrCode } from './qr';

/** Keep the sealing animation visible even when encryption is instant. */
const MIN_SEALING_MS = 900;

const SUBMIT_LABEL = 'Verschlüsseln & Link erstellen';

function formatBytes(bytes: number): string {
    return bytes < 1024 ? `${bytes} B` : `${(bytes / 1024).toFixed(1)} KiB`;
}

function relativeExpiry(expiresAt: Date): string {
    const minutes = Math.round((expiresAt.getTime() - Date.now()) / 60000);
    const format = new Intl.RelativeTimeFormat('de', { numeric: 'auto' });

    if (minutes < 60) {
        return format.format(minutes, 'minute');
    }

    if (minutes < 1440) {
        return format.format(Math.round(minutes / 60), 'hour');
    }

    return format.format(Math.round(minutes / 1440), 'day');
}

function delay(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

export function init(): void {
    const form = byId<HTMLFormElement>('create-form');
    const seal = byId<SVGElement>('create-seal');
    const text = byId<HTMLTextAreaElement>('secret-text');
    const size = byId<HTMLElement>('secret-size');
    const password = byId<HTMLInputElement>('secret-password');
    const passwordToggle = byId<HTMLButtonElement>('password-toggle');
    const passwordField = byId<HTMLElement>('password-field');
    const ttl = byId<HTMLSelectElement>('secret-ttl');
    const maxViews = byId<HTMLSelectElement>('secret-max-views');
    const button = byId<HTMLButtonElement>('create-button');
    const error = byId<HTMLElement>('create-error');
    const shareLink = byId<HTMLInputElement>('share-link');
    const manageLink = byId<HTMLInputElement>('manage-link');

    bindCopyButton(
        byId('copy-share-link'),
        byId('copy-share-link-status'),
        () => shareLink.value,
    );
    bindCopyButton(
        byId('copy-manage-link'),
        byId('copy-manage-link-status'),
        () => manageLink.value,
    );

    text.addEventListener('input', () => {
        const bytes = new TextEncoder().encode(text.value).length;
        size.textContent = `${formatBytes(bytes)} von 64 KiB`;
        size.classList.toggle('is-over', bytes > MAX_PLAINTEXT_BYTES);
    });

    passwordToggle.addEventListener('click', () => {
        const expand = passwordField.hidden;

        passwordField.hidden = !expand;
        passwordToggle.setAttribute('aria-expanded', String(expand));

        if (expand) {
            password.focus();
        } else {
            password.value = '';
        }
    });

    button.disabled = false;

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        hideMessage(error);

        const plaintext = text.value;
        const passwordValue = password.value;
        const expiresIn = Number(ttl.value);
        const views = Number(maxViews.value);

        if (plaintext.length === 0) {
            showMessage(error, 'Bitte gib ein Secret ein.');
            text.focus();
            return;
        }

        if (new TextEncoder().encode(plaintext).length > MAX_PLAINTEXT_BYTES) {
            showMessage(error, 'Das Secret ist zu groß (maximal 64 KiB).');
            return;
        }

        if (!Number.isInteger(views) || views < 1) {
            showMessage(error, 'Bitte wähle die maximale Anzahl an Abrufen.');
            return;
        }

        button.disabled = true;
        setButtonLabel(button, 'Wird versiegelt …');
        form.classList.add('is-sealing');
        setSealState(seal, 'sealing');

        void (async () => {
            const passwordProtected = passwordValue !== '';
            const deleteToken = randomBytes(DELETE_TOKEN_BYTES);
            const minimumDuration = delay(
                prefersReducedMotion() ? 0 : MIN_SEALING_MS,
            );

            try {
                const { envelope, linkKey } = await encryptV1(
                    plaintext,
                    passwordProtected ? passwordValue : undefined,
                );

                const created = await createSecret({
                    crypto_version: 1,
                    envelope,
                    password_protected: passwordProtected,
                    expires_in: expiresIn,
                    max_views: views,
                    delete_token_hash: await hashDeleteToken(deleteToken),
                });

                shareLink.value = `${location.origin}/s/${created.public_id}${buildShareFragment(linkKey, passwordProtected)}`;
                manageLink.value = `${location.origin}/manage/${created.public_id}${buildDeleteFragment(deleteToken)}`;
                linkKey.fill(0);

                await minimumDuration;

                // Drop plaintext and password from the form once sealed.
                text.value = '';
                password.value = '';

                const expiresAt = new Date(created.expires_at);
                const expires = byId('result-expires');
                const relative = document.createElement('span');
                relative.className = 'meta-sub';
                relative.textContent = relativeExpiry(expiresAt);
                expires.replaceChildren(
                    expiresAt.toLocaleString('de-DE', {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    }),
                    relative,
                );

                byId('result-max-views').textContent =
                    created.max_views === 1
                        ? '1× (Einmal-Link)'
                        : `${created.max_views}×`;
                const passwordHint = document.createElement('span');
                passwordHint.className = 'meta-sub';
                passwordHint.textContent = passwordProtected
                    ? 'Link und Passwort nötig'
                    : 'Link genügt zum Öffnen';
                byId('result-password').replaceChildren(
                    passwordProtected ? 'Ja' : 'Nein',
                    passwordHint,
                );

                renderQrCode(byId('qr-code'), shareLink.value);

                form.hidden = true;
                byId('create-result').hidden = false;
                shareLink.focus();
                shareLink.select();
            } catch (caught) {
                await minimumDuration;
                showMessage(
                    error,
                    apiErrorMessage(caught) ??
                        (caught instanceof CryptoError
                            ? 'Die Verschlüsselung ist fehlgeschlagen.'
                            : 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.'),
                );
                form.classList.remove('is-sealing');
                setSealState(seal, 'open');
                button.disabled = false;
                setButtonLabel(button, SUBMIT_LABEL);
            } finally {
                deleteToken.fill(0);
            }
        })();
    });
}
