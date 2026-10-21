/**
 * Tests of tracker.js, run with: node --test plugins/EcommerceEnhanced/tests/javascript/
 * The minified build: TRACKER_FILE=tracker.min.js node --test plugins/EcommerceEnhanced/tests/javascript/
 *
 * tracker.js is loaded in a sandbox with a fake Matomo tracker that records every call.
 */

const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Objects built inside the sandbox have other prototypes, compare their JSON form
const plain = (value) => JSON.parse(JSON.stringify(value));

const source = fs.readFileSync(path.join(__dirname, '..', '..', process.env.TRACKER_FILE || 'tracker.js'), 'utf8');

function createStorage() {
    const data = {};
    return {
        getItem: (key) => (key in data ? data[key] : null),
        setItem: (key, value) => { data[key] = String(value); },
        removeItem: (key) => { delete data[key]; },
    };
}

function load({pageViews = 1, dataLayer, tagManagerEvents, localStorage = createStorage(), sessionStorage = createStorage(), cookiesEnabled = true, itemDimensions} = {}) {
    const calls = [];
    const plugins = {};
    let pending = null;

    const tracker = {
        getSiteId: () => 1,
        areCookiesEnabled: () => cookiesEnabled,
        getNumTrackedPageViews: () => pageViews,
        trackRequest: (request) => calls.push(['request', parse(request)]),
        clearEcommerceCart: () => calls.push(['clear']),
        addEcommerceItem: (...args) => calls.push(['item', ...args]),
        setEcommerceView: (...args) => calls.push(['view', ...args]),
        trackEcommerceCartUpdate: (total) => calls.push(['cart', total, parse(hook())]),
        trackEcommerceOrder: (...args) => calls.push(['order', ...args.slice(0, 6), parse(hook())]),
        forgetCookieConsentGiven: () => { cookiesEnabled = false; },
        rememberCookieConsentGiven: () => { cookiesEnabled = true; },
    };

    function hook() {
        return Object.values(plugins).map((plugin) => (plugin.ecommerce ? plugin.ecommerce({tracker}) : '')).join('').replace(/^&/, '');
    }

    function parse(query) {
        const params = {};
        for (const pair of query.split('&')) {
            if (!pair) continue;
            const [key, value = ''] = pair.split('=');
            params[key] = decodeURIComponent(value);
        }
        if (params.eex_d) params.eex_d = JSON.parse(params.eex_d);
        return params;
    }

    const window = {
        sessionStorage,
        localStorage,
        Matomo: {
            addPlugin: (name, plugin) => { plugins[name] = plugin; },
            getAsyncTrackers: () => [tracker],
            retryMissedPluginCalls: () => {},
        },
    };
    if (dataLayer) window.dataLayer = dataLayer;
    if (itemDimensions) window.matomoEcommerceEnhancedItemDimensions = itemDimensions;
    if (tagManagerEvents) {
        const callbacks = [];
        window.MatomoTagManager = {dataLayer: {
            getAllEvents: () => tagManagerEvents,
            on: (callback) => callbacks.push(callback),
            push: (value) => { tagManagerEvents.push(value); callbacks.forEach((callback) => callback(value)); },
        }};
    }
    window.window = window;

    const context = vm.createContext({window, Matomo: window.Matomo, JSON, Math, Date, Object, String, isFinite, isNaN, parseFloat, encodeURIComponent, decodeURIComponent, unescape, setTimeout});
    vm.runInContext(source, context);

    return {ee: window.Matomo.EcommerceEnhanced, calls, window, tracker};
}

const ga4Items = [
    {item_id: 'SKU1', item_name: 'Tee', item_brand: 'Nike', item_variant: 'Red', item_category: 'Apparel', price: 20, quantity: 1, size: 'M', email: 'x@y.z'},
    {item_id: 'SKU1', item_name: 'Tee', item_variant: 'Blue', price: '22,00', quantity: '2'},
];

