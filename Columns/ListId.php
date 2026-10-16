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

class ListId extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_EVENT;
    protected $columnName = 'list_id';
    protected $segmentName = 'eeListId';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_ListId';
    protected $namePlural = 'EcommerceEnhanced_ListIdPlural';
    protected $acceptValues = 'Id of an item list viewed or selected, for example related_products';
}
