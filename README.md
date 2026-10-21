# Ecommerce Enhanced

## Description

**Get GA4 grade ecommerce analytics in Matomo, without changing a line of your shop.**

Your shop already describes every product view, add to cart, checkout step and order in its GA4 dataLayer. Native Matomo ecommerce only keeps the order and a few product fields. Ecommerce Enhanced reads the whole GA4 dataLayer and turns it into the reports GA4 users rely on: product and brand performance, item lists, promotions, coupons, shopping and checkout funnels, sales by source / medium, buyers and revenue per user. Your data stays in your Matomo, with no sampling.

One checkbox in Matomo Tag Manager, or one line in your tracking code, and it works with WooCommerce, Shopify, Magento, PrestaShop and every shop that speaks GA4.

### What you get for free

**Collection that just works**

- Reads GA4 dataLayer pushes, `gtag()` calls and legacy Universal Analytics Enhanced Ecommerce, from any CMS or extension (GTM4WP, Google Analytics for WooCommerce, Shopify, Magento, PrestaShop, Elevar, Stape...).
- Turned on with a checkbox in the Matomo Tag Manager "Matomo Configuration" variable (recommended), with one line in your tracking code (`_paq.push(['EcommerceEnhanced::enable'])`), or event by event from JavaScript with `_paq.push(['EcommerceEnhanced::track', ...])`.
- Every GA4 ecommerce event: item list views and clicks, product views, wishlist, adds and removals from cart, cart views, checkout, shipping and payment steps, purchases, refunds, promotion views and clicks.
- Native Matomo carts and orders are still sent, so the Ecommerce reports, goals and abandoned carts you know keep working, even if you deactivate the plugin.
- Variants kept apart, even when they share a SKU, with brand, list, promotion, coupon and discount per product line.
- Item lists and promotions remembered from the click to the order, as GA4 attributes them.
- Duplicate orders blocked, even when the confirmation page is reloaded or order ids are anonymised.
- Privacy by design: `user_data` (emails, phone numbers, addresses, hashed identifiers) is never read, product fields outside GA4 are only sent for the item dimensions you created, coupon codes can be hashed or skipped.

**Reports GA4 users expect**

- Shopping behavior and checkout behavior funnels, with step and abandonment rates, without the Funnels plugin.
- Product performance, and Product Categories, Product Brands and Product Variants in the dimension switcher of the native Products report, next to Product Name, SKU and Category: views, adds to cart, purchases, item revenue, cart to view and purchase to view rates.
- Item lists and promotions: views, clicks, click rate, and the items added to cart and bought after them.
- Order details: orders, revenue and average order value by coupon, payment type, shipping tier, customer type (new or returning), affiliation and currency.
- Transactions: every order with its revenue, tax, shipping, items, coupon, payment and shipping choices.
- Sales by source / medium, the way GA4 shows it: `google / organic`, `newsletter / email`, `(direct) / (none)`.
- Buyers and revenue: purchasers, first time purchasers, revenue per user (ARPU) and per paying user (ARPPU), average order value and items per order, conversion rate and cart abandonment rate.
- Every step also appears in the Events report (category "Ecommerce"), with viewing steps kept out of the bounce rate, as in GA4.
- The visitor log shows the detail of each step: products, list, promotion, coupon, payment, shipping and order rank of the customer.
- One click creates the funnels of the Funnels plugin, when it is activated: the sales funnel of the ecommerce orders, and the Purchase journey and Checkout journey of GA4.

**Native Matomo integration**

- 23 new segments (`eeItemBrand`, `eeOrderCoupon`, `eeStep`, `eePromotionName`...), for example "visits that added to cart without buying": `eeStep==add_to_cart;eeStep!=purchase`.
- Every field available in Custom Reports, the segment editor, the API, scheduled reports and dashboards.
- GDPR tools (data subject export and deletion) and the deletion of old raw data cover every stored row.

### What Premium adds

Every Premium report is visible in the free edition with a preview, so you know what you get. Premium shows them with your data, from the data already collected: no new tracking, no history lost, no report to rebuild.

- **Active users and buyers (Premium):** 1, 7 and 30 day active users and buyers (DAU, WAU, MAU) and the DAU / MAU stickiness ratio.
- **Customers (Premium):** repeat purchase rate, time between purchases, retention cohorts by day, week or month of the first purchase as a heatmap, customer lifetime value and RFM segments.
- **Acquisition (Premium):** sales by first user source, time and visits from the first visit to the first purchase, abandoned carts by channel.
- **Shopping behavior (Premium):** the shopping and checkout funnels split by device and by channel.
- **Item dimensions (Premium):** your own product parameters (size, color, stock status...), configured as an "Item" scope of Custom Dimensions, each with a report in the Products dimension switcher, a segment and a Custom Reports dimension.
- **Merchandising (Premium):** products bought together (market basket analysis), most abandoned products, product profit and margin from your cost of goods.
- **Orders (Premium):** refunds, refund rate by product and net revenue, discount depth by product, coupon and category, orders by basket size and value, revenue of foreign currency orders converted to your website currency.

### Why the GA4 format

GA4 ecommerce is the most widely used ecommerce dataLayer format. Most shop platforms and their extensions already emit it (WooCommerce with GTM4WP or Google Analytics for WooCommerce, Shopify, Magento, PrestaShop, Elevar, Stape and many more). Reading it means your shop does not need any Matomo specific development. The legacy Universal Analytics Enhanced Ecommerce format and `gtag()` calls are read as well.

Official GA4 references:

- [Measure ecommerce](https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?client_type=gtm)
- [Recommended events reference](https://developers.google.com/analytics/devguides/collection/ga4/reference/events?client_type=gtm): every event and parameter Ecommerce Enhanced reads

## Installation

1. Install and activate the plugin, then enable Ecommerce for your website (Administration, Websites, Manage).
2. Recommended: check "Read the GA4 ecommerce dataLayer (Ecommerce Enhanced)" in the "Matomo Configuration" variable of your Matomo Tag Manager container, check "Actively synchronise from the Google Tag Manager data layer" in the container settings, and publish it. Without Tag Manager (not recommended), add `_paq.push(['EcommerceEnhanced::enable']);` to your Matomo tracking code, before `trackPageView`.

## Documentation

The full documentation is in [docs/index.md](docs/index.md), also shown in the Documentation tab of the Marketplace: Matomo Tag Manager setup, every expected dataLayer push, browser storage and consent, website settings, item dimensions, reports and segments. Common questions are answered in [docs/faq.md](docs/faq.md).

## Development

- `tracker.min.js` is the file Matomo merges into `matomo.js`. Rebuild it after any change to `tracker.js`, from the Matomo directory: `node_modules/.bin/terser plugins/EcommerceEnhanced/tracker.js -c -m --comments false --preamble "/*! Ecommerce Enhanced tracker for Matomo, GPL v3 or later. Minified build of tracker.js, see README.md */" -o plugins/EcommerceEnhanced/tracker.min.js`, then run the JS tests on it: `TRACKER_FILE=tracker.min.js node --test plugins/EcommerceEnhanced/tests/javascript/tracker.test.js`.
- The Vue components are built with `./console vue:build EcommerceEnhanced` (webpack in Matomo 5: on Node 17 or later, set `NODE_OPTIONS=--openssl-legacy-provider`). The build lints the code, lines are limited to 100 characters.
- Unit (tracker, payload) and archiving integration tests: `./console tests:run plugins/EcommerceEnhanced`.

## Support

Openmost, [ronan@openmost.com](mailto:ronan@openmost.com).

## License

GPL v3 or later, see [LICENSE](LICENSE).
