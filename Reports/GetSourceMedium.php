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

class GetSourceMedium extends Base
{
    protected $labelTranslationKey = 'EcommerceEnhanced_SourceMedium';

    protected $metricNames = [
        Metrics::VISITS,
        Metrics::ORDERS,
        Metrics::REVENUE,
    ];

    protected $ratioNames = [
        Metrics::CONVERSION_RATE,
        Metrics::AVERAGE_ORDER_VALUE,
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getSourceMedium';
        $this->name = Piwik::translate('EcommerceEnhanced_SalesBySourceMedium');
        $this->documentation = Piwik::translate('EcommerceEnhanced_SourceMediumDocumentation');
        $this->subcategoryId = 'Ecommerce_Sales';
        $this->order = 1;
    }
}
