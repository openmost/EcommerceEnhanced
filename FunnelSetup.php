<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\API\Request;
use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Site;

/**
 * The GA4 funnels created in the Funnels plugin in one click, from the "Ecommerce" events: the sales funnel of the
 * ecommerce order goal, and the GA4 purchase and checkout journeys. An existing journey is kept, recognised by its steps
 * so that a renamed one is not created twice. An existing sales funnel is never replaced without the user asking.
 * The steps must be shown in the Events report (website settings) for Funnels to see them.
 */
class FunnelSetup
{
    public const PLUGIN = 'Funnels';

    public const STATE_MISSING = 'missing';
    public const STATE_EXISTS = 'exists';
    public const STATE_OUTDATED = 'outdated';

    private const SALES = 'sales';

    /**
     * key => [name translation key, steps: [step name, GA4 event, required]]. Funnel names are translated in the
     * language of the user creating them, as GA4 does. Step names follow the "Ecommerce" event actions, in English.
     */
    private const FUNNELS = [
        self::SALES => ['EcommerceEnhanced_FunnelSales', [
            ['View item list', 'view_item_list', false],
            ['Select item', 'select_item', false],
            ['View item', 'view_item', true],
            ['Add to cart', 'add_to_cart', true],
            ['View cart', 'view_cart', false],
            ['Begin checkout', 'begin_checkout', true],
            ['Add shipping info', 'add_shipping_info', false],
            ['Add payment info', 'add_payment_info', false],
        ]],
        'purchase' => ['EcommerceEnhanced_FunnelPurchaseJourney', [
            ['Product view', 'view_item', true],
            ['Add to cart', 'add_to_cart', true],
            ['Checkout', 'begin_checkout', true],
            ['Purchase', 'purchase', true],
        ]],
        'checkout' => ['EcommerceEnhanced_FunnelCheckoutJourney', [
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
     * @return array<int, array{key: string, name: string, state: string}>
     */
    public static function getStatus(int $idSite): array
    {
        $signatures = array_map([self::class, 'signature'], self::getActivatedNonGoalFunnelSteps($idSite));
        $salesSteps = self::getSalesFunnelSteps($idSite);

        $status = [];
        foreach (self::FUNNELS as $key => [$nameKey, $definition]) {
            $template = self::signature(self::buildSteps($definition));
            if ($key === self::SALES) {
                $state = $salesSteps === null ? self::STATE_MISSING
                    : (self::signature($salesSteps) === $template ? self::STATE_EXISTS : self::STATE_OUTDATED);
            } else {
                $state = in_array($template, $signatures, true) ? self::STATE_EXISTS : self::STATE_MISSING;
            }

            $status[] = ['key' => $key, 'name' => Piwik::translate($nameKey), 'state' => $state];
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
            if ($funnel['state'] !== self::STATE_MISSING) {
                continue;
            }

            $steps = self::buildSteps(self::FUNNELS[$funnel['key']][1]);
            if ($funnel['key'] === self::SALES) {
                self::saveSalesFunnel($idSite, $steps);
            } else {
                Request::processRequest('Funnels.saveNonGoalFunnel', ['idSite' => $idSite, 'idFunnel' => 0, 'funnelName' => $funnel['name'], 'steps' => $steps], []);
            }

            $created[] = $funnel['name'];
        }

        return $created;
    }

    /**
     * Replaces the steps of the existing sales funnel by the complete template, on the user's explicit request.
     */
    public static function updateSalesFunnel(int $idSite): void
    {
        self::saveSalesFunnel($idSite, self::buildSteps(self::FUNNELS[self::SALES][1]));
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     */
    private static function saveSalesFunnel(int $idSite, array $steps): void
    {
        Request::processRequest('Funnels.setGoalFunnel', ['idSite' => $idSite, 'idGoal' => 0, 'isActivated' => 1, 'steps' => $steps], []);
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
     * What makes a funnel the template one: its step patterns and whether they are required, not the names, which the
     * user may translate or rename.
     *
     * @param array<int, array<string, mixed>> $steps
     */
    private static function signature(array $steps): string
    {
        return implode('|', array_map(function ($step) {
            return ($step['pattern_type'] ?? '') . ':' . ($step['pattern'] ?? '') . ':' . (empty($step['required']) ? 0 : 1);
        }, array_values($steps)));
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private static function getActivatedNonGoalFunnelSteps(int $idSite): array
    {
        $funnels = Request::processRequest('Funnels.getAllActivatedFunnelsForSite', ['idSite' => $idSite, 'filter_limit' => -1], []);

        $steps = [];
        foreach (is_array($funnels) ? $funnels : [] as $funnel) {
            if (!empty($funnel['isNonGoalFunnel']) && !empty($funnel['steps'])) {
                $steps[] = $funnel['steps'];
            }
        }

        return $steps;
    }

    /**
     * @return array<int, array<string, mixed>>|null the steps of the activated sales funnel, null when not configured
     */
    private static function getSalesFunnelSteps(int $idSite): ?array
    {
        try {
            $funnel = Request::processRequest('Funnels.getGoalFunnel', ['idSite' => $idSite, 'idGoal' => 0], []);
        } catch (\Exception $e) {
            return null;
        }

        return !empty($funnel['activated']) && !empty($funnel['steps']) ? $funnel['steps'] : null;
    }
}
