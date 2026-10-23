import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    build: {
        rollupOptions: {
            output: {
                // Pustaka inti dipisah dari kode aplikasi: ukuran chunk < 500 kB dan cache browser
                // tetap berlaku saat hanya kode HUMANIX yang berubah.
                manualChunks: (id) => {
                    if (!id.includes('node_modules')) {
                        return undefined;
                    }

                    if (
                        /node_modules[\\/](react|react-dom|scheduler)[\\/]/.test(
                            id,
                        )
                    ) {
                        return 'vendor-react';
                    }

                    if (
                        /node_modules[\\/](@inertiajs|axios|qs)[\\/]/.test(id)
                    ) {
                        return 'vendor-inertia';
                    }

                    if (
                        /node_modules[\\/](@radix-ui|@headlessui|@floating-ui)[\\/]/.test(
                            id,
                        )
                    ) {
                        return 'vendor-ui';
                    }

                    return undefined;
                },
            },
        },
    },
});
