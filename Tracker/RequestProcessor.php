<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Tracker;

use Piwik\Common;
use Piwik\Date;
use Piwik\Plugins\EcommerceEnhanced\Dao\LogWriter;
use Piwik\Plugins\EcommerceEnhanced\EventType;
use Piwik\Plugins\EcommerceEnhanced\MeasurableSettings;
use Piwik\SettingsPiwik;
use Piwik\Tracker;
use Piwik\Exception\InvalidRequestParameterException;
use Piwik\Tracker\GoalManager;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visit\VisitProperties;

/**
 * Reads the eex_* parameters the tracker adds to two kinds of requests:
 *
 * - Matomo event requests (category "Ecommerce"), one per ecommerce interaction. They write log_ee_event and
 *   log_ee_event_item, and follow the site settings for the Events report and the bounce rate.
 * - Native ecommerce requests (cart update, order). They write the variant aware lines of log_ee_order_item and
 *   the ee_* columns of log_conversion. The native request itself is never changed, except the revenue split
 *   when the site declares that its GA4 value includes tax and shipping.
 *
 * With the plugin deactivated, core ignores the eex_* parameters and still records both kinds of requests.
 */
class RequestProcessor extends Tracker\RequestProcessor
{
    public const METADATA = 'EcommerceEnhanced';

    public const VALUE_MODE_SUBTOTAL = 1;
    public const VALUE_MODE_GRAND_TOTAL = 2;

    private const KIND_EVENT = 'event';
    private const KIND_CART = 'cart';
    private const KIND_ORDER = 'order';

    /**
     * @var LogWriter
     */
    private $writer;

    /**
     * @var array<int, SiteConfig>
     */
    private $configs = [];

    public function __construct(LogWriter $writer)
    {
        $this->writer = $writer;
    }

    public function manipulateRequest(Request $request)
    {
        $params = $request->getParams();
        if ((string) ($params['eex_v'] ?? '') !== Payload::VERSION) {
            return;
        }

        $type = EventType::fromName((string) ($params['eex_e'] ?? ''));
        if ($type === null) {
            return;
        }

        $kind = $this->getKind($request, $type);
        if ($kind === null) {
            return;
        }

        $data = null;
        $json = (string) ($params['eex_d'] ?? '');
        if ($json !== '') {
            $data = Payload::parse((string) $params['eex_e'], $json);
        }

        $config = $this->getConfig($request->getIdSite());

        if ($data !== null) {
            $data = $this->applyCouponStorage($data, $config->getCouponStorage());
        }

        if ($kind === self::KIND_ORDER && $data !== null) {
            $data['event']['value_mode'] = $this->applyValueMode($request, $data, $config->valueIncludesTaxAndShipping());
        }

        $raw = $request->getRawParams();
        $rawOrderId = isset($raw['ec_id']) && is_string($raw['ec_id']) ? $raw['ec_id'] : null;

        // Core rejects an order id seen before, but not once PrivacyManager replaced it by a random hash. The keyed
        // hash of the raw id restores the check, with the same outcome as core
        if ($kind === self::KIND_ORDER && ($orderRef = self::getOrderRef($rawOrderId)) !== null && $this->writer->findOrderId($request->getIdSite(), $orderRef) !== null) {
            throw new InvalidRequestParameterException(sprintf('Invalid non-unique idsite/idorder combination (%d, %s), conversion was not inserted.', $request->getIdSite(), $rawOrderId));
        }

        $request->setMetadata(self::METADATA, 'request', [
            'kind' => $kind,
            'type' => $type,
            'data' => $data,
            'rawOrderId' => $rawOrderId,
        ]);
    }

    public function afterRequestProcessed(VisitProperties $visitProperties, Request $request)
    {
        $eeRequest = $this->getEeRequest($request);
        if ($eeRequest === null || $eeRequest['kind'] !== self::KIND_EVENT) {
            return;
        }

        if (!$this->getConfig($request->getIdSite())->isInEventsReport($eeRequest['type'])) {
            $request->setMetadata('Actions', 'action', null);
            $request->setMetadata('Goals', 'goalsConverted', []);
            $request->setMetadata('Goals', 'visitIsConverted', false);
        }
    }

