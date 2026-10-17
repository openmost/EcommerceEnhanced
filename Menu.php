<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;
use Piwik\Plugins\UsersManager\UserPreferences;
use Piwik\Request;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        $idSite = Request::fromRequest()->getIntegerParameter('idSite', (int) (new UserPreferences())->getDefaultWebsiteId());

        if (!empty($idSite) && Piwik::isUserHasAdminAccess($idSite)) {
            $menu->addMeasurableItem('EcommerceEnhanced_SettingsMenu', $this->urlForAction('settings'), 45);
        }
    }
}
