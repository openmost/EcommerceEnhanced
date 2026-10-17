<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Tracker;

use Piwik\Plugins\EcommerceEnhanced\EventType;

/**
 * Validates the eex_d payload sent by the tracker: a GA4 shaped ecommerce object already normalised by the
 * tracker. The server never trusts it: unknown keys are dropped, strings are cleaned and capped, numbers are
 * bounded. Personal data never reaches this point (the tracker never reads it), and no key outside the
 * allowlist below is ever stored.
 */
class Payload
{
    public const VERSION = '1';

    public const MAX_JSON_BYTES = 131072;
    public const MAX_ITEMS = 200;
    public const MAX_MONEY = 1000000000000;
    public const MAX_CUSTOM_PARAMS = 10;

    /**
     * Shops often add more fields to their items than the website records: up to 30 are read, the configured item
     * dimensions among them are kept.
     */
    public const MAX_RECEIVED_CUSTOM_PARAMS = 30;

    /**
     * Item lists, promotions and search results: a page shows a few dozen products, never 200.
     */
    public const MAX_LIST_ITEMS = 30;

    /**
     * Free text fields a visitor or a form may fill with personal data, and that no setting chooses.
     */
    private const FREE_TEXT_FIELDS = ['search_term', 'lead_source', 'lead_status'];

    /**
     * An email address, or a phone number written with a + prefix or as 0X XX XX XX XX.
     */
    private const PERSONAL_VALUE = '/[^\s@]+@[^\s@]+\.[^\s@]{2,}|\+\d[\d\s().-]{7,}\d|\b0[1-9](?:[\s.-]?\d{2}){4}\b/u';

    public const SOURCE_DATALAYER_GA4 = 1;
    public const SOURCE_GTAG = 2;
    public const SOURCE_DATALAYER_UA = 3;
    public const SOURCE_JS_API = 4;
    public const SOURCE_SERVER_API = 5;

    public const CUSTOMER_TYPE_NEW = 1;
    public const CUSTOMER_TYPE_RETURNING = 2;

    public const ATTRIBUTION_NONE = 0;
    public const ATTRIBUTION_EXPLICIT = 1;
    public const ATTRIBUTION_REMEMBERED = 2;

    /**
     * Event level string fields => max length.
     */
    private const EVENT_STRINGS = [
        'transaction_id' => 100,
        'coupon' => 255,
        'payment_type' => 100,
        'shipping_tier' => 100,
        'affiliation' => 100,
        'checkout_option' => 100,
        'item_list_id' => 100,
        'item_list_name' => 100,
        'promotion_id' => 100,
        'promotion_name' => 100,
        'creative_name' => 100,
        'creative_slot' => 100,
        'search_term' => 100,
        'lead_source' => 100,
        'lead_status' => 100,
    ];

    private const EVENT_MONEY = ['value', 'tax', 'shipping', 'discount', 'customer_lifetime_value'];

    /**
     * Item level string fields => max length.
     */
    private const ITEM_STRINGS = [
        'item_id' => 255,
        'item_name' => 255,
        'item_category' => 255,
        'item_category2' => 255,
        'item_category3' => 255,
        'item_category4' => 255,
        'item_category5' => 255,
        'item_brand' => 100,
        'item_variant' => 100,
        'item_group_id' => 100,
        'coupon' => 100,
        'affiliation' => 100,
        'location_id' => 100,
        'item_list_id' => 100,
        'item_list_name' => 100,
        'promotion_id' => 100,
        'promotion_name' => 100,
        'creative_name' => 100,
        'creative_slot' => 100,
    ];

    /**
     * Item fields that fall back to the event level value when the item lacks them, as GA4 does.
     */
    private const ITEM_INHERITED = [
        'item_list_id',
        'item_list_name',
        'promotion_id',
        'promotion_name',
        'creative_name',
        'creative_slot',
    ];

