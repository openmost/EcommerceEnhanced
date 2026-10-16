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

class PromotionCreativeSlot extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_EVENT;
    protected $columnName = 'creative_slot';
    protected $segmentName = 'eePromotionCreativeSlot';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_PromotionCreativeSlot';
    protected $namePlural = 'EcommerceEnhanced_PromotionCreativeSlotPlural';
    protected $acceptValues = 'Slot of a promotion viewed or selected, for example featured_app_1';
}
