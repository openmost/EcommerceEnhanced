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
use Piwik\Plugins\EcommerceEnhanced\Columns\OrderCurrency;
use Piwik\Plugins\EcommerceEnhanced\Metrics;

class GetCurrencies extends Base
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

        $this->action = 'getCurrencies';
        $this->name = Piwik::translate('EcommerceEnhanced_OrderCurrencyPlural');
        $this->documentation = Piwik::translate('EcommerceEnhanced_CurrenciesDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_OrderDetails';
        $this->order = 12;
        $this->defaultView = Pie::ID;
        $this->dimension = new OrderCurrency();
    }
}
