<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Plugin\Manager;
use Piwik\Widget\WidgetConfig;

/**
 * Every Ecommerce Enhanced Premium report is shown in the free edition as a locked card next to the free reports,
 * so users see what they miss. The cards disappear once Premium is activated, Premium shows the real reports in
 * the same places.
 */
class PremiumTeasers
{
    public const PREMIUM_PLUGIN = 'EcommerceEnhancedPremium';

    public const URL = 'https://openmost.com/matomo/extensions/ecommerce-enhanced?utm_source=matomo_onpremise&utm_medium=plugin&utm_campaign=premium_upgrade&utm_content=ecommerceenhanced';

    public const IMAGE_TABLE = 'premium-table';
    public const IMAGE_GRAPH = 'premium-graph';

    /**
     * id => [subcategory, widget order, image]. The order is the one of the Premium widget, so the card stands where
     * the report will (report widgets are ordered 100 + report order). Name and description are the translations
     * EcommerceEnhanced_Teaser{Id} and EcommerceEnhanced_Teaser{Id}Description.
     */
    private const TEASERS = [
        'Refunds' => ['General_Overview', 35, self::IMAGE_GRAPH],
        'ActiveUsers' => ['General_Overview', 40, self::IMAGE_GRAPH],
        'FirstUserSource' => ['Ecommerce_Sales', 120, self::IMAGE_TABLE],
        'BehaviorByDeviceAndChannel' => ['EcommerceEnhanced_ShoppingBehavior', 110, self::IMAGE_TABLE],
        'AbandonedCartsByChannel' => ['EcommerceEnhanced_ShoppingBehavior', 112, self::IMAGE_TABLE],
        'AbandonedProducts' => ['EcommerceEnhanced_ShoppingBehavior', 114, self::IMAGE_TABLE],
        'CustomItemParameters' => ['Goals_Products', 105, self::IMAGE_TABLE],
        'ProductAffinity' => ['EcommerceEnhanced_Merchandising', 130, self::IMAGE_TABLE],
        'ProductProfit' => ['EcommerceEnhanced_Merchandising', 140, self::IMAGE_TABLE],
        'BasketDistribution' => ['EcommerceEnhanced_OrderDetails', 101, self::IMAGE_GRAPH],
        'DiscountDepth' => ['EcommerceEnhanced_OrderDetails', 106, self::IMAGE_TABLE],
        'MultiCurrency' => ['EcommerceEnhanced_OrderDetails', 112, self::IMAGE_TABLE],
        'RepeatPurchase' => ['EcommerceEnhanced_Customers', 1, self::IMAGE_GRAPH],
        'TimeToPurchase' => ['EcommerceEnhanced_Customers', 104, self::IMAGE_GRAPH],
        'RetentionCohorts' => ['EcommerceEnhanced_Customers', 103, self::IMAGE_TABLE],
        'LifetimeValue' => ['EcommerceEnhanced_Customers', 107, self::IMAGE_TABLE],
    ];

    public static function isPremiumActivated(): bool
    {
        return Manager::getInstance()->isPluginActivated(self::PREMIUM_PLUGIN);
    }

    public static function exists(string $id): bool
    {
        return isset(self::TEASERS[$id]);
    }

    public static function getImage(string $id): string
    {
        return self::TEASERS[$id][2];
    }

    /**
     * @return WidgetConfig[]
     */
    public static function getWidgetConfigs(): array
    {
        $configs = [];
        foreach (self::TEASERS as $id => [$subcategory, $order]) {
            $config = new WidgetConfig();
            $config->setCategoryId('Goals_Ecommerce');
            $config->setSubcategoryId($subcategory);
            $config->setModule('EcommerceEnhanced');
            $config->setAction('premiumTeaser');
            $config->setParameters(['teaser' => $id]);
            $config->setName('EcommerceEnhanced_Teaser' . $id);
            $config->setOrder($order);
            $configs[] = $config;
        }

        return $configs;
    }
}
