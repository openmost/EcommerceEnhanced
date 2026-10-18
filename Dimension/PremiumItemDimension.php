<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Dimension;

use Piwik\Columns\Dimension;
use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;

/**
 * An item dimension listed in the segment editor and in Custom Reports while Premium is not activated, marked
 * "Premium". It has the id and the segment name of the Premium dimension, so nothing changes for the user once
 * Premium is activated. Until then, a segment using it is refused with a message naming the Premium plugin, and so is
 * a custom report (see EcommerceEnhanced::refusePremiumDimensionsInCustomReport).
 */
class PremiumItemDimension extends Dimension
{
    public const ID_PREFIX = 'EcommerceEnhancedPremium.ItemDimension';

    public const SEGMENT_PREFIX = 'eeItemDimension';

    protected $category = 'Goals_Ecommerce';

    protected $type = self::TYPE_TEXT;

    protected $dbTableName = Schema::TABLE_ORDER_ITEM;

    /**
     * @var int
     */
    private $slot;

    /**
     * @var string
     */
    private $dimensionName;

    public function __construct(int $slot, string $name)
    {
        $this->slot = $slot;
        $this->dimensionName = $name;
        $this->columnName = 'custom' . $slot;
        $this->segmentName = self::SEGMENT_PREFIX . $slot;
        $this->nameSingular = $this->getName();
        $this->acceptValues = self::getRequiredMessage($name);
        $this->sqlFilter = function () use ($name) {
            throw new \Exception(self::getRequiredMessage($name));
        };
    }

    public function getId()
    {
        return self::ID_PREFIX . $this->slot;
    }

    public function getName()
    {
        return Piwik::translate('EcommerceEnhanced_PremiumDimensionName', [$this->dimensionName]);
    }

    public static function getRequiredMessage(string $name): string
    {
        return Piwik::translate('EcommerceEnhanced_PremiumDimensionRequired', [$name]);
    }
}
