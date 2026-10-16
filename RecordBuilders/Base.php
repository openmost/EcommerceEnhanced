<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\RecordBuilders;

use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\RecordBuilder;
use Piwik\DataAccess\LogAggregator;
use Piwik\DataTable;
use Piwik\Plugins\EcommerceEnhanced\Records;

abstract class Base extends RecordBuilder
{
    public function __construct()
    {
        parent::__construct(Records::MAX_ROWS);
    }

    public function isEnabled(ArchiveProcessor $archiveProcessor): bool
    {
        return $archiveProcessor->getParams()->getSite()->isEcommerceEnabled();
    }

    /**
     * Runs one aggregation on a log table and adds its rows to the table, summed by label.
     *
     * @param array<string, string> $metrics metric name => SQL aggregate
     * @param array<int, array<string, string>> $joins
     */
    protected function addRows(DataTable $table, LogAggregator $logAggregator, string $logTable, string $label, array $metrics, string $where, array $joins = []): void
    {
        $select = $label . ' AS label';
        foreach ($metrics as $name => $sql) {
            $select .= sprintf(', %s AS `%s`', $sql, $name);
        }

        $where = $logAggregator->getWhereStatement($logTable, 'server_time') . ' AND ' . $where;
        $query = $logAggregator->generateQuery($select, array_merge([$logTable], $joins), $where, $label, false);
        $result = $logAggregator->getDb()->query($query['sql'], $query['bind']);

        while ($row = $result->fetch()) {
            $rowLabel = $row['label'] === null || $row['label'] === '' ? Records::NOT_SET : (string) $row['label'];
            unset($row['label']);

            $columns = [];
            foreach ($row as $name => $value) {
                $columns[$name] = $value === null ? 0 : $value + 0;
            }

            $table->sumRowWithLabel($rowLabel, $columns);
        }
    }
}
