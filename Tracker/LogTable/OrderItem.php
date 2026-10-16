<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Tracker\LogTable;

use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Tracker\LogTable;

/**
 * Registered so segments, GDPR deletion and export, and the raw data purge cover the table. No idvisitor is
 * stored, rows are reached through idvisit.
 */
class OrderItem extends LogTable
{
    public function getName()
    {
        return Schema::TABLE_ORDER_ITEM;
    }

    public function getIdColumn()
    {
        return 'idee_order_item';
    }

    public function getColumnToJoinOnIdVisit()
    {
        return 'idvisit';
    }

    public function getPrimaryKey()
    {
        return ['idee_order_item'];
    }

    public function getDateTimeColumn()
    {
        return 'server_time';
    }

    public function hasIdVisitorColumn(): bool
    {
        return false;
    }
}