    /**
     * @return array{type: int, event: array<string, mixed>, items: list<array<string, mixed>>}|null
     */
    public static function parse(string $eventName, string $json): ?array
    {
        $type = EventType::fromName($eventName);
        if ($type === null || $json === '' || strlen($json) > self::MAX_JSON_BYTES) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }

        return self::fromArray($type, $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{type: int, event: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public static function fromArray(int $type, array $data): array
    {
        $event = [
            'source' => self::toInt($data['source'] ?? null, 1, 5) ?? self::SOURCE_DATALAYER_GA4,
            'currency' => self::toCurrency($data['currency'] ?? null),
            'customer_type' => self::toCustomerType($data['customer_type'] ?? null),
            'checkout_step' => self::toInt($data['checkout_step'] ?? null, 1, 255),
            'dropped_items' => self::toInt($data['dropped_items'] ?? null, 0, 65535) ?? 0,
        ];

        foreach (self::EVENT_STRINGS as $key => $max) {
            $event[$key] = self::toText($data[$key] ?? null, $max);
        }

        foreach (self::FREE_TEXT_FIELDS as $key) {
            $event[$key] = self::withoutPersonalData($event[$key]);
        }

        foreach (self::EVENT_MONEY as $key) {
            $event[$key] = self::toMoney($data[$key] ?? null);
        }

        if ($event['coupon'] !== null) {
            $codes = array_filter(array_map(function ($code) {
                return self::toText($code, 100);
            }, explode(',', $event['coupon'])));
            $event['coupon'] = empty($codes) ? null : self::toText(implode(',', $codes), 255);
        }

        if ($event['discount'] !== null) {
            $event['discount'] = abs($event['discount']);
        }

        $items = [];
        $rawItems = isset($data['items']) && is_array($data['items']) ? array_values($data['items']) : [];
        $isListOnly = in_array($type, [EventType::VIEW_ITEM_LIST, EventType::VIEW_PROMOTION, EventType::VIEW_SEARCH_RESULTS], true);
        $maxItems = $isListOnly ? self::MAX_LIST_ITEMS : self::MAX_ITEMS;
        foreach ($rawItems as $rawItem) {
            if (count($items) >= $maxItems) {
                $event['dropped_items']++;
                continue;
            }

            $item = is_array($rawItem) ? self::toItem($rawItem, $event) : null;
            if ($item === null) {
                $event['dropped_items']++;
                continue;
            }

            $items[] = $item;
        }

        // GA4 allows the list and the promotion on the items only: list and promotion steps take them from their
        // first item. Other steps keep them on their items only, the list there is an attribution, not a view
        $isListStep = in_array($type, [EventType::VIEW_ITEM_LIST, EventType::SELECT_ITEM, EventType::VIEW_PROMOTION, EventType::SELECT_PROMOTION, EventType::VIEW_SEARCH_RESULTS], true);
        foreach (self::ITEM_INHERITED as $key) {
            if ($isListStep && $event[$key] === null && !empty($items) && $items[0][$key] !== null) {
                $event[$key] = $items[0][$key];
            }
        }

        $event['items_count'] = count($items);
        $event['dropped_items'] = min(65535, $event['dropped_items']);

        return ['type' => $type, 'event' => $event, 'items' => $items];
    }

    /**
     * Sum of price * quantity, the GA4 definition of value.
     *
     * @param list<array<string, mixed>> $items
     */
    public static function getItemsTotal(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $total += ($item['price'] ?? 0) * $item['quantity'];
        }

        return round($total, 2);
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $event
     * @return array<string, mixed>|null
     */
    private static function toItem(array $raw, array $event): ?array
    {
        $item = [];
        foreach (self::ITEM_STRINGS as $key => $max) {
            $item[$key] = self::toText($raw[$key] ?? null, $max);
        }

        $item['item_id'] = $item['item_id'] ?? (self::toText($raw['item_name'] ?? null, 255));
        $item['item_name'] = $item['item_name'] ?? $item['item_id'];
        if ($item['item_id'] === null) {
            return null;
        }

        foreach (self::ITEM_INHERITED as $key) {
            $item[$key] = $item[$key] ?? $event[$key];
        }

        $item['price'] = self::toMoney($raw['price'] ?? null);
        $discount = self::toMoney($raw['discount'] ?? null);
        $item['discount'] = $discount === null ? null : abs($discount);
        $quantity = self::toMoney($raw['quantity'] ?? null);
        $item['quantity'] = $quantity === null || $quantity <= 0 ? 1 : (int) min(65535, max(1, round($quantity)));
        $item['index'] = self::toInt($raw['index'] ?? null, 0, 65535);
        $item['attribution'] = self::toInt($raw['attribution'] ?? null, 0, 2)
            ?? ($item['item_list_name'] !== null || $item['promotion_name'] !== null ? self::ATTRIBUTION_EXPLICIT : self::ATTRIBUTION_NONE);

        $item['custom'] = [];
        if (isset($raw['custom']) && is_array($raw['custom'])) {
            foreach ($raw['custom'] as $name => $value) {
                if (count($item['custom']) >= self::MAX_RECEIVED_CUSTOM_PARAMS) {
                    break;
                }

                $name = self::toParamName($name);
                // Only the item dimensions the website configured are stored: what they hold is the choice of the site
                $value = is_scalar($value) && !is_bool($value) ? self::toText((string) $value, 100) : null;
                if ($name !== null && $value !== null) {
                    $item['custom'][$name] = $value;
                }
            }
        }

        return $item;
    }

    /**
     * Drops a value that holds an email address or a phone number: requests built outside the tracker, server side
     * or by hand, never went through its personal key filter.
     */
    public static function withoutPersonalData(?string $value): ?string
    {
        return $value !== null && preg_match(self::PERSONAL_VALUE, $value) ? null : $value;
    }

    public static function toText($value, int $max): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = self::numberToString($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max, 'UTF-8');
    }

    /**
     * Accepts numbers and strings like "1 299,90 €", "1,299.90" or "12,5".
     */
    public static function toMoney($value): ?float
    {
        if (is_bool($value) || $value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = (string) preg_replace('/[^\d,.\-]/u', '', $value);
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma !== false && $lastDot !== false) {
                $decimal = $lastComma > $lastDot ? ',' : '.';
                $thousands = $decimal === ',' ? '.' : ',';
                $value = str_replace([$thousands, $decimal], ['', '.'], $value);
            } elseif ($lastComma !== false) {
                $value = preg_match('/,\d{1,2}$/', $value) && substr_count($value, ',') === 1
                    ? str_replace(',', '.', $value)
                    : str_replace(',', '', $value);
            }

            if (!is_numeric($value)) {
                return null;
            }

            $value = (float) $value;
        }

        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        $value = (float) $value;
        if (!is_finite($value) || abs($value) > self::MAX_MONEY) {
            return null;
        }

        return $value;
    }

    public static function toInt($value, int $min, int $max): ?int
    {
        $number = self::toMoney($value);
        if ($number === null) {
            return null;
        }

        $number = (int) round($number);

        return $number < $min || $number > $max ? null : $number;
    }

    public static function toCurrency($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z]{3}$/', $value) ? $value : null;
    }

    public static function toCustomerType($value): ?int
    {
        if ($value === true || $value === 1 || $value === '1') {
            return self::CUSTOMER_TYPE_NEW;
        }

        if ($value === false || $value === 0 || $value === '0') {
            return self::CUSTOMER_TYPE_RETURNING;
        }

        if (!is_string($value)) {
            return null;
        }

        switch (strtolower(trim($value))) {
            case 'new':
            case 'true':
                return self::CUSTOMER_TYPE_NEW;
            case 'returning':
            case 'false':
                return self::CUSTOMER_TYPE_RETURNING;
            default:
                return null;
        }
    }

    private static function toParamName($name): ?string
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $name)) {
            return null;
        }

        return $name;
    }

    private static function numberToString($value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        $string = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $string === '-0' ? '0' : $string;
    }
}
