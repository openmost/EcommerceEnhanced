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

class PromotionId extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_EVENT;
    protected $columnName = 'promotion_id';
    protected $segmentName = 'eePromotionId';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_PromotionId';
    protected $namePlural = 'EcommerceEnhanced_PromotionIdPlural';
    protected $acceptValues = 'Id of a promotion viewed or selected, for example SUMMER_FUN';
}
