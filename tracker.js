/**
 * Matomo - free/libre analytics platform
 *
 * Ecommerce Enhanced tracker plugin. Reads the GA4 ecommerce events of the page dataLayer (GA4 objects, gtag()
 * calls and legacy Universal Analytics Enhanced Ecommerce objects) and records them in Matomo:
 * - every step as a Matomo event (category "Ecommerce", name = the GA4 event name), with the GA4 detail in the
 *   eex_* parameters read by the plugin;
 * - carts and orders as native Matomo ecommerce requests, so the native reports keep working, even if the
 *   plugin is later deactivated.
 *
 * Turned on with _paq.push(['EcommerceEnhanced::enable']). Personal data (user_data, emails, addresses,
 * hashed identifiers) is never read.
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

(function () {

    var PAYLOAD_VERSION = '1';
    var EVENT_CATEGORY = 'Ecommerce';
    var MAX_ITEMS = 200;
    var MAX_LIST_ITEMS = 30;
    // Unknown item keys sent per item: the server keeps the configured item dimensions among them, up to 10
    var MAX_CUSTOM_PARAMS = 30;
    var MAX_REMEMBERED = 200;
    var MAX_TRANSACTIONS = 50;
    var DUPLICATE_WINDOW_MS = 1000;

    var SOURCE_GA4 = 1;
    var SOURCE_GTAG = 2;
    var SOURCE_UA = 3;
    var SOURCE_API = 4;

    var EVENT_ACTIONS = {
        view_item_list: 'View item list',
        select_item: 'Select item',
        view_item: 'View item',
        add_to_wishlist: 'Add to wishlist',
        add_to_cart: 'Add to cart',
        remove_from_cart: 'Remove from cart',
        view_cart: 'View cart',
        begin_checkout: 'Begin checkout',
        add_shipping_info: 'Add shipping info',
        add_payment_info: 'Add payment info',
        checkout_progress: 'Checkout progress',
        purchase: 'Purchase',
        refund: 'Refund',
        view_promotion: 'View promotion',
        select_promotion: 'Select promotion'
    };

    var EVENT_ALIASES = {
        set_checkout_option: 'checkout_progress'
    };

    var EVENT_STRINGS = {
        transaction_id: 100, coupon: 255, payment_type: 100, shipping_tier: 100, affiliation: 100,
        checkout_option: 100, item_list_id: 100, item_list_name: 100, promotion_id: 100, promotion_name: 100,
        creative_name: 100, creative_slot: 100
    };

    var ITEM_STRINGS = {
        item_id: 255, item_name: 255, item_category: 255, item_category2: 255, item_category3: 255,
        item_category4: 255, item_category5: 255, item_brand: 100, item_variant: 100, item_group_id: 100,
        coupon: 100, affiliation: 100, location_id: 100, item_list_id: 100, item_list_name: 100,
        promotion_id: 100, promotion_name: 100, creative_name: 100, creative_slot: 100
    };

    // Item keys read for a purpose, never sent as custom parameters
    var ITEM_KNOWN_KEYS = {
        price: 1, quantity: 1, discount: 1, index: 1, id: 1, name: 1, brand: 1, category: 1, variant: 1,
        list: 1, list_name: 1, list_position: 1, position: 1, sku: 1, variant_id: 1, product_id: 1, title: 1,
        google_business_vertical: 1, image: 1, url: 1, compare_at_price: 1, internal_id: 1, properties: 1,
        destination: 1, origin: 1, start_date: 1, end_date: 1
    };


    var EVENT_ITEM_INHERITED = ['item_list_id', 'item_list_name', 'promotion_id', 'promotion_name', 'creative_name', 'creative_slot'];

    var UA_ACTIONS = ['purchase', 'refund', 'checkout', 'checkout_option', 'add', 'remove', 'detail', 'click', 'promoClick'];

    var configs = {
        enabled: false,
        dataLayerName: 'dataLayer',
        defaultCurrency: null
    };

    var memory = {};
    var lastPush = {key: null, time: 0};

    /*
     * Value helpers
     */

    function isObject(value) {
        return value !== null && 'object' === typeof value;
    }

    function isArray(value) {
        return Object.prototype.toString.call(value) === '[object Array]';
    }

    function isArgumentsLike(value) {
        return Object.prototype.toString.call(value) === '[object Arguments]'
            || (isObject(value) && !isArray(value) && 'string' === typeof value[0] && 'number' === typeof value.length);
    }

    function hasOwn(object, key) {
        return Object.prototype.hasOwnProperty.call(object, key);
    }

    var entityDecoder = null;

    // Without the DOM, or when a Trusted Types policy forbids innerHTML: the entities shops actually emit
    function decodeEntitiesWithoutDom(value) {
        var named = {amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' '};

        return value.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, function (entity, code) {
            if (code.charAt(0) === '#') {
                var number = code.charAt(1).toLowerCase() === 'x' ? parseInt(code.slice(2), 16) : parseInt(code.slice(1), 10);

                return number > 0 && number < 0x110000 ? String.fromCodePoint(number) : entity;
            }

            return hasOwn(named, code.toLowerCase()) ? named[code.toLowerCase()] : entity;
        });
    }

    function decodeEntities(value) {
        if (value.indexOf('&') === -1) {
            return value;
        }

        try {
            if (!entityDecoder && 'undefined' !== typeof document && document.createElement) {
                entityDecoder = document.createElement('textarea');
            }

            if (entityDecoder) {
                entityDecoder.innerHTML = value.replace(/</g, '&lt;');

                return entityDecoder.value;
            }
        } catch (e) {
            entityDecoder = null;
        }

        return decodeEntitiesWithoutDom(value);
    }

    function toText(value, max) {
        if ('number' === typeof value && isFinite(value)) {
            value = String(value);
        }

        if ('string' !== typeof value) {
            return null;
        }

        value = decodeEntities(value).replace(/[\u0000-\u001F\u007F]+/g, ' ').replace(/\s+/g, ' ');
        value = value.replace(/^\s+|\s+$/g, '');

        if (value === '') {
            return null;
        }

        // Cut by code point so an emoji is never split in two
        var chars = value.match(/[\uD800-\uDBFF][\uDC00-\uDFFF]|[\s\S]/g) || [];

        return chars.length > max ? chars.slice(0, max).join('') : value;
    }

    // Accepts numbers and strings like "1 299,90 €", "1,299.90" or "12,5", and Shopify MoneyV2 objects
    function toNumber(value) {
        if (isObject(value) && hasOwn(value, 'amount')) {
            value = value.amount;
        }

        if ('string' === typeof value) {
            value = value.replace(/[^\d,.\-]/g, '');
            var lastComma = value.lastIndexOf(','), lastDot = value.lastIndexOf('.');

            if (lastComma !== -1 && lastDot !== -1) {
                value = lastComma > lastDot ? value.replace(/\./g, '').replace(',', '.') : value.replace(/,/g, '');
            } else if (lastComma !== -1) {
                value = /,\d{1,2}$/.test(value) && value.split(',').length === 2 ? value.replace(',', '.') : value.replace(/,/g, '');
            }

            if (value === '' || value === '-' || isNaN(value)) {
                return null;
            }

            value = parseFloat(value);
        }

        if ('number' !== typeof value || !isFinite(value) || Math.abs(value) > 1e12) {
            return null;
        }

        return value;
    }

    function toInt(value, min, max) {
        var number = toNumber(value);
        if (number === null) {
            return null;
        }

        number = Math.round(number);

        return number < min || number > max ? null : number;
    }

    function round2(value) {
        return Math.round(value * 100) / 100;
    }

    function toCurrency(value) {
        if (isObject(value) && hasOwn(value, 'currencyCode')) {
            value = value.currencyCode;
        }

        if ('string' !== typeof value) {
            return null;
        }

        value = value.replace(/^\s+|\s+$/g, '').toUpperCase();

        return /^[A-Z]{3}$/.test(value) ? value : null;
    }

    function toCustomerType(value) {
        if (value === true || value === 1 || value === '1') {
            return 'new';
        }

        if (value === false || value === 0 || value === '0') {
            return 'returning';
        }

        if ('string' === typeof value) {
            value = value.toLowerCase();
            if (value === 'new' || value === 'true') {
                return 'new';
            }
            if (value === 'returning' || value === 'false') {
                return 'returning';
            }
        }

        return null;
    }

    function firstDefined() {
        for (var i = 0; i < arguments.length; i++) {
            if (arguments[i] !== undefined && arguments[i] !== null && arguments[i] !== '') {
                return arguments[i];
            }
        }

        return null;
    }

    function hashString(value) {
        var hash = 0;
        for (var i = 0; i < value.length; i++) {
            hash = ((hash << 5) - hash + value.charCodeAt(i)) | 0;
        }

        return String(hash);
    }

    // Empty fields are left out of the requests, the server reads a missing field as empty
    function withoutEmpty(key, value) {
        return value === null ? undefined : value;
    }

    function stringify(value, compact) {
        try {
            return JSON.stringify(value, compact ? withoutEmpty : null);
        } catch (e) {
            return null;
        }
    }

    /*
     * Items
     */

    function splitCategories(raw) {
        var categories = [];
        var first = raw.item_category !== undefined ? raw.item_category : raw.category;

        if (isArray(first)) {
            categories = first.slice(0, 5);
        } else {
            categories = [first, raw.item_category2, raw.item_category3, raw.item_category4, raw.item_category5];
            var onlyFirst = categories[0] !== undefined && categories[1] === undefined && categories[2] === undefined
                && categories[3] === undefined && categories[4] === undefined;

            if (onlyFirst && 'string' === typeof categories[0] && categories[0].indexOf('/') !== -1) {
                categories = categories[0].split('/').slice(0, 5);
            }
        }

        var cleaned = [];
        for (var i = 0; i < categories.length; i++) {
            var category = toText(categories[i], 255);
            if (category !== null) {
                cleaned.push(category);
            }
        }

        return cleaned;
    }

    // Shopify global ids (gid://shopify/ProductVariant/123) are reduced to their number
    function cleanId(value) {
        value = toText(value, 255);
        if (value !== null && value.indexOf('gid://') === 0) {
            value = value.substring(value.lastIndexOf('/') + 1);
        }

        return value;
    }

    function normalizeItem(raw, eventFields) {
        if (!isObject(raw)) {
            return null;
        }

        var item = {};
        var id = cleanId(firstDefined(raw.item_id, raw.id, raw.sku, raw.variant_id));
        var name = toText(firstDefined(raw.item_name, raw.name, raw.title), 255);

        item.item_id = id !== null ? id : name;
        item.item_name = name !== null ? name : item.item_id;

        if (item.item_id === null) {
            return null;
        }

        var categories = splitCategories(raw);
        item.item_category = categories[0] || null;
        for (var level = 2; level <= 5; level++) {
            item['item_category' + level] = categories[level - 1] || null;
        }

        item.item_brand = toText(firstDefined(raw.item_brand, raw.brand, raw.vendor), 100);
        item.item_variant = toText(firstDefined(raw.item_variant, raw.variant), 100);
        item.item_group_id = cleanId(firstDefined(raw.item_group_id, raw.product_id));
        item.coupon = toText(raw.coupon, 100);
        item.affiliation = toText(raw.affiliation, 100);
        item.location_id = toText(raw.location_id, 100);
        item.item_list_id = toText(raw.item_list_id, 100);
        item.item_list_name = toText(firstDefined(raw.item_list_name, raw.list_name, raw.list), 100);
        item.promotion_id = toText(raw.promotion_id, 100);
        item.promotion_name = toText(raw.promotion_name, 100);
        item.creative_name = toText(firstDefined(raw.creative_name, raw.creative), 100);
        item.creative_slot = toText(firstDefined(raw.creative_slot, raw.creative_position), 100);

        var explicit = item.item_list_name !== null || item.item_list_id !== null || item.promotion_name !== null || item.promotion_id !== null;

        for (var i = 0; i < EVENT_ITEM_INHERITED.length; i++) {
            var key = EVENT_ITEM_INHERITED[i];
            if (item[key] === null && eventFields[key] !== null && eventFields[key] !== undefined) {
                item[key] = eventFields[key];
                explicit = true;
            }
        }

        item.attribution = explicit ? 1 : 0;

        var price = toNumber(raw.price);
        item.price = price;
        var discount = toNumber(raw.discount);
        item.discount = discount === null ? null : Math.abs(discount);
        var quantity = toNumber(raw.quantity);
        item.quantity = quantity === null || quantity <= 0 ? 1 : Math.min(65535, Math.max(1, Math.round(quantity)));
        item.index = toInt(firstDefined(raw.index, raw.position, raw.list_position), 0, 65535);

        var custom = {}, customCount = 0;
        for (var param in raw) {
            if (!hasOwn(raw, param) || customCount >= MAX_CUSTOM_PARAMS) {
                continue;
            }

            if (hasOwn(ITEM_STRINGS, param) || hasOwn(ITEM_KNOWN_KEYS, param) || /^item_category/.test(param)
                || !/^[A-Za-z][A-Za-z0-9_]{0,39}$/.test(param)) {
                continue;
            }

            var value = raw[param];
            if ('string' === typeof value || ('number' === typeof value && isFinite(value))) {
                value = toText(value, 100);
                if (value !== null) {
                    custom[param] = value;
                    customCount++;
                }
            }
        }

        if (customCount) {
            item.custom = custom;
        }

        return item;
    }

    function normalizeItems(rawItems, eventFields, limit) {
        var result = {items: [], dropped: 0};
        if (!isArray(rawItems)) {
            return result;
        }

        for (var i = 0; i < rawItems.length; i++) {
            if (result.items.length >= limit) {
                result.dropped++;
                continue;
            }

            var item = normalizeItem(rawItems[i], eventFields);
            if (item) {
                result.items.push(item);
            } else {
                result.dropped++;
            }
        }

        return result;
    }

    function itemsTotal(items) {
        var total = 0;
        for (var i = 0; i < items.length; i++) {
            total += (items[i].price || 0) * items[i].quantity;
        }

        return round2(total);
    }

    /*
     * Events: every supported dataLayer shape becomes {name, source, data}, data being a GA4 event object
     * (event level fields + items) with clean values.
     */

    function normalizeEventName(name) {
        if ('string' !== typeof name) {
            return null;
        }

        name = name.toLowerCase().replace(/^dl_/, '').replace(/_stape$/, '');
        if (hasOwn(EVENT_ALIASES, name)) {
            name = EVENT_ALIASES[name];
        }

        return hasOwn(EVENT_ACTIONS, name) ? name : null;
    }

    function buildEvent(name, params, source) {
        var fields = {};
        var key;

        for (key in EVENT_STRINGS) {
            if (hasOwn(EVENT_STRINGS, key)) {
                fields[key] = toText(params[key], EVENT_STRINGS[key]);
            }
        }

        if (fields.coupon !== null) {
            var codes = fields.coupon.split(',');
            var cleaned = [];
            for (var c = 0; c < codes.length; c++) {
                var code = toText(codes[c], 100);
                if (code !== null) {
                    cleaned.push(code);
                }
            }
            fields.coupon = cleaned.length ? toText(cleaned.join(','), 255) : null;
        }

        fields.currency = toCurrency(firstDefined(params.currency, params.value)) || configs.defaultCurrency;
        fields.value = toNumber(params.value);
        fields.tax = toNumber(params.tax);
        fields.shipping = toNumber(params.shipping);
        fields.discount = toNumber(params.discount);
        if (fields.discount !== null) {
            fields.discount = Math.abs(fields.discount);
        }
        fields.customer_type = toCustomerType(firstDefined(params.customer_type, params.new_customer));
        fields.customer_lifetime_value = toNumber(params.customer_lifetime_value);
        fields.checkout_step = toInt(params.checkout_step, 1, 255);

        if (fields.checkout_step === null) {
            fields.checkout_step = {begin_checkout: 1, add_shipping_info: 2, add_payment_info: 3}[name] || null;
        }

        var isList = name === 'view_item_list' || name === 'view_promotion';
        var normalized = normalizeItems(params.items, fields, isList ? MAX_LIST_ITEMS : MAX_ITEMS);

        fields.items = normalized.items;
        fields.dropped_items = normalized.dropped;
        fields.source = source;

        return {name: name, source: source, data: fields};
    }

    function fromUaProducts(products, list) {
        var items = [];
        if (!isArray(products)) {
            return items;
        }

        for (var i = 0; i < products.length; i++) {
            var product = products[i];
            if (!isObject(product)) {
                continue;
            }

            var item = {};
            for (var key in product) {
                if (hasOwn(product, key)) {
                    item[key] = product[key];
                }
            }

            if (list && item.list === undefined) {
                item.list = list;
            }

            items.push(item);
        }

        return items;
    }

    function fromUaPromotions(name, promotions) {
        var events = [];
        if (!isArray(promotions)) {
            return events;
        }

        for (var i = 0; i < promotions.length; i++) {
            var promotion = promotions[i];
            if (!isObject(promotion)) {
                continue;
            }

            events.push(buildEvent(name, {
                promotion_id: promotion.id,
                promotion_name: promotion.name,
                creative_name: promotion.creative,
                creative_slot: promotion.position
            }, SOURCE_UA));
        }

        return events;
    }

    // Universal Analytics Enhanced Ecommerce: one action per push, impressions and promotion views may come along
    function fromUa(ecommerce) {
        var events = [];
        var currency = ecommerce.currencyCode;
        var action = null, i;

        for (i = 0; i < UA_ACTIONS.length; i++) {
            if (isObject(ecommerce[UA_ACTIONS[i]])) {
                action = UA_ACTIONS[i];
                break;
            }
        }

        if (action) {
            var payload = ecommerce[action];
            var field = isObject(payload.actionField) ? payload.actionField : {};
            var list = field.list;
            var params = {currency: currency, items: fromUaProducts(payload.products, list)};

            if (action === 'purchase' || action === 'refund') {
                params.transaction_id = field.id;
                params.affiliation = field.affiliation;
                params.coupon = field.coupon;
                params.tax = field.tax;
                params.shipping = field.shipping;

                // UA revenue is the grand total, GA4 value excludes tax and shipping
                var revenue = toNumber(field.revenue);
                if (revenue !== null) {
                    params.value = round2(revenue - (toNumber(field.tax) || 0) - (toNumber(field.shipping) || 0));
                }

                events.push(buildEvent(action, params, SOURCE_UA));
            } else if (action === 'checkout' || action === 'checkout_option') {
                var step = toInt(field.step, 1, 255);
                params.checkout_step = step;
                params.checkout_option = field.option;
                var name = 'checkout_progress';

                if (step === 1 && action === 'checkout') {
                    name = 'begin_checkout';
                } else if (step === 2) {
                    name = 'add_shipping_info';
                    params.shipping_tier = field.option;
                } else if (step === 3) {
                    name = 'add_payment_info';
                    params.payment_type = field.option;
                }

                events.push(buildEvent(name, params, SOURCE_UA));
            } else if (action === 'promoClick') {
                events = events.concat(fromUaPromotions('select_promotion', payload.promotions));
            } else {
                var names = {add: 'add_to_cart', remove: 'remove_from_cart', detail: 'view_item', click: 'select_item'};
                params.item_list_name = list;
                events.push(buildEvent(names[action], params, SOURCE_UA));
            }
        }

        if (isArray(ecommerce.impressions) && ecommerce.impressions.length) {
            var byList = {}, order = [];
            for (i = 0; i < ecommerce.impressions.length; i++) {
                var impression = ecommerce.impressions[i];
                var listName = isObject(impression) && impression.list ? String(impression.list) : '';
                if (!hasOwn(byList, listName)) {
                    byList[listName] = [];
                    order.push(listName);
                }
                byList[listName].push(impression);
            }

            for (i = 0; i < order.length; i++) {
                events.push(buildEvent('view_item_list', {
                    currency: currency,
                    item_list_name: order[i] || null,
                    items: fromUaProducts(byList[order[i]])
                }, SOURCE_UA));
            }
        }

        if (isObject(ecommerce.promoView)) {
            events = events.concat(fromUaPromotions('view_promotion', ecommerce.promoView.promotions));
        }

        return events;
    }

    function isUaEcommerce(ecommerce) {
        if (isArray(ecommerce.impressions) || isObject(ecommerce.promoView)) {
            return true;
        }

        for (var i = 0; i < UA_ACTIONS.length; i++) {
            if (isObject(ecommerce[UA_ACTIONS[i]])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Turns one dataLayer entry into zero, one or several ecommerce events. Pure function, exposed for tests.
     */
    function parseEntry(entry) {
        if (isArgumentsLike(entry)) {
            if (entry[0] === 'set' && isObject(entry[1]) && toCurrency(entry[1].currency)) {
                configs.defaultCurrency = toCurrency(entry[1].currency);
                return [];
            }

            var gtagName = entry[0] === 'event' ? normalizeEventName(entry[1]) : null;

            return gtagName ? [buildEvent(gtagName, isObject(entry[2]) ? entry[2] : {}, SOURCE_GTAG)] : [];
        }

        if (!isObject(entry) || isArray(entry)) {
            return [];
        }

        var ecommerce = entry.ecommerce;

        if (isObject(ecommerce) && isUaEcommerce(ecommerce)) {
            return fromUa(ecommerce);
        }

        var name = normalizeEventName(entry.event);
        if (!name) {
            return [];
        }

        if (isObject(ecommerce)) {
            return [buildEvent(name, ecommerce, SOURCE_GA4)];
        }

        if (isObject(entry.eventModel)) {
            return [buildEvent(name, entry.eventModel, SOURCE_GTAG)];
        }

        // Some custom setups carry their parameters at the root of the push
        return [buildEvent(name, entry, SOURCE_GA4)];
    }

    /*
     * Storage: sessionStorage while the tracker may use cookies, memory only otherwise
     */

    function canStore(tracker) {
        try {
            return !!window.sessionStorage && (!tracker.areCookiesEnabled || tracker.areCookiesEnabled());
        } catch (e) {
            return false;
        }
    }

    function load(tracker, key, fallback) {
        var fullKey = 'mtm_ee_' + key + '_' + tracker.getSiteId();

        if (canStore(tracker)) {
            try {
                var stored = window.sessionStorage.getItem(fullKey);
                if (stored) {
                    return JSON.parse(stored);
                }
            } catch (e) {
                // corrupted or blocked storage, start from scratch
            }
        }

        return hasOwn(memory, fullKey) ? memory[fullKey] : fallback;
    }

    function save(tracker, key, value) {
        var fullKey = 'mtm_ee_' + key + '_' + tracker.getSiteId();
        memory[fullKey] = value;

        if (canStore(tracker)) {
            try {
                window.sessionStorage.setItem(fullKey, JSON.stringify(value));
            } catch (e) {
                // storage full or blocked, memory copy is enough for this page
            }
        }
    }

    function lineKey(item) {
        return item.item_id + '|' + (item.item_variant || '');
    }

    /*
     * Attribution: select_item and select_promotion are remembered, so the item keeps its list and promotion when
     * it is later added to cart and bought, as in GA4.
     */

    // Maps keyed by item id use a prefix: an item id such as __proto__ must stay an ordinary key
    function itemKey(item) {
        return 'i:' + item.item_id;
    }

    function rememberSelection(tracker, data) {
        var remembered = load(tracker, 'sel', {});
        var keys = [], key;

        for (var i = 0; i < data.items.length; i++) {
            var item = data.items[i];
            remembered[itemKey(item)] = {
                item_list_id: item.item_list_id,
                item_list_name: item.item_list_name,
                promotion_id: item.promotion_id,
                promotion_name: item.promotion_name,
                creative_name: item.creative_name,
                creative_slot: item.creative_slot,
                t: new Date().getTime()
            };
        }

        for (key in remembered) {
            if (hasOwn(remembered, key)) {
                keys.push(key);
            }
        }

        if (keys.length > MAX_REMEMBERED) {
            keys.sort(function (a, b) {
                return remembered[a].t - remembered[b].t;
            });
            for (i = 0; i < keys.length - MAX_REMEMBERED; i++) {
                delete remembered[keys[i]];
            }
        }

        save(tracker, 'sel', remembered);
    }

    function applySelection(tracker, items) {
        var remembered = load(tracker, 'sel', {});

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var selection = hasOwn(remembered, itemKey(item)) ? remembered[itemKey(item)] : null;
            if (!selection || item.attribution === 1) {
                continue;
            }

            var applied = false;
            for (var j = 0; j < EVENT_ITEM_INHERITED.length; j++) {
                var key = EVENT_ITEM_INHERITED[j];
                if (item[key] === null && selection[key]) {
                    item[key] = selection[key];
                    applied = true;
                }
            }

            if (applied) {
                item.attribution = 2;
            }
        }
    }

    /*
     * Sending
     */

    function getTrackers() {
        if (!window.Matomo || !window.Matomo.getAsyncTrackers) {
            return [];
        }

        return window.Matomo.getAsyncTrackers();
    }

    function encode(value) {
        return encodeURIComponent(value);
    }

    // The server reads at most 128 KB of payload: the last items are dropped, and counted, until it fits
    var MAX_PAYLOAD_BYTES = 120000;

    function byteLength(text) {
        return unescape(encodeURIComponent(text)).length;
    }

    function fitPayload(data) {
        var json = stringify(data, true);
        if (!data || !data.items || byteLength(json) <= MAX_PAYLOAD_BYTES) {
            return json;
        }

        var fitted = {};
        for (var key in data) {
            if (hasOwn(data, key)) {
                fitted[key] = data[key];
            }
        }

        fitted.items = data.items.slice();
        while (fitted.items.length && byteLength(json) > MAX_PAYLOAD_BYTES) {
            var drop = Math.max(1, Math.ceil(fitted.items.length / 10));
            fitted.items = fitted.items.slice(0, fitted.items.length - drop);
            fitted.dropped_items = (data.dropped_items || 0) + data.items.length - fitted.items.length;
            json = stringify(fitted, true);
        }

        return json;
    }

    function buildPayloadParams(name, data) {
        var json = data ? fitPayload(data) : null;

        return 'eex_v=' + PAYLOAD_VERSION + '&eex_e=' + encode(name) + (json ? '&eex_d=' + encode(json) : '');
    }

    function sendEvent(tracker, name, data, withPayload) {
        var request = 'e_c=' + encode(EVENT_CATEGORY) + '&e_a=' + encode(EVENT_ACTIONS[name]) + '&e_n=' + encode(name);

        if (data && data.value !== null && data.value !== undefined) {
            request += '&e_v=' + encode(String(round2(data.value)));
        }

        tracker.trackRequest(request + '&' + buildPayloadParams(name, withPayload ? data : null), null, null, 'event');
    }

    var pendingEcommerceParams = null;

    // The native ecommerce request is built by the tracker; the payload is added through the "ecommerce" hook
    function withEcommerceParams(params, callback) {
        pendingEcommerceParams = params;
        try {
            callback();
        } finally {
            pendingEcommerceParams = null;
        }
    }

    // The native cart keys lines by SKU: lines sharing a SKU (variants) are merged, the variant detail is kept in
    // the payload
    function addNativeItems(tracker, items) {
        var merged = {}, order = [], i;

        for (i = 0; i < items.length; i++) {
            var item = items[i];
            var key = itemKey(item);
            if (!hasOwn(merged, key)) {
                merged[key] = {item: item, quantity: 0, amount: 0};
                order.push(key);
            }

            merged[key].quantity += item.quantity;
            merged[key].amount += (item.price || 0) * item.quantity;
        }

        tracker.clearEcommerceCart();

        for (i = 0; i < order.length; i++) {
            var line = merged[order[i]];
            var categories = [];
            for (var level = 1; level <= 5; level++) {
                var category = line.item[level === 1 ? 'item_category' : 'item_category' + level];
                if (category) {
                    categories.push(category);
                }
            }

            tracker.addEcommerceItem(line.item.item_id, line.item.item_name || '', categories.length ? categories : '', round2(line.amount / line.quantity), line.quantity);
        }
    }

    function updateNativeCart(tracker, name, cart, data) {
        addNativeItems(tracker, cart);

        var snapshot = {source: data.source, currency: data.currency, items: cart};

        withEcommerceParams(buildPayloadParams(name, snapshot), function () {
            tracker.trackEcommerceCartUpdate(itemsTotal(cart));
        });

        save(tracker, 'cart', cart);
    }

    function changeCart(tracker, name, data) {
        var cart = load(tracker, 'cart', []);
        var sign = name === 'add_to_cart' ? 1 : -1;

        for (var i = 0; i < data.items.length; i++) {
            var item = data.items[i];
            var found = -1;

            for (var j = 0; j < cart.length; j++) {
                if (lineKey(cart[j]) === lineKey(item)) {
                    found = j;
                    break;
                }
            }

            if (found === -1 && sign > 0) {
                cart.push(item);
            } else if (found !== -1) {
                cart[found].quantity += sign * item.quantity;
                if (sign > 0 && item.price !== null) {
                    cart[found].price = item.price;
                }
                if (cart[found].quantity <= 0) {
                    cart.splice(found, 1);
                }
            }
        }

        updateNativeCart(tracker, name, cart, data);
    }

    // view_cart and begin_checkout list the whole cart: the mirror is resynchronised when it drifted
    function resyncCart(tracker, name, data) {
        if (!data.items.length) {
            return;
        }

        var cart = load(tracker, 'cart', []);
        var current = stringify(cart), next = stringify(data.items);

        if (current !== next) {
            updateNativeCart(tracker, name, data.items, data);
        }
    }

    function rememberCheckoutChoices(tracker, data) {
        var choices = load(tracker, 'checkout', {});

        if (data.shipping_tier) {
            choices.shipping_tier = data.shipping_tier;
        }

        if (data.payment_type) {
            choices.payment_type = data.payment_type;
        }

        save(tracker, 'checkout', choices);
    }

    // Same consent rule as the other storage: without cookie consent the list lives in the page only
    function isNewTransaction(tracker, transactionId) {
        var key = 'mtm_ee_tx';
        var hash = hashString(transactionId);
        var persistent = false;
        try {
            persistent = !!window.localStorage && (!tracker.areCookiesEnabled || tracker.areCookiesEnabled());
        } catch (e) {
            persistent = false;
        }

        var list = memory[key] || [];
        if (persistent) {
            try {
                list = JSON.parse(window.localStorage.getItem(key) || '[]') || [];
            } catch (e) {
                list = memory[key] || [];
            }
        }

        for (var i = 0; i < list.length; i++) {
            if (list[i] === hash) {
                return false;
            }
        }

        list.push(hash);
        list = list.slice(-MAX_TRANSACTIONS);
        memory[key] = list;

        if (persistent) {
            try {
                window.localStorage.setItem(key, JSON.stringify(list));
            } catch (e) {
                // the memory copy still prevents a resend on this page
            }
        }

        return true;
    }

    function trackPurchase(tracker, data) {
        if (!data.transaction_id || !isNewTransaction(tracker, tracker.getSiteId() + '|' + data.transaction_id)) {
            return;
        }

        var choices = load(tracker, 'checkout', {});
        data.shipping_tier = data.shipping_tier || choices.shipping_tier || null;
        data.payment_type = data.payment_type || choices.payment_type || null;

        var subtotal = data.value !== null ? data.value : itemsTotal(data.items);
        var tax = data.tax || 0;
        var shipping = data.shipping || 0;
        var discount = data.discount;

        if (discount === null) {
            discount = 0;
            for (var i = 0; i < data.items.length; i++) {
                discount += (data.items[i].discount || 0) * data.items[i].quantity;
            }
        }

        addNativeItems(tracker, data.items);

        var purchase = {};
        for (var key in data) {
            if (hasOwn(data, key) && key !== 'transaction_id') {
                purchase[key] = data[key];
            }
        }

        withEcommerceParams(buildPayloadParams('purchase', purchase), function () {
            tracker.trackEcommerceOrder(data.transaction_id, round2(subtotal + tax + shipping), round2(subtotal), tax ? round2(tax) : false, shipping ? round2(shipping) : false, discount ? round2(discount) : false);
        });

        save(tracker, 'cart', []);
        save(tracker, 'checkout', {});
        sendEvent(tracker, 'purchase', data, false);
    }

    function trackForTracker(tracker, event) {
        var name = event.name;
        var data = event.data;

        if (name === 'select_item' || name === 'select_promotion') {
            rememberSelection(tracker, data);
        }

        if (name === 'add_to_cart' || name === 'remove_from_cart' || name === 'begin_checkout' || name === 'add_shipping_info'
            || name === 'add_payment_info' || name === 'purchase' || name === 'view_cart') {
            applySelection(tracker, data.items);
        }

        if (name === 'add_shipping_info' || name === 'add_payment_info' || name === 'checkout_progress') {
            rememberCheckoutChoices(tracker, data);
        }

        if (name === 'purchase') {
            trackPurchase(tracker, data);
            return;
        }

        sendEvent(tracker, name, data, true);

        if (name === 'add_to_cart' || name === 'remove_from_cart') {
            changeCart(tracker, name, data);
        } else if (name === 'view_cart' || name === 'begin_checkout') {
            resyncCart(tracker, name, data);
        }
    }

    function isDuplicatePush(event) {
        var key = event.name + stringify(event.data);
        var now = new Date().getTime();
        var duplicate = key === lastPush.key && now - lastPush.time < DUPLICATE_WINDOW_MS;

        lastPush.key = key;
        lastPush.time = now;

        return duplicate;
    }

    var queue = [];
    var flushScheduled = false;
    var flushAttempts = 0;
    var MAX_FLUSH_ATTEMPTS = 100;

    // Events read before the tracker exists wait for it, up to 10 seconds; a later push retries
    function flush() {
        var trackers = getTrackers();
        if (!trackers.length) {
            if (!flushScheduled && flushAttempts < MAX_FLUSH_ATTEMPTS) {
                flushScheduled = true;
                flushAttempts++;
                setTimeout(function () {
                    flushScheduled = false;
                    flush();
                }, 100);
            }
            return;
        }

        flushAttempts = 0;

        while (queue.length) {
            var event = queue.shift();
            for (var i = 0; i < trackers.length; i++) {
                // each tracker gets its own copy, a tracker adds remembered attribution to it
                trackForTracker(trackers[i], {name: event.name, data: JSON.parse(stringify(event.data))});
            }
        }
    }

    function handleEntry(entry) {
        var events;
        try {
            events = parseEntry(entry);
        } catch (e) {
            return;
        }

        for (var i = 0; i < events.length; i++) {
            if (!isDuplicatePush(events[i])) {
                queue.push(events[i]);
            }
        }

        if (queue.length) {
            flushAttempts = 0;
            flush();
        }
    }

    function init() {
        if ('object' === typeof window && 'object' === typeof window.Matomo && 'object' === typeof window.Matomo.EcommerceEnhanced) {
            return;
        }

        if ('object' === typeof window && !window.Matomo) {
            return;
        }

        Matomo.EcommerceEnhanced = {
            /**
             * Starts reading the dataLayer: entries already pushed are replayed, later pushes are read as they come.
             *
             * In Matomo Tag Manager ({tagManager: true}) the container data layer is read instead: it holds the _mtm
             * pushes, and the dataLayer pushes when the container synchronises the Google Tag Manager data layer.
             * Reading one source only means a push is never counted twice.
             *
             * @param {{dataLayerName?: string, tagManager?: boolean}} [options]
             */
            enable: function (options) {
                if (configs.enabled) {
                    return;
                }

                if (isObject(options) && 'string' === typeof options.dataLayerName && /^[A-Za-z_$][\w$]*$/.test(options.dataLayerName)) {
                    configs.dataLayerName = options.dataLayerName;
                }

                configs.enabled = true;

                var tagManager = isObject(options) && options.tagManager && window.MatomoTagManager && window.MatomoTagManager.dataLayer;
                if (tagManager && typeof tagManager.getAllEvents === 'function' && typeof tagManager.on === 'function') {
                    var events = tagManager.getAllEvents();
                    for (var e = 0; e < events.length; e++) {
                        handleEntry(events[e]);
                    }

                    tagManager.on(handleEntry);
                    return;
                }

                var dataLayer = window[configs.dataLayerName] = window[configs.dataLayerName] || [];
                for (var i = 0; i < dataLayer.length; i++) {
                    handleEntry(dataLayer[i]);
                }

                var originalPush = dataLayer.push;
                dataLayer.push = function () {
                    var result = originalPush.apply(dataLayer, arguments);
                    for (var j = 0; j < arguments.length; j++) {
                        handleEntry(arguments[j]);
                    }

                    return result;
                };
            },

            /**
             * Records one GA4 ecommerce event without a dataLayer, for example
             * _paq.push(['EcommerceEnhanced::track', 'add_to_cart', {currency: 'EUR', value: 20, items: [...]}]).
             */
            track: function (eventName, params) {
                var name = normalizeEventName(eventName);
                if (!name) {
                    return;
                }

                var event = buildEvent(name, isObject(params) ? params : {}, SOURCE_API);
                if (!isDuplicatePush(event)) {
                    queue.push(event);
                    flush();
                }
            },

            /**
             * Records an object shaped like a dataLayer push (GA4, gtag arguments or Universal Analytics), without
             * a dataLayer, for example _paq.push(['EcommerceEnhanced::push', {event: 'purchase', ecommerce: {...}}]).
             */
            push: function (entry) {
                handleEntry(entry);
            },

            /**
             * Currency used when an event has none, like gtag('set', {currency: 'EUR'}).
             */
            setCurrency: function (currency) {
                configs.defaultCurrency = toCurrency(currency);
            },

            parse: parseEntry
        };

        Matomo.addPlugin('EcommerceEnhanced', {
            ecommerce: function () {
                return pendingEcommerceParams ? '&' + pendingEcommerceParams : '';
            }
        });

        if (Matomo.retryMissedPluginCalls) {
            Matomo.retryMissedPluginCalls();
        }
    }

    if ('object' === typeof window.Matomo) {
        init();
    } else {
        if ('object' !== typeof window.matomoPluginAsyncInit) {
            window.matomoPluginAsyncInit = [];
        }

        window.matomoPluginAsyncInit.push(init);
    }

})();
