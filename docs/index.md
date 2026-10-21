## Documentation

### Installation

Install and activate the plugin, then enable Ecommerce for your website (Administration, Websites, Manage). Then turn on the reading of the dataLayer, with one of the two methods below.

#### Recommended: Matomo Tag Manager

1. In your container, edit the "Matomo Configuration" variable: check "Read the GA4 ecommerce dataLayer (Ecommerce Enhanced)" and "Register As Default Tracker", so ecommerce events reach the Matomo tracker of the container.
2. Edit the container itself (Tag Manager, Manage Containers): check "Actively synchronise from the Google Tag Manager data layer" and leave "Ignore Google Tag Manager data layer" unchecked. Shop extensions push their GA4 events to `dataLayer`: the container copies every push into its own data layer, where Ecommerce Enhanced reads it. Without this setting, the `dataLayer` pushes of your shop are not seen.
3. Publish a new version of the container.

At Openmost we recommend this method: the settings live in your container, so they are turned on, tested in preview mode and versioned without touching the code of your shop.

Ecommerce events pushed directly to the Matomo Tag Manager data layer, `_mtm.push({event: 'purchase', ecommerce: {...}})`, are read as well. Ecommerce Enhanced reads the container data layer only, so a push synchronised from the dataLayer is never counted twice.

#### Other method (not recommended): the classic tracking code

Without Matomo Tag Manager, add one line to your Matomo tracking code, before `trackPageView`:

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

It works the same way, but every change then means editing the tracking code of your shop.

#### Without a dataLayer, or choosing each event yourself

With either method above, Ecommerce Enhanced reads every ecommerce push of the dataLayer: this is the recommended setup. To decide yourself which events are sent and when, for example from your own Matomo Tag Manager tags, do not turn on the reading of the dataLayer and send each event from JavaScript instead, with the same GA4 fields. The mapping, reports and settings stay the same:

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

### Expected dataLayer pushes

Clear the previous ecommerce object before each push, as GA4 recommends: `dataLayer.push({ecommerce: null});`. Ecommerce Enhanced reads each push on its own, the reset is harmless.

