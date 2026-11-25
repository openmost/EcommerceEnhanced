<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\tests\Integration;

use Piwik\Plugins\EcommerceEnhanced\EcommerceEnhanced;
use Piwik\Tests\Framework\Fixture;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group EcommerceEnhanced
 * @group Integration
 */
class PremiumTeasersTest extends IntegrationTestCase
{
    private $backupGet;

    public function setUp(): void
    {
        parent::setUp();

        $this->backupGet = $_GET;
    }

    public function tearDown(): void
    {
        $_GET = $this->backupGet;

        parent::tearDown();
    }

    public function test_addPremiumTeasers_addsTheCardsOnAnEcommerceSite(): void
    {
        $_GET['idSite'] = Fixture::createWebsite('2026-01-01 00:00:00', 1);

        $this->assertNotEmpty($this->getTeaserConfigs());
    }

    public function test_addPremiumTeasers_addsNothingWhenEcommerceIsOff(): void
    {
        $_GET['idSite'] = Fixture::createWebsite('2026-01-01 00:00:00', 0);

        $this->assertSame([], $this->getTeaserConfigs());
    }

    public function test_addPremiumTeasers_addsNothingWithoutASite(): void
    {
        unset($_GET['idSite']);

        $this->assertSame([], $this->getTeaserConfigs());
    }

    private function getTeaserConfigs(): array
    {
        $configs = [];
        (new EcommerceEnhanced())->addPremiumTeasers($configs);

        return $configs;
    }
}
