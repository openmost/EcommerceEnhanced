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
use Piwik\DataTable;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\EventType;
use Piwik\Plugins\EcommerceEnhanced\Metrics;
use Piwik\Plugins\EcommerceEnhanced\Records;
use Piwik\Tracker\GoalManager;

/**
 * Product search terms (view_search_results): searches, visits, the items they led to and the visits that
 * ordered.
 */
class SearchReports extends Base
{
    private const EVENT = Schema::TABLE_EVENT;

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        return [
            Record::make(Record::TYPE_BLOB, Records::SEARCH_TERMS)
                ->setMaxRowsInTable(Records::MAX_ROWS)
                ->setColumnToSortByBeforeTruncation(Metrics::SEARCHES),
        ];
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $logAggregator = $archiveProcessor->getLogAggregator();
        $table = new DataTable();
        $where = sprintf('%1$s.event_type = %2$d AND %1$s.search_term IS NOT NULL', self::EVENT, EventType::VIEW_SEARCH_RESULTS);

        $this->addRows($table, $logAggregator, self::EVENT, self::EVENT . '.search_term', [
            Metrics::SEARCHES => 'COUNT(*)',
            Metrics::VISITS => sprintf('COUNT(DISTINCT %s.idvisit)', self::EVENT),
            Metrics::ITEMS_LISTED => sprintf('SUM(%s.items_count)', self::EVENT),
        ], $where);

        // Searches are counted above, joining the orders would count them once per order
        $this->addRows($table, $logAggregator, self::EVENT, self::EVENT . '.search_term', [
            Metrics::CONVERTED_VISITS => 'COUNT(DISTINCT search_order.idvisit)',
        ], $where, [[
            'table' => 'log_conversion',
            'tableAlias' => 'search_order',
            'joinOn' => sprintf('%s.idvisit = search_order.idvisit AND search_order.idgoal = %d', self::EVENT, GoalManager::IDGOAL_ORDER),
        ]]);

        return [Records::SEARCH_TERMS => $table];
    }
}
