<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Columns\Dimension;
use Piwik\Plugin\Metric;
use Piwik\Plugins\EcommerceEnhanced\Columns\Metrics\Precomputed;
use Piwik\Plugins\EcommerceEnhanced\Columns\Metrics\Ratio;
use Piwik\Plugins\EcommerceEnhanced\Columns\Metrics\StoredMetric;

/**
 * Metrics of the plugin reports, prefixed so they never collide with core metrics.
 */
class Metrics
{
    public const ITEMS_VIEWED = 'ee_items_viewed';
    public const ITEMS_ADDED_TO_CART = 'ee_items_added_to_cart';
    public const ITEMS_PURCHASED = 'ee_items_purchased';
    public const ITEM_REVENUE = 'ee_item_revenue';
    public const ORDERS = 'ee_orders';
    public const LIST_VIEWS = 'ee_list_views';
    public const LIST_CLICKS = 'ee_list_clicks';
    public const PROMOTION_VIEWS = 'ee_promotion_views';
    public const PROMOTION_CLICKS = 'ee_promotion_clicks';
    public const REVENUE = 'ee_revenue';
    public const ITEMS = 'ee_items';
    public const VISITS = 'nb_visits';
    public const STEP_RATE = 'ee_step_rate';
    public const ABANDONMENT_RATE = 'ee_abandonment_rate';

    public const CART_TO_VIEW_RATE = 'ee_cart_to_view_rate';
    public const PURCHASE_TO_VIEW_RATE = 'ee_purchase_to_view_rate';
    public const LIST_CLICK_RATE = 'ee_list_click_rate';
    public const PROMOTION_CLICK_RATE = 'ee_promotion_click_rate';
    public const AVERAGE_ORDER_VALUE = 'ee_average_order_value';

    /**
     * name => [translation key, type]
     */
    private const STORED = [
        self::ITEMS_VIEWED => ['EcommerceEnhanced_ItemsViewed', Dimension::TYPE_NUMBER],
        self::ITEMS_ADDED_TO_CART => ['EcommerceEnhanced_ItemsAddedToCart', Dimension::TYPE_NUMBER],
        self::ITEMS_PURCHASED => ['EcommerceEnhanced_ItemsPurchased', Dimension::TYPE_NUMBER],
        self::ITEM_REVENUE => ['EcommerceEnhanced_ItemRevenue', Dimension::TYPE_MONEY],
        self::ORDERS => ['EcommerceEnhanced_Orders', Dimension::TYPE_NUMBER],
        self::LIST_VIEWS => ['EcommerceEnhanced_ListViews', Dimension::TYPE_NUMBER],
        self::LIST_CLICKS => ['EcommerceEnhanced_ListClicks', Dimension::TYPE_NUMBER],
        self::PROMOTION_VIEWS => ['EcommerceEnhanced_PromotionViews', Dimension::TYPE_NUMBER],
        self::PROMOTION_CLICKS => ['EcommerceEnhanced_PromotionClicks', Dimension::TYPE_NUMBER],
        self::REVENUE => ['General_ColumnRevenue', Dimension::TYPE_MONEY],
        self::ITEMS => ['EcommerceEnhanced_ItemsInOrders', Dimension::TYPE_NUMBER],
        self::VISITS => ['General_ColumnNbVisits', Dimension::TYPE_NUMBER],
    ];

    /**
     * name => translation key, percentages computed by the API across rows
     */
    private const PRECOMPUTED = [
        self::STEP_RATE => 'EcommerceEnhanced_StepRate',
        self::ABANDONMENT_RATE => 'EcommerceEnhanced_AbandonmentRate',
    ];

    /**
     * name => [translation key, numerator, denominator, type]
     */
    private const RATIOS = [
        self::CART_TO_VIEW_RATE => ['EcommerceEnhanced_CartToViewRate', self::ITEMS_ADDED_TO_CART, self::ITEMS_VIEWED, Dimension::TYPE_PERCENT],
        self::PURCHASE_TO_VIEW_RATE => ['EcommerceEnhanced_PurchaseToViewRate', self::ITEMS_PURCHASED, self::ITEMS_VIEWED, Dimension::TYPE_PERCENT],
        self::LIST_CLICK_RATE => ['EcommerceEnhanced_ListClickRate', self::LIST_CLICKS, self::LIST_VIEWS, Dimension::TYPE_PERCENT],
        self::PROMOTION_CLICK_RATE => ['EcommerceEnhanced_PromotionClickRate', self::PROMOTION_CLICKS, self::PROMOTION_VIEWS, Dimension::TYPE_PERCENT],
        self::AVERAGE_ORDER_VALUE => ['General_AverageOrderValue', self::REVENUE, self::ORDERS, Dimension::TYPE_MONEY],
    ];

    /**
     * @param string[] $names
     * @return Metric[]
     */
    public static function make(array $names): array
    {
        $metrics = [];
        foreach ($names as $name) {
            if (isset(self::STORED[$name])) {
                [$translationKey, $type] = self::STORED[$name];
                $metrics[] = new StoredMetric($name, $translationKey, $type);
            } elseif (isset(self::PRECOMPUTED[$name])) {
                $metrics[] = new Precomputed($name, self::PRECOMPUTED[$name]);
            } elseif (isset(self::RATIOS[$name])) {
                [$translationKey, $numerator, $denominator, $type] = self::RATIOS[$name];
                $metrics[] = new Ratio($name, $translationKey, $numerator, $denominator, $type);
            }
        }

        return $metrics;
    }
}
