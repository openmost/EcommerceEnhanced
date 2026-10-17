<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\API\Request;
use Piwik\Plugin\Manager;
use Piwik\Site;

/**
 * The GA4 funnels created in the Funnels plugin in one click, from the "Ecommerce" events: the sales funnel of the
 * ecommerce order goal, and the shopping and checkout behavior funnels. A funnel that already exists is kept.
 * The steps must be shown in the Events report (website settings) for Funnels to see them.
 */
class FunnelSetup
{
    public const PLUGIN = 'Funnels';

    private const SALES = 'sales';

    /**
     * key => [name, steps: [step name, GA4 event, required]]. Names are data stored in Funnels, kept in English.
     */
    private const FUNNELS = [
        self::SALES => ['Sales', [
            ['View item list', 'view_item_list', false],
            ['Select item', 'select_item', false],
            ['View item', 'view_item', true],
            ['Add to cart', 'add_to_cart', true],
            ['View cart', 'view_cart', false],
            ['Begin checkout', 'begin_checkout', true],
            ['Add shipping info', 'add_shipping_info', false],
            ['Add payment info', 'add_payment_info', false],
        ]],
        'shopping' => ['Shopping behavior (GA4)', [
            ['Product view', 'view_item', true],
            ['Add to cart', 'add_to_cart', true],
            ['Checkout', 'begin_checkout', true],
            ['Purchase', 'purchase', true],
        ]],
        'checkout' => ['Checkout behavior (GA4)', [
            ['Begin checkout', 'begin_checkout', true],
            ['Add shipping info', 'add_shipping_info', true],
            ['Add payment info', 'add_payment_info', true],
            ['Purchase', 'purchase', true],
        ]],
    ];

    /**
     * Funnels is activated and the website has ecommerce, the sales funnel being the one of the ecommerce orders.
     */
    public static function isAvailable(int $idSite): bool
    {
        return Manager::getInstance()->isPluginActivated(self::PLUGIN) && (new Site($idSite))->isEcommerceEnabled();
    }

    /**
     * @return array<int, array{key: string, name: string, exists: bool}>
     */
    public static function getStatus(int $idSite): array
    {
        $existing = self::getExistingNames($idSite);
        $hasSalesFunnel = self::hasSalesFunnel($idSite);

        $status = [];
        foreach (self::FUNNELS as $key => [$name]) {
            $status[] = ['key' => $key, 'name' => $name, 'exists' => $key === self::SALES ? $hasSalesFunnel : in_array($name, $existing, true)];
        }

        return $status;
    }

    /**
     * @return string[] names of the funnels created
     */
    public static function create(int $idSite): array
    {
        $created = [];
        foreach (self::getStatus($idSite) as $funnel) {
            if ($funnel['exists']) {
                continue;
            }

            $steps = self::buildSteps(self::FUNNELS[$funnel['key']][1]);
            if ($funnel['key'] === self::SALES) {
                Request::processRequest('Funnels.setGoalFunnel', ['idSite' => $idSite, 'idGoal' => 0, 'isActivated' => 1, 'steps' => $steps], []);
            } else {
                Request::processRequest('Funnels.saveNonGoalFunnel', ['idSite' => $idSite, 'idFunnel' => 0, 'funnelName' => $funnel['name'], 'steps' => $steps], []);
            }

            $created[] = $funnel['name'];
        }

        return $created;
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: bool}> $definition
     * @return array<int, array<string, mixed>>
     */
    private static function buildSteps(array $definition): array
    {
        $steps = [];
        foreach ($definition as $position => [$name, $event, $required]) {
            $steps[] = [
                'position' => $position + 1,
                'name' => $name,
                'pattern_type' => 'eventname_equals',
                'pattern' => $event,
                'required' => $required ? 1 : 0,
            ];
        }

        return $steps;
    }

    /**
     * @return string[]
     */
    private static function getExistingNames(int $idSite): array
    {
        $funnels = Request::processRequest('Funnels.getAllActivatedFunnelsForSite', ['idSite' => $idSite, 'filter_limit' => -1], []);

        return array_map(function ($funnel) {
            return (string) ($funnel['name'] ?? '');
        }, is_array($funnels) ? $funnels : []);
    }

    private static function hasSalesFunnel(int $idSite): bool
    {
        try {
            $funnel = Request::processRequest('Funnels.getGoalFunnel', ['idSite' => $idSite, 'idGoal' => 0], []);
        } catch (\Exception $e) {
            return false;
        }

        return !empty($funnel) && !empty($funnel['steps']);
    }
}
