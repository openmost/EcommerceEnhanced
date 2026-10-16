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

class ItemListId extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_ORDER_ITEM;
    protected $columnName = 'list_id';
    protected $segmentName = 'eeItemListId';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_ItemListId';
    protected $namePlural = 'EcommerceEnhanced_ItemListIdPlural';
    protected $acceptValues = 'Id of the item list a product in the cart or order came from, for example related_products';
}
