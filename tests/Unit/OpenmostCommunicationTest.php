<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\EcommerceEnhanced\OpenmostCommunication;

/**
 * @group EcommerceEnhanced
 * @group OpenmostCommunication
 * @group Unit
 */
class OpenmostCommunicationTest extends TestCase
{
    public function testEcommerceBannerShowsOnAnEcommerceSiteWithoutEcommerceEnhanced()
    {
        $this->assertTrue($this->isEcommerceBannerShown(12, [], [], [12]));
    }

    public function testEcommerceBannerIsHiddenOnceEcommerceEnhancedIsActivated()
    {
        $this->assertFalse($this->isEcommerceBannerShown(12, ['EcommerceEnhanced'], ['EcommerceEnhanced'], [12]));
    }

    public function testEcommerceBannerIsHiddenWhileEcommerceEnhancedIsInstalledButDeactivated()
    {
        $this->assertFalse($this->isEcommerceBannerShown(12, [], ['EcommerceEnhanced'], [12]));
    }

    public function testEcommerceBannerIgnoresOtherOpenmostPlugins()
    {
        $this->assertTrue($this->isEcommerceBannerShown(12, ['KPIWidgets', 'EventsEnhanced'], ['KPIWidgets', 'EventsEnhanced'], [12]));
    }

    public function testEcommerceBannerIsHiddenOnASiteWithoutEcommerce()
    {
        $this->assertFalse($this->isEcommerceBannerShown(3, [], [], [12]));
    }

    public function testEcommerceBannerIsHiddenWithoutAWebsite()
    {
        $checked = false;
        $shown = OpenmostCommunication::isCampaignShown('ecommerce_enhanced', 0, $this->none(), $this->none(), function () use (&$checked) {
            $checked = true;
            return true;
        });

        $this->assertFalse($shown);
        $this->assertFalse($checked);
    }

    public function testEcommerceBannerIsHiddenWhenTheWebsiteCannotBeRead()
    {
        $shown = OpenmostCommunication::isCampaignShown('ecommerce_enhanced', 12, $this->none(), $this->none(), function () {
            throw new \Exception('No access');
        });

        $this->assertFalse($shown);
    }

    public function testOtherPlacementsDoNotNeedAnEcommerceSite()
    {
        $notEcommerce = function () {
            return false;
        };

        $this->assertTrue(OpenmostCommunication::isCampaignShown('tracking_plan', 0, $this->none(), $this->none(), $notEcommerce));
        $this->assertTrue(OpenmostCommunication::isCampaignShown('events_enhanced', 3, $this->none(), $this->none(), $notEcommerce));
    }

    public function testEventsEnhancedLinkStaysHiddenOnlyWhileActivated()
    {
        $installed = $this->in(['EventsEnhanced']);

        $this->assertTrue(OpenmostCommunication::isCampaignShown('events_enhanced', 1, $this->none(), $installed));
        $this->assertFalse(OpenmostCommunication::isCampaignShown('events_enhanced', 1, $this->in(['EventsEnhancedPremium']), $installed));
    }

    public function testUnknownCampaignIsNeverShown()
    {
        $this->assertFalse(OpenmostCommunication::isCampaignShown('unknown', 12, $this->none(), $this->none()));
    }

    public function testMarketplaceUrlOpensThePluginInTheInstanceMarketplaceForSuperUsers()
    {
        $url = OpenmostCommunication::marketplaceUrl('EcommerceEnhanced', true);

        $this->assertStringStartsWith('index.php?module=Marketplace&action=overview&', $url);
        $this->assertStringEndsWith('#?showPlugin=EcommerceEnhanced', $url);
    }

    public function testMarketplaceUrlOpensThePublicPluginPageForOtherUsers()
    {
        $this->assertSame('https://plugins.matomo.org/EcommerceEnhanced', OpenmostCommunication::marketplaceUrl('EcommerceEnhanced', false));
    }

    private function isEcommerceBannerShown(int $idSite, array $activated, array $installed, array $ecommerceSites): bool
    {
        return OpenmostCommunication::isCampaignShown(
            'ecommerce_enhanced',
            $idSite,
            $this->in($activated),
            $this->in($installed),
            function (int $idSite) use ($ecommerceSites) {
                return in_array($idSite, $ecommerceSites, true);
            }
        );
    }

    private function in(array $plugins): callable
    {
        return function (string $plugin) use ($plugins) {
            return in_array($plugin, $plugins, true);
        };
    }

    private function none(): callable
    {
        return $this->in([]);
    }
}
