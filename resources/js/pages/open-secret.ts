import { ApiError, claimSecret } from '../api/secrets';
import { decryptV1, parseEnvelopeV1 } from '../crypto/protocol-v1';
import { parseShareFragment, type ShareFragment } from '../security/fragment';
import {
    apiErrorMessage,
    bindCopyButton,
    byId,
    hideMessage,
    removeFragmentFromAddressBar,
    showMessage,
} from './dom';

const DECRYPT_FAILED =
    'Das Secret konnte nicht entschlüsselt werden. Prüfe das Passwort und den Link.';

/**
 * Loading this page never calls the API. Only the explicit click on
 * "Secret anzeigen" claims (and consumes) a view.
 */
export function init(): void {
    const root = byId<HTMLElement>('open-secret');
    const revealStep = byId<HTMLElement>('reveal-step');
    const passwordStep = byId<HTMLElement>('password-step');
    const password = byId<HTMLInputElement>('open-password');
    const button = byId<HTMLButtonElement>('reveal-button');
    const error = byId<HTMLElement>('open-error');
    const output = byId<HTMLElement>('secret-output');
    const publicId = root.dataset.publicId ?? '';

    let fragment: ShareFragment;

    try {
        fragment = parseShareFragment(location.hash);
    } catch {
        showMessage(
            error,
            'Der Link ist unvollständig oder ungültig. Bitte prüfe, ob er vollständig kopiert wurde.',
        );
        revealStep.hidden = true;
        return;
    }

    let envelope: ReturnType<typeof parseEnvelopeV1> | null = null;

    passwordStep.hidden = !fragment.passwordRequired;
    button.disabled = false;

    bindCopyButton(
        byId('copy-secret'),
        byId('copy-secret-status'),
        () => output.textContent ?? '',
    );

    button.addEventListener('click', () => {
        hideMessage(error);

        // Require the password before claiming so an empty prompt can never burn a view.
        if (!passwordStep.hidden && password.value === '') {
            showMessage(error, 'Bitte gib zuerst das Passwort ein.');
            password.focus();
            return;
        }

        button.disabled = true;
        button.textContent = envelope === null ? 'Rufe ab…' : 'Entschlüssele…';

        void (async () => {
            try {
                if (envelope === null) {
                    const claimed = await claimSecret(publicId);

                    if (claimed.crypto_version !== 1) {
                        throw new Error('Unsupported protocol version.');
                    }

                    envelope = parseEnvelopeV1(claimed.envelope);
                }

                // Envelope says "password" although the link had no ".p" marker.
                if (envelope.password.enabled && password.value === '') {
                    passwordStep.hidden = false;
                    showMessage(
                        error,
                        'Dieses Secret ist zusätzlich mit einem Passwort geschützt. Der Abruf wurde bereits verbraucht – bitte gib jetzt das Passwort ein.',
                    );
                    button.disabled = false;
                    button.textContent = 'Entschlüsseln';
                    password.focus();
                    return;
                }

                const plaintext = await decryptV1(
                    envelope,
                    fragment.linkKey,
                    envelope.password.enabled ? password.value : undefined,
                );

                // Decrypted content is untrusted: text only, never HTML.
                output.textContent = plaintext;
                password.value = '';
                fragment.linkKey.fill(0);
                envelope = null;
                revealStep.hidden = true;
                byId('secret-result').hidden = false;
                removeFragmentFromAddressBar();
            } catch (caught) {
                if (caught instanceof ApiError) {
                    showMessage(
                        error,
                        apiErrorMessage(caught) ?? DECRYPT_FAILED,
                    );

                    if (caught.status === 404) {
                        revealStep.hidden = true;
                        return;
                    }
                } else {
                    // The claimed view is consumed; retrying only re-runs local decryption.
                    showMessage(error, DECRYPT_FAILED);
                }

                button.disabled = false;
                button.textContent =
                    envelope === null
                        ? 'Secret anzeigen'
                        : 'Erneut entschlüsseln';
            }
        })();
    });
}