test('parses a GA4 dataLayer push with clean values and never reads user_data', () => {
    const {ee} = load();
    const [event] = ee.parse({event: 'add_to_cart', ecommerce: {currency: 'eur', value: 64, items: ga4Items}, user_data: {email: 'x@y.z'}});

    assert.strictEqual(event.name, 'add_to_cart');
    assert.strictEqual(event.source, 1);
    assert.strictEqual(event.data.currency, 'EUR');
    assert.strictEqual(event.data.items.length, 2);
    assert.strictEqual(event.data.items[1].price, 22);
    assert.strictEqual(event.data.items[1].quantity, 2);
    // Unknown item keys stay in the page until the tracker narrows them to the website item dimensions
    assert.deepStrictEqual(plain(event.data.items[0].custom), {size: 'M', email: 'x@y.z'});
    assert.ok(!('user_data' in event.data));
});

test('sends and stores only the item keys of the website active item dimensions', () => {
    const localStorage = createStorage();
    const items = [{item_id: 'SKU1', price: 20, size: 'M', canary: 'never-sent'}, {item_id: 'SKU2', price: 5, canary: 'never-sent'}];
    const {ee, calls} = load({localStorage, itemDimensions: {1: ['size'], 2: ['canary']}});
    ee.track('add_to_cart', {items});
    ee.track('purchase', {transaction_id: 'T1', items});

    const sent = JSON.stringify(calls) + localStorage.getItem('mtm_ee_cart_1');
    assert.ok(sent.includes('"size":"M"'));
    assert.ok(!sent.includes('never-sent'));
    assert.strictEqual(calls.find((c) => c[0] === 'request')[1].eex_d.items[1].custom, undefined);
});

test('without item dimensions, no item key outside the GA4 fields is sent or stored', () => {
    const localStorage = createStorage();
    const {ee, calls} = load({localStorage});
    ee.track('add_to_cart', {items: ga4Items});

    const sent = JSON.stringify(calls) + localStorage.getItem('mtm_ee_cart_1');
    assert.ok(!sent.includes('x@y.z'));
    assert.ok(!sent.includes('"custom"'));
});

test('parses gtag arguments, gtag set currency and prefixed event names', () => {
    const {ee} = load();
    (function () { ee.parse(arguments); })('set', {currency: 'USD'});
    const events = (function () { return ee.parse(arguments); })('event', 'purchase', {transaction_id: 'T1', value: 10, items: [{id: 'A', name: 'a', price: 10}]});

    assert.strictEqual(events[0].source, 2);
    assert.strictEqual(events[0].data.currency, 'USD');
    assert.strictEqual(events[0].data.items[0].item_id, 'A');
    assert.strictEqual(ee.parse({event: 'dl_view_item_stape', ecommerce: {items: [{item_id: 'A'}]}})[0].name, 'view_item');
    assert.deepStrictEqual(plain(ee.parse({event: 'gtm.js'})), []);
    assert.deepStrictEqual(plain(ee.parse({ecommerce: null})), []);
});

test('converts Universal Analytics purchases to GA4 value semantics', () => {
    const {ee} = load();
    const [event] = ee.parse({
        event: 'transaction',
        ecommerce: {
            currencyCode: 'EUR',
            purchase: {
                actionField: {id: 'T9', revenue: '35.43', tax: '4.90', shipping: '5.99', coupon: 'SUMMER'},
                products: [{id: 'P1', name: 'Shirt', category: 'Apparel/Men/Shirts', variant: 'Gray', price: '24.54', quantity: 1, position: 3}],
            },
        },
    });

    assert.strictEqual(event.name, 'purchase');
    assert.strictEqual(event.source, 3);
    assert.strictEqual(event.data.value, 24.54);
    assert.strictEqual(event.data.transaction_id, 'T9');
    assert.strictEqual(event.data.items[0].item_category3, 'Shirts');
    assert.strictEqual(event.data.items[0].index, 3);
});

