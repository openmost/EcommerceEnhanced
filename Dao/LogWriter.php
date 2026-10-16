<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\EcommerceEnhanced\EventType;
use Piwik\Tracker\Action;
use Piwik\Tracker\GoalManager;
use Piwik\Tracker\TableLogAction;

/**
 * Writes the own log tables at tracking time.
 */
class LogWriter
{
    private const ITEM_STRING_COLUMNS = [
        'item_brand' => 'brand',
        'item_variant' => 'variant',
        'item_group_id' => 'item_group_id',
        'coupon' => 'coupon',
        'affiliation' => 'affiliation',
        'location_id' => 'location_id',
        'item_list_id' => 'list_id',
        'item_list_name' => 'list_name',
        'promotion_id' => 'promotion_id',
        'promotion_name' => 'promotion_name',
        'creative_name' => 'creative_name',
        'creative_slot' => 'creative_slot',
    ];

    private const EVENT_COLUMNS = [
        'source' => 'source',
        'currency' => 'currency',
        'value' => 'value',
        'value_mode' => 'value_mode',
        'tax' => 'tax',
        'shipping' => 'shipping',
        'discount' => 'discount',
        'coupon' => 'coupon',
        'payment_type' => 'payment_type',
        'shipping_tier' => 'shipping_tier',
        'affiliation' => 'affiliation',
        'customer_type' => 'customer_type',
        'customer_lifetime_value' => 'customer_ltv',
        'checkout_step' => 'checkout_step',
        'checkout_option' => 'checkout_option',
        'item_list_id' => 'list_id',
        'item_list_name' => 'list_name',
        'promotion_id' => 'promotion_id',
        'promotion_name' => 'promotion_name',
        'creative_name' => 'creative_name',
        'creative_slot' => 'creative_slot',
        'search_term' => 'search_term',
        'lead_source' => 'lead_source',
        'lead_status' => 'lead_status',
        'items_count' => 'items_count',
        'dropped_items' => 'dropped_items',
    ];

    /**
     * @param array<string, mixed> $context idsite, idvisit, server_time
     * @param array<string, mixed> $event normalised event (see Tracker\Payload)
     * @param list<array<string, mixed>> $items
     * @param array<string, int> $customSlots
     */
    public function insertEvent(array $context, int $type, array $event, array $items, ?string $idOrder, ?string $orderRef, array $customSlots): int
    {
        $row = [
            'idsite' => $context['idsite'],
            'idvisit' => $context['idvisit'],
            'server_time' => $context['server_time'],
            'event_type' => $type,
            'idorder' => $idOrder,
            'order_ref' => $orderRef,
        ];

        foreach (self::EVENT_COLUMNS as $key => $column) {
            $row[$column] = $event[$key] ?? null;
        }

        $row['items_count'] = $row['items_count'] ?? 0;
        $row['dropped_items'] = $row['dropped_items'] ?? 0;

        $this->insert(Schema::TABLE_EVENT, $row);
        $idEvent = (int) Db::get()->lastInsertId();

        if (!empty($items)) {
            $rows = [];
            foreach ($this->buildItemRows($items, $customSlots, false) as $lineNo => $itemRow) {
                $rows[] = [
                    'idee_event' => $idEvent,
                    'idsite' => $context['idsite'],
                    'idvisit' => $context['idvisit'],
                    'server_time' => $context['server_time'],
                    'event_type' => $type,
                    'line_no' => $lineNo,
                ] + $itemRow;
            }

            $this->insertMany(Schema::TABLE_EVENT_ITEM, $rows);
        }

        return $idEvent;
    }

    /**
     * Mirrors the native cart: the cart lines of the visit are replaced by the new snapshot.
     *
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $items
     * @param array<string, int> $customSlots
     */
    public function replaceCartItems(array $context, array $items, array $customSlots): void
    {
        $this->deleteCartItems((int) $context['idvisit']);
        $this->insertOrderLines($context, GoalManager::IDGOAL_CART, (string) GoalManager::ITEM_IDORDER_ABANDONED_CART, $items, $customSlots);
    }

    /**
     * An order turns the cart of the visit into order lines, as the native ecommerce does.
     *
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $items
     * @param array<string, int> $customSlots
     */
    public function insertOrderItems(array $context, string $idOrder, array $items, array $customSlots): void
    {
        $this->deleteCartItems((int) $context['idvisit']);
        $this->insertOrderLines($context, GoalManager::IDGOAL_ORDER, $idOrder, $items, $customSlots);
    }

    public function hasOrderConversion(int $idVisit, string $idOrder): bool
    {
        $sql = sprintf('SELECT 1 FROM %s WHERE idvisit = ? AND idgoal = ? AND idorder = ? LIMIT 1', Common::prefixTable('log_conversion'));

        return (bool) Db::fetchOne($sql, [$idVisit, GoalManager::IDGOAL_ORDER, $idOrder]);
    }

