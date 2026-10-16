# Ecommerce Enhanced

## Description

GA4 ecommerce for Matomo. Ecommerce Enhanced reads the GA4 ecommerce events your shop already sends to its dataLayer and records them in Matomo, on top of the native Matomo ecommerce:

- every shopping step (list views, product views, adds to cart, checkout steps, purchases, refunds, promotions) is tracked as a Matomo event and stored with its full GA4 detail;
- carts and orders are sent as native Matomo ecommerce requests, so the native Ecommerce reports keep working;
- brands, variants, item lists, promotions, coupons, payment types, shipping tiers and customer types get their own reports, segments and Custom Reports dimensions;
- shopping and checkout behavior funnels are built in, without the Funnels plugin.

No Tag Manager is needed, and no connector per CMS: one line in your tracking code is enough.

### Why the GA4 format

GA4 ecommerce is the most widely used ecommerce dataLayer format. Most shop platforms and their extensions already emit it (WooCommerce with GTM4WP or Google Analytics for WooCommerce, Shopify, Magento, PrestaShop, Elevar, Stape and many more). Reading it means your shop does not need any Matomo specific development. The legacy Universal Analytics Enhanced Ecommerce format and `gtag()` calls are read as well.

Official GA4 references:

- [Measure ecommerce](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?client_type=gtm)
- [Recommended events reference](https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtag)

## Installation

1. Install and activate the plugin, then enable Ecommerce for your website (Administration, Websites, Manage).
2. Add one line to your Matomo tracking code, before `trackPageView`:

```js
var _paq = window._paq = window._paq || [];
_paq.push(['EcommerceEnhanced::enable']);
_paq.push(['trackPageView']);
// ... the rest of your tracking code
```

The tracker replays the ecommerce events already in the dataLayer, then reads every new push. If your dataLayer has another name:

```js
_paq.push(['EcommerceEnhanced::enable', {dataLayerName: 'myDataLayer'}]);
```

### Without a dataLayer

Every event can be sent from JavaScript instead, with the same GA4 fields:

```js
_paq.push(['EcommerceEnhanced::track', 'add_to_cart', {
  currency: 'EUR',
  value: 30.00,
  items: [{item_id: 'SKU_123', item_name: 'Organic tee', item_variant: 'Red', price: 30.00, quantity: 1}]
}]);
```

An object shaped like a dataLayer push (GA4, Universal Analytics or `gtag()` arguments) can also be passed as is:

```js
_paq.push(['EcommerceEnhanced::push', {event: 'purchase', ecommerce: { /* GA4 purchase fields */ }}]);
```

`_paq.push(['EcommerceEnhanced::setCurrency', 'EUR'])` sets the currency used when an event has none, like `gtag('set', {currency: 'EUR'})`.

## Expected dataLayer pushes

Clear the previous ecommerce object before each push, as GA4 recommends: `dataLayer.push({ecommerce: null});`. Ecommerce Enhanced reads each push on its own, the reset is harmless.

### Item fields

Every `items` entry can carry these fields. `item_id` or `item_name` is required.

| Field | Read as |
|---|---|
| `item_id` | product SKU (also `id`, `sku`, `variant_id`) |
| `item_name` | product name (also `name`, `title`) |
| `item_brand` | brand |
| `item_category` to `item_category5` | categories (a single `"A/B/C"` category is split, as in Universal Analytics) |
| `item_variant` | variant, kept apart even when two variants share a SKU |
| `price` | unit price, already discounted |
| `quantity` | quantity, 1 when missing |
| `discount` | unit discount |
| `coupon` | item coupon |
| `item_list_id`, `item_list_name`, `index` | list the item was shown or selected in, and its position |
| `promotion_id`, `promotion_name`, `creative_name`, `creative_slot` | promotion the item came from |
| `affiliation`, `location_id`, `item_group_id` | supplier or store, physical location, parent product |
| any other field | custom item parameter, recorded when its name is listed in the website settings (up to 10) |

Item list and promotion fields set on the event apply to the items that lack them. When an item is selected in a list or a promotion (`select_item`, `select_promotion`), it keeps that list or promotion when it is later added to cart and bought.

### view_item_list

```js
dataLayer.push({
  event: 'view_item_list',
  ecommerce: {
    item_list_id: 'related_products',
    item_list_name: 'Related products',
    items: [
      {item_id: 'SKU_123', item_name: 'Organic tee', item_brand: 'Lumen', item_category: 'Apparel', price: 30.00, index: 1}
    ]
  }
});
```

Read: `item_list_id`, `item_list_name`, items. The first 30 items of a list are recorded.

### select_item

