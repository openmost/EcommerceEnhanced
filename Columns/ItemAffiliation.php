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

class ItemAffiliation extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_ORDER_ITEM;
    protected $columnName = 'affiliation';
    protected $segmentName = 'eeItemAffiliation';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_ItemAffiliation';
    protected $namePlural = 'EcommerceEnhanced_ItemAffiliationPlural';
    protected $acceptValues = 'Store or supplier of a product in the cart or order';
}
