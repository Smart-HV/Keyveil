import { expect, test } from '@playwright/test';
import {
    createSecret,
    recordRequests,
    requestHeaders,
    revealSecret,
} from './helpers';

/*
 * The URL fragment must never appear in any request URL, body or
 * header — neither for the share link nor for the management link.
 */
test('the share key and delete token never leave the browser', async ({
    page,
    browser,
}) => {
    const sender = recordRequests(page);
    const { shareLink, manageLink } = await createSecret(page, {
        text: 'fragment leak check',
        maxViews: 2,
    });

    const shareKey = new URL(shareLink).hash.slice(1);
    const deleteToken = new URL(manageLink).hash.slice(3);

    const recipient = await (await browser.newContext()).newPage();
    const received = recordRequests(recipient);
    await recipient.goto(shareLink);
    await revealSecret(recipient);
    await expect(recipient.locator('#secret-output')).toHaveText(
        'fragment leak check',
    );

    const manager = await (await browser.newContext()).newPage();
    const managed = recordRequests(manager);
    await manager.goto(manageLink);
    await manager
        .getByRole('button', { name: 'Secret endgültig löschen' })
        .click();
    await manager.getByRole('button', { name: 'Löschen', exact: true }).click();
    await expect(
        manager.getByRole('status').filter({ hasText: 'gelöscht' }),
    ).toBeVisible();

    const all = [...sender, ...received, ...managed];
    expect(all.length).toBeGreaterThan(0);

    for (const request of all) {
        const headers = await requestHeaders(request);
        const body = request.postData() ?? '';

        for (const secret of [shareKey, shareKey.slice(3), deleteToken]) {
            expect(request.url()).not.toContain(secret);
            expect(body).not.toContain(secret);
            expect(headers).not.toContain(secret);
        }
    }
});

test('a synthetic fragment is never transmitted', async ({ page }) => {
    const requests = recordRequests(page);

    await page.goto('/s/AAAAAAAAAAAAAAAAAAAAAA#SUPER_SECRET_TEST_FRAGMENT');
    await page.waitForLoadState('networkidle');

    for (const request of requests) {
        expect(request.url()).not.toContain('SUPER_SECRET_TEST_FRAGMENT');
        expect(request.postData() ?? '').not.toContain(
            'SUPER_SECRET_TEST_FRAGMENT',
        );
        expect(await requestHeaders(request)).not.toContain(
            'SUPER_SECRET_TEST_FRAGMENT',
        );
    }
});
