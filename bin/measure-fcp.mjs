/**
 * Mesure du premier affichage utile (FCP) sous bridage réseau et processeur.
 *
 * CE SCRIPT EST VERSIONNÉ EXPRÈS. Les mesures du jalon 2 ont été prises avec un
 * script gardé hors du dépôt : leurs conditions exactes n'ont pas pu être
 * reconstituées, et le jalon 4 a constaté qu'elles étaient étiquetées
 * « 3G lente » alors que leurs valeurs correspondent au préréglage 3G rapide.
 * Une mesure non reproductible n'est pas une mesure.
 *
 * Usage :
 *   php artisan serve --host=127.0.0.1 --port=8123     (APP_DEBUG=false !)
 *   MEASURE_EMAIL=… MEASURE_PASSWORD=… PATHS=/,/recherche node bin/measure-fcp.mjs
 *
 * Deux pièges déjà payés, traités ici :
 *   - APP_DEBUG=true sert un paquet Livewire non minifié : la mesure devient
 *     fausse d'un facteur 7.
 *   - Sans vidage du cache, on mesure un rechargement, pas une première visite.
 *
 * Limite connue : `artisan serve` ne compresse pas. La colonne des octets
 * transférés est donc PESSIMISTE d'environ un facteur 3 par rapport à
 * FrankenPHP. Le FCP, lui, est dominé par la latence (voir docs/PERFORMANCE.md).
 */
import { chromium } from 'playwright';

const BASE = process.env.BASE ?? 'http://127.0.0.1:8123';
const EMAIL = process.env.MEASURE_EMAIL;
const PASSWORD = process.env.MEASURE_PASSWORD;
const PATHS = (process.env.PATHS ?? '/').split(',');

/** Préréglages Chrome DevTools, valeurs explicites plutôt qu'un nom. */
const PROFILES = {
    'slow-3g': { downloadThroughput: (400 * 1024) / 8, uploadThroughput: (400 * 1024) / 8, latency: 2000 },
    'fast-3g': { downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8, latency: 562.5 },
};

const CPU_THROTTLING = 4;
const VIEWPORT = { width: 360, height: 800 };

/**
 * Connexion faite UNE SEULE FOIS, puis état de session réutilisé.
 *
 * Se reconnecter à chaque page fait buter la mesure sur le limiteur de débit
 * de la connexion (5 tentatives par minute) : l'outil de mesure se heurte à un
 * contrôle qu'il n'est pas censé mesurer.
 */
async function signIn(browser) {
    const context = await browser.newContext({ viewport: VIEWPORT });
    const page = await context.newPage();

    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await page.fill('#email', EMAIL);
    await page.fill('#password', PASSWORD);
    await Promise.all([
        page.waitForURL(url => !url.pathname.endsWith('/login')),
        page.click('button[type=submit]'),
    ]);

    const state = await context.storageState();
    await context.close();

    return state;
}

async function measure(browser, state, path, profileName) {
    const context = await browser.newContext({ viewport: VIEWPORT, storageState: state });
    const page = await context.newPage();
    const client = await context.newCDPSession(page);

    await client.send('Network.enable');
    await client.send('Network.setCacheDisabled', { cacheDisabled: true });
    await client.send('Network.clearBrowserCache');
    await client.send('Network.emulateNetworkConditions', { offline: false, ...PROFILES[profileName] });
    await client.send('Emulation.setCPUThrottlingRate', { rate: CPU_THROTTLING });

    let transferred = 0;
    page.on('response', response => {
        const length = response.headers()['content-length'];
        if (length) transferred += Number(length);
    });

    await page.goto(`${BASE}${path}`, { waitUntil: 'load', timeout: 120000 });

    const fcp = await page.evaluate(() => new Promise(resolve => {
        const entry = performance.getEntriesByName('first-contentful-paint')[0];
        if (entry) return resolve(entry.startTime);

        new PerformanceObserver((list, observer) => {
            for (const e of list.getEntries()) {
                if (e.name === 'first-contentful-paint') {
                    observer.disconnect();
                    resolve(e.startTime);
                }
            }
        }).observe({ type: 'paint', buffered: true });

        setTimeout(() => resolve(null), 30000);
    }));

    await context.close();

    // Un FCP absent est un ÉCHEC DE MESURE, jamais un succès : `null < 2500`
    // vaut `true` en JavaScript, et la première version du script concluait
    // « budget tenu » sur une mesure vide. Une mesure qui ne peut pas échouer
    // ne mesure rien.
    if (fcp === null || Number.isNaN(fcp)) {
        throw new Error(`FCP non relevé pour ${path} (${profileName}) — mesure invalide`);
    }

    return {
        path,
        profile: profileName,
        latency_ms: PROFILES[profileName].latency,
        fcp_ms: Math.round(fcp),
        transferred_kb: +(transferred / 1024).toFixed(1),
    };
}

const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM_PATH ?? '/opt/pw-browsers/chromium',
});

const state = await signIn(browser);
const results = [];

for (const path of PATHS) {
    for (const profile of Object.keys(PROFILES)) {
        results.push(await measure(browser, state, path, profile));
    }
}

await browser.close();
console.log(JSON.stringify(results, null, 2));
