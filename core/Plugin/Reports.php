<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugin;

use Piwik\CacheId;
use Piwik\Category\CategoryList;
use Piwik\Plugin;
use Piwik\Cache as PiwikCache;

/**
 * Get reports that are defined by plugins.
 */
class Reports
{

    /**
     * Get an instance of a specific report belonging to the given module and having the given action.
     * @param  string $module
     * @param  string $action
     * @return null|\Piwik\Plugin\Report
     * @api
     */
    public static function factory($module, $action)
    {
        $listApiToReport = self::getMapOfModuleActionsToReport();
        $api = $module . '.' . ucfirst($action);

        if (!array_key_exists($api, $listApiToReport)) {
            return null;
        }

        $klassName = $listApiToReport[$api];

        return new $klassName;
    }

    private static function getMapOfModuleActionsToReport()
    {
        $cacheId = CacheId::pluginAware('ReportFactoryMap');

        $cache = PiwikCache::getEagerCache();
        if ($cache->contains($cacheId)) {
            $mapApiToReport = $cache->fetch($cacheId);
        } else {
            $reports = new static();
            $reports = $reports->getAllReports();

            $mapApiToReport = array();
            foreach ($reports as $report) {
                $key = $report->getModule() . '.' . ucfirst($report->getAction());

                if (isset($mapApiToReport[$key]) && $report->getParameters()) {
                    // sometimes there are multiple reports with same module/action but different parameters.
                    // we might pick the "wrong" one. At some point we should compare all parameters and if there is
                    // a report which parameters mach $_REQUEST then we should prefer that report
                    continue;
                }
                $mapApiToReport[$key] = get_class($report);
            }

            $cache->save($cacheId, $mapApiToReport);
        }

        return $mapApiToReport;
    }

    /**
     * Returns a list of all available reports. Even not enabled reports will be returned. They will be already sorted
     * depending on the order and category of the report.
     * @return \Piwik\Plugin\Report[]
     * @api
     */
    public function getAllReports()
    {
        $reports = $this->getAllReportClasses();
        $cacheId = CacheId::languageAware('Reports' . md5(implode('', $reports)));
        $cache   = PiwikCache::getTransientCache();

        if (!$cache->contains($cacheId)) {
            $instances = array();

            foreach ($reports as $report) {
                $instances[] = new $report();
            }

            usort($instances, array($this, 'sort'));

            $cache->save($cacheId, $instances);
        }

        return $cache->fetch($cacheId);
    }

    /**
     * API metadata are sorted by category/name,
     * with a little tweak to replicate the standard Piwik category ordering
     *
     * @param Report $a
     * @param Report $b
     * @return int
     */
    private function sort($a, $b)
    {
        return $this->compareCategories($a->getCategoryId(), $a->getSubcategoryId(), $a->getOrder(), $b->getCategoryId(), $b->getSubcategoryId(), $b->getOrder());
    }

    public function compareCategories($catIdA, $subcatIdA, $orderA, $catIdB, $subcatIdB, $orderB)
    {
        static $categoryList;

        if (!isset($categoryList)) {
            $categoryList = CategoryList::get();
        }

        $catA = $categoryList->getCategory($catIdA);
        $catB = $categoryList->getCategory($catIdB);

        // in case there is a category class for both reports
        if (isset($catA) && isset($catB)) {

            if ($catA->getOrder() == $catB->getOrder()) {
                // same category order, compare subcategory order
                $subcatA = $catA->getSubcategory($subcatIdA);
                $subcatB = $catB->getSubcategory($subcatIdB);

                // both reports have a subcategory with custom subcategory class
                if ($subcatA && $subcatB) {
                    if ($subcatA->getOrder() == $subcatB->getOrder()) {
                        // same subcategory order, compare order of report

                        if ($orderA == $orderB) {
                            return 0;
                        }

                        return $orderA < $orderB ? -1 : 1;
                    }

                    return $subcatA->getOrder() < $subcatB->getOrder() ? -1 : 1;

                } elseif ($subcatA) {
                    return -1;
                } elseif ($subcatB) {
                    return 1;
                }

                if ($orderA == $orderB) {
                    return 0;
                }

                return $orderA < $orderB ? -1 : 1;
            }

            return $catA->getOrder() < $catB->getOrder() ? -1 : 1;

        } elseif (isset($catA)) {
            return -1;
        } elseif (isset($catB)) {
            return 1;
        }

        if ($catIdA === $catIdB) {
            // both have same category, compare order
            if ($orderA == $orderB) {
                return 0;
            }

            return $orderA < $orderB ? -1 : 1;
        }

        return strnatcasecmp($catIdA, $catIdB);
    }

    /**
     * Returns class names of all Report metadata classes.
     *
     * @return string[]
     * @api
     */
    public function getAllReportClasses()
    {
        return Plugin\Manager::getInstance()->findMultipleComponents('Reports', '\\Piwik\\Plugin\\Report');
    }
}