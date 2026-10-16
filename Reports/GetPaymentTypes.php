<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Columns\OrderPaymentType;
use Piwik\Plugins\EcommerceEnhanced\Metrics;

class GetPaymentTypes extends Base
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

        $this->action = 'getPaymentTypes';
        $this->name = Piwik::translate('EcommerceEnhanced_OrderPaymentTypePlural');
        $this->documentation = Piwik::translate('EcommerceEnhanced_PaymentTypesDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_OrderDetails';
        $this->order = 2;
        $this->dimension = new OrderPaymentType();
    }
}
