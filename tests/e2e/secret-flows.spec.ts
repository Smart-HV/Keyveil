import { expect, test } from '@playwright/test';
import {
    createSecret,
    openFresh,
    recordRequests,
    requestHeaders,
    revealSecret,
    trackCspViolations,
} from './helpers';

const UNAVAILABLE = 'Dieses Secret ist nicht verfügbar.';

test('create, open and decrypt a one-time secret', async ({
    page,
    browser,
}) => {
    const plaintext = 'Pässwörd 🔐 <b>not html</b>\nZeile 2';
    const { shareLink } = await createSecret(page, { text: plaintext });

    expect(shareLink).toMatch(/\/s\/[A-Za-z0-9_-]{22}#v1\.[A-Za-z0-9_-]{43}$/);
    await expect(page.locator('#qr-code svg path')).toHaveAttribute('d', /^M/);
    await expect(page.locator('#result-max-views')).toHaveText(
        '1× (Einmal-Link)',
    );

    const recipient = await (await browser.newContext()).newPage();
    await recipient.goto(shareLink);
    await revealSecret(recipient);

    const output = recipient.locator('#secret-output');
    await expect(output).toHaveText(plaintext);
    await expect(output.locator('b')).toHaveCount(0);
    expect(new URL(recipient.url()).hash).toBe('');

    const second = await (await browser.newContext()).newPage();
    await second.goto(shareLink);
    await revealSecret(second);
    await expect(second.getByRole('alert')).toContainText(UNAVAILABLE);
});

test('reloading before the claim consumes nothing', async ({ page }) => {
    const { shareLink } = await createSecret(page, { text: 'reload-safe' });

    await page.goto(shareLink);
    await page.reload();
    await openFresh(page, shareLink);
    await revealSecret(page);
    await expect(page.locator('#secret-output')).toHaveText('reload-safe');

    // After the claim the view is gone; reloading cannot show it again.
    await openFresh(page, shareLink);
    await revealSecret(page);
    await expect(page.getByRole('alert')).toContainText(UNAVAILABLE);
});

test('a multi-view secret can be opened exactly max views times', async ({
    page,
}) => {
    const { shareLink } = await createSecret(page, {
        text: 'multi',
        maxViews: 2,
    });

    for (let view = 0; view < 2; view++) {
        await openFresh(page, shareLink);
        await revealSecret(page);
        await expect(page.locator('#secret-output')).toHaveText('multi');
    }

    await openFresh(page, shareLink);
    await revealSecret(page);
    await expect(page.getByRole('alert')).toContainText(UNAVAILABLE);
});

test('password protected secrets require link and password', async ({
    page,
}) => {
    const { shareLink } = await createSecret(page, {
        text: 'protected',
        password: 'korrekt-pferd',
    });

    expect(shareLink).toMatch(/\.p$/);

    await page.goto(shareLink);
    await expect(page.getByLabel('Passwort', { exact: true })).toBeVisible();

    // An empty password never triggers the claim.
    await page.getByRole('button', { name: 'Secret anzeigen' }).click();
    await expect(page.getByRole('alert')).toContainText(
        'Bitte gib zuerst das Passwort ein.',
    );

    // A wrong password consumes the view but decryption can be retried locally.
    await revealSecret(page, 'falsch');
    await expect(page.getByRole('alert')).toContainText(
        'Das Secret konnte nicht entschlüsselt werden.',
    );

    await page.getByLabel('Passwort', { exact: true }).fill('korrekt-pferd');
    await page.getByRole('button', { name: 'Erneut entschlüsseln' }).click();
    await expect(page.locator('#secret-output')).toHaveText('protected');
});

test('the password never reaches the server', async ({ page }) => {
    const requests = recordRequests(page);
    const password = 'NeverSendThisPassword-42';

    const { shareLink } = await createSecret(page, { text: 'pw', password });

    await page.goto(shareLink);
    await revealSecret(page, password);
    await expect(page.locator('#secret-output')).toHaveText('pw');

    for (const request of requests) {
        expect(request.url()).not.toContain(password);
        expect(request.postData() ?? '').not.toContain(password);
        expect(await requestHeaders(request)).not.toContain(password);
    }
});

test('the management link deletes the secret', async ({ page }) => {
    const { shareLink, manageLink } = await createSecret(page, {
        text: 'delete me',
        maxViews: 3,
    });

    expect(manageLink).toMatch(
        /\/manage\/[A-Za-z0-9_-]{22}#d=[A-Za-z0-9_-]{43}$/,
    );

    await page.goto(manageLink);
    await page
        .getByRole('button', { name: 'Secret endgültig löschen' })
        .click();
    await page.getByRole('button', { name: 'Löschen', exact: true }).click();
    await expect(
        page.getByRole('status').filter({ hasText: 'gelöscht' }),
    ).toBeVisible();
    expect(new URL(page.url()).hash).toBe('');

    await openFresh(page, shareLink);
    await revealSecret(page);
    await expect(page.getByRole('alert')).toContainText(UNAVAILABLE);
});

test('an incomplete link is rejected without contacting the API', async ({
    page,
}) => {
    const requests = recordRequests(page);

    await page.goto('/s/AAAAAAAAAAAAAAAAAAAAAA#v1.too-short');

    await expect(page.getByRole('alert')).toContainText(
        'unvollständig oder ungültig',
    );
    expect(
        requests.filter((request) => request.url().includes('/api/')),
    ).toHaveLength(0);
});

test('copying the share link uses the clipboard on click', async ({
    page,
    context,
    browserName,
}) => {
    test.skip(
        browserName !== 'chromium',
        'Clipboard permissions are Chromium only.',
    );

    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    const { shareLink } = await createSecret(page, { text: 'copy' });

    await page.locator('#copy-share-link').click();
    await expect(page.locator('#copy-share-link-status')).toHaveText(
        'In Zwischenablage kopiert.',
    );
    expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(
        shareLink,
    );
});

test('pages produce no CSP violations, no foreign requests and no service worker', async ({
    page,
    baseURL,
}) => {
    const violations = await trackCspViolations(page);
    const requests = recordRequests(page);

    const { shareLink, manageLink } = await createSecret(page, {
        text: 'csp',
        password: 'argon2-needs-wasm',
        maxViews: 2,
    });
    expect(await violations()).toEqual([]);

    await page.goto(shareLink);
    await revealSecret(page, 'argon2-needs-wasm');
    await expect(page.locator('#secret-output')).toHaveText('csp');
    expect(await violations()).toEqual([]);

    await page.goto(manageLink);
    expect(await violations()).toEqual([]);

    for (const request of requests) {
        expect(
            request.url().startsWith(`${baseURL}/`) ||
                request.url().startsWith('data:'),
        ).toBe(true);
    }

    expect(
        await page.evaluate(
            () =>
                navigator.serviceWorker
                    ?.getRegistrations()
                    .then((r) => r.length) ?? 0,
        ),
    ).toBe(0);
    expect(
        await page.evaluate(() => [localStorage.length, sessionStorage.length]),
    ).toEqual([0, 0]);
});
