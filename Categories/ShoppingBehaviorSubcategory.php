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

class ShoppingBehaviorSubcategory extends Subcategory
{
    protected $categoryId = 'Goals_Ecommerce';
    protected $id = 'EcommerceEnhanced_ShoppingBehavior';
    protected $order = 12;

    public function getHelp()
    {
        return '<p>' . Piwik::translate('EcommerceEnhanced_ShoppingBehaviorHelp') . '</p>';
    }
}
