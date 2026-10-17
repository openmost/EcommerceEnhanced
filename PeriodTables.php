<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\DataTable;
use Piwik\Period;
use Piwik\Site;

/**
 * Reports computed from the logs on each call (transactions, customer analyses) answer multiple periods
 * (date=last7, date=2025-01-01,2025-01-31 with period=day) like archived reports: one table per period, keyed by
 * date.
 */
class PeriodTables
{
    /**
     * @param callable(string, string): DataTable $build table of one period, from the period and its date
     * @return DataTable|DataTable\Map
     */
    public static function build(int $idSite, string $period, string $date, callable $build)
    {
        if (!Period::isMultiplePeriod($date, $period)) {
            return $build($period, $date);
        }

        $map = new DataTable\Map();
        $map->setKeyName('date');

        $range = new Period\Range($period, $date, Site::getTimezoneFor($idSite));
        foreach ($range->getSubperiods() as $subPeriod) {
            $map->addTable($build($period, $subPeriod->getDateStart()->toString()), $subPeriod->getPrettyString());
        }

        return $map;
    }
}
