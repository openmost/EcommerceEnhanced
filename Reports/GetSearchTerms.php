<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Columns\SearchTerm;
use Piwik\Plugins\EcommerceEnhanced\Metrics;

class GetSearchTerms extends Base
{
    protected $metricNames = [
        Metrics::SEARCHES,
        Metrics::VISITS,
        Metrics::ITEMS_LISTED,
        Metrics::CONVERTED_VISITS,
    ];

    protected $ratioNames = [
        Metrics::CONVERSION_RATE,
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getSearchTerms';
        $this->name = Piwik::translate('EcommerceEnhanced_SearchTermPlural');
        $this->documentation = Piwik::translate('EcommerceEnhanced_SearchTermsDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_Merchandising';
        $this->order = 22;
        $this->dimension = new SearchTerm();
    }
}
