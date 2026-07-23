// Records a captioned walkthrough of the CSV → Shopify Import app with a visible
// mouse cursor + click ripple, covering the website and the admin panel in
// light and dark mode. Run via scripts/record-demo.sh (adds the ffmpeg step).
//
// Usage: node scripts/record-demo.mjs   (app served at BASE with a queue worker)

import { chromium } from 'playwright';

const BASE = process.env.DEMO_BASE || 'http://localhost:8000';
const OUT = 'storage/app/demo';
const VIEW = { width: 1920, height: 1080 };
const CSV_A = process.cwd() + '/scripts/demo-a.csv';
const CSV_B = process.cwd() + '/scripts/demo-b.csv';

const mouseHelper = () => {
    const ensure = () => {
        if (document.getElementById('__cursor') || !document.body) return document.getElementById('__cursor');
        const s = document.createElement('style');
        s.textContent = `
      #__cursor{position:fixed;top:-60px;left:-60px;width:28px;height:28px;border-radius:50%;
        background:rgba(37,99,235,.35);border:3px solid #2563eb;margin:-14px 0 0 -14px;
        pointer-events:none;z-index:2147483647;box-shadow:0 0 0 2px rgba(255,255,255,.6)}
      .__ring{position:fixed;width:16px;height:16px;border-radius:50%;border:4px solid #2563eb;
        margin:-8px 0 0 -8px;pointer-events:none;z-index:2147483647;animation:__r .6s ease-out forwards}
      @keyframes __r{to{width:70px;height:70px;margin:-35px 0 0 -35px;opacity:0}}`;
        document.head.appendChild(s);
        const c = document.createElement('div');
        c.id = '__cursor';
        document.body.appendChild(c);
        return c;
    };
    document.addEventListener('mousemove', (e) => {
        const c = ensure();
        if (c) { c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px'; }
    }, true);
    document.addEventListener('mousedown', (e) => {
        ensure();
        const r = document.createElement('div');
        r.className = '__ring';
        r.style.left = e.clientX + 'px';
        r.style.top = e.clientY + 'px';
        (document.body || document.documentElement).appendChild(r);
        setTimeout(() => r.remove(), 600);
    }, true);
};

const captionHelper = () => {
    window.__cap = (text) => {
        let el = document.getElementById('__cap');
        if (!el) {
            el = document.createElement('div');
            el.id = '__cap';
            el.style.cssText = 'position:fixed;left:50%;bottom:38px;transform:translateX(-50%);z-index:2147483647;' +
                'background:rgba(17,24,39,.94);color:#fff;padding:16px 30px;border-radius:999px;' +
                'font:600 22px/1.25 system-ui,-apple-system,sans-serif;box-shadow:0 12px 40px rgba(0,0,0,.4);' +
                'pointer-events:none;max-width:82vw;text-align:center;letter-spacing:.2px;';
            (document.body || document.documentElement).appendChild(el);
        }
        el.textContent = text;
    };
};

const run = async () => {
    const browser = await chromium.launch({ slowMo: 55 });
    const context = await browser.newContext({
        viewport: VIEW,
        deviceScaleFactor: 2,
        recordVideo: { dir: OUT, size: VIEW },
    });
    await context.addInitScript(mouseHelper);
    await context.addInitScript(captionHelper);

    const page = await context.newPage();
    const video = page.video();

    const beat = (ms = 1100) => page.waitForTimeout(ms);
    const cap = async (t) => { await page.evaluate((x) => window.__cap?.(x), t); await beat(650); };
    const glide = async (loc) => {
        const l = loc.first();
        await l.scrollIntoViewIfNeeded().catch(() => {});
        const b = await l.boundingBox();
        if (!b) return false;
        await page.mouse.move(b.x + b.width / 2, b.y + b.height / 2, { steps: 30 });
        await beat(300);
        return true;
    };
    const click = async (loc) => {
        if (!(await glide(loc))) return;
        await page.mouse.down();
        await beat(100);
        await page.mouse.up();
        await beat(500);
    };
    const type = async (loc, txt) => {
        await click(loc);
        await loc.first().fill('');
        await page.keyboard.type(txt, { delay: 42 });
        await beat(300);
    };
    const safe = async (fn) => { try { await fn(); } catch (e) { console.log('skip:', e.message.split('\n')[0]); } };
    const waitCompleted = () => safe(() => page.getByText('Completed').first().waitFor({ timeout: 90000 }));

    // ============================ PART 1 — WEBSITE ============================
    await page.goto(BASE + '/login');
    await cap('CSV → Shopify Product Import');
    await beat(1200);

    await cap('Website · Log in');
    await type(page.getByRole('textbox', { name: 'Email address' }), 'test@example.com');
    await type(page.getByRole('textbox', { name: 'Password' }), 'password');
    await click(page.getByRole('button', { name: 'Log in' }));
    await page.waitForURL('**/dashboard');

    await cap('Website · Dashboard');
    await beat(3000);

    await click(page.getByRole('link', { name: 'Imports' }));
    await page.waitForURL('**/imports');
    await cap('Website · Imports');
    await beat(2200);

    await click(page.getByRole('link', { name: 'New import' }));
    await page.waitForURL('**/imports/create');
    await cap('Website · New import');
    await beat(900);
    await page.setInputFiles('[data-testid=csv-input]', CSV_A);
    await beat(1300);
    await click(page.getByRole('button', { name: 'Start import' }));

    await page.waitForURL(/\/imports\/\d+$/);
    await cap('Website · Import in progress (10 products)');
    await beat(2500);
    await waitCompleted();
    await cap('Website · Import complete — per-row statuses (success + failed)');
    await beat(1500);
    await page.mouse.wheel(0, 500);
    await beat(2600);

    await page.goto(BASE + '/dashboard');
    await cap('Website · Dashboard — updated stats');
    await beat(3200);

    // Second import, then straight to the dashboard to show the live widget.
    await page.goto(BASE + '/imports/create');
    await page.setInputFiles('[data-testid=csv-input]', CSV_B);
    await beat(900);
    await click(page.getByRole('button', { name: 'Start import' }));
    await page.waitForURL(/\/imports\/\d+$/);
    await page.goto(BASE + '/dashboard');
    await cap('Website · Live "ongoing imports" widget updates in real time');
    // Wait on the dashboard until the ongoing widget clears (import finished).
    await safe(() => page.getByText('No imports are currently running').waitFor({ timeout: 90000 }));
    await beat(1600);

    // Back to the imports list to show the run is now complete.
    await click(page.getByRole('link', { name: 'Imports' }));
    await page.waitForURL('**/imports');
    await cap('Website · Imports — the run now shows as complete');
    await beat(2800);

    // Settings — all three sub-pages.
    await page.goto(BASE + '/settings/profile');
    await cap('Website · Settings — profile');
    await beat(2600);
    await page.goto(BASE + '/settings/security');
    await safe(async () => {
        if (/confirm-password/.test(page.url())) {
            await cap('Website · Settings — confirm password to continue');
            await page.locator('input[type=password]').first().fill('password');
            await page.keyboard.press('Enter');
            await page.waitForURL('**/settings/security', { timeout: 8000 });
        }
    });
    await cap('Website · Settings — security');
    await beat(2600);
    await page.goto(BASE + '/settings/appearance');
    await cap('Website · Settings — appearance');
    await beat(1800);

    // Dark mode.
    await safe(() => click(page.getByRole('button', { name: 'Dark' })));
    await beat(1200);
    await page.goto(BASE + '/dashboard');
    await cap('Website · Dark mode');
    await beat(3200);

    // ============================ PART 2 — ADMIN ============================
    await context.clearCookies();
    await page.goto(BASE + '/admin');
    await page.waitForURL('**/admin/login');
    await cap('Admin panel · Log in');
    await beat(600);
    await safe(() => type(page.getByLabel('Email address'), 'test@example.com'));
    await safe(() => type(page.getByLabel('Password'), 'password'));
    await click(page.getByRole('button', { name: 'Sign in' }));
    await page.waitForURL(/\/admin$/);

    await cap('Admin · Dashboard & stats');
    await beat(3200);

    await safe(() => click(page.getByRole('link', { name: 'Import Runs' })));
    await cap('Admin · Import runs');
    await beat(2200);

    await safe(() => click(page.getByRole('link', { name: 'View' })));
    await cap('Admin · Inside an import');
    await beat(1600);
    await page.mouse.wheel(0, 700);
    await cap('Admin · Rows (relation manager)');
    await beat(2400);
    await safe(() => click(page.getByRole('tab', { name: 'Log' })));
    await safe(() => click(page.getByText('Log', { exact: true })));
    await cap('Admin · Log (relation manager)');
    await beat(2600);

    await safe(() => click(page.getByRole('link', { name: 'Import Logs' })));
    await cap('Admin · Import log list');
    await beat(2200);
    await safe(() => click(page.getByRole('link', { name: 'View' })));
    await cap('Admin · A single log entry');
    await beat(2600);

    await safe(() => click(page.getByRole('link', { name: 'Dashboard' })));
    await beat(1200);
    await safe(() => click(page.getByRole('button', { name: /Notifications/ })));
    await cap('Admin · Failure notification');
    await beat(3000);
    await page.keyboard.press('Escape');

    // Global search.
    await safe(async () => {
        await click(page.getByRole('searchbox', { name: /search/i }));
        await page.keyboard.type('demo', { delay: 80 });
    });
    await cap('Admin · Global search');
    await beat(3000);

    // Dark mode.
    await page.evaluate(() => localStorage.setItem('theme', 'dark'));
    await page.goto(BASE + '/admin');
    await cap('Admin · Dark mode');
    await beat(3200);
    await page.evaluate(() => localStorage.setItem('theme', 'light'));

    await cap('CSV → Shopify Import — thanks for watching');
    await beat(2200);

    await context.close();
    await browser.close();
    process.stdout.write((await video.path()) + '\n');
};

run().catch((e) => { console.error(e); process.exit(1); });
