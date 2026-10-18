<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

/**
 * Ecommerce interaction types. Ids are stored in log_ee_event.event_type and log_ee_event_item.event_type, they
 * are never renumbered. Names are the GA4 event names, also used as the Matomo event name.
 */
class EventType
{
    public const VIEW_ITEM_LIST = 1;
    public const SELECT_ITEM = 2;
    public const VIEW_ITEM = 3;
    public const ADD_TO_WISHLIST = 4;
    public const ADD_TO_CART = 5;
    public const REMOVE_FROM_CART = 6;
    public const VIEW_CART = 7;
    public const BEGIN_CHECKOUT = 8;
    public const ADD_SHIPPING_INFO = 9;
    public const ADD_PAYMENT_INFO = 10;
    public const CHECKOUT_PROGRESS = 11;
    public const PURCHASE = 12;
    public const REFUND = 13;
    public const VIEW_PROMOTION = 14;
    public const SELECT_PROMOTION = 15;

    public const EVENT_CATEGORY = 'Ecommerce';

    /**
     * id => [GA4 event name, Matomo event action]. The event action is tracking data, so it stays in English
     * whatever the language of the Matomo user.
     */
    private const TYPES = [
        self::VIEW_ITEM_LIST => ['view_item_list', 'View item list'],
        self::SELECT_ITEM => ['select_item', 'Select item'],
        self::VIEW_ITEM => ['view_item', 'View item'],
        self::ADD_TO_WISHLIST => ['add_to_wishlist', 'Add to wishlist'],
        self::ADD_TO_CART => ['add_to_cart', 'Add to cart'],
        self::REMOVE_FROM_CART => ['remove_from_cart', 'Remove from cart'],
        self::VIEW_CART => ['view_cart', 'View cart'],
        self::BEGIN_CHECKOUT => ['begin_checkout', 'Begin checkout'],
        self::ADD_SHIPPING_INFO => ['add_shipping_info', 'Add shipping info'],
        self::ADD_PAYMENT_INFO => ['add_payment_info', 'Add payment info'],
        self::CHECKOUT_PROGRESS => ['checkout_progress', 'Checkout progress'],
        self::PURCHASE => ['purchase', 'Purchase'],
        self::REFUND => ['refund', 'Refund'],
        self::VIEW_PROMOTION => ['view_promotion', 'View promotion'],
        self::SELECT_PROMOTION => ['select_promotion', 'Select promotion'],
    ];

    /**
     * Viewing is not interacting: these events do not change the bounce rate unless a site opts in.
     */
    public const NON_INTERACTION_BY_DEFAULT = [
        self::VIEW_ITEM_LIST,
        self::VIEW_ITEM,
        self::VIEW_CART,
        self::VIEW_PROMOTION,
    ];

    /**
     * @return int[]
     */
    public static function getAll(): array
    {
        return array_keys(self::TYPES);
    }

    public static function getName(int $type): ?string
    {
        return self::TYPES[$type][0] ?? null;
    }

    public static function getEventAction(int $type): ?string
    {
        return self::TYPES[$type][1] ?? null;
    }

    public static function fromName(string $name): ?int
    {
        foreach (self::TYPES as $type => [$typeName]) {
            if ($typeName === $name) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> GA4 event name => Matomo event action
     */
    public static function getNamesWithActions(): array
    {
        $names = [];
        foreach (self::TYPES as [$name, $action]) {
            $names[$name] = $action;
        }

        return $names;
    }
}
