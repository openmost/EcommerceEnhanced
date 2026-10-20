## Changelog

### v6.0.0

- First release, for Matomo 6.x (`>=6.0.0-b1,<7.0.0-b1`), PHP 8.1+.
- Reads the GA4 ecommerce dataLayer (GA4 objects, `gtag()` calls, Universal Analytics Enhanced Ecommerce), the Matomo Tag Manager data layer (`_mtm`, and the dataLayer when the container synchronises it) and `_paq.push(['EcommerceEnhanced::track', ...])` calls. Carts and orders keep being sent as native Matomo ecommerce requests.
- Records brands, variants, item lists, promotions, coupons, payment types, shipping tiers, customer types, checkout steps, wishlists and refunds in its own log tables, covered by segments, GDPR tools and the raw data purge.
- Item dimensions (size, color, stock status...) recorded from the items of the dataLayer, configured with Premium as an "Item" scope of the Custom Dimensions page: a dataLayer key set at creation and a free label, 10 per website, more with `./console customdimensions:add-custom-dimension --scope=item`. Without Premium they are listed marked "Premium" in the segment editor and Custom Reports.
- Reports: buyers and revenue, shopping and checkout funnels, product performance, Product Categories, Product Brands and Product Variants in the dimension switcher of the native Products report, item lists, promotions, transactions, coupons, payment types, shipping tiers, customer types, affiliations, currencies, sales by source / medium. 23 segments.
- Website settings on their own page, Administration, Websites, Ecommerce Enhanced: the ecommerce steps as a table with switches (shown in the Events report, non-interaction), readable or hashed coupon codes, and a one-click creation of the sales funnel and the GA4 Purchase journey and Checkout journey when the Funnels plugin is activated (an existing sales funnel is only updated on request).
- Visitor log: each ecommerce step shows its products, list, promotion, coupon, payment, shipping, customer type and order rank.
- Source / medium reports point to the free Marketing Campaigns Reporting plugin when it is not activated.
- Whole carts from GA4 adds and removals: the tracker keeps a copy of the cart in `localStorage`, shared by every tab and expiring after 48 hours without change, and `view_cart` / `begin_checkout` correct it. Browser storage follows the Matomo cookie consent and is cleared once consent is withdrawn.
- Oversized payloads: the tracker trims the items above 120 KB and reports how many were dropped, the server caps lists at 30 entries.
- Personal data: `user_data` is never read, coupon codes are hashed unless the website stores them as sent, item dimensions only record what the site owner created.
- Uninstalling keeps the data.
- Translated into 12 languages.