```js
dataLayer.push({
  event: 'select_item',
  ecommerce: {
    item_list_id: 'related_products',
    item_list_name: 'Related products',
    items: [{item_id: 'SKU_123', item_name: 'Organic tee', index: 1}]
  }
});
```

Read: `item_list_id`, `item_list_name`, items. The list is remembered for the item.

### view_item

```js
dataLayer.push({
  event: 'view_item',
  ecommerce: {
    currency: 'EUR',
    value: 30.00,
    items: [{item_id: 'SKU_123', item_name: 'Organic tee', item_brand: 'Lumen', item_category: 'Apparel', item_variant: 'Red', price: 30.00}]
  }
});
```

Read: `currency`, `value`, items. When it is pushed before the first page view, the native Matomo product view is also set.

### add_to_wishlist

```js
dataLayer.push({
  event: 'add_to_wishlist',
  ecommerce: {currency: 'EUR', value: 30.00, items: [{item_id: 'SKU_123', item_name: 'Organic tee', price: 30.00}]}
});
```

Read: `currency`, `value`, items.

### add_to_cart and remove_from_cart

```js
dataLayer.push({
  event: 'add_to_cart',
  ecommerce: {
    currency: 'EUR',
    value: 60.00,
    items: [{item_id: 'SKU_123', item_name: 'Organic tee', item_variant: 'Red', price: 30.00, quantity: 2}]
  }
});
```

Read: `currency`, `value`, items. The plugin keeps the cart content for the visit and sends the native Matomo cart update, so abandoned carts are reported natively. `remove_from_cart` has the same shape.

### view_cart

```js
dataLayer.push({
  event: 'view_cart',
  ecommerce: {currency: 'EUR', value: 60.00, items: [/* every item of the cart */]}
});
```

Read: `currency`, `value`, items. The items list the whole cart: the cart kept by the plugin is corrected if it differs.

### begin_checkout

```js
dataLayer.push({
  event: 'begin_checkout',
  ecommerce: {currency: 'EUR', value: 60.00, coupon: 'SUMMER10', items: [/* every item of the cart */]}
});
```

Read: `currency`, `value`, `coupon`, items. Checkout step 1.

### add_shipping_info

```js
dataLayer.push({
  event: 'add_shipping_info',
  ecommerce: {currency: 'EUR', value: 60.00, shipping_tier: 'Express', items: [/* cart items */]}
});
```

Read: `currency`, `value`, `coupon`, `shipping_tier`, items. Checkout step 2. The shipping tier is copied to the order when the purchase event lacks it.

### add_payment_info

```js
dataLayer.push({
  event: 'add_payment_info',
  ecommerce: {currency: 'EUR', value: 60.00, payment_type: 'Credit card', items: [/* cart items */]}
});
```

Read: `currency`, `value`, `coupon`, `payment_type`, items. Checkout step 3. The payment type is copied to the order when the purchase event lacks it.

### purchase

```js
dataLayer.push({
  event: 'purchase',
  ecommerce: {
    transaction_id: 'T_12345',
    currency: 'EUR',
    value: 60.00,
    tax: 12.00,
    shipping: 4.90,
    coupon: 'SUMMER10',
    affiliation: 'Online store',
    payment_type: 'Credit card',
    shipping_tier: 'Express',
    customer_type: 'new',
    items: [
      {item_id: 'SKU_123', item_name: 'Organic tee', item_brand: 'Lumen', item_variant: 'Red', price: 30.00, quantity: 1},
      {item_id: 'SKU_123', item_name: 'Organic tee', item_brand: 'Lumen', item_variant: 'Blue', price: 30.00, quantity: 1}
    ]
  }
});
```

Read: `transaction_id` (required), `currency`, `value`, `tax`, `shipping`, `discount`, `coupon` (several codes separated by commas), `affiliation`, `payment_type`, `shipping_tier`, `customer_type` (`new` or `returning`, the Google Ads `new_customer` flag is read too), `customer_lifetime_value`, items.

The native Matomo order is recorded with a revenue of `value + tax + shipping`, as GA4 defines `value` without tax and shipping. If your shop sends the grand total in `value`, check "The GA4 value includes tax and shipping" in the website settings. A purchase is recorded once: reloading the confirmation page does not record it again.

### refund

```js
dataLayer.push({
  event: 'refund',
  ecommerce: {
    transaction_id: 'T_12345',
    currency: 'EUR',
    value: 30.00,
    items: [{item_id: 'SKU_123', item_variant: 'Red', price: 30.00, quantity: 1}]
  }
});
```

Read: `transaction_id`, `currency`, `value`, `tax`, `shipping`, items (partial refund). The refund is matched to its order, even when Matomo anonymises order ids.

