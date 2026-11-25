import {test, expect, configureHyvaGtm, addProductToCart} from '@yireo-googletagmanager/gtm-objects';
import {Page} from '@playwright/test';

const PRODUCT_SKU = 'gtm2-hyva-checkout-html-name';
const PRODUCT_NAME = 'GTM2 </script><script>window.__gtm2HyvaXss=1</script> <!--<script> Product';

async function gotoHyvaCheckout(page: Page) {
    await page.goto('/checkout/');
    await page.waitForLoadState('networkidle').catch(() => undefined);

    const hasHyvaCheckout = await page.evaluate(() => typeof (window as any).hyvaCheckout !== 'undefined');
    test.skip(!hasHyvaCheckout, 'Hyvä Checkout is not installed');
}

test.describe('GTM Hyvä Checkout security', function () {
    test.beforeEach(async function ({page}) {
        await configureHyvaGtm(page, {product: {sku: PRODUCT_SKU, name: PRODUCT_NAME}});
        await addProductToCart(page, 1, {sku: PRODUCT_SKU});
    });

    test('renders a product name with HTML in begin_checkout without breaking out of the script', async function ({page, dataLayer}) {
        const cspViolations: string[] = [];
        page.on('console', message => {
            if (message.text().includes('Content Security Policy')) {
                cspViolations.push(message.text());
            }
        });

        await gotoHyvaCheckout(page);

        const beginCheckout = await dataLayer.event('begin_checkout').toBePushed();
        expect(beginCheckout.getItems().map(item => item.item_name)).toContain(PRODUCT_NAME);
        await dataLayer.event('begin_checkout').toBePushedTimes(1);

        expect(await page.evaluate(() => (window as any).__gtm2HyvaXss), 'Injected script did not run').toBeUndefined();
        await expect(page.locator('footer').first(), 'Page after the data layer block is rendered').toBeAttached();
        expect(cspViolations, 'No CSP violations').toEqual([]);
    });

    test('ignores ga:trigger-event events without a valid data layer event', async function ({page, dataLayer}) {
        await gotoHyvaCheckout(page);
        await dataLayer.event('begin_checkout').toBePushed();
        const before = (await dataLayer.getEntries()).length;

        await page.evaluate(() => {
            for (const detail of [undefined, null, 'string', [null], [{foo: 'bar'}], [{event: 42}]]) {
                window.dispatchEvent(new CustomEvent('ga:trigger-event', {detail}));
            }
        });
        expect((await dataLayer.getEntries()).length, 'Invalid events are not pushed').toBe(before);

        await page.evaluate(() => {
            window.dispatchEvent(new CustomEvent('ga:trigger-event', {detail: [{event: 'gtm2_hyva_test'}]}));
        });
        await dataLayer.event('gtm2_hyva_test').toBePushedTimes(1);
    });

    test('handles listeners that are triggered directly from the browser', async function ({page, dataLayer}) {
        await gotoHyvaCheckout(page);
        await dataLayer.event('begin_checkout').toBePushed();

        const serverErrors: number[] = [];
        page.on('response', response => {
            if (response.request().method() === 'POST' && response.status() >= 500) {
                serverErrors.push(response.status());
            }
        });

        await page.evaluate(() => {
            const magewire = (window as any).Magewire;
            for (let i = 0; i < 3; i++) {
                magewire.dispatch('payment_method_selected', {method: 'evil', cartId: 1});
                magewire.dispatch('shipping_method_selected', {method: 'evil'});
            }
        });
        await page.waitForLoadState('networkidle').catch(() => undefined);

        expect(serverErrors, 'No server errors').toEqual([]);
        expect(await dataLayer.event('add_payment_info').count()).toBeLessThanOrEqual(1);
        expect(await dataLayer.event('add_shipping_info').count()).toBeLessThanOrEqual(1);
        await dataLayer.expectNoDuplicateEvents();
    });
});
