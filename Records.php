<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

/**
 * Archive record names. They are owned by the free plugin and never change: Premium adds its own records and
 * never rewrites these ones, so upgrading never requires archiving again.
 */
class Records
{
    public const PRODUCTS = 'EcommerceEnhanced_products';
    public const BRANDS = 'EcommerceEnhanced_brands';
    public const VARIANTS = 'EcommerceEnhanced_variants';
    public const CATEGORIES = 'EcommerceEnhanced_categories';
    public const ITEM_LISTS = 'EcommerceEnhanced_itemLists';
    public const PROMOTIONS = 'EcommerceEnhanced_promotions';

    public const COUPONS = 'EcommerceEnhanced_coupons';
    public const PAYMENT_TYPES = 'EcommerceEnhanced_paymentTypes';
    public const SHIPPING_TIERS = 'EcommerceEnhanced_shippingTiers';
    public const AFFILIATIONS = 'EcommerceEnhanced_affiliations';
    public const CUSTOMER_TYPES = 'EcommerceEnhanced_customerTypes';
    public const CURRENCIES = 'EcommerceEnhanced_currencies';

    public const SOURCE_MEDIUM = 'EcommerceEnhanced_sourceMedium';

    public const ORDERS = 'EcommerceEnhanced_orders';
    public const REVENUE = 'EcommerceEnhanced_revenue';
    public const ITEMS = 'EcommerceEnhanced_items';
    public const PURCHASERS = 'EcommerceEnhanced_purchasers';
    public const FIRST_TIME_PURCHASERS = 'EcommerceEnhanced_firstTimePurchasers';
    public const CONVERTED_VISITS = 'EcommerceEnhanced_convertedVisits';
    public const CART_VISITS = 'EcommerceEnhanced_cartVisits';

    /**
     * Numeric records of the buyer KPIs => metric name in the API output.
     */
    public const BUYER_METRICS = [
        self::ORDERS => Metrics::ORDERS,
        self::REVENUE => Metrics::REVENUE,
        self::ITEMS => Metrics::ITEMS,
        self::PURCHASERS => Metrics::PURCHASERS,
        self::FIRST_TIME_PURCHASERS => Metrics::FIRST_TIME_PURCHASERS,
        self::CONVERTED_VISITS => Metrics::CONVERTED_VISITS,
        self::CART_VISITS => Metrics::CART_VISITS,
    ];

    public const SHOPPING_BEHAVIOR = 'EcommerceEnhanced_shoppingBehavior';
    public const CHECKOUT_BEHAVIOR = 'EcommerceEnhanced_checkoutBehavior';

    /**
     * Label of the rows without a value, as in GA4. Never translated, it is stored in archives.
     */
    public const NOT_SET = '(not set)';

    public const MAX_ROWS = 500;

    /**
     * Products, brands, variants and categories: a shop catalog is large, a product often bought but rarely viewed
     * must not end in "Others".
     */
    public const MAX_ITEM_ROWS = 5000;
}
