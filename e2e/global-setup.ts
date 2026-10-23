import { execSync } from 'node:child_process';
import { E2E_ENV } from '../playwright.config';

/** Database E2E selalu dimulai dari kondisi seed yang diketahui. */
export default function globalSetup() {
    execSync('php artisan migrate:fresh --seed --force', {
        stdio: 'inherit',
        env: { ...process.env, ...E2E_ENV },
    });
}
