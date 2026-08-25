import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
                'resources/js/booking-form.js',
                'resources/js/waitlist-form.js',
                'resources/js/vehicle-filters.js',
                'resources/css/filament/operator/theme.css',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
            // Fonts are downloaded from Bunny at BUILD time and served from our
            // own origin — no runtime request to a third party, no GDPR exposure,
            // no render-blocking external stylesheet.
            //
            // `latin-ext` is not optional: Albanian's ë and ç live there
            // (U+0100–U+017F). Bunny emits one @font-face per subset with a
            // unicode-range, so a page with no ë never downloads that file.
            //
            // `preload` is narrowed to weight 400 on the tenant-selectable
            // families — the default (`true`) preloads every weight, which would
            // mean fetching faces we may never paint with.
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Inter', {
                    weights: [400, 600, 700],
                    subsets: ['latin', 'latin-ext'],
                    display: 'swap',
                    preload: [{ weight: 400 }],
                }),
                bunny('Poppins', {
                    weights: [400, 600, 700],
                    subsets: ['latin', 'latin-ext'],
                    display: 'swap',
                    preload: [{ weight: 400 }],
                }),
                bunny('Roboto', {
                    weights: [400, 500, 700],
                    subsets: ['latin', 'latin-ext'],
                    display: 'swap',
                    preload: [{ weight: 400 }],
                }),
                bunny('Nunito', {
                    weights: [400, 600, 700],
                    subsets: ['latin', 'latin-ext'],
                    display: 'swap',
                    preload: [{ weight: 400 }],
                }),
                bunny('Lato', {
                    weights: [400, 700],
                    subsets: ['latin', 'latin-ext'],
                    display: 'swap',
                    preload: [{ weight: 400 }],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