#### Item fields

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
| any other field | item dimension (Premium), recorded when an item dimension with this dataLayer key exists, see [Item dimensions](#item-dimensions-premium) |

Item list and promotion fields set on the event apply to the items that lack them. When an item is selected in a list or a promotion (`select_item`, `select_promotion`), it keeps that list or promotion when it is later added to cart and bought.

#### view_item_list

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

#### select_item

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

#### view_item

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

#### add_to_wishlist

```js
dataLayer.push({
  event: 'add_to_wishlist',
  ecommerce: {currency: 'EUR', value: 30.00, items: [{item_id: 'SKU_123', item_name: 'Organic tee', price: 30.00}]}
});
```

Read: `currency`, `value`, items.

#### add_to_cart and remove_from_cart

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

Read: `currency`, `value`, items. GA4 only sends the items added or removed, while a native Matomo cart update needs the whole cart: the plugin keeps a copy of the cart in the browser, applies each add and removal to it and sends the whole cart, so abandoned carts are reported natively. `remove_from_cart` has the same shape. See [Browser storage and consent](#browser-storage-and-consent).

#### view_cart

```js
dataLayer.push({
  event: 'view_cart',
  ecommerce: {currency: 'EUR', value: 60.00, items: [/* every item of the cart */]}
});
```

Read: `currency`, `value`, items. The items list the whole cart: the cart kept by the plugin is corrected if it differs.

#### begin_checkout

```js
dataLayer.push({
  event: 'begin_checkout',
  ecommerce: {currency: 'EUR', value: 60.00, coupon: 'SUMMER10', items: [/* every item of the cart */]}
});
```

Read: `currency`, `value`, `coupon`, items. Checkout step 1.

#### add_shipping_info

```js
dataLayer.push({
  event: 'add_shipping_info',
  ecommerce: {currency: 'EUR', value: 60.00, shipping_tier: 'Express', items: [/* cart items */]}
});
```

Read: `currency`, `value`, `coupon`, `shipping_tier`, items. Checkout step 2. The shipping tier is copied to the order when the purchase event lacks it.

#### add_payment_info

```js
dataLayer.push({
  event: 'add_payment_info',
  ecommerce: {currency: 'EUR', value: 60.00, payment_type: 'Credit card', items: [/* cart items */]}
});
```

Read: `currency`, `value`, `coupon`, `payment_type`, items. Checkout step 3. The payment type is copied to the order when the purchase event lacks it.

#### purchase

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

#### refund

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

#### view_promotion and select_promotion

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

#### Search results

A page of search results is an item list: send it as `view_item_list` with a list of its own, for example `item_list_id: 'search_results'` and `item_list_name: 'Search results'`. It then appears in the item list reports, and the products clicked or bought from it are attributed to it. The terms searched are already in the Site Search reports of Matomo.

#### Other formats

- **gtag:** `gtag('event', 'purchase', {...})` calls are read like the GA4 pushes above, `gtag('set', {currency: 'EUR'})` sets the default currency.
- **Universal Analytics Enhanced Ecommerce:** `impressions`, `click`, `detail`, `add`, `remove`, `checkout` (step 1 begin checkout, 2 shipping, 3 payment), `checkout_option`, `purchase`, `refund`, `promoView` and `promoClick` are converted to their GA4 events. UA `revenue` is the grand total and is converted to the GA4 `value`.
- Event names prefixed with `dl_` (Elevar) or suffixed with `_stape` (Stape) are recognised.
- Event names otherwise follow GA4 exactly, letter case aside: `add_to_cart` is read, `addToCart` or `AddToCart` is ignored.

### What is never recorded

`user_data` and the other event level fields outside the GA4 ecommerce parameters are never read. Coupon codes are stored as a hash unless "Keep coupon codes readable" is turned on in the website settings. Other product fields are only sent by the tracker, and recorded, when you create an item dimension for them: choosing what they hold is the responsibility of the site owner and their DPO.

### Browser storage and consent

To send whole carts and to never record an order twice, the tracker keeps a little state in the browser:

- `mtm_ee_cart_<idSite>` in `localStorage`: the copy of the cart. It is shared by every tab and kept between visits, like the shop cart, and expires after 48 hours without change.
- `mtm_ee_checkout_<idSite>` in `sessionStorage`: the shipping and payment choices of the current checkout.
- `mtm_ee_sel_<idSite>` in `sessionStorage`: the list and promotion of the products selected in the current tab, to credit them when the product is added to cart or bought.
- `mtm_ee_tx` in `localStorage`: a hash of the last 50 order ids already sent.

This storage follows the cookie rules of your Matomo tracker. When cookies are disabled (`disableCookies`) or wait for consent (`requireCookieConsent` before `rememberCookieConsentGiven`), nothing is written and the state only lives in the current page. When consent is withdrawn (`forgetCookieConsentGiven`, `forgetConsentGiven` or `optUserOut`), every key above is removed at once, and so is the copy kept in the page. Browser storage is subject to the same consent rules as cookies: if your site relies on a consent exemption for audience measurement, check with your DPO that it covers these keys.

Without browser storage, an add to cart on a new page only sends the item just added. Push `view_cart` and `begin_checkout` with every item of the cart, as GA4 expects: the plugin then corrects the cart it sends.

### Website settings

Administration, Websites, Ecommerce Enhanced (website administrators):

- **The GA4 value includes tax and shipping:** for shops that send the grand total in `value`.
- **Ecommerce steps:** a table with one line per step, its event category (`Ecommerce`), event action (`Add to cart`) and event name (the GA4 event name, `add_to_cart`), and two switches:
  - **Shown in the Events report:** the step is also a Matomo event. Switched off, it is still recorded in the Ecommerce Enhanced reports.
  - **Non-interaction:** the step keeps the visit alive but does not count as an action, so it does not lower the bounce rate. Viewing steps (`view_item_list`, `view_item`, `view_cart`, `view_promotion`) are switched on by default, as in GA4.
- **Keep coupon codes readable (do not hash them):** off by default, each code is hashed before it is stored, so single use and gift codes are never readable. Turn it on for shared promotional codes (`SUMMER10`) you want to read in the reports.
- **Unit cost item parameter (Premium):** the item dimension that holds the unit cost of goods, for the profit reports.

The same page creates the funnels of the Funnels plugin, when it is activated: the sales funnel of the ecommerce orders, and the Purchase journey and Checkout journey of GA4, named in the language of the user who creates them. A funnel that already exists is kept, recognised by its steps. A sales funnel configured by hand is never replaced: when it differs from the complete template, an "Update the sales funnel configuration" button applies the template. The steps must be shown in the Events report for Funnels to see them.

### Item dimensions (Premium)

Custom parameters of your products, for example a size, a color or a stock status, are configured with Ecommerce Enhanced Premium in Administration, Websites, Custom Dimensions, in the "Item" scope added next to the visit and action scopes. Each item dimension has:

- a **dataLayer key**, set when it is created: the key of the parameter in the items of your dataLayer, for example `color`;
- a **name**, the label displayed in the reports, for example "Product Color", which can be changed at any time without touching the tracking.

```js
dataLayer.push({
  event: 'add_to_cart',
  ecommerce: {
    items: [{
      item_id: 'SKU_123',
      item_name: 'Organic tee',
      price: 30,
      quantity: 1,
      color: 'Red'
    }]
  }
});
```

Add the parameter the same way to the items of every ecommerce event (`view_item`, `begin_checkout`, `purchase`...). Each item dimension then has a report in the dimension switcher of the Products report, a segment (`eeItemDimension1`...) and a Custom Reports dimension.

10 item dimensions are available per website. More can be added from the command line, up to 30, as for the visit and action scopes:

```bash
./console customdimensions:add-custom-dimension --scope=item --count=5
```

The tracker only sends the item keys of the active item dimensions of the website: their list is written into `matomo.js`, which Matomo rewrites as soon as an item dimension is created, renamed, activated or deactivated. If your website loads a copy of the tracker (CDN, self-hosted file), refresh that copy after changing your item dimensions, as after any change of the Matomo tracker.

Renaming an item dimension keeps the values recorded before, deactivating it stops recording new values. Only the item dimensions you create are stored, whatever their key: what they collect is the choice of the site owner and their DPO. Without Premium, the item dimensions already created are kept and still recorded, and they are listed marked "Premium" in the segment editor and in Custom Reports.

### Reports

In the Ecommerce menu, in the order a shop owner reads them (Premium in italics):

- **Overview:** buyers and revenue (purchasers, first time purchasers, ARPU, ARPPU, average order value, items per order, conversion rate, cart abandonment rate) as sparklines and over time; *refunds and net revenue*; *daily, weekly and monthly active users and buyers*.
- **Products:** the native report, with Product Categories, Product Brands, Product Variants and *one report per item dimension* added to its dimension switcher, after Product Name, SKU and Category.
- **Merchandising:** product performance with views, wishlist and cart adds, purchases, item revenue and rates; item lists and promotions; *products bought together*; *profit and margin by product, category and brand*.
- **Sales:** sales by source / medium, next to the native sales by channel, campaign, page and location; *sales by first user channel and source / medium*.
- **Shopping behavior:** the shopping and checkout funnels with step and abandonment rates; *the same journey by device type and by channel*; *abandoned carts by channel and source / medium, most abandoned products*.
- **Order details:** transactions (every order with its revenue split and GA4 details); *orders by basket size and by order value*; payment types and shipping tiers (pies); coupons and *discount depth by coupon, product and category*; *refunds over time and refunded products*; affiliations and order currencies.
- **Customers:** *repeat purchases*, with the *retention cohorts* by day, week or month right below; new and returning customers; *time to first purchase*; *lifetime value by channel*; *RFM segments*.

Premium reports appear as locked cards, in the place of the report, until Ecommerce Enhanced Premium is activated.

### Segments

Every stored field is a segment, also available in Custom Reports:

- order: `eeOrderCoupon`, `eeOrderPaymentType`, `eeOrderShippingTier`, `eeOrderCustomerType`, `eeOrderCurrency`, `eeOrderAffiliation`;
- cart and order lines: `eeItemBrand`, `eeItemVariant`, `eeItemListId`, `eeItemListName`, `eeItemCoupon`, `eeItemDiscount`, `eeItemAffiliation`, `eeItemLocationId`;
- shopping steps: `eeStep` (GA4 event name), `eeListId`, `eeListName`, `eeCheckoutStep`, `eeCheckoutOption`, `eePromotionId`, `eePromotionName`, `eePromotionCreativeName`, `eePromotionCreativeSlot`;
- item dimensions (Premium): `eeItemDimension1`, `eeItemDimension2`...

For example `eeStep==add_to_cart;eeStep!=purchase` selects the visits that added to cart without buying.

### Deactivating and uninstalling the plugin

The native Matomo ecommerce keeps working: carts and orders are always sent as native requests. While the plugin is deactivated its data is kept, but GDPR deletions and the deletion of old raw data no longer cover it.

Uninstalling the plugin keeps its data as well: the `log_ee_order_item`, `log_ee_event` and `log_ee_event_item` tables and the `ee_*` columns of `log_conversion` stay in the database, and installing the plugin again reuses them. Matomo itself deletes the website settings of a plugin when it is uninstalled.

### Removing the data

To delete the data recorded by the plugin, run this command **before** deactivating or uninstalling it:

```
./console ecommerceenhanced:remove-data
./console ecommerceenhanced:remove-data --idsite=3,7
```

Without `--idsite` it deletes the data of every website. The native Matomo ecommerce data (orders, carts, products) is kept, and so are the reports already archived, which only hold totals. Then, to also drop the tables and columns, uninstall the plugin and run (with your table prefix):

```sql
DROP TABLE matomo_log_ee_order_item, matomo_log_ee_event, matomo_log_ee_event_item;
ALTER TABLE matomo_log_conversion DROP COLUMN ee_affiliation, DROP COLUMN ee_coupon, DROP COLUMN ee_currency,
    DROP COLUMN ee_customer_type, DROP COLUMN ee_payment_type, DROP COLUMN ee_shipping_tier;
```

Database backups made before keep their copy of the data: delete or expire them as your retention policy requires.

### Large databases

Installing the plugin adds six empty columns to the `log_conversion` table. On MySQL 8.0.12+ and MariaDB 10.3.2+ this is instant. On older versions the table is rebuilt: run `./console core:update` from the command line on large databases.
