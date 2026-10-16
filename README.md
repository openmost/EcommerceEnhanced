# Ecommerce Enhanced

## Description

**Get GA4 grade ecommerce analytics in Matomo, without changing a line of your shop.**

Your shop already describes every product view, add to cart, checkout step and order in its GA4 dataLayer. Native Matomo ecommerce only keeps the order and a few product fields. Ecommerce Enhanced reads the whole GA4 dataLayer and turns it into the reports GA4 users rely on: product and brand performance, item lists, promotions, coupons, shopping and checkout funnels, sales by source / medium, buyers and revenue per user. Your data stays in your Matomo, with no sampling.

One line in your tracking code, or one checkbox in Matomo Tag Manager, and it works with WooCommerce, Shopify, Magento, PrestaShop and every shop that speaks GA4.

### What you get for free

**Collection that just works**

- Reads GA4 dataLayer pushes, `gtag()` calls and legacy Universal Analytics Enhanced Ecommerce, from any CMS or extension (GTM4WP, Google Analytics for WooCommerce, Shopify, Magento, PrestaShop, Elevar, Stape...).
- Works without Tag Manager (`_paq.push(['EcommerceEnhanced::enable'])`), with a checkbox in the Matomo Tag Manager "Matomo Configuration" variable, or from JavaScript with `_paq.push(['EcommerceEnhanced::track', ...])`.
- Every GA4 ecommerce event: item list views and clicks, product views, wishlist, adds and removals from cart, cart views, checkout, shipping and payment steps, purchases, refunds, promotion views and clicks, product searches and leads.
- Native Matomo carts and orders are still sent, so the Ecommerce reports, goals and abandoned carts you know keep working, even if you deactivate the plugin.
- Variants kept apart, even when they share a SKU, with brand, list, promotion, coupon, discount and up to 10 custom item parameters per product line.
- Item lists and promotions remembered from the click to the order, as GA4 attributes them.
- Duplicate orders blocked, even when the confirmation page is reloaded or order ids are anonymised.
- Privacy by design: emails, phone numbers, addresses and hashed identifiers are never read, coupon codes can be hashed or skipped.

**Reports GA4 users expect**

- Shopping behavior and checkout behavior funnels, with step and abandonment rates, without the Funnels plugin.
- Product performance, brands, variants and product categories: views, adds to cart, purchases, item revenue, cart to view and purchase to view rates.
- Item lists and promotions: views, clicks, click rate, and the items added to cart and bought after them.
- Product search terms: what visitors searched and what they viewed, added and bought next.
- Order details: orders, revenue and average order value by coupon, payment type, shipping tier, customer type (new or returning), affiliation and currency.
- Transactions: every order with its revenue, tax, shipping, items, coupon, payment and shipping choices.
- Sales by source / medium, the way GA4 shows it: `google / organic`, `newsletter / email`, `(direct) / (none)`.
- Buyers and revenue: purchasers, first time purchasers, revenue per user (ARPU) and per paying user (ARPPU), average order value and items per order, conversion rate and cart abandonment rate.
- Every step also appears in the Events report (category "Ecommerce"), with viewing steps kept out of the bounce rate, as in GA4.

**Native Matomo integration**

- 24 new segments (`eeItemBrand`, `eeOrderCoupon`, `eeStep`, `eePromotionName`...), for example "visits that added to cart without buying": `eeStep==add_to_cart;eeStep!=purchase`.
- Every field available in Custom Reports, the segment editor, the API, scheduled reports and dashboards.
- GDPR tools (data subject export and deletion) and the deletion of old raw data cover every stored row.

### What Premium adds

Every Premium report is visible in the free edition with a preview, so you know what you get. Premium shows them with your data, from the data already collected: no new tracking, no history lost, no report to rebuild.

- **Active users and buyers (Premium):** 1, 7 and 30 day active users and buyers (DAU, WAU, MAU) and the DAU / MAU stickiness ratio.
- **Customers (Premium):** repeat purchase rate, time between purchases, retention cohorts by first purchase month, customer lifetime value and RFM segments.
- **Acquisition (Premium):** sales by first user source, time and visits from the first visit to the first purchase, abandoned carts by channel.
- **Shopping behavior (Premium):** the shopping and checkout funnels split by device and by channel, and the lead pipeline from `generate_lead` to `close_convert_lead`.
- **Merchandising (Premium):** products bought together (market basket analysis), most abandoned products, product profit and margin from your cost of goods, reports and segments for your custom item parameters.
- **Orders (Premium):** refunds, refund rate by product and net revenue, discount depth by product, coupon and category, orders by basket size and value, revenue of foreign currency orders converted to your website currency.

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

- **Overview:** buyers and revenue (purchasers, first time purchasers, ARPU, ARPPU, average order value, items per order, conversion rate, cart abandonment rate), as sparklines and over time.
- **Shopping behavior:** visits that viewed a product, added to cart, began checkout and bought; visits through the checkout steps, with step and abandonment rates.
- **Merchandising:** products, brands, variants, product categories, item lists, promotions and product search terms with views, wishlist and cart adds, purchases, item revenue and rates.
- **Order details:** transactions (every order with its revenue split and GA4 details), and orders and revenue by coupon, payment type, shipping tier, customer type, affiliation and currency.
- **Sales:** sales by source / medium, next to the native sales by channel, campaign, page and location.
- **Customers:** Premium reports, see above.

Premium reports appear as locked cards next to the free ones until Ecommerce Enhanced Premium is activated.

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
