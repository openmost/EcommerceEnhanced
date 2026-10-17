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
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\EventType;
use Piwik\Plugins\EcommerceEnhanced\Records;
use Piwik\Tracker\GoalManager;

/**
 * The GA4 monetization overview numbers: orders, revenue, items, purchasers, first time purchasers, visits with a cart
 * and visits with an order.
 *
 * Purchasers are unique visitors over the whole period, they cannot be summed from days: every period is computed
 * from the orders, through the (idsite, server_time) indexes only.
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

        $select = sprintf(
            'SUM(IF(log_conversion.idgoal = %1$d, 1, 0)) AS `%2$s`,
            ROUND(SUM(IF(log_conversion.idgoal = %1$d, log_conversion.revenue, 0)), 2) AS `%3$s`,
            SUM(IF(log_conversion.idgoal = %1$d, log_conversion.items, 0)) AS `%7$s`,
            COUNT(DISTINCT IF(log_conversion.idgoal = %1$d, log_conversion.idvisitor, NULL)) AS `%4$s`,
            COUNT(DISTINCT IF(log_conversion.idgoal = %1$d, log_conversion.idvisit, NULL)) AS `%5$s`,
            COUNT(DISTINCT log_conversion.idvisit) AS `%6$s`',
            GoalManager::IDGOAL_ORDER,
            Records::ORDERS,
            Records::REVENUE,
            Records::PURCHASERS,
            Records::CONVERTED_VISITS,
            Records::CART_VISITS,
            Records::ITEMS
        );

        $where = $logAggregator->getWhereStatement('log_conversion', 'server_time')
            . sprintf(' AND log_conversion.idgoal IN (%d, %d)', GoalManager::IDGOAL_ORDER, GoalManager::IDGOAL_CART);

        $query = $logAggregator->generateQuery($select, ['log_conversion'], $where, false, false);
        $row = $logAggregator->getDb()->fetchRow($query['sql'], $query['bind']) ?: [];

        // A first time purchaser placed the first order of their history in the period: the rank of each order is
        // stored with it at tracking time, so a visitor counts once whatever the period
        $where = $logAggregator->getWhereStatement(Schema::TABLE_EVENT, 'server_time')
            . sprintf(' AND %1$s.event_type = %2$d AND %1$s.order_no = 1', Schema::TABLE_EVENT, EventType::PURCHASE);
        $query = $logAggregator->generateQuery('COUNT(*)', [Schema::TABLE_EVENT], $where, false, false);
        $row[Records::FIRST_TIME_PURCHASERS] = $logAggregator->getDb()->fetchOne($query['sql'], $query['bind']);

        $values = [];
        foreach (array_keys(Records::BUYER_METRICS) as $name) {
            $values[$name] = isset($row[$name]) ? $row[$name] + 0 : 0;
        }

        return $values;
    }
}
