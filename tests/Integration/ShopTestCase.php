<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\tests\Integration;

use Piwik\API\Request;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * A small shop with a fully known history, tracked like tracker.js does (eex_* parameters on events, carts and
 * orders), shared by the archiving tests of Ecommerce Enhanced and Premium. Site timezone UTC.
 *
 * - Visitor A, from Google, 2025-01-06: list, product view, cart, order O1 (2 trail shoes at 10, 1 socks at 5,
 *   coupon SUMMER10, revenue 25).
 * - Visitor B, direct, 2025-01-06: views and adds a hoodie, leaves the cart (abandoned).
 * - Visitor A, 2025-01-08: searches "shoes", orders O2 (1 hoodie at 30, revenue 30): a repeat order 2 days later.
 * - Visitor C, direct, 2025-01-08: views the trail shoe.
 * - Visitor A, 2025-01-09: refunds O1 in full, the refund is sent twice.
 */
abstract class ShopTestCase extends IntegrationTestCase
{
    protected const TRAIL_SHOE = ['item_id' => 'SKU-SHOE', 'item_name' => 'Trail shoe', 'item_brand' => 'Nike', 'item_category' => 'Shoes', 'price' => 10];
    protected const SOCKS = ['item_id' => 'SKU-SOCK', 'item_name' => 'Socks', 'item_brand' => 'Puma', 'item_category' => 'Accessories', 'price' => 5];
    protected const HOODIE = ['item_id' => 'SKU-HOOD', 'item_name' => 'Hoodie', 'item_brand' => 'Nike', 'item_category' => 'Apparel', 'price' => 30];

    protected const VISITOR_A = 'a000000000000001';
    protected const VISITOR_B = 'b000000000000002';
    protected const VISITOR_C = 'c000000000000003';

    /**
     * @var int
     */
    protected $idSite;

    public function setUp(): void
    {
        parent::setUp();

        Fixture::createSuperUser();
        FakeAccess::clearAccess(true);
        $this->idSite = (int) Fixture::createWebsite('2025-01-01 00:00:00', 1, 'Shop', 'https://shop.example', 1, null, null, 'UTC');

        $this->configureSite();
        $this->trackShop();
    }

    /**
     * Website settings to set before tracking.
     */
    protected function configureSite(): void
    {
    }

    protected function trackShop(): void
    {
        $shoes = self::TRAIL_SHOE + ['quantity' => 2, 'custom' => ['size' => 'M']];
        $socks = self::SOCKS + ['quantity' => 1];
        $hoodie = self::HOODIE + ['quantity' => 1];

        $this->visit(self::VISITOR_A, '2025-01-06 10:00:00', 'https://www.google.com/search?q=trail', [
            ['event', 'view_item_list', ['item_list_name' => 'Home', 'items' => [self::TRAIL_SHOE, self::HOODIE]]],
            ['event', 'view_item', ['currency' => 'EUR', 'items' => [self::TRAIL_SHOE]]],
            ['event', 'add_to_cart', ['currency' => 'EUR', 'value' => 25, 'items' => [$shoes, $socks]]],
            ['cart', 'add_to_cart', ['currency' => 'EUR', 'items' => [$shoes, $socks]]],
            ['order', 'O1', ['currency' => 'EUR', 'value' => 25, 'coupon' => 'SUMMER10', 'payment_type' => 'Card', 'items' => [$shoes, $socks]]],
        ]);

        $this->visit(self::VISITOR_B, '2025-01-06 11:00:00', '', [
            ['event', 'view_item', ['currency' => 'EUR', 'items' => [self::HOODIE]]],
            ['event', 'add_to_cart', ['currency' => 'EUR', 'value' => 30, 'items' => [$hoodie]]],
            ['cart', 'add_to_cart', ['currency' => 'EUR', 'items' => [$hoodie]]],
        ]);

        $this->visit(self::VISITOR_A, '2025-01-08 10:00:00', '', [
            ['event', 'view_search_results', ['search_term' => 'shoes', 'items' => [self::TRAIL_SHOE]]],
            ['order', 'O2', ['currency' => 'EUR', 'value' => 30, 'payment_type' => 'PayPal', 'items' => [$hoodie]]],
        ]);

        $this->visit(self::VISITOR_C, '2025-01-08 12:00:00', '', [
            ['event', 'view_item', ['currency' => 'EUR', 'items' => [self::TRAIL_SHOE]]],
        ]);

        $this->visit(self::VISITOR_A, '2025-01-09 09:00:00', '', [
            ['event', 'refund', ['transaction_id' => 'O1', 'currency' => 'EUR']],
            ['event', 'refund', ['transaction_id' => 'O1', 'currency' => 'EUR']],
        ]);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: array<string, mixed>}> $steps
     */
    protected function visit(string $visitorId, string $dateTime, string $referrer, array $steps): void
    {
        $tracker = Fixture::getTracker($this->idSite, $dateTime, true, true);
        $tracker->setVisitorId($visitorId);
        $tracker->setUrl('https://shop.example/');
        if ($referrer !== '') {
            $tracker->setUrlReferrer($referrer);
        }

        Fixture::checkResponse($tracker->doTrackPageView('Home'));

        foreach ($steps as $index => [$kind, $name, $data]) {
            $tracker->setForceVisitDateTime(date('Y-m-d H:i:s', strtotime($dateTime) + 60 * ($index + 1)));
            $tracker->clearCustomTrackingParameters();
            $tracker->setCustomTrackingParameter('eex_v', '1');
            $tracker->setCustomTrackingParameter('eex_e', $kind === 'order' ? 'purchase' : $name);
            $tracker->setCustomTrackingParameter('eex_d', (string) json_encode(['source' => 1] + $data));

            if ($kind === 'event') {
                $response = $tracker->doTrackEvent('Ecommerce', $name, $name);
            } else {
                $tracker->ecommerceItems = [];
                $total = 0;
                foreach ($data['items'] as $item) {
                    $tracker->addEcommerceItem($item['item_id'], $item['item_name'], $item['item_category'], $item['price'], $item['quantity']);
                    $total += $item['price'] * $item['quantity'];
                }

                $response = $kind === 'cart'
                    ? $tracker->doTrackEcommerceCartUpdate($total)
                    : $tracker->doTrackEcommerceOrder($name, $total, $total);
            }

            Fixture::checkResponse($response);
        }

        $tracker->clearCustomTrackingParameters();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>|array<string, mixed>
     */
    protected function api(string $method, string $period = 'month', string $date = '2025-01-01', array $params = []): array
    {
        $json = Request::processRequest($method, array_merge([
            'idSite' => $this->idSite,
            'period' => $period,
            'date' => $date,
            'format' => 'json',
            'filter_limit' => -1,
        ], $params));

        return json_decode($json, true);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed> label => value
     */
    protected function column(array $rows, string $column): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = $row[$column] ?? 0;
            $values[(string) $row['label']] = is_numeric($value) ? $value + 0 : $value;
        }
        ksort($values, SORT_STRING);

        return $values;
    }
}
