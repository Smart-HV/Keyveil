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
    showMessage,
} from './dom';
import { renderQrCode } from './qr';

export function init(): void {
    const form = byId<HTMLFormElement>('create-form');
    const text = byId<HTMLTextAreaElement>('secret-text');
    const password = byId<HTMLInputElement>('secret-password');
    const ttl = byId<HTMLSelectElement>('secret-ttl');
    const maxViews = byId<HTMLInputElement>('secret-max-views');
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
            return;
        }

        if (new TextEncoder().encode(plaintext).length > MAX_PLAINTEXT_BYTES) {
            showMessage(error, 'Das Secret ist zu groß (maximal 64 KiB).');
            return;
        }

        if (
            !Number.isInteger(views) ||
            views < Number(maxViews.min) ||
            views > Number(maxViews.max)
        ) {
            showMessage(
                error,
                `Maximale Abrufe muss zwischen ${maxViews.min} und ${maxViews.max} liegen.`,
            );
            return;
        }

        button.disabled = true;
        button.textContent = 'Verschlüssele…';

        void (async () => {
            const passwordProtected = passwordValue !== '';
            const deleteToken = randomBytes(DELETE_TOKEN_BYTES);

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

                // Drop plaintext and password from the form as soon as possible.
                text.value = '';
                password.value = '';

                renderQrCode(byId('qr-code'), shareLink.value);
                byId('result-expires').textContent = new Date(
                    created.expires_at,
                ).toLocaleString('de-DE');
                byId('result-max-views').textContent = String(
                    created.max_views,
                );
                byId('result-password').textContent = passwordProtected
                    ? 'Ja – Empfänger benötigt Link und Passwort'
                    : 'Nein';

                form.hidden = true;
                byId('create-result').hidden = false;
                shareLink.focus();
                shareLink.select();
            } catch (caught) {
                showMessage(
                    error,
                    apiErrorMessage(caught) ??
                        (caught instanceof CryptoError
                            ? 'Die Verschlüsselung ist fehlgeschlagen.'
                            : 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.'),
                );
                button.disabled = false;
                button.textContent = 'Verschlüsseln & Link erstellen';
            } finally {
                deleteToken.fill(0);
            }
        })();
    });
}