    public function onNewVisit(VisitProperties $visitProperties, Request $request)
    {
        $eeRequest = $this->getEeRequest($request);
        if ($eeRequest === null || $eeRequest['kind'] !== self::KIND_EVENT) {
            return;
        }

        $this->afterRequestProcessed($visitProperties, $request);

        // A dataLayer replayed before the first page view can open the visit with a view_item_list event
        if ($this->getConfig($request->getIdSite())->isNonInteraction($eeRequest['type'])) {
            $visitProperties->setProperty('visit_total_actions', 0);
            $visitProperties->setProperty('visit_total_interactions', 0);
        }
    }

    public function onExistingVisit(&$valuesToUpdate, VisitProperties $visitProperties, Request $request)
    {
        $eeRequest = $this->getEeRequest($request);
        if ($eeRequest === null || $eeRequest['kind'] !== self::KIND_EVENT) {
            return;
        }

        $this->afterRequestProcessed($visitProperties, $request);

        // A non interaction event keeps the visit alive but is not counted as an action, so a visit with one
        // page view and a view_item_list event stays a bounce, like in GA4
        if ($this->getConfig($request->getIdSite())->isNonInteraction($eeRequest['type'])) {
            unset($valuesToUpdate['visit_total_actions'], $valuesToUpdate['visit_total_interactions']);
        }
    }

    public function recordLogs(VisitProperties $visitProperties, Request $request)
    {
        $eeRequest = $this->getEeRequest($request);
        if ($eeRequest === null) {
            return;
        }

        $idVisit = (int) $visitProperties->getProperty('idvisit');
        if ($idVisit <= 0) {
            return;
        }

        $data = $eeRequest['data'];
        $context = [
            'idsite' => $request->getIdSite(),
            'idvisit' => $idVisit,
            'server_time' => Date::getDatetimeFromTimestamp($request->getCurrentTimestamp()),
        ];
        $customSlots = $this->getConfig($request->getIdSite())->getItemCustomParamSlots();

        if ($eeRequest['kind'] === self::KIND_CART) {
            $this->writer->replaceCartItems($context, $data['items'] ?? [], $customSlots);
            return;
        }

        if ($eeRequest['kind'] === self::KIND_ORDER) {
            // A rejected order (duplicate order id) throws before this point. The check also covers any other
            // reason core would not record the order, so lines never exist without their order
            $idOrder = (string) $request->getParam('ec_id');
            if ($data === null || !$this->writer->hasOrderConversion($idVisit, $idOrder)) {
                return;
            }

            $this->writer->insertOrderItems($context, $idOrder, $data['items'], $customSlots);
            $this->writer->insertEvent($context, EventType::PURCHASE, $data['event'], [], $idOrder, self::getOrderRef($eeRequest['rawOrderId']), $customSlots);
            return;
        }

        if ($data === null) {
            return;
        }

        // The order id as stored with the order, anonymised or not, never the raw id the refund carries
        $orderRef = self::getOrderRef($data['event']['transaction_id'] ?? null);
        $idOrder = $orderRef === null ? null : $this->writer->findOrderId((int) $context['idsite'], $orderRef);
        $this->writer->insertEvent($context, $eeRequest['type'], $data['event'], $data['items'], $idOrder, $orderRef, $customSlots);
    }

    /**
     * Keyed hash of the order id as the shop knows it, before any anonymisation by PrivacyManager. A refund sent
     * later carries the same id, so it is matched to its order even when the stored order id is anonymised.
     */
    public static function getOrderRef(?string $orderId): ?string
    {
        if ($orderId === null || trim($orderId) === '') {
            return null;
        }

        return hash_hmac('sha1', trim($orderId), 'EcommerceEnhanced' . SettingsPiwik::getSalt(), true);
    }

