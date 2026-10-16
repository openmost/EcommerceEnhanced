<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Columns\PromotionName;
use Piwik\Plugins\EcommerceEnhanced\Metrics;

class GetPromotions extends Base
{
    protected $metricNames = [
        Metrics::PROMOTION_VIEWS,
        Metrics::PROMOTION_CLICKS,
        Metrics::ITEMS_ADDED_TO_CART,
        Metrics::ITEMS_PURCHASED,
        Metrics::ITEM_REVENUE,
    ];

    protected $ratioNames = [
        Metrics::PROMOTION_CLICK_RATE,
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getPromotions';
        $this->name = Piwik::translate('EcommerceEnhanced_PromotionNamePlural');
        $this->documentation = Piwik::translate('EcommerceEnhanced_PromotionsDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_Merchandising';
        $this->order = 5;
        $this->dimension = new PromotionName();
    }
}
