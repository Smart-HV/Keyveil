import { defineConfig, devices } from '@playwright/test';

const port = 8123;
const baseURL = `http://127.0.0.1:${port}`;

export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL,
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
    webServer: {
        // Built assets only: the E2E run must exercise the production CSP.
        command: `rm -f public/hot && npm run build && php artisan migrate:fresh --force && php artisan serve --host=127.0.0.1 --port=${port}`,
        url: `${baseURL}/up`,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        env: {
            APP_ENV: 'e2e',
            APP_DEBUG: 'false',
            APP_URL: baseURL,
            DB_DATABASE: process.env.E2E_DB_DATABASE ?? 'keyveil_e2e',
            CACHE_STORE: 'array',
            PHP_CLI_SERVER_WORKERS: '4',
            SECRET_CREATE_PER_MINUTE: '1000',
            SECRET_CREATE_PER_HOUR: '10000',
            SECRET_CLAIM_PER_MINUTE: '1000',
            SECRET_DELETE_PER_MINUTE: '1000',
            SECRET_API_PER_MINUTE: '10000',
        },
    },
});