    private function getKind(Request $request, int $type): ?string
    {
        $params = $request->getParams();

        if (isset($params['idgoal']) && (string) $params['idgoal'] === (string) GoalManager::IDGOAL_ORDER) {
            $hasOrderId = isset($params['ec_id']) && (string) $params['ec_id'] !== '';
            if ($hasOrderId) {
                return $type === EventType::PURCHASE ? self::KIND_ORDER : null;
            }

            return self::KIND_CART;
        }

        if (isset($params['e_c']) && $params['e_c'] !== '') {
            return self::KIND_EVENT;
        }

        return null;
    }

    /**
     * The tracker builds the native order with GA4 semantics, value without tax and shipping. Shops whose value
     * includes them get the native split fixed here, so the native revenue is never counted twice.
     *
     * @param array{type: int, event: array<string, mixed>, items: list<array<string, mixed>>} $data
     */
    private function applyValueMode(Request $request, array $data, bool $valueIncludesTaxAndShipping): int
    {
        $event = $data['event'];
        if (!$valueIncludesTaxAndShipping || $event['value'] === null) {
            return self::VALUE_MODE_SUBTOTAL;
        }

        $subtotal = !empty($data['items'])
            ? Payload::getItemsTotal($data['items'])
            : $event['value'] - ($event['tax'] ?? 0) - ($event['shipping'] ?? 0);

        $request->setParam('revenue', round($event['value'], 2));
        $request->setParam('ec_st', round(max(0, $subtotal), 2));

        return self::VALUE_MODE_GRAND_TOTAL;
    }

    /**
     * @param array{type: int, event: array<string, mixed>, items: list<array<string, mixed>>} $data
     * @return array{type: int, event: array<string, mixed>, items: list<array<string, mixed>>}
     */
    private function applyCouponStorage(array $data, string $storage): array
    {
        if ($storage === MeasurableSettings::COUPON_FULL) {
            return $data;
        }

        $transform = function (?string $coupon) use ($storage): ?string {
            if ($coupon === null || $storage === MeasurableSettings::COUPON_NONE) {
                return null;
            }

            $codes = array_map(function ($code) {
                return substr(hash_hmac('sha256', trim($code), 'EcommerceEnhanced' . SettingsPiwik::getSalt()), 0, 16);
            }, explode(',', $coupon));

            return Common::mb_substr(implode(',', $codes), 0, 255);
        };

        $data['event']['coupon'] = $transform($data['event']['coupon']);
        foreach ($data['items'] as &$item) {
            $item['coupon'] = $transform($item['coupon']);
        }
        unset($item);

        return $data;
    }

    /**
     * @return array{kind: string, type: int, data: array|null, rawOrderId: string|null}|null
     */
    private function getEeRequest(Request $request): ?array
    {
        $eeRequest = $request->getMetadata(self::METADATA, 'request');

        return is_array($eeRequest) ? $eeRequest : null;
    }

    private function getConfig(int $idSite): SiteConfig
    {
        if (!isset($this->configs[$idSite])) {
            $this->configs[$idSite] = SiteConfig::forSite($idSite);
        }

        return $this->configs[$idSite];
    }

    /**
     * Read by the conversion dimensions.
     *
     * @return array<string, mixed>|null
     */
    public static function getOrderEvent(Request $request): ?array
    {
        $eeRequest = $request->getMetadata(self::METADATA, 'request');
        if (!is_array($eeRequest) || !in_array($eeRequest['kind'], [self::KIND_ORDER, self::KIND_CART], true) || $eeRequest['data'] === null) {
            return null;
        }

        return $eeRequest['data']['event'];
    }

    public static function isOrder(Request $request): bool
    {
        $eeRequest = $request->getMetadata(self::METADATA, 'request');

        return is_array($eeRequest) && $eeRequest['kind'] === self::KIND_ORDER;
    }
}
