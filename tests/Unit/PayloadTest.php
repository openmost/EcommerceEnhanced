<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\EcommerceEnhanced\EventType;
use Piwik\Plugins\EcommerceEnhanced\Tracker\Payload;

/**
 * @group EcommerceEnhanced
 * @group Unit
 */
class PayloadTest extends TestCase
{
    public function testParseKeepsOnlyAllowlistedFields()
    {
        $data = Payload::parse('purchase', json_encode([
            'currency' => 'eur',
            'value' => '64',
            'coupon' => 'WELCOME, TEE5 ,',
            'customer_type' => 'returning',
            'user_data' => ['email' => 'x@y.z'],
            'items' => [
                ['item_id' => 'SKU1', 'item_brand' => 'Nike', 'price' => 20, 'quantity' => '2', 'custom' => ['size' => 'M', 'bad key' => 'x', 'flag' => true]],
            ],
        ]));

        $this->assertSame(EventType::PURCHASE, $data['type']);
        $this->assertSame('EUR', $data['event']['currency']);
        $this->assertSame(64.0, $data['event']['value']);
        $this->assertSame('WELCOME,TEE5', $data['event']['coupon']);
        $this->assertSame(Payload::CUSTOMER_TYPE_RETURNING, $data['event']['customer_type']);
        $this->assertArrayNotHasKey('user_data', $data['event']);
        $this->assertSame(['size' => 'M'], $data['items'][0]['custom']);
        $this->assertSame(2, $data['items'][0]['quantity']);
        $this->assertStringNotContainsString('x@y.z', json_encode($data));
    }

    public function testParseRejectsUnknownEventsAndInvalidJson()
    {
        $this->assertNull(Payload::parse('page_view', '{}'));
        $this->assertNull(Payload::parse('purchase', 'not json'));
        $this->assertNull(Payload::parse('purchase', str_repeat('a', Payload::MAX_JSON_BYTES + 1)));
    }

    public function testItemsWithoutIdOrNameAreDroppedAndCounted()
    {
        $data = Payload::fromArray(EventType::VIEW_ITEM_LIST, [
            'item_list_name' => 'Home',
            'dropped_items' => 3,
            'items' => [['item_name' => 'Lamp'], ['price' => 10], 'not an item'],
        ]);

        $this->assertCount(1, $data['items']);
        $this->assertSame('Lamp', $data['items'][0]['item_id']);
        $this->assertSame('Home', $data['items'][0]['item_list_name']);
        $this->assertSame(5, $data['event']['dropped_items']);
        $this->assertSame(1, $data['event']['items_count']);
    }

    public function testEventListAndPromotionFallBackOnTheFirstItem()
    {
        $data = Payload::fromArray(EventType::VIEW_PROMOTION, ['items' => [
            ['item_id' => 'A', 'promotion_name' => 'Summer sale', 'item_list_name' => 'Home'],
            ['item_id' => 'B', 'promotion_name' => 'Other'],
        ]]);

        $this->assertSame('Summer sale', $data['event']['promotion_name']);
        $this->assertSame('Home', $data['event']['item_list_name']);
        $this->assertSame('Other', $data['items'][1]['promotion_name']);

        $checkout = Payload::fromArray(EventType::BEGIN_CHECKOUT, ['items' => [['item_id' => 'A', 'item_list_name' => 'Home']]]);
        $this->assertNull($checkout['event']['item_list_name']);
        $this->assertSame('Home', $checkout['items'][0]['item_list_name']);
    }

    public function testItemsAreCapped()
    {
        $data = Payload::fromArray(EventType::ADD_TO_CART, ['items' => array_fill(0, Payload::MAX_ITEMS + 5, ['item_id' => 'A'])]);

        $this->assertCount(Payload::MAX_ITEMS, $data['items']);
        $this->assertSame(5, $data['event']['dropped_items']);
    }

    /**
     * @dataProvider getMoneyValues
     */
    public function testToMoney($input, $expected)
    {
        $this->assertSame($expected, Payload::toMoney($input));
    }

    public function getMoneyValues()
    {
        return [
            [12, 12.0],
            ['12.5', 12.5],
            ['12,5', 12.5],
            ['1 299,90 €', 1299.9],
            ['1,299.90', 1299.9],
            ['1.299,90', 1299.9],
            ['1,299', 1299.0],
            ['abc', null],
            [true, null],
            [INF, null],
            [2e12, null],
        ];
    }

    public function testToTextCleansAndCaps()
    {
        $this->assertSame('Ben & Jerry', Payload::toText("  Ben &amp;\n Jerry ", 100));
        $this->assertSame('abc', Payload::toText('abcdef', 3));
        $this->assertSame('123', Payload::toText(123, 10));
        $this->assertNull(Payload::toText('   ', 10));
        $this->assertNull(Payload::toText(['a'], 10));
    }

    public function testCustomerType()
    {
        $this->assertSame(Payload::CUSTOMER_TYPE_NEW, Payload::toCustomerType('new'));
        $this->assertSame(Payload::CUSTOMER_TYPE_NEW, Payload::toCustomerType(true));
        $this->assertSame(Payload::CUSTOMER_TYPE_RETURNING, Payload::toCustomerType('false'));
        $this->assertNull(Payload::toCustomerType('guest'));
    }

    public function testItemsTotal()
    {
        $items = Payload::fromArray(EventType::PURCHASE, ['items' => [
            ['item_id' => 'A', 'price' => 20, 'quantity' => 1],
            ['item_id' => 'B', 'price' => 22.1, 'quantity' => 2],
            ['item_id' => 'C'],
        ]])['items'];

        $this->assertSame(64.2, Payload::getItemsTotal($items));
    }

    public function testFreeTextWithPersonalDataIsDropped()
    {
        $event = Payload::fromArray(EventType::VIEW_SEARCH_RESULTS, ['search_term' => 'john.doe@mail.com'])['event'];
        $this->assertNull($event['search_term']);

        $event = Payload::fromArray(EventType::GENERATE_LEAD, ['lead_source' => 'call +33 6 12 34 56 78', 'lead_status' => '06 12 34 56 78'])['event'];
        $this->assertNull($event['lead_source']);
        $this->assertNull($event['lead_status']);

        $event = Payload::fromArray(EventType::VIEW_SEARCH_RESULTS, ['search_term' => 'trail shoes 42'])['event'];
        $this->assertSame('trail shoes 42', $event['search_term']);

        $item = Payload::fromArray(EventType::ADD_TO_CART, ['items' => [['item_id' => 'A', 'custom' => ['note' => 'me@x.io', 'size' => 'M']]]])['items'][0];
        $this->assertSame(['size' => 'M'], $item['custom']);
    }

    public function testListStepsKeepThirtyItems()
    {
        $items = [];
        for ($i = 0; $i < 50; $i++) {
            $items[] = ['item_id' => 'SKU' . $i];
        }

        $data = Payload::fromArray(EventType::VIEW_ITEM_LIST, ['items' => $items]);
        $this->assertCount(Payload::MAX_LIST_ITEMS, $data['items']);
        $this->assertSame(20, $data['event']['dropped_items']);

        $this->assertCount(50, Payload::fromArray(EventType::ADD_TO_CART, ['items' => $items])['items']);
    }
}
