<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\UserLanguage\Reports;

use Piwik\Piwik;
use Piwik\Plugins\UserLanguage\Columns\Language;
use Piwik\Report\Reports;

class GetLanguageCode extends GetLanguage
{
    protected function init()
    {
        parent::init();
        $this->dimension     = new Language();
        $this->name          = Piwik::translate('UserLanguage_LanguageCode');
        $this->documentation = '';
        $this->order = 11;
    }

    public function getRelatedReports()
    {
        return array(
            Reports::factory('UserLanguage', 'getLanguage'),
        );
    }

}