### view_promotion and select_promotion

```js
dataLayer.push({
  event: 'view_promotion',
  ecommerce: {
    promotion_id: 'summer_sale',
    promotion_name: 'Summer sale',
    creative_name: 'summer_banner',
    creative_slot: 'home_hero',
    items: [{item_id: 'SKU_123', item_name: 'Organic tee'}]
  }
});
```

Read: `promotion_id`, `promotion_name`, `creative_name`, `creative_slot`, items. `select_promotion` has the same shape, and the promotion is remembered for its items.

### view_search_results

```js
dataLayer.push({
  event: 'view_search_results',
  ecommerce: {search_term: 'tee', items: [/* products listed */]}
});
```

Read: `search_term`, items.

### Leads

`generate_lead`, `qualify_lead`, `working_lead`, `close_convert_lead`, `close_unconvert_lead` and `disqualify_lead` are recorded with `currency`, `value`, `lead_source` and `lead_status` (or `unconvert_lead_reason`, `disqualified_lead_reason`). Their parameters can sit at the root of the push.

### Other formats

- **gtag:** `gtag('event', 'purchase', {...})` calls are read like the GA4 pushes above, `gtag('set', {currency: 'EUR'})` sets the default currency.
- **Universal Analytics Enhanced Ecommerce:** `impressions`, `click`, `detail`, `add`, `remove`, `checkout` (step 1 begin checkout, 2 shipping, 3 payment), `checkout_option`, `purchase`, `refund`, `promoView` and `promoClick` are converted to their GA4 events. UA `revenue` is the grand total and is converted to the GA4 `value`.
- Event names prefixed with `dl_` (Elevar) or suffixed with `_stape` (Stape) are recognised.

## What is never recorded

`user_data`, emails, phone numbers, names, addresses and hashed identifiers are never read, nor any item field whose name suggests personal data. Single use coupon codes can identify a person: the website settings can store a hash of them, or nothing.

## Website settings

Administration, Websites, Manage, then the Ecommerce Enhanced section of a website:

- **The GA4 value includes tax and shipping:** for shops that send the grand total in `value`.
- **Ecommerce steps shown in the Events report:** each step is a Matomo event with the category `Ecommerce`, a readable action (`Add to cart`) and the GA4 event name as the name (`add_to_cart`). Unchecked steps are still recorded in the Ecommerce Enhanced reports.
- **Steps that do not count as an interaction:** viewing steps (`view_item_list`, `view_item`, `view_cart`, `view_promotion`, `view_search_results`) do not lower the bounce rate by default, as in GA4.
- **Coupon codes:** store them, a hash of them, or nothing.
- **Custom item parameters:** up to 10 item parameter names to record, for example `size` or `color`. Only add names at the end of the list.

## Reports

In the Ecommerce menu:

- **Shopping behavior:** visits that viewed a product, added to cart, began checkout and bought; visits through the checkout steps, with step and abandonment rates.
- **Merchandising:** products, brands, variants, item lists and promotions with views, adds to cart, purchases, item revenue and rates.
- **Order details:** orders and revenue by coupon, payment type, shipping tier, customer type, affiliation and currency.

## Segments

Every stored field is a segment, also available in Custom Reports:

- order: `eeOrderCoupon`, `eeOrderPaymentType`, `eeOrderShippingTier`, `eeOrderCustomerType`, `eeOrderCurrency`, `eeOrderAffiliation`;
- cart and order lines: `eeItemBrand`, `eeItemVariant`, `eeItemListId`, `eeItemListName`, `eeItemCoupon`, `eeItemDiscount`, `eeItemAffiliation`, `eeItemLocationId`;
- shopping steps: `eeStep` (GA4 event name), `eeListId`, `eeListName`, `eeCheckoutStep`, `eeCheckoutOption`, `eeSearchTerm`, `eePromotionId`, `eePromotionName`, `eePromotionCreativeName`, `eePromotionCreativeSlot`.

For example `eeStep==add_to_cart;eeStep!=purchase` selects the visits that added to cart without buying.

## Deactivating the plugin

The native Matomo ecommerce keeps working: carts and orders are always sent as native requests. While the plugin is deactivated its data is kept, but GDPR deletions and the deletion of old raw data no longer cover it. Uninstall the plugin to delete its data.

## Large databases

Installing the plugin adds six empty columns to the `log_conversion` table. On MySQL 8.0.12+ and MariaDB 10.3.2+ this is instant. On older versions the table is rebuilt: run `./console core:update` from the command line on large databases.

## Support

Openmost, [ronan@openmost.com](mailto:ronan@openmost.com).

## License

GPL v3 or later.
