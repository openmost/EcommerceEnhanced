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
use Piwik\ArchiveProcessor\RecordBuilder;
use Piwik\DataTable;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\EventType;
use Piwik\Plugins\EcommerceEnhanced\Metrics;
use Piwik\Plugins\EcommerceEnhanced\Records;
use Piwik\Tracker\GoalManager;

/**
 * The GA4 shopping and checkout behavior funnels: visits that reached each step, in any order. No Funnels plugin
 * is needed, the steps come from the ecommerce events and the orders.
 */
class FunnelReports extends Base
{
    public const STEP_ALL_VISITS = 'all_visits';

    public const SHOPPING_STEPS = [self::STEP_ALL_VISITS, 'view_item', 'add_to_cart', 'begin_checkout', 'purchase'];
    public const CHECKOUT_STEPS = ['begin_checkout', 'add_shipping_info', 'add_payment_info', 'purchase'];

    public function __construct()
    {
        // No truncation: it would sort the steps by visits, the funnel keeps them in step order
        RecordBuilder::__construct();
    }

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        return [
            Record::make(Record::TYPE_BLOB, Records::SHOPPING_BEHAVIOR),
            Record::make(Record::TYPE_BLOB, Records::CHECKOUT_BEHAVIOR),
        ];
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $logAggregator = $archiveProcessor->getLogAggregator();
        $visits = $this->countVisits($archiveProcessor);

        $steps = new DataTable();
        $types = [EventType::VIEW_ITEM, EventType::ADD_TO_CART, EventType::BEGIN_CHECKOUT, EventType::ADD_SHIPPING_INFO, EventType::ADD_PAYMENT_INFO];
        $this->addRows($steps, $logAggregator, Schema::TABLE_EVENT, Schema::TABLE_EVENT . '.event_type', [
            Metrics::VISITS => sprintf('COUNT(DISTINCT %s.idvisit)', Schema::TABLE_EVENT),
        ], sprintf('%s.event_type IN (%s)', Schema::TABLE_EVENT, implode(',', $types)));

        $this->addRows($steps, $logAggregator, 'log_conversion', "'purchase'", [
            Metrics::VISITS => 'COUNT(DISTINCT log_conversion.idvisit)',
        ], 'log_conversion.idgoal = ' . GoalManager::IDGOAL_ORDER);

        $counts = [self::STEP_ALL_VISITS => $visits];
        foreach ($steps->getRows() as $row) {
            $label = (string) $row->getColumn('label');
            $name = is_numeric($label) ? EventType::getName((int) $label) : $label;
            $counts[$name] = (int) $row->getColumn(Metrics::VISITS);
        }

        return [
            Records::SHOPPING_BEHAVIOR => $this->buildFunnel(self::SHOPPING_STEPS, $counts),
            Records::CHECKOUT_BEHAVIOR => $this->buildFunnel(self::CHECKOUT_STEPS, $counts),
        ];
    }

    /**
     * @param string[] $steps
     * @param array<string, int> $counts
     */
    private function buildFunnel(array $steps, array $counts): DataTable
    {
        $table = new DataTable();
        foreach ($steps as $step) {
            $table->addRowFromSimpleArray(['label' => $step, Metrics::VISITS => $counts[$step] ?? 0]);
        }

        return $table;
    }

    private function countVisits(ArchiveProcessor $archiveProcessor): int
    {
        $logAggregator = $archiveProcessor->getLogAggregator();
        $where = $logAggregator->getWhereStatement('log_visit', 'visit_last_action_time');
        $query = $logAggregator->generateQuery('COUNT(*) AS visits', ['log_visit'], $where, false, false);

        return (int) $logAggregator->getDb()->fetchOne($query['sql'], $query['bind']);
    }
}
