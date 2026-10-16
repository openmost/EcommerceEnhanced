<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Dimension;

use Piwik\Common;
use Piwik\Date;
use Piwik\Db;

/**
 * Segment editor suggestions read from the column itself. The default suggestions come from the visitor log,
 * which does not expose the plugin columns.
 */
class SuggestedValues
{
    private const DAYS = 60;

    public static function fromColumn(string $table, string $column): callable
    {
        return function ($idSite, $maxValuesToReturn) use ($table, $column) {
            if (!is_numeric($idSite)) {
                return [];
            }

            $sql = sprintf(
                'SELECT `%1$s` AS value, COUNT(*) AS hits FROM %2$s WHERE idsite = ? AND server_time >= ? AND `%1$s` IS NOT NULL GROUP BY `%1$s` ORDER BY hits DESC LIMIT %3$d',
                $column,
                Common::prefixTable($table),
                max(1, min(1000, (int) $maxValuesToReturn))
            );

            $since = Date::now()->subDay(self::DAYS)->getDatetime();

            return array_column(Db::fetchAll($sql, [(int) $idSite, $since]), 'value');
        };
    }
}
