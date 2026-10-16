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
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\EventType;
use Piwik\Plugins\EcommerceEnhanced\Metrics;
use Piwik\Plugins\EcommerceEnhanced\Records;
use Piwik\Tracker\GoalManager;

/**
 * Product, brand, variant, item list and promotion reports, the GA4 "items" view: how often items were seen,
 * added to cart and bought, and the revenue they made. Order lines are counted once per variant line.
 */
class ItemReports extends Base
{
    private const EVENT_ITEM = Schema::TABLE_EVENT_ITEM;
    private const ORDER_ITEM = Schema::TABLE_ORDER_ITEM;
    private const EVENT = Schema::TABLE_EVENT;

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        $records = [];
        foreach ($this->getSortColumns() as $name => $sortColumn) {
            $records[] = Record::make(Record::TYPE_BLOB, $name)
                ->setMaxRowsInTable(Records::MAX_ROWS)
                ->setColumnToSortByBeforeTruncation($sortColumn);
        }

        return $records;
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $logAggregator = $archiveProcessor->getLogAggregator();

        $productName = 'log_action_item_name.name';
        $variant = "IF({t}.variant IS NULL, NULL, CONCAT(log_action_item_name.name, ' - ', {t}.variant))";

        return [
            Records::PRODUCTS => $this->buildItemTable($logAggregator, $productName, $productName, true),
            Records::BRANDS => $this->buildItemTable($logAggregator, self::EVENT_ITEM . '.brand', self::ORDER_ITEM . '.brand', false),
            Records::VARIANTS => $this->buildItemTable($logAggregator, $this->forTable($variant, self::EVENT_ITEM), $this->forTable($variant, self::ORDER_ITEM), true),
            Records::ITEM_LISTS => $this->buildListTable($logAggregator),
            Records::PROMOTIONS => $this->buildPromotionTable($logAggregator),
        ];
    }

    /**
     * @return array<string, string> record => metric used to keep the top rows
     */
    private function getSortColumns(): array
    {
        return [
            Records::PRODUCTS => Metrics::ITEMS_VIEWED,
            Records::BRANDS => Metrics::ITEMS_VIEWED,
            Records::VARIANTS => Metrics::ITEMS_VIEWED,
            Records::ITEM_LISTS => Metrics::LIST_VIEWS,
            Records::PROMOTIONS => Metrics::PROMOTION_VIEWS,
        ];
    }

    private function buildItemTable(LogAggregator $logAggregator, string $eventLabel, string $orderLabel, bool $withName): DataTable
    {
        $table = new DataTable();

        $this->addRows($table, $logAggregator, self::EVENT_ITEM, $eventLabel, [
            Metrics::ITEMS_VIEWED => sprintf('SUM(IF(%s.event_type = %d, 1, 0))', self::EVENT_ITEM, EventType::VIEW_ITEM),
            Metrics::ITEMS_ADDED_TO_CART => sprintf('SUM(IF(%1$s.event_type = %2$d, %1$s.quantity, 0))', self::EVENT_ITEM, EventType::ADD_TO_CART),
        ], sprintf('%s.event_type IN (%d, %d)', self::EVENT_ITEM, EventType::VIEW_ITEM, EventType::ADD_TO_CART), $withName ? $this->joinName(self::EVENT_ITEM) : []);

        $this->addOrderRows($table, $logAggregator, $orderLabel, $withName);

        return $table;
    }

    private function buildListTable(LogAggregator $logAggregator): DataTable
    {
        $table = new DataTable();

        $this->addRows($table, $logAggregator, self::EVENT, sprintf('COALESCE(%1$s.list_name, %1$s.list_id)', self::EVENT), [
            Metrics::LIST_VIEWS => sprintf('SUM(IF(%s.event_type = %d, 1, 0))', self::EVENT, EventType::VIEW_ITEM_LIST),
            Metrics::LIST_CLICKS => sprintf('SUM(IF(%s.event_type = %d, 1, 0))', self::EVENT, EventType::SELECT_ITEM),
        ], sprintf('%1$s.event_type IN (%2$d, %3$d) AND (%1$s.list_name IS NOT NULL OR %1$s.list_id IS NOT NULL)', self::EVENT, EventType::VIEW_ITEM_LIST, EventType::SELECT_ITEM));

        $this->addCartAndOrderRowsFromLists($table, $logAggregator, 'COALESCE({t}.list_name, {t}.list_id)', '({t}.list_name IS NOT NULL OR {t}.list_id IS NOT NULL)');

        return $table;
    }

    private function buildPromotionTable(LogAggregator $logAggregator): DataTable
    {
        $table = new DataTable();

        $this->addRows($table, $logAggregator, self::EVENT, sprintf('COALESCE(%1$s.promotion_name, %1$s.promotion_id)', self::EVENT), [
            Metrics::PROMOTION_VIEWS => sprintf('SUM(IF(%s.event_type = %d, 1, 0))', self::EVENT, EventType::VIEW_PROMOTION),
            Metrics::PROMOTION_CLICKS => sprintf('SUM(IF(%s.event_type = %d, 1, 0))', self::EVENT, EventType::SELECT_PROMOTION),
        ], sprintf('%1$s.event_type IN (%2$d, %3$d) AND (%1$s.promotion_name IS NOT NULL OR %1$s.promotion_id IS NOT NULL)', self::EVENT, EventType::VIEW_PROMOTION, EventType::SELECT_PROMOTION));

        $this->addCartAndOrderRowsFromLists($table, $logAggregator, 'COALESCE({t}.promotion_name, {t}.promotion_id)', '({t}.promotion_name IS NOT NULL OR {t}.promotion_id IS NOT NULL)');

        return $table;
    }

    /**
     * Items added to cart and bought after being selected in a list or a promotion.
     */
    private function addCartAndOrderRowsFromLists(DataTable $table, LogAggregator $logAggregator, string $label, string $where): void
    {
        $this->addRows($table, $logAggregator, self::EVENT_ITEM, $this->forTable($label, self::EVENT_ITEM), [
            Metrics::ITEMS_ADDED_TO_CART => sprintf('SUM(%s.quantity)', self::EVENT_ITEM),
        ], sprintf('%s.event_type = %d AND ', self::EVENT_ITEM, EventType::ADD_TO_CART) . $this->forTable($where, self::EVENT_ITEM));

        $this->addRows($table, $logAggregator, self::ORDER_ITEM, $this->forTable($label, self::ORDER_ITEM), $this->getOrderMetrics(), sprintf('%s.idgoal = %d AND ', self::ORDER_ITEM, GoalManager::IDGOAL_ORDER) . $this->forTable($where, self::ORDER_ITEM));
    }

    private function addOrderRows(DataTable $table, LogAggregator $logAggregator, string $label, bool $withName): void
    {
        $this->addRows($table, $logAggregator, self::ORDER_ITEM, $label, $this->getOrderMetrics(), sprintf('%s.idgoal = %d', self::ORDER_ITEM, GoalManager::IDGOAL_ORDER), $withName ? $this->joinName(self::ORDER_ITEM) : []);
    }

    /**
     * @return array<string, string>
     */
    private function getOrderMetrics(): array
    {
        return [
            Metrics::ITEMS_PURCHASED => sprintf('SUM(%s.quantity)', self::ORDER_ITEM),
            Metrics::ITEM_REVENUE => sprintf('ROUND(SUM(%1$s.price * %1$s.quantity), 2)', self::ORDER_ITEM),
            Metrics::ORDERS => sprintf('COUNT(DISTINCT %1$s.idvisit, %1$s.idorder)', self::ORDER_ITEM),
        ];
    }

    /**
     * SQL fragments are written with {t} for the log table they apply to.
     */
    private function forTable(string $sql, string $logTable): string
    {
        return str_replace('{t}', $logTable, $sql);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function joinName(string $logTable): array
    {
        return [[
            'table' => 'log_action',
            'tableAlias' => 'log_action_item_name',
            'joinOn' => $logTable . '.idaction_name = log_action_item_name.idaction',
        ]];
    }
}
