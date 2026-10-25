import { expect, type Page, type Request } from '@playwright/test';

export interface CreatedLinks {
    shareLink: string;
    manageLink: string;
}

export async function createSecret(
    page: Page,
    options: { text: string; password?: string; maxViews?: number },
): Promise<CreatedLinks> {
    await page.goto('/');
    await page.getByLabel('Secret', { exact: true }).fill(options.text);

    if (options.maxViews !== undefined) {
        await page.getByLabel('Maximale Abrufe').fill(String(options.maxViews));
    }

    if (options.password !== undefined) {
        await page
            .getByLabel('Zusätzliches Passwort (optional)')
            .fill(options.password);
    }

    await page
        .getByRole('button', { name: 'Verschlüsseln & Link erstellen' })
        .click();

    await expect(
        page.getByRole('heading', { name: 'Link erstellt' }),
    ).toBeVisible();

    return {
        shareLink: await page.locator('#share-link').inputValue(),
        manageLink: await page.locator('#manage-link').inputValue(),
    };
}

export async function revealSecret(
    page: Page,
    password?: string,
): Promise<void> {
    if (password !== undefined) {
        await page.getByLabel('Passwort', { exact: true }).fill(password);
    }

    await page.getByRole('button', { name: 'Secret anzeigen' }).click();
}

/**
 * Record every request (URL, headers, body) the page sends.
 */
export function recordRequests(page: Page): Request[] {
    const requests: Request[] = [];
    page.on('request', (request) => requests.push(request));

    return requests;
}

/**
 * Collect CSP violations reported in the page.
 */
export async function trackCspViolations(
    page: Page,
): Promise<() => Promise<string[]>> {
    await page.addInitScript(() => {
        const violations: string[] = [];
        (window as unknown as { __cspViolations: string[] }).__cspViolations =
            violations;
        document.addEventListener('securitypolicyviolation', (event) => {
            // WebKit reports its own <select> shadow DOM styling as style-src-attr; not app code.
            if (
                event.violatedDirective === 'style-src-attr' &&
                event.sourceFile === '' &&
                (event.target as Element | null)?.tagName === 'SELECT'
            ) {
                return;
            }

            violations.push(`${event.violatedDirective} ${event.blockedURI}`);
        });
    });

    return () =>
        page.evaluate(
            () =>
                (window as unknown as { __cspViolations: string[] })
                    .__cspViolations,
        );
}

/**
 * All request headers. WebKit may never resolve allHeaders() for some
 * requests, so fall back to the provisional headers.
 */
export async function requestHeaders(request: Request): Promise<string> {
    const headers = await Promise.race([
        request.allHeaders(),
        new Promise<Record<string, string>>((resolve) =>
            setTimeout(() => resolve(request.headers()), 1000),
        ),
    ]);

    return JSON.stringify(headers);
}

/**
 * Navigate via about:blank so a link differing only in its fragment triggers
 * a real page load instead of a same-document navigation.
 */
export async function openFresh(page: Page, url: string): Promise<void> {
    await page.goto('about:blank');
    await page.goto(url);
}
