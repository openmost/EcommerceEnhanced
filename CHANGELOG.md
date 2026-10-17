## Changelog

### v6.0.0

- First release, for Matomo 6.x (`>=6.0.0-b1,<7.0.0-b1`), PHP 8.1+.
- Reads the GA4 ecommerce dataLayer (GA4 objects, `gtag()` calls, Universal Analytics Enhanced Ecommerce), the Matomo Tag Manager data layer (`_mtm`, and the dataLayer when the container synchronises it) and `_paq.push(['EcommerceEnhanced::track', ...])` calls. Carts and orders keep being sent as native Matomo ecommerce requests.
- Records brands, variants, item lists, promotions, coupons, payment types, shipping tiers, customer types, checkout steps, searches, wishlists, refunds and leads in its own log tables, covered by segments, GDPR tools and the raw data purge.
- Item dimensions (size, color, stock status...) managed as an "Item" scope of the Custom Dimensions page.
- Reports: buyers and revenue, shopping and checkout funnels, products, categories, brands, variants, item lists, promotions, product search terms, transactions, coupons, payment types, shipping tiers, customer types, affiliations, currencies, sales by source / medium. 24 segments.
- Website settings on their own page, Administration, Websites, Ecommerce Enhanced, with a one-click creation of the GA4 sales, shopping and checkout funnels when the Funnels plugin is activated.
- Visitor log: each ecommerce step shows its products, list, promotion, search term, coupon, payment, shipping, customer type and order rank.
- Source / medium reports point to the free Marketing Campaigns Reporting plugin when it is not activated.
- Oversized payloads: the tracker trims the items above 120 KB and reports how many were dropped, the server caps lists at 30 entries.
- Personal data: `user_data` is never read, emails and phone numbers are dropped from search terms and lead fields, coupon codes are hashed unless the website stores them as sent, item dimensions only record what the site owner created.
- Uninstalling keeps the data.
- Translated into 12 languages.
- Tests: unit (tracker, payload) and archiving integration tests, `./console tests:run plugins/EcommerceEnhanced`.

Development notes:
- `tracker.min.js` is the file Matomo merges into `matomo.js`. Rebuild it after any change to `tracker.js`, from the Matomo directory: `node_modules/.bin/terser plugins/EcommerceEnhanced/tracker.js -c -m --comments false -o plugins/EcommerceEnhanced/tracker.min.js`, then add the license line back at the top and run the JS tests on it: `TRACKER_FILE=tracker.min.js node --test plugins/EcommerceEnhanced/tests/javascript/`.
- The Vue components are built with `./console vue:build EcommerceEnhanced`.
