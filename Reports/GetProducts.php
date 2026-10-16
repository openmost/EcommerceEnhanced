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
use Piwik\Plugins\Ecommerce\Columns\ProductName;

class GetProducts extends Base
{
    protected $metricNames = [
        Metrics::ITEMS_VIEWED,
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

        $this->action = 'getProducts';
        $this->name = Piwik::translate('EcommerceEnhanced_ProductsReport');
        $this->documentation = Piwik::translate('EcommerceEnhanced_ProductsDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_Merchandising';
        $this->order = 1;
        $this->dimension = new ProductName();
    }
}
