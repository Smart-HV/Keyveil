import { deleteSecret } from '../api/secrets';
import { hashDeleteToken } from '../crypto/protocol-v1';
import { parseDeleteFragment } from '../security/fragment';
import {
    apiErrorMessage,
    byId,
    removeFragmentFromAddressBar,
    showMessage,
} from './dom';

/**
 * Management page: deletes a secret with the delete token from the fragment.
 * It never retrieves secret content.
 */
export function init(): void {
    const root = byId<HTMLElement>('manage-secret');
    const button = byId<HTMLButtonElement>('delete-button');
    const dialog = byId<HTMLDialogElement>('delete-dialog');
    const status = byId<HTMLElement>('manage-status');
    const publicId = root.dataset.publicId ?? '';

    let deleteToken: Uint8Array<ArrayBuffer>;

    try {
        deleteToken = parseDeleteFragment(location.hash);
    } catch {
        showMessage(status, 'Der Lösch-Link ist unvollständig oder ungültig.');
        button.hidden = true;
        return;
    }

    button.disabled = false;
    button.addEventListener('click', () => dialog.showModal());

    dialog.addEventListener('close', () => {
        if (dialog.returnValue !== 'confirm') {
            return;
        }

        button.disabled = true;

        void (async () => {
            try {
                await deleteSecret(
                    publicId,
                    await hashDeleteToken(deleteToken),
                );
                deleteToken.fill(0);
                showMessage(status, 'Das Secret wurde endgültig gelöscht.');
                button.hidden = true;
                removeFragmentFromAddressBar();
            } catch (caught) {
                showMessage(
                    status,
                    apiErrorMessage(caught) ??
                        'Ein Fehler ist aufgetreten. Bitte versuche es erneut.',
                );
                button.disabled = false;
            }
        })();
    });
}
