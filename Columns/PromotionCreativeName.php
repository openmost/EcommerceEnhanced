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

class PromotionCreativeName extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_EVENT;
    protected $columnName = 'creative_name';
    protected $segmentName = 'eePromotionCreativeName';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_PromotionCreativeName';
    protected $namePlural = 'EcommerceEnhanced_PromotionCreativeNamePlural';
    protected $acceptValues = 'Creative of a promotion viewed or selected, for example summer_banner2';
}
