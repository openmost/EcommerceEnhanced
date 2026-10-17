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
use Piwik\DbHelper;

/**
 * Own tables of the plugin. Native ecommerce tables are never altered, the only change to a core table is the
 * set of NULLable ee_* columns on log_conversion, handled by the conversion dimensions.
 */
class Schema
{
    public const TABLE_ORDER_ITEM = 'log_ee_order_item';
    public const TABLE_EVENT = 'log_ee_event';
    public const TABLE_EVENT_ITEM = 'log_ee_event_item';

    public const CUSTOM_SLOTS = 10;

    /**
     * Columns referencing log_action, declared to the unused action purge.
     */
    public const ITEM_ACTION_COLUMNS = [
        'idaction_sku',
        'idaction_name',
        'idaction_category',
        'idaction_category2',
        'idaction_category3',
        'idaction_category4',
        'idaction_category5',
    ];

    public static function getTables(): array
    {
        return [self::TABLE_ORDER_ITEM, self::TABLE_EVENT, self::TABLE_EVENT_ITEM];
    }

    public function install(): void
    {
        DbHelper::createTable(self::TABLE_ORDER_ITEM, '
            `idee_order_item` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idsite` INT UNSIGNED NOT NULL,
            `idvisit` BIGINT UNSIGNED NOT NULL,
            `server_time` DATETIME NOT NULL,
            `idgoal` TINYINT NOT NULL,
            `idorder` VARCHAR(100) NOT NULL,
            `line_no` SMALLINT UNSIGNED NOT NULL,
            ' . $this->getItemColumnsSql() . ',
            `attribution` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`idee_order_item`),
            UNIQUE KEY `uniq_line` (`idvisit`, `idgoal`, `idorder`, `line_no`),
            INDEX `index_idsite_servertime` (`idsite`, `server_time`)');

        DbHelper::createTable(self::TABLE_EVENT, '
            `idee_event` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idsite` INT UNSIGNED NOT NULL,
            `idvisit` BIGINT UNSIGNED NOT NULL,
            `server_time` DATETIME NOT NULL,
            `event_type` TINYINT UNSIGNED NOT NULL,
            `source` TINYINT UNSIGNED NOT NULL,
            `idorder` VARCHAR(100) NULL,
            `order_ref` BINARY(20) NULL,
            `currency` CHAR(3) NULL,
            `value` DOUBLE NULL,
            `value_mode` TINYINT UNSIGNED NULL,
            `tax` DOUBLE NULL,
            `shipping` DOUBLE NULL,
            `discount` DOUBLE NULL,
            `coupon` VARCHAR(255) NULL,
            `payment_type` VARCHAR(100) NULL,
            `shipping_tier` VARCHAR(100) NULL,
            `affiliation` VARCHAR(100) NULL,
            `customer_type` TINYINT UNSIGNED NULL,
            `customer_ltv` DOUBLE NULL,
            `checkout_step` TINYINT UNSIGNED NULL,
            `checkout_option` VARCHAR(100) NULL,
            `list_id` VARCHAR(100) NULL,
            `list_name` VARCHAR(100) NULL,
            `promotion_id` VARCHAR(100) NULL,
            `promotion_name` VARCHAR(100) NULL,
            `creative_name` VARCHAR(100) NULL,
            `creative_slot` VARCHAR(100) NULL,
            `search_term` VARCHAR(100) NULL,
            `lead_source` VARCHAR(100) NULL,
            `lead_status` VARCHAR(100) NULL,
            `items_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `dropped_items` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`idee_event`),
            INDEX `index_idsite_servertime` (`idsite`, `server_time`),
            INDEX `index_idvisit` (`idvisit`),
            INDEX `index_idsite_order_ref` (`idsite`, `order_ref`)');

        DbHelper::createTable(self::TABLE_EVENT_ITEM, '
            `idee_event_item` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `idee_event` BIGINT UNSIGNED NOT NULL,
            `idsite` INT UNSIGNED NOT NULL,
            `idvisit` BIGINT UNSIGNED NOT NULL,
            `server_time` DATETIME NOT NULL,
            `event_type` TINYINT UNSIGNED NOT NULL,
            `line_no` SMALLINT UNSIGNED NOT NULL,
            ' . $this->getItemColumnsSql() . ',
            PRIMARY KEY (`idee_event_item`),
            INDEX `index_idsite_servertime` (`idsite`, `server_time`),
            INDEX `index_event` (`idee_event`),
            INDEX `index_idvisit` (`idvisit`)');
    }


    private function getItemColumnsSql(): string
    {
        $columns = [
            '`idaction_sku` INT UNSIGNED NOT NULL',
            '`idaction_name` INT UNSIGNED NULL',
            '`idaction_category` INT UNSIGNED NULL',
            '`idaction_category2` INT UNSIGNED NULL',
            '`idaction_category3` INT UNSIGNED NULL',
            '`idaction_category4` INT UNSIGNED NULL',
            '`idaction_category5` INT UNSIGNED NULL',
            '`price` DOUBLE NULL',
            '`discount` DOUBLE NULL',
            '`quantity` SMALLINT UNSIGNED NOT NULL DEFAULT 1',
            '`brand` VARCHAR(100) NULL',
            '`variant` VARCHAR(100) NULL',
            '`item_group_id` VARCHAR(100) NULL',
            '`coupon` VARCHAR(100) NULL',
            '`affiliation` VARCHAR(100) NULL',
            '`location_id` VARCHAR(100) NULL',
            '`list_id` VARCHAR(100) NULL',
            '`list_name` VARCHAR(100) NULL',
            '`list_index` SMALLINT UNSIGNED NULL',
            '`promotion_id` VARCHAR(100) NULL',
            '`promotion_name` VARCHAR(100) NULL',
            '`creative_name` VARCHAR(100) NULL',
            '`creative_slot` VARCHAR(100) NULL',
        ];

        for ($slot = 1; $slot <= self::CUSTOM_SLOTS; $slot++) {
            $columns[] = sprintf('`custom%d` VARCHAR(100) NULL', $slot);
        }

        return implode(",\n            ", $columns);
    }
}
