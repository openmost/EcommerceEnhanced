<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Columns\ListName;
use Piwik\Plugins\EcommerceEnhanced\Metrics;

class GetItemLists extends Base
{
    protected $metricNames = [
        Metrics::LIST_VIEWS,
        Metrics::LIST_CLICKS,
        Metrics::ITEMS_ADDED_TO_CART,
        Metrics::ITEMS_PURCHASED,
        Metrics::ITEM_REVENUE,
    ];

    protected $ratioNames = [
        Metrics::LIST_CLICK_RATE,
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getItemLists';
        $this->name = Piwik::translate('EcommerceEnhanced_ListNamePlural');
        $this->documentation = Piwik::translate('EcommerceEnhanced_ItemListsDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_Merchandising';
        $this->order = 5;
        $this->dimension = new ListName();
    }
}
