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
     * id => [subcategory, order, image]. Name and description are the translations EcommerceEnhanced_Teaser{Id}
     * and EcommerceEnhanced_Teaser{Id}Description.
     */
    private const TEASERS = [
        'ActiveUsers' => ['General_Overview', 40, self::IMAGE_GRAPH],
        'FirstUserSource' => ['Ecommerce_Sales', 2, self::IMAGE_TABLE],
        'AbandonedCartsByChannel' => ['Ecommerce_Sales', 3, self::IMAGE_TABLE],
        'TimeToPurchase' => ['Ecommerce_Sales', 4, self::IMAGE_TABLE],
        'BehaviorByDeviceAndChannel' => ['EcommerceEnhanced_ShoppingBehavior', 10, self::IMAGE_TABLE],
        'LeadPipeline' => ['EcommerceEnhanced_ShoppingBehavior', 11, self::IMAGE_TABLE],
        'ProductAffinity' => ['EcommerceEnhanced_Merchandising', 10, self::IMAGE_TABLE],
        'AbandonedProducts' => ['EcommerceEnhanced_Merchandising', 11, self::IMAGE_TABLE],
        'ProductProfit' => ['EcommerceEnhanced_Merchandising', 12, self::IMAGE_TABLE],
        'CustomItemParameters' => ['EcommerceEnhanced_Merchandising', 13, self::IMAGE_TABLE],
        'Refunds' => ['EcommerceEnhanced_OrderDetails', 10, self::IMAGE_GRAPH],
        'DiscountDepth' => ['EcommerceEnhanced_OrderDetails', 11, self::IMAGE_TABLE],
        'BasketDistribution' => ['EcommerceEnhanced_OrderDetails', 12, self::IMAGE_TABLE],
        'MultiCurrency' => ['EcommerceEnhanced_OrderDetails', 13, self::IMAGE_TABLE],
        'RepeatPurchase' => ['EcommerceEnhanced_Customers', 1, self::IMAGE_GRAPH],
        'RetentionCohorts' => ['EcommerceEnhanced_Customers', 2, self::IMAGE_TABLE],
        'LifetimeValue' => ['EcommerceEnhanced_Customers', 3, self::IMAGE_TABLE],
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
            $config->setOrder(1000 + $order);
            $configs[] = $config;
        }

        return $configs;
    }
}
