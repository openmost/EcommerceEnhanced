<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Columns\OrderCoupon;
use Piwik\Plugins\EcommerceEnhanced\Metrics;

class GetCoupons extends Base
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

        $this->action = 'getCoupons';
        $this->name = Piwik::translate('EcommerceEnhanced_OrderCouponPlural');
        $this->documentation = Piwik::translate('EcommerceEnhanced_CouponsDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_OrderDetails';
        $this->order = 5;
        $this->dimension = new OrderCoupon();
    }
}
