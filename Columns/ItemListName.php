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

class ItemListName extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_ORDER_ITEM;
    protected $columnName = 'list_name';
    protected $segmentName = 'eeItemListName';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_ItemListName';
    protected $namePlural = 'EcommerceEnhanced_ItemListNamePlural';
    protected $acceptValues = 'Name of the item list a product in the cart or order came from, for example Related products';
}
