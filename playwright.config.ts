import { defineConfig, devices } from '@playwright/test';

/**
 * E2E HUMANIX: alur utama tiap role di 5 lebar layar.
 *
 * Server terpisah (port 8010) memakai database `humanix_test` yang di-seed ulang di global-setup,
 * sehingga data pengembangan lokal (`humanix`) tidak tersentuh. Browser: Microsoft Edge bawaan
 * Windows (channel msedge); set PW_CHANNEL=chromium bila memakai Chromium hasil `playwright install`.
 */
export const E2E_PORT = 8010;
export const E2E_ENV = {
    APP_ENV: 'local',
    APP_URL: `http://127.0.0.1:${E2E_PORT}`,
    DB_DATABASE: 'humanix_test',
    MAIL_MAILER: 'log',
    QUEUE_CONNECTION: 'sync',
    DEBUGBAR_ENABLED: 'false',
};

const channel = process.env.PW_CHANNEL ?? 'msedge';
const widths = [360, 414, 768, 1366, 1920];

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.ts',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 60_000,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: E2E_ENV.APP_URL,
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: widths.map((width) => ({
        name: `w${width}`,
        use: {
            ...(width < 768 ? devices['Pixel 7'] : devices['Desktop Edge']),
            channel: channel === 'chromium' ? undefined : channel,
            viewport: { width, height: width < 768 ? 800 : 900 },
            isMobile: width < 768,
            hasTouch: width < 768,
        },
    })),
    webServer: {
        // `php -S` langsung (bukan `artisan serve`) agar variabel lingkungan di atas diteruskan ke Laravel.
        command: `php -S 127.0.0.1:${E2E_PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: 'public',
        url: `${E2E_ENV.APP_URL}/login`,
        env: E2E_ENV,
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
