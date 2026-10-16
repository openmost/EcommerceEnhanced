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

class ItemLocationId extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_ORDER_ITEM;
    protected $columnName = 'location_id';
    protected $segmentName = 'eeItemLocationId';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_ItemLocationId';
    protected $namePlural = 'EcommerceEnhanced_ItemLocationIdPlural';
    protected $acceptValues = 'Physical location of a product in the cart or order, for example a store id';
}