test('splits UA impressions per list and promotions per promotion', () => {
    const {ee} = load();
    const events = ee.parse({
        ecommerce: {
            impressions: [{id: 'A', list: 'Home'}, {id: 'B', list: 'Search'}, {id: 'C', list: 'Home'}],
            promoView: {promotions: [{id: 'P1', name: 'Sale', creative: 'banner', position: 'top'}, {id: 'P2', name: 'New'}]},
        },
    });

    assert.deepStrictEqual(plain(events.map((e) => e.name)), ['view_item_list', 'view_item_list', 'view_promotion', 'view_promotion']);
    assert.strictEqual(events[0].data.items.length, 2);
    assert.strictEqual(events[2].data.creative_slot, 'top');
});

test('reads money strings the way shops write them', () => {
    const {ee} = load();
    const value = (v) => ee.parse({event: 'view_item', ecommerce: {value: v, items: [{item_id: 'A'}]}})[0].data.value;

    assert.strictEqual(value('1 299,90 €'), 1299.9);
    assert.strictEqual(value('1,299.90'), 1299.9);
    assert.strictEqual(value('1.299,90'), 1299.9);
    assert.strictEqual(value('1,299'), 1299);
    assert.strictEqual(value({amount: '12.5', currencyCode: 'CAD'}), 12.5);
    assert.strictEqual(value('abc'), null);
});

test('replays the dataLayer, tracks events and mirrors the cart natively', () => {
    const dataLayer = [{event: 'view_item_list', ecommerce: {item_list_name: 'Home', items: ga4Items}}];
    const {ee, calls, window} = load({dataLayer});

    ee.enable();
    window.dataLayer.push({event: 'add_to_cart', ecommerce: {currency: 'EUR', value: 64, items: ga4Items}});

    const requests = calls.filter((c) => c[0] === 'request').map((c) => c[1]);
    assert.deepStrictEqual(plain(requests.map((r) => r.e_n)), ['view_item_list', 'add_to_cart']);
    assert.strictEqual(requests[0].e_c, 'Ecommerce');
    assert.strictEqual(requests[1].e_a, 'Add to cart');
    assert.strictEqual(requests[1].e_v, '64');
    assert.strictEqual(requests[1].eex_d.items.length, 2);

    // Both variants share one SKU natively, the variant detail travels in the payload
    const items = calls.filter((c) => c[0] === 'item');
    assert.deepStrictEqual(plain(items), [['item', 'SKU1', 'Tee', ['Apparel'], 21.33, 3]]);
    const cart = calls.find((c) => c[0] === 'cart');
    assert.strictEqual(cart[1], 64);
    assert.strictEqual(cart[2].eex_e, 'add_to_cart');
    assert.strictEqual(cart[2].eex_d.items.length, 2);
});

test('removing an item updates the mirrored cart', () => {
    const {ee, calls} = load();
    ee.track('add_to_cart', {items: ga4Items});
    ee.track('remove_from_cart', {items: [{item_id: 'SKU1', item_variant: 'Blue', quantity: 2}]});

    const carts = calls.filter((c) => c[0] === 'cart');
    assert.strictEqual(carts[1][1], 20);
    assert.strictEqual(carts[1][2].eex_d.items.length, 1);
});

test('the mirrored cart is shared by tabs, so an add from a new tab resends the whole cart', () => {
    const localStorage = createStorage();
    load({localStorage}).ee.track('add_to_cart', {items: [ga4Items[0]]});

    const {ee, calls} = load({localStorage});
    ee.track('add_to_cart', {items: [{item_id: 'SKU2', item_name: 'Cap', price: 10, quantity: 1}]});

    const cart = calls.find((c) => c[0] === 'cart');
    assert.strictEqual(cart[1], 30);
    assert.strictEqual(cart[2].eex_d.items.length, 2);
});

