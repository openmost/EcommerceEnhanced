<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\Tracker\Payload;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\Site;
use Piwik\Tracker\GoalManager;

/**
 * The GA4 detail of each ecommerce step in the visitor log and in Live.getLastVisitsDetails: products with brand,
 * variant and quantity, list, promotion, search term or lead of the "Ecommerce" events, and coupon, payment,
 * shipping, customer type, currency and rank of the native orders. Steps are matched to their event or order by
 * time, event name and order id.
 */
class VisitorDetails extends VisitorDetailsAbstract
{
    /**
     * idvisit => list of steps, loaded once for the visits of the page
     *
     * @var array<int, array<int, array<string, mixed>>>
     */
    private static $stepsByVisit = [];

    private const EVENT_FIELDS = [
        'value' => 'value',
        'currency' => 'currency',
        'list_name' => 'listName',
        'promotion_name' => 'promotionName',
        'creative_name' => 'creativeName',
        'search_term' => 'searchTerm',
        'checkout_option' => 'checkoutOption',
        'coupon' => 'coupon',
        'payment_type' => 'paymentType',
        'shipping_tier' => 'shippingTier',
        'affiliation' => 'affiliation',
        'lead_source' => 'leadSource',
        'lead_status' => 'leadStatus',
    ];

    public function provideActionsForVisitIds(&$actions, $idVisits)
    {
        $idVisits = array_values(array_filter(array_map('intval', (array) $idVisits)));
        if (empty($idVisits)) {
            return;
        }

        $in = implode(',', $idVisits);
        $steps = [];
        $rows = $this->getDb()->fetchAll(sprintf(
            'SELECT idee_event, idvisit, event_type, server_time, idorder, customer_type, order_no, %s
            FROM %s WHERE idvisit IN (%s)',
            implode(', ', array_keys(self::EVENT_FIELDS)),
            Common::prefixTable(Schema::TABLE_EVENT),
            $in
        ));
        foreach ($rows as $row) {
            $steps[(int) $row['idee_event']] = $row + ['items' => []];
        }

        if (!empty($steps)) {
            $items = $this->getDb()->fetchAll(sprintf(
                'SELECT item.idee_event, item.brand, item.variant, item.price, item.quantity, product.name AS name, sku.name AS sku
                FROM %1$s AS item
                LEFT JOIN %2$s AS product ON product.idaction = item.idaction_name
                LEFT JOIN %2$s AS sku ON sku.idaction = item.idaction_sku
                WHERE item.idvisit IN (%3$s) ORDER BY item.idee_event, item.line_no',
                Common::prefixTable(Schema::TABLE_EVENT_ITEM),
                Common::prefixTable('log_action'),
                $in
            ));
            foreach ($items as $item) {
                if (isset($steps[(int) $item['idee_event']])) {
                    $steps[(int) $item['idee_event']]['items'][] = $item;
                }
            }
        }

        // An order keeps its products on the order lines
        $orderLines = $this->getDb()->fetchAll(sprintf(
            'SELECT line.idvisit, line.idorder, line.brand, line.variant, line.price, line.quantity, product.name AS name, sku.name AS sku
            FROM %1$s AS line
            LEFT JOIN %2$s AS product ON product.idaction = line.idaction_name
            LEFT JOIN %2$s AS sku ON sku.idaction = line.idaction_sku
            WHERE line.idvisit IN (%3$s) AND line.idgoal = %4$d ORDER BY line.line_no',
            Common::prefixTable(Schema::TABLE_ORDER_ITEM),
            Common::prefixTable('log_action'),
            $in,
            GoalManager::IDGOAL_ORDER
        ));

        foreach ($steps as $step) {
            if ((int) $step['event_type'] === EventType::PURCHASE) {
                foreach ($orderLines as $line) {
                    if ((int) $line['idvisit'] === (int) $step['idvisit'] && (string) $line['idorder'] === (string) $step['idorder']) {
                        $step['items'][] = $line;
                    }
                }
            }

            self::$stepsByVisit[(int) $step['idvisit']][] = $step;
        }
    }

