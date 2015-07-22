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

class Model
{
    private static $rawPrefix = 'goal';
    private $table;

    public function __construct()
    {
        $this->table = Common::prefixTable(self::$rawPrefix);
    }

    private function getNextIdGoal($idSite)
    {
        $db     = $this->getDb();
        $idGoal = $db->fetchOne("SELECT max(idgoal) + 1 FROM " . $this->table . "
                                 WHERE idsite = ?", $idSite);

        if (empty($idGoal)) {
            $idGoal = 1;
        }

        return $idGoal;
    }

    public function createGoalForSite($idSite, $goal)
    {
        $db     = $this->getDb();
        $goalId = $this->getNextIdGoal($idSite);

        $goal['idgoal'] = $goalId;
        $goal['idsite'] = $idSite;

        $db->insert($this->table, $goal);

        return $goalId;
    }

    public function updateGoal($idSite, $idGoal, $goal)
    {
        $idSite = (int) $idSite;
        $idGoal = (int) $idGoal;

        $db = $this->getDb();
        $db->update($this->table, $goal, "idsite = '$idSite' AND idgoal = '$idGoal'");
    }

    // actually this should be in a log_conversion model
    public function deleteGoalConversions($idSite, $idGoal)
    {
        $table = Common::prefixTable("log_conversion");

        Db::deleteAllRows($table, "WHERE idgoal = ? AND idsite = ?", "idvisit", 100000, array($idGoal, $idSite));
    }

    public function getActiveGoals($idSite)
    {
        $idSite = array_map('intval', $idSite);
        $goals  = Db::fetchAll("SELECT * FROM " . $this->table . "
                                WHERE idsite IN (" . implode(", ", $idSite) . ")
                                      AND deleted = 0");

        return $goals;
    }

    public function deleteGoalsForSite($idSite)
    {
        Db::query("DELETE FROM " . $this->table . " WHERE idsite = ? ", array($idSite));
    }

    public function deleteGoal($idSite, $idGoal)
    {
        $query = "UPDATE " . $this->table . " SET deleted = 1
                  WHERE idsite = ? AND idgoal = ?";
        $bind  = array($idSite, $idGoal);

        Db::query($query, $bind);
    }

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

        $datatable = Request::processRequest('Goals.get', array(
            'idGoal' => $idGoal,
            'period' => $period,
            'date' => $date,
            'idSite' => $idSite,
            'serialize' => 0,
            'segment' => false
        ));

        // we ignore the segment even if there is one set. We still want to show conversion overview if there are conversions
        // in general but not for this segment

        $dataRow = $datatable->getFirstRow();

        if (!$dataRow) {
            return false;
        }

        $conversions = $dataRow->getColumn('nb_conversions');
        $cache->save($key, $conversions);

        return $conversions;
    }

    private function getDb()
    {
        return Db::get();
    }
}