test('an expired mirrored cart is dropped instead of being resent', () => {
    const localStorage = createStorage();
    localStorage.setItem('mtm_ee_cart_1', JSON.stringify({time: Date.now() - 3 * 24 * 60 * 60 * 1000, value: [ga4Items[0]]}));

    const {ee, calls} = load({localStorage});
    ee.track('add_to_cart', {items: [{item_id: 'SKU2', item_name: 'Cap', price: 10, quantity: 1}]});

    assert.strictEqual(calls.find((c) => c[0] === 'cart')[1], 10);
});

test('without cookie consent the cart mirror is never written to the browser storage', () => {
    const localStorage = createStorage();
    load({localStorage, cookiesEnabled: false}).ee.track('add_to_cart', {items: [ga4Items[0]]});

    assert.strictEqual(localStorage.getItem('mtm_ee_cart_1'), null);
});

test('once cookie consent is withdrawn, what earlier pages stored is removed', () => {
    const localStorage = createStorage();
    load({localStorage}).ee.track('purchase', {transaction_id: 'T1', items: [ga4Items[0]]});
    load({localStorage}).ee.track('add_to_cart', {items: [ga4Items[0]]});
    assert.notStrictEqual(localStorage.getItem('mtm_ee_cart_1'), null);
    assert.notStrictEqual(localStorage.getItem('mtm_ee_tx'), null);

    load({localStorage, cookiesEnabled: false}).ee.track('view_cart', {items: [ga4Items[0]]});

    assert.strictEqual(localStorage.getItem('mtm_ee_cart_1'), null);
    assert.strictEqual(localStorage.getItem('mtm_ee_tx'), null);
});

test('withdrawing consent clears every stored key at once, and a later consent does not reuse old selections', () => {
    const localStorage = createStorage();
    const sessionStorage = createStorage();
    const {ee, calls, tracker} = load({localStorage, sessionStorage});
    ee.track('select_item', {item_list_name: 'Search results', items: [{item_id: 'SKU2'}]});
    ee.track('add_shipping_info', {shipping_tier: 'Express', items: []});
    ee.track('add_to_cart', {items: [ga4Items[0]]});
    ee.track('purchase', {transaction_id: 'T1', items: [ga4Items[0]]});
    for (const key of ['mtm_ee_sel_1', 'mtm_ee_checkout_1']) assert.notStrictEqual(sessionStorage.getItem(key), null);
    for (const key of ['mtm_ee_cart_1', 'mtm_ee_tx']) assert.notStrictEqual(localStorage.getItem(key), null);

    tracker.forgetCookieConsentGiven();

    for (const key of ['mtm_ee_sel_1', 'mtm_ee_checkout_1']) assert.strictEqual(sessionStorage.getItem(key), null);
    for (const key of ['mtm_ee_cart_1', 'mtm_ee_tx']) assert.strictEqual(localStorage.getItem(key), null);

    tracker.rememberCookieConsentGiven();
    ee.track('add_to_cart', {items: [{item_id: 'SKU2', price: 10}]});

    const cart = calls.filter((c) => c[0] === 'cart').pop();
    assert.strictEqual(cart[2].eex_d.items.length, 1);
    assert.strictEqual(cart[2].eex_d.items[0].item_list_name, undefined);
});

