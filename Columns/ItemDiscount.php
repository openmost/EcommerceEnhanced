<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\Dimension\OwnTableDimension;

class ItemDiscount extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_ORDER_ITEM;
    protected $columnName = 'discount';
    protected $segmentName = 'eeItemDiscount';
    protected $type = self::TYPE_MONEY;
    protected $nameSingular = 'EcommerceEnhanced_ItemDiscount';
    protected $acceptValues = 'Unit discount of a product in the cart or order, for example 5';
}
