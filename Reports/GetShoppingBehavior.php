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

class GetShoppingBehavior extends Base
{
    protected $labelTranslationKey = 'EcommerceEnhanced_EcommerceStep';

    protected $keepRowOrder = true;

    protected $metricNames = [
        Metrics::VISITS,
    ];

    // Computed by the API across rows, declared here so they are formatted as percentages
    protected $ratioNames = [
        Metrics::STEP_RATE,
        Metrics::ABANDONMENT_RATE,
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getShoppingBehavior';
        $this->name = Piwik::translate('EcommerceEnhanced_ShoppingBehaviorReport');
        $this->documentation = Piwik::translate('EcommerceEnhanced_ShoppingBehaviorDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_ShoppingBehavior';
        $this->order = 1;
    }
}