test('records a purchase once, with remembered checkout choices and list attribution', () => {
    const {ee, calls} = load();
    ee.track('select_item', {item_list_name: 'Search results', items: [{item_id: 'SKU2'}]});
    ee.track('add_shipping_info', {shipping_tier: 'Express', items: []});
    ee.track('add_payment_info', {payment_type: 'PayPal', items: []});

    const purchase = {transaction_id: 'T1', currency: 'EUR', value: 50, tax: 10, shipping: 5, items: [{item_id: 'SKU2', price: 25, quantity: 2, discount: 1}], user_data: {email: 'x@y.z'}};
    ee.push({event: 'purchase', ecommerce: purchase});
    ee.push({event: 'purchase', ecommerce: purchase, gtm: 'second trigger'});

    const orders = calls.filter((c) => c[0] === 'order');
    assert.strictEqual(orders.length, 1);
    const [, id, grandTotal, subTotal, tax, shipping, discount, params] = orders[0];
    assert.deepStrictEqual(plain([id, grandTotal, subTotal, tax, shipping, discount]), ['T1', 65, 50, 10, 5, 2]);
    assert.strictEqual(params.eex_d.shipping_tier, 'Express');
    assert.strictEqual(params.eex_d.payment_type, 'PayPal');
    assert.strictEqual(params.eex_d.items[0].item_list_name, 'Search results');
    assert.strictEqual(params.eex_d.items[0].attribution, 2);
    assert.strictEqual(params.eex_d.transaction_id, undefined);
    assert.ok(!JSON.stringify(params).includes('x@y.z'));

    const purchaseEvents = calls.filter((c) => c[0] === 'request' && c[1].e_n === 'purchase');
    assert.strictEqual(purchaseEvents.length, 1);
    assert.strictEqual(purchaseEvents[0][1].eex_d, undefined);
});

test('a purchase without transaction id is never sent as an order', () => {
    const {ee, calls} = load();
    ee.track('purchase', {value: 10, items: [{item_id: 'A', price: 10}]});

    assert.strictEqual(calls.filter((c) => c[0] === 'order').length, 0);
});

test('never sets the page level product view, the server adds it to the view_item request only', () => {
    const {ee, calls} = load({pageViews: 0});
    ee.track('view_item', {items: [{item_id: 'A', item_name: 'Lamp', item_category: 'Home/Lights', price: 49}]});

    assert.strictEqual(calls.filter((c) => c[0] === 'view').length, 0);
});

test('drops the same push sent twice in a row by two triggers', () => {
    const {ee, calls} = load();
    ee.push({event: 'view_item', ecommerce: {items: [{item_id: 'A'}]}});
    ee.push({event: 'view_item', ecommerce: {items: [{item_id: 'A'}]}});

    assert.strictEqual(calls.filter((c) => c[0] === 'request').length, 1);
});

test('in Tag Manager, reads the container data layer only, so a synchronised push is not counted twice', () => {
    const viewItem = {event: 'view_item', ecommerce: {currency: 'EUR', items: [{item_id: 'A', price: 5}]}};
    const {ee, calls, window} = load({dataLayer: [viewItem], tagManagerEvents: [viewItem]});
    ee.enable({tagManager: true});
    window.MatomoTagManager.dataLayer.push({event: 'add_to_wishlist', ecommerce: {currency: 'EUR', items: [{item_id: 'A', price: 5}]}});

    const names = calls.filter((c) => c[0] === 'request').map((c) => c[1].eex_e);
    assert.deepStrictEqual(names, ['view_item', 'add_to_wishlist']);
});

test('trims a payload above the server limit by dropping the last items, and counts them', () => {
    const {ee, calls} = load({itemDimensions: {1: Array.from({length: 30}, (_, k) => 'param' + k)}});
    const items = [];
    for (let i = 0; i < 200; i++) {
        const item = {item_id: 'SKU' + i, item_name: 'Product '.repeat(30) + i, price: 10, quantity: 1};
        for (let k = 0; k < 30; k++) {
            item['param' + k] = 'x'.repeat(100);
        }
        items.push(item);
    }
    ee.track('add_to_cart', {currency: 'EUR', value: 2000, items});

    const event = calls.find((c) => c[0] === 'request' && c[1].eex_e === 'add_to_cart')[1];
    assert.ok(JSON.stringify(event.eex_d).length <= 120000);
    assert.ok(event.eex_d.items.length < 200);
    assert.strictEqual(event.eex_d.items.length + event.eex_d.dropped_items, 200);
});
