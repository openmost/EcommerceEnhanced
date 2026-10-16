<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\RecordBuilders;

use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Record;
use Piwik\Common;
use Piwik\Plugins\EcommerceEnhanced\Records;
use Piwik\Tracker\GoalManager;

/**
 * The GA4 monetization overview numbers: orders, revenue, items, purchasers, first time purchasers, visits with a cart
 * and visits with an order.
 *
 * Purchasers are unique visitors over the whole period, they cannot be summed from days: every period is computed
 * from the orders of log_conversion, a cheap query as it only reads orders.
 */
class BuyerReports extends Base
{
    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        $records = [];
        foreach (array_keys(Records::BUYER_METRICS) as $name) {
            $records[] = Record::make(Record::TYPE_NUMERIC, $name);
        }

        return $records;
    }

    public function buildForNonDayPeriod(ArchiveProcessor $archiveProcessor): void
    {
        $this->buildFromLogs($archiveProcessor);
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $logAggregator = $archiveProcessor->getLogAggregator();
        $periodStart = $archiveProcessor->getParams()->getDateTimeStart()->getDatetime();

        // A first time purchaser has no order before the period, in any visit
        $firstTime = sprintf(
            'NOT EXISTS (SELECT 1 FROM %s AS previous WHERE previous.idsite = log_conversion.idsite AND previous.idvisitor = log_conversion.idvisitor AND previous.idgoal = %d AND previous.server_time < %s)',
            Common::prefixTable('log_conversion'),
            GoalManager::IDGOAL_ORDER,
            $logAggregator->getDb()->quote($periodStart)
        );

        $select = sprintf(
            'SUM(IF(log_conversion.idgoal = %1$d, 1, 0)) AS `%2$s`,
            ROUND(SUM(IF(log_conversion.idgoal = %1$d, log_conversion.revenue, 0)), 2) AS `%3$s`,
            SUM(IF(log_conversion.idgoal = %1$d, log_conversion.items, 0)) AS `%9$s`,
            COUNT(DISTINCT IF(log_conversion.idgoal = %1$d, log_conversion.idvisitor, NULL)) AS `%4$s`,
            COUNT(DISTINCT IF(log_conversion.idgoal = %1$d AND %5$s, log_conversion.idvisitor, NULL)) AS `%6$s`,
            COUNT(DISTINCT IF(log_conversion.idgoal = %1$d, log_conversion.idvisit, NULL)) AS `%7$s`,
            COUNT(DISTINCT log_conversion.idvisit) AS `%8$s`',
            GoalManager::IDGOAL_ORDER,
            Records::ORDERS,
            Records::REVENUE,
            Records::PURCHASERS,
            $firstTime,
            Records::FIRST_TIME_PURCHASERS,
            Records::CONVERTED_VISITS,
            Records::CART_VISITS,
            Records::ITEMS
        );

        $where = $logAggregator->getWhereStatement('log_conversion', 'server_time')
            . sprintf(' AND log_conversion.idgoal IN (%d, %d)', GoalManager::IDGOAL_ORDER, GoalManager::IDGOAL_CART);

        $query = $logAggregator->generateQuery($select, ['log_conversion'], $where, false, false);
        $row = $logAggregator->getDb()->fetchRow($query['sql'], $query['bind']) ?: [];

        $values = [];
        foreach (array_keys(Records::BUYER_METRICS) as $name) {
            $values[$name] = isset($row[$name]) ? $row[$name] + 0 : 0;
        }

        return $values;
    }
}
