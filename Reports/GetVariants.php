<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Metrics;

class GetVariants extends Base
{
    protected $labelTranslationKey = 'EcommerceEnhanced_ItemVariant';

    protected $metricNames = [
        Metrics::ITEMS_VIEWED,
        Metrics::ITEMS_ADDED_TO_WISHLIST,
        Metrics::ITEMS_ADDED_TO_CART,
        Metrics::ITEMS_PURCHASED,
        Metrics::ITEM_REVENUE,
        Metrics::ORDERS,
    ];

    protected $ratioNames = [
        Metrics::CART_TO_VIEW_RATE,
        Metrics::PURCHASE_TO_VIEW_RATE,
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getVariants';
        $this->name = Piwik::translate('EcommerceEnhanced_ItemVariantPlural');
        $this->documentation = Piwik::translate('EcommerceEnhanced_VariantsDocumentation');
        $this->subcategoryId = 'Goals_Products';
        $this->order = 4;
        $this->inProductsSwitcher = true;
    }
}
