<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Metrics\Formatter;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\Dimension\OwnTableDimension;
use Piwik\Plugins\EcommerceEnhanced\EventType;

/**
 * Ecommerce step of an interaction, segmented by its GA4 event name: eeStep==add_to_cart;eeStep!=purchase
 * selects the visits that added to cart without buying.
 */
class EcommerceStep extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_EVENT;
    protected $columnName = 'event_type';
    protected $segmentName = 'eeStep';
    protected $type = self::TYPE_ENUM;
    protected $nameSingular = 'EcommerceEnhanced_EcommerceStep';
    protected $namePlural = 'EcommerceEnhanced_EcommerceStepPlural';

    public function __construct()
    {
        parent::__construct();

        $this->acceptValues = 'GA4 event name: ' . implode(', ', array_keys(EventType::getNamesWithActions()));
        $this->sqlFilterValue = function ($value) {
            if (is_numeric($value)) {
                return (int) $value;
            }

            return EventType::fromName((string) $value) ?? -1;
        };
    }

    public function getEnumColumnValues()
    {
        $values = [];
        foreach (EventType::getAll() as $type) {
            $values[$type] = EventType::getName($type);
        }

        return $values;
    }

    public function formatValue($value, $idSite, Formatter $formatter)
    {
        return EventType::getEventAction((int) $value) ?? $value;
    }
}
