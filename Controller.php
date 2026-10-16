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

class Controller extends \Piwik\Plugin\Controller
{
    /**
     * A locked card for a Premium report, see PremiumTeasers.
     */
    public function premiumTeaser()
    {
        $this->checkSitePermission();

        $id = Common::getRequestVar('teaser', '', 'string');
        if (!PremiumTeasers::exists($id)) {
            throw new \Exception('Unknown report.');
        }

        return $this->renderTemplate('premiumTeaser', [
            'name' => Piwik::translate('EcommerceEnhanced_Teaser' . $id),
            'description' => Piwik::translate('EcommerceEnhanced_Teaser' . $id . 'Description'),
            'image' => 'plugins/EcommerceEnhanced/images/' . PremiumTeasers::getImage($id) . '.jpg',
            'premiumUrl' => PremiumTeasers::URL,
        ]);
    }
}
