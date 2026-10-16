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
use Piwik\Plugins\EcommerceEnhanced\Metrics;
use Piwik\Plugins\EcommerceEnhanced\Records;
use Piwik\Plugins\EcommerceEnhanced\Tracker\Payload;
use Piwik\Tracker\GoalManager;

/**
 * Orders by coupon, payment type, shipping tier, affiliation, customer type and currency. Read from the order
 * rows of log_conversion, so each order and its revenue is counted exactly once.
 */
class OrderReports extends Base
{
    /**
     * record => log_conversion column
     */
    private const COLUMNS = [
        Records::COUPONS => 'ee_coupon',
        Records::PAYMENT_TYPES => 'ee_payment_type',
        Records::SHIPPING_TIERS => 'ee_shipping_tier',
        Records::AFFILIATIONS => 'ee_affiliation',
        Records::CUSTOMER_TYPES => 'ee_customer_type',
        Records::CURRENCIES => 'ee_currency',
    ];

    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        $records = [];
        foreach (array_keys(self::COLUMNS) as $name) {
            $records[] = Record::make(Record::TYPE_BLOB, $name)
                ->setMaxRowsInTable(Records::MAX_ROWS)
                ->setColumnToSortByBeforeTruncation(Metrics::ORDERS);
        }

        return $records;
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $logAggregator = $archiveProcessor->getLogAggregator();
        $tables = [];

        foreach (self::COLUMNS as $name => $column) {
            $table = new DataTable();
            $this->addRows($table, $logAggregator, 'log_conversion', 'log_conversion.' . $column, [
                Metrics::ORDERS => 'COUNT(*)',
                Metrics::REVENUE => 'ROUND(SUM(log_conversion.revenue), 2)',
                Metrics::ITEMS => 'SUM(log_conversion.items)',
            ], 'log_conversion.idgoal = ' . GoalManager::IDGOAL_ORDER);

            if ($name === Records::CUSTOMER_TYPES) {
                $this->renameCustomerTypes($table);
            }

            $tables[$name] = $table;
        }

        return $tables;
    }

    /**
     * Archived labels stay language neutral, the report translates them.
     */
    private function renameCustomerTypes(DataTable $table): void
    {
        $names = [
            (string) Payload::CUSTOMER_TYPE_NEW => 'new',
            (string) Payload::CUSTOMER_TYPE_RETURNING => 'returning',
        ];

        foreach ($table->getRows() as $row) {
            $label = (string) $row->getColumn('label');
            if (isset($names[$label])) {
                $row->setColumn('label', $names[$label]);
            }
        }
    }
}
