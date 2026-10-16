<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Categories;

use Piwik\Category\Subcategory;
use Piwik\Piwik;

class OrderDetailsSubcategory extends Subcategory
{
    protected $categoryId = 'Goals_Ecommerce';
    protected $id = 'EcommerceEnhanced_OrderDetails';
    protected $order = 14;

    public function getHelp()
    {
        return '<p>' . Piwik::translate('EcommerceEnhanced_OrderDetailsHelp') . '</p>';
    }
}
