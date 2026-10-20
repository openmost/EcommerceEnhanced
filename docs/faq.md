## FAQ

**Does my shop need any development?**

Usually not. WooCommerce (with GTM4WP or Google Analytics for WooCommerce), Shopify, Magento, PrestaShop, Elevar, Stape and most shop extensions already push GA4 ecommerce events to the dataLayer. Enable the plugin with one line in your tracking code, or one checkbox in Matomo Tag Manager, and those events are read as they are. The Documentation tab lists every push the plugin expects.

**Do I need Matomo Tag Manager or Google Tag Manager?**

Matomo Tag Manager is the recommended way: check "Read the GA4 ecommerce dataLayer (Ecommerce Enhanced)" in the "Matomo Configuration" variable of your container and "Actively synchronise from the Google Tag Manager data layer" in the container settings, and the settings are tested in preview mode and versioned without touching your shop. Without Tag Manager, `_paq.push(['EcommerceEnhanced::enable'])` in your Matomo tracking code works too. Google Tag Manager is not needed: the plugin reads the dataLayer your shop already fills.

**How do I check that it works?**

Open the Visitor log of your website after a test visit: each ecommerce step (product view, add to cart, checkout, purchase) is listed with its products, list, promotion, coupon, payment and shipping. The steps also appear in the Events report, under the "Ecommerce" category.

**Nothing is recorded, my tracker is self-hosted or served from a CDN**

The plugin adds its code to the `matomo.js` file served by your Matomo. Matomo updates this file automatically when it is writable, otherwise run `./console custom-matomo-js:update`. A copy of `matomo.js` hosted elsewhere (CDN, your own server, a WordPress plugin bundling the tracker) does not contain it: update that copy from your Matomo after activating the plugin and after each plugin update.

**Why are the reports empty before the activation date?**

The plugin records the details of each ecommerce step from its activation on: earlier visits never sent them. Your native Matomo ecommerce reports keep their full history.

**I updated the plugin, why do past reports still show the old numbers?**

Reports are built during archiving. To rebuild past periods with the latest version, invalidate and re-archive them, for every period type (the default of the command), for example:

```
./console core:invalidate-report-data --sites=1 --dates=2026-01-01,2026-12-31
./console core:archive
```

Adjust `--sites` and `--dates` to your data.

**Why is the revenue different from my shop back office?**

GA4 defines `value` without tax and shipping, so the plugin records an order revenue of `value + tax + shipping`. If your shop already sends the grand total in `value`, check "The GA4 value includes tax and shipping" in Administration, Websites, Ecommerce Enhanced. Refunds and cancelled orders are not subtracted from the native revenue: refunds and net revenue are reported by Ecommerce Enhanced Premium.

**Can an order be counted twice?**

No. Reloading the confirmation page does not send the order again, and Matomo keeps a single order per order id.

**Is it compatible with consent banners and cookieless tracking?**

Yes. The plugin follows the cookie settings of your Matomo tracker: with `disableCookies`, or while `requireCookieConsent` waits for consent, nothing is written in the browser. Emails, phone numbers, addresses and hashed identifiers are never read, and coupon codes are hashed by default. See "Browser storage and consent" in the Documentation tab.

**Do I need the Funnels plugin?**

No. The shopping and checkout funnels are built in. When the Funnels plugin is activated, the plugin settings can also create its sales funnel and the GA4 Purchase journey and Checkout journey in one click.

**What happens if I deactivate the plugin?**

Carts and orders keep being sent as native Matomo ecommerce requests, so your native Ecommerce reports, goals and abandoned carts keep working. The data recorded by the plugin is kept, also after uninstalling, and is used again if you reinstall it.

**What does Premium add, and do I lose data by upgrading later?**

Ecommerce Enhanced Premium adds customer, retention, merchandising, refund and multi-currency reports, previewed in the free edition. It reads the data the free plugin already collects, so upgrading later shows your full history without any tracking change. More on [Ecommerce Enhanced Premium](https://openmost.com/matomo/extensions/ecommerce-enhanced?utm_source=matomo_marketplace&utm_medium=referral&utm_campaign=premium_upgrade&utm_content=ecommerceenhanced).

**Will it slow down my large Matomo database?**

Installing the plugin adds six empty columns to the `log_conversion` table, instant on MySQL 8.0.12+ and MariaDB 10.3.2+. On older versions run `./console core:update` from the command line. Reports are built during archiving, like the native ones.

**Which versions of Matomo are supported?**

Version 5.x of the plugin supports Matomo 5 (PHP 7.2.5 or later). Use the 6.x versions of the plugin for Matomo 6 (PHP 8.1 or later).
