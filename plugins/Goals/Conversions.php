<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Goals;

use Piwik\API\Request;
use Piwik\Cache;
use Piwik\Common;
use Piwik\Db;

class Conversions
{

    public function getConversionForGoal($idGoal = '')
    {
        $period = Common::getRequestVar('period', '', 'string');
        $date   = Common::getRequestVar('date', '', 'string');
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$period || !$date || !$idSite) {
            return false;
        }

        $cache = Cache::getTransientCache();
        $key   = 'Goals.getConversionForGoal_' . implode('_', array($idGoal, $period, $date, $idSite));

        if ($cache->contains($key)) {
            return $cache->fetch($key);
        }

        $conversions = $this->requestGoalConversions($idGoal, $idSite, $period, $date);
        $cache->save($key, $conversions);

        return $conversions;
    }

    private function requestGoalConversions($idGoal, $idSite, $period, $date)
    {
        $datatable = Request::processRequest('Goals.get', array(
            'idGoal'    => $idGoal,
            'period'    => $period,
            'date'      => $date,
            'idSite'    => $idSite,
            'serialize' => 0,
            'segment'   => false
        ));

        // we ignore the segment even if there is one set. We still want to show conversion overview if there are conversions
        // in general but not for this segment

        $dataRow = $datatable->getFirstRow();

        if (!$dataRow) {
            return false;
        }

        return $dataRow->getColumn('nb_conversions');
    }
}