    public function findOrderId(int $idSite, string $orderRef): ?string
    {
        $sql = sprintf('SELECT idorder FROM %s WHERE idsite = ? AND order_ref = ? AND event_type = ? LIMIT 1', Common::prefixTable(Schema::TABLE_EVENT));
        $idOrder = Db::fetchOne($sql, [$idSite, $orderRef, EventType::PURCHASE]);

        return $idOrder === false || $idOrder === null ? null : (string) $idOrder;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<array<string, mixed>> $items
     * @param array<string, int> $customSlots
     */
    private function insertOrderLines(array $context, int $idGoal, string $idOrder, array $items, array $customSlots): void
    {
        $rows = [];
        foreach ($this->buildItemRows($items, $customSlots, true) as $lineNo => $itemRow) {
            $rows[] = [
                'idsite' => $context['idsite'],
                'idvisit' => $context['idvisit'],
                'server_time' => $context['server_time'],
                'idgoal' => $idGoal,
                'idorder' => $idOrder,
                'line_no' => $lineNo,
            ] + $itemRow;
        }

        $this->insertMany(Schema::TABLE_ORDER_ITEM, $rows);
    }

    private function deleteCartItems(int $idVisit): void
    {
        $sql = sprintf('DELETE FROM %s WHERE idvisit = ? AND idgoal = ?', Common::prefixTable(Schema::TABLE_ORDER_ITEM));
        Db::query($sql, [$idVisit, GoalManager::IDGOAL_CART]);
    }

    /**
     * Item lines with their log_action references, keyed by line number. SKU, name and categories share the
     * native ecommerce action types, so the strings are shared with log_conversion_item.
     *
     * @param list<array<string, mixed>> $items
     * @param array<string, int> $customSlots
     * @param bool $withAttribution order lines record how their list or promotion was found, interaction lines do not
     * @return array<int, array<string, mixed>>
     */
    private function buildItemRows(array $items, array $customSlots, bool $withAttribution): array
    {
        $actions = [];
        foreach ($items as $lineNo => $item) {
            $actions["{$lineNo}_sku"] = [$item['item_id'], Action::TYPE_ECOMMERCE_ITEM_SKU];
            $actions["{$lineNo}_name"] = [$item['item_name'], Action::TYPE_ECOMMERCE_ITEM_NAME];
            foreach (['', '2', '3', '4', '5'] as $level) {
                if (isset($item['item_category' . $level])) {
                    $actions["{$lineNo}_category{$level}"] = [$item['item_category' . $level], Action::TYPE_ECOMMERCE_ITEM_CATEGORY];
                }
            }
        }

        $ids = empty($actions) ? [] : TableLogAction::loadIdsAction($actions);

        $rows = [];
        foreach ($items as $lineNo => $item) {
            if (empty($ids["{$lineNo}_sku"])) {
                continue;
            }

            $row = [
                'idaction_sku' => $ids["{$lineNo}_sku"],
                'idaction_name' => $ids["{$lineNo}_name"] ?? null,
                'idaction_category' => $ids["{$lineNo}_category"] ?? null,
                'idaction_category2' => $ids["{$lineNo}_category2"] ?? null,
                'idaction_category3' => $ids["{$lineNo}_category3"] ?? null,
                'idaction_category4' => $ids["{$lineNo}_category4"] ?? null,
                'idaction_category5' => $ids["{$lineNo}_category5"] ?? null,
                'price' => $item['price'],
                'discount' => $item['discount'],
                'quantity' => $item['quantity'],
                'list_index' => $item['index'],
            ];

            foreach (self::ITEM_STRING_COLUMNS as $key => $column) {
                $row[$column] = $item[$key];
            }

            for ($slot = 1; $slot <= Schema::CUSTOM_SLOTS; $slot++) {
                $row['custom' . $slot] = null;
            }

            foreach ($item['custom'] as $name => $value) {
                if (isset($customSlots[$name])) {
                    $row['custom' . $customSlots[$name]] = $value;
                }
            }

            if ($withAttribution) {
                $row['attribution'] = $item['attribution'];
            }

            $rows[$lineNo] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(string $table, array $row): void
    {
        $this->insertMany($table, [$row]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function insertMany(string $table, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $columns = array_keys(reset($rows));
        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $bind = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bind[] = $row[$column] ?? null;
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (`%s`) VALUES %s',
            Common::prefixTable($table),
            implode('`,`', $columns),
            implode(',', array_fill(0, count($rows), $placeholders))
        );

        Db::query($sql, $bind);
    }
}
