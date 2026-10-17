<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Piwik;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Pie;
use Piwik\Plugins\EcommerceEnhanced\Columns\OrderShippingTier;
use Piwik\Plugins\EcommerceEnhanced\Metrics;

class GetShippingTiers extends Base
{
    protected $metricNames = [
        Metrics::ORDERS,
        Metrics::REVENUE,
        Metrics::ITEMS,
    ];

    protected $ratioNames = [
        Metrics::AVERAGE_ORDER_VALUE,
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getShippingTiers';
        $this->name = Piwik::translate('EcommerceEnhanced_OrderShippingTierPlural');
        $this->documentation = Piwik::translate('EcommerceEnhanced_ShippingTiersDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_OrderDetails';
        $this->order = 4;
        $this->defaultView = Pie::ID;
        $this->dimension = new OrderShippingTier();
    }
}