    public function extendActionDetails(&$action, $nextAction, $visitorDetails)
    {
        $idVisit = (int) ($visitorDetails['idVisit'] ?? 0);
        if (empty(self::$stepsByVisit[$idVisit])) {
            return;
        }

        // The visitor log time is the website local time read as a UTC timestamp
        $zone = self::getTimezone(Site::getTimezoneFor((int) $visitorDetails['idSite']));
        $localTimestamp = function (array $step) use ($zone): int {
            $local = (new \DateTime((string) $step['server_time'], new \DateTimeZone('UTC')))->setTimezone($zone);

            return (int) strtotime($local->format('Y-m-d H:i:s') . ' UTC');
        };

        $step = null;
        // Read before or after the Events plugin formats the action: the event name is still in pageTitle before
        if (!empty($action['eventType']) || ($action['type'] ?? '') === 'event') {
            if (($action['eventCategory'] ?? '') !== EventType::EVENT_CATEGORY) {
                return;
            }

            $type = EventType::fromName((string) ($action['eventName'] ?? $action['pageTitle'] ?? ''));
            $step = $this->findStep($idVisit, function (array $step) use ($type, $action, $localTimestamp) {
                return (int) $step['event_type'] === $type && $localTimestamp($step) === (int) ($action['timestamp'] ?? 0);
            });
        } elseif (($action['type'] ?? '') === 'ecommerceOrder' && isset($action['orderId'])) {
            $step = $this->findStep($idVisit, function (array $step) use ($action) {
                return (int) $step['event_type'] === EventType::PURCHASE && (string) $step['idorder'] === (string) $action['orderId'];
            });
        }

        if ($step !== null) {
            $action['ecommerceEnhanced'] = $this->describe($step);
        }
    }

    public function renderActionTooltip($action, $visitInfo)
    {
        if (empty($action['ecommerceEnhanced'])) {
            return [];
        }

        $detail = $action['ecommerceEnhanced'];
        $lines = [];
        foreach ($detail['items'] ?? [] as $item) {
            $variant = array_filter([$item['brand'], $item['variant']]);
            $lines[] = sprintf('%s x %s%s', $item['quantity'], $item['name'], $variant ? ' (' . implode(', ', $variant) . ')' : '');
        }

        $labels = [
            'listName' => 'EcommerceEnhanced_ListName',
            'promotionName' => 'EcommerceEnhanced_PromotionName',
            'searchTerm' => 'EcommerceEnhanced_SearchTerm',
            'checkoutOption' => 'EcommerceEnhanced_CheckoutOption',
            'coupon' => 'EcommerceEnhanced_OrderCoupon',
            'paymentType' => 'EcommerceEnhanced_OrderPaymentType',
            'shippingTier' => 'EcommerceEnhanced_OrderShippingTier',
            'affiliation' => 'EcommerceEnhanced_OrderAffiliation',
            'customerType' => 'EcommerceEnhanced_OrderCustomerType',
            'leadSource' => 'EcommerceEnhanced_VisitorLogLeadSource',
            'currency' => 'SitesManager_Currency',
        ];
        foreach ($labels as $key => $translationKey) {
            if (isset($detail[$key]) && $detail[$key] !== '') {
                $lines[] = Piwik::translate($translationKey) . ': ' . $detail[$key];
            }
        }

        if (isset($detail['orderRank'])) {
            $lines[] = $detail['orderRank'] === 1
                ? Piwik::translate('EcommerceEnhanced_VisitorLogFirstOrder')
                : Piwik::translate('EcommerceEnhanced_VisitorLogRepeatOrder', [$detail['orderRank']]);
        }

        // Matomo appends the entries to the page URL line
        return [[20, "\n" . implode("\n", $lines)]];
    }

    /**
     * Matomo accepts "UTC+2" style timezones that PHP does not know.
     */
    private static function getTimezone(string $timezone): \DateTimeZone
    {
        if (preg_match('/^UTC([+-])(\d+(?:\.\d+)?)$/', $timezone, $matches)) {
            $minutes = (int) round((float) $matches[2] * 60);
            $timezone = sprintf('%s%02d:%02d', $matches[1], intdiv($minutes, 60), $minutes % 60);
        }

        return new \DateTimeZone($timezone);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStep(int $idVisit, callable $matches): ?array
    {
        foreach (self::$stepsByVisit[$idVisit] as $step) {
            if ($matches($step)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $step
     * @return array<string, mixed>
     */
    private function describe(array $step): array
    {
        $detail = ['step' => EventType::getName((int) $step['event_type'])];
        foreach (self::EVENT_FIELDS as $column => $key) {
            if ($step[$column] !== null && $step[$column] !== '') {
                $detail[$key] = is_numeric($step[$column]) && $column === 'value' ? (float) $step[$column] : $step[$column];
            }
        }

        if ($step['customer_type'] !== null) {
            $detail['customerType'] = (int) $step['customer_type'] === Payload::CUSTOMER_TYPE_RETURNING
                ? Piwik::translate('EcommerceEnhanced_CustomerTypeReturning')
                : Piwik::translate('EcommerceEnhanced_CustomerTypeNew');
        }

        if ($step['order_no'] !== null) {
            $detail['orderRank'] = (int) $step['order_no'];
        }

        $detail['items'] = array_map(function (array $item) {
            return [
                'sku' => (string) $item['sku'],
                'name' => (string) ($item['name'] ?? $item['sku']),
                'brand' => (string) $item['brand'],
                'variant' => (string) $item['variant'],
                'price' => $item['price'] === null ? null : (float) $item['price'],
                'quantity' => (int) $item['quantity'],
            ];
        }, $step['items']);

        return $detail;
    }
}
