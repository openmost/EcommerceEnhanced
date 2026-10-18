<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\tests\Integration;

use Piwik\Plugins\EcommerceEnhanced\FunnelSetup;

/**
 * Archives the shop of ShopTestCase and checks each report against the known history, for days, weeks, months
 * and ranges, with and without segment.
 *
 * @group EcommerceEnhanced
 * @group EcommerceEnhancedArchivingTest
 * @group Plugins
 */
class ArchivingTest extends ShopTestCase
{
    public function test_itemReports_countViewsCartsAndPurchases_forEveryPeriodType(): void
    {
        $expected = [
            'Hoodie' => ['viewed' => 1, 'cart' => 1, 'purchased' => 1, 'revenue' => 30],
            'Socks' => ['viewed' => 0, 'cart' => 1, 'purchased' => 1, 'revenue' => 5],
            'Trail shoe' => ['viewed' => 2, 'cart' => 2, 'purchased' => 2, 'revenue' => 20],
        ];

        foreach ([['month', '2025-01-01'], ['week', '2025-01-06'], ['range', '2025-01-06,2025-01-08']] as [$period, $date]) {
            $rows = $this->api('EcommerceEnhanced.getProducts', $period, $date);
            $this->assertSame(array_combine(array_keys($expected), array_column($expected, 'viewed')), $this->column($rows, 'ee_items_viewed'), "$period viewed");
            $this->assertSame(array_combine(array_keys($expected), array_column($expected, 'cart')), $this->column($rows, 'ee_items_added_to_cart'), "$period cart");
            $this->assertSame(array_combine(array_keys($expected), array_column($expected, 'purchased')), $this->column($rows, 'ee_items_purchased'), "$period purchased");
            $this->assertEquals(array_combine(array_keys($expected), array_column($expected, 'revenue')), $this->column($rows, 'ee_item_revenue'), "$period revenue");
        }

        $day = $this->column($this->api('EcommerceEnhanced.getProducts', 'day', '2025-01-08'), 'ee_items_viewed');
        $this->assertSame(1, $day['Trail shoe']);

        $this->assertEquals(['Nike' => 50, 'Puma' => 5], $this->column($this->api('EcommerceEnhanced.getBrands'), 'ee_item_revenue'));
        $this->assertEquals(['Accessories' => 5, 'Apparel' => 30, 'Shoes' => 20], $this->column($this->api('EcommerceEnhanced.getCategories'), 'ee_item_revenue'));
        $this->assertSame(['Home' => 1], $this->column($this->api('EcommerceEnhanced.getItemLists'), 'ee_list_views'));
    }

    public function test_buyerKpis_countEachPurchaserOnce_andFirstOrdersOnly(): void
    {
        $month = $this->api('EcommerceEnhanced.getBuyerKpis');
        $this->assertSame(2, $month['ee_orders']);
        $this->assertEquals(55, $month['ee_revenue']);
        $this->assertSame(1, $month['ee_purchasers']);
        $this->assertSame(1, $month['ee_first_time_purchasers']);
        $this->assertSame(2, $month['ee_converted_visits']);
        $this->assertSame(3, $month['ee_cart_visits']);

        // The second order of visitor A is not a first order
        $day = $this->api('EcommerceEnhanced.getBuyerKpis', 'day', '2025-01-08');
        $this->assertSame(1, $day['ee_purchasers']);
        $this->assertSame(0, $day['ee_first_time_purchasers']);
    }

    public function test_funnels_followTheShoppingSteps(): void
    {
        $steps = array_column($this->api('EcommerceEnhanced.getShoppingBehavior'), 'nb_visits');
        $this->assertSame([5, 3, 2, 0, 2], $steps);
    }

    public function test_orderDetails_hashCouponCodes_andListTransactions(): void
    {
        $coupons = $this->column($this->api('EcommerceEnhanced.getCoupons'), 'ee_orders');
        $this->assertCount(2, $coupons);
        $this->assertSame(1, $coupons['(not set)']);
        $this->assertArrayNotHasKey('SUMMER10', $coupons, 'coupon codes are hashed by default');

        $this->assertSame(['Card' => 1, 'PayPal' => 1], $this->column($this->api('EcommerceEnhanced.getPaymentTypes'), 'ee_orders'));
        $this->assertSame(['O1', 'O2'], array_values(array_map('strval', array_column(array_reverse($this->api('EcommerceEnhanced.getTransactions')), 'label'))));
    }

    public function test_segments_applyToTheReports(): void
    {
        // Visits with an add to cart: visitor A on the 6th (order O1) and visitor B (abandoned)
        $kpis = $this->api('EcommerceEnhanced.getBuyerKpis', 'month', '2025-01-01', ['segment' => 'eeStep==add_to_cart']);
        $this->assertSame(1, $kpis['ee_orders']);
        $this->assertEquals(25, $kpis['ee_revenue']);

        $products = $this->column($this->api('EcommerceEnhanced.getProducts', 'month', '2025-01-01', ['segment' => 'eeItemBrand==Puma']), 'ee_items_purchased');
        $this->assertSame(1, $products['Socks']);
    }

    public function test_funnelSetup_createsTheGa4FunnelsOnce(): void
    {
        if (!FunnelSetup::isAvailable($this->idSite)) {
            $this->markTestSkipped('The Funnels plugin is not activated.');
        }

        $this->assertSame(['Sales', 'Shopping behavior (GA4)', 'Checkout behavior (GA4)'], FunnelSetup::create($this->idSite));
        $this->assertSame([true, true, true], array_column(FunnelSetup::getStatus($this->idSite), 'exists'));
        $this->assertSame([], FunnelSetup::create($this->idSite), 'existing funnels are kept');
    }
}
