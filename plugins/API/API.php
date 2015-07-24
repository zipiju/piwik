<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\API;

use Piwik\API\Proxy;
use Piwik\API\Request;
use Piwik\Cache;
use Piwik\CacheId;
use Piwik\Category\CategoryList;
use Piwik\Common;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\DataTable\Filter\ColumnDelete;
use Piwik\DataTable\Row;
use Piwik\Date;
use Piwik\IP;
use Piwik\Metrics;
use Piwik\Period;
use Piwik\Period\Range;
use Piwik\Piwik;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugin\Report;
use Piwik\Plugins\API\DataTable\MergeDataTables;
use Piwik\Plugins\CoreAdminHome\CustomLogo;
use Piwik\Translation\Translator;
use Piwik\Measurable\Type\TypeManager;
use Piwik\Version;
use Piwik\Widget\WidgetsList;

require_once PIWIK_INCLUDE_PATH . '/core/Config.php';

/**
 * This API is the <a href='http://piwik.org/docs/analytics-api/metadata/' rel='noreferrer' target='_blank'>Metadata API</a>: it gives information about all other available APIs methods, as well as providing
 * human readable and more complete outputs than normal API methods.
 *
 * Some of the information that is returned by the Metadata API:
 * <ul>
 * <li>the dynamically generated list of all API methods via "getReportMetadata"</li>
 * <li>the list of metrics that will be returned by each method, along with their human readable name, via "getDefaultMetrics" and "getDefaultProcessedMetrics"</li>
 * <li>the list of segments metadata supported by all functions that have a 'segment' parameter</li>
 * <li>the (truly magic) method "getProcessedReport" will return a human readable version of any other report, and include the processed metrics such as
 * conversion rate, time on site, etc. which are not directly available in other methods.</li>
 * <li>the method "getSuggestedValuesForSegment" returns top suggested values for a particular segment. It uses the Live.getLastVisitsDetails API to fetch the most recently used values, and will return the most often used values first.</li>
 * </ul>
 * The Metadata API is for example used by the Piwik Mobile App to automatically display all Piwik reports, with translated report & columns names and nicely formatted values.
 * More information on the <a href='http://piwik.org/docs/analytics-api/metadata/' rel='noreferrer' target='_blank'>Metadata API documentation page</a>
 *
 * @method static \Piwik\Plugins\API\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * Get Piwik version
     * @return string
     */
    public function getPiwikVersion()
    {
        Piwik::checkUserHasSomeViewAccess();
        return Version::VERSION;
    }

    /**
     * Returns the most accurate IP address availble for the current user, in
     * IPv4 format. This could be the proxy client's IP address.
     *
     * @return string IP address in presentation format.
     */
    public function getIpFromHeader()
    {
        Piwik::checkUserHasSomeViewAccess();
        return IP::getIpFromHeader();
    }

    /**
     * Returns the section [APISettings] if defined in config.ini.php
     * @return array
     */
    public function getSettings()
    {
        return Config::getInstance()->APISettings;
    }

    /**
     * Default translations for many core metrics.
     * This is used for exports with translated labels. The exports contain columns that
     * are not visible in the UI and not present in the API meta data. These columns are
     * translated here.
     * @return array
     */
    public static function getDefaultMetricTranslations()
    {
        return Metrics::getDefaultMetricTranslations();
    }

    /**
     * Returns all available measurable types.
     * Marked as deprecated so it won't appear in API page. It won't be a public API for now.
     * @deprecated
     * @return array
     */
    public function getAvailableMeasurableTypes()
    {
        $typeManager = new TypeManager();
        $types = $typeManager->getAllTypes();

        $available = array();
        foreach ($types as $type) {
            $available[] = array(
                'id' => $type->getId(),
                'name' => Piwik::translate($type->getName()),
                'description' => Piwik::translate($type->getDescription()),
                'howToSetupUrl' => $type->getHowToSetupUrl()
            );
        }

        return $available;
    }

    public function getSegmentsMetadata($idSites = array(), $_hideImplementationData = true)
    {
        $isAuthenticatedWithViewAccess = Piwik::isUserHasViewAccess($idSites) && !Piwik::isUserIsAnonymous();

        $sites   = (is_array($idSites) ? implode('.', $idSites) : (int) $idSites);
        $cache   = Cache::getTransientCache();
        $cachKey = 'API.getSegmentsMetadata' . $sites . '_' . (int) $_hideImplementationData . '_' . (int) $isAuthenticatedWithViewAccess;
        $cachKey = CacheId::pluginAware($cachKey);

        if ($cache->contains($cachKey)) {
            return $cache->fetch($cachKey);
        }

        $metadata = new SegmentMetadata();
        $segments = $metadata->getSegmentsMetadata($idSites, $_hideImplementationData, $isAuthenticatedWithViewAccess);

        $cache->save($cachKey, $segments);

        return $segments;
    }

    /**
     * Returns the url to application logo (~280x110px)
     *
     * @param bool $pathOnly If true, returns path relative to doc root. Otherwise, returns a URL.
     * @return string
     */
    public function getLogoUrl($pathOnly = false)
    {
        $logo = new CustomLogo();
        return $logo->getLogoUrl($pathOnly);
    }

    /**
     * Returns the url to header logo (~127x50px)
     *
     * @param bool $pathOnly If true, returns path relative to doc root. Otherwise, returns a URL.
     * @return string
     */
    public function getHeaderLogoUrl($pathOnly = false)
    {
        $logo = new CustomLogo();
        return $logo->getHeaderLogoUrl($pathOnly);
    }

    /**
     * Returns the URL to application SVG Logo
     *
     * @ignore
     * @param bool $pathOnly If true, returns path relative to doc root. Otherwise, returns a URL.
     * @return string
     */
    public function getSVGLogoUrl($pathOnly = false)
    {
        $logo = new CustomLogo();
        return $logo->getSVGLogoUrl($pathOnly);
    }

    /**
     * Returns whether there is an SVG Logo available.
     * @ignore
     * @return bool
     */
    public function hasSVGLogo()
    {
        $logo = new CustomLogo();
        return $logo->hasSVGLogo();
    }

    /**
     * Loads reports metadata, then return the requested one,
     * matching optional API parameters.
     */
    public function getMetadata($idSite, $apiModule, $apiAction, $apiParameters = array(), $language = false,
                                $period = false, $date = false, $hideMetricsDoc = false, $showSubtableReports = false)
    {
        if ($language) {
            /** @var Translator $translator */
            $translator = StaticContainer::get('Piwik\Translation\Translator');
            $translator->setCurrentLanguage($language);
        }

        $reporter = new ProcessedReport();
        $metadata = $reporter->getMetadata($idSite, $apiModule, $apiAction, $apiParameters, $language, $period, $date, $hideMetricsDoc, $showSubtableReports);
        return $metadata;
    }

    /**
     * Triggers a hook to ask plugins for available Reports.
     * Returns metadata information about each report (category, name, dimension, metrics, etc.)
     *
     * @param string $idSites Comma separated list of website Ids
     * @param bool|string $period
     * @param bool|Date $date
     * @param bool $hideMetricsDoc
     * @param bool $showSubtableReports
     * @return array
     */
    public function getReportMetadata($idSites = '', $period = false, $date = false, $hideMetricsDoc = false,
                                      $showSubtableReports = false)
    {
        $reporter = new ProcessedReport();
        $metadata = $reporter->getReportMetadata($idSites, $period, $date, $hideMetricsDoc, $showSubtableReports);
        return $metadata;
    }

    public function getProcessedReport($idSite, $period, $date, $apiModule, $apiAction, $segment = false,
                                       $apiParameters = false, $idGoal = false, $language = false,
                                       $showTimer = true, $hideMetricsDoc = false, $idSubtable = false, $showRawMetrics = false,
                                       $format_metrics = null)
    {
        $reporter = new ProcessedReport();
        $processed = $reporter->getProcessedReport($idSite, $period, $date, $apiModule, $apiAction, $segment,
            $apiParameters, $idGoal, $language, $showTimer, $hideMetricsDoc, $idSubtable, $showRawMetrics, $format_metrics);

        return $processed;
    }

    /**
     * Get a list of all pages that shall be shown in a Piwik UI including a list of all widgets that shall
     * be shown within each page.
     *
     * @param int $idSite
     * @return array
     */
    public function getReportPagesMetadata($idSite)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $widgetsList  = WidgetsList::get();
        $categoryList = CategoryList::get();
        $metadata     = new WidgetMetadata();

        return $metadata->getPagesMetadata($categoryList, $widgetsList);
    }

    /**
     * Get a list of all widgetizable widgets.
     *
     * @param int $idSite
     * @return array
     */
    public function getWidgetMetadata($idSite)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $widgetsList  = WidgetsList::get();
        $categoryList = CategoryList::get();
        $metadata     = new WidgetMetadata();

        return $metadata->getWidgetMetadata($categoryList, $widgetsList);
    }

    /**
     * Get a combined report of the *.get API methods.
     */
    public function get($idSite, $period, $date, $segment = false, $columns = false)
    {
        $columns = Piwik::getArrayFromApiParameter($columns);

        // build columns map for faster checks later on
        $columnsMap = array();
        foreach ($columns as $column) {
            $columnsMap[$column] = true;
        }

        // find out which columns belong to which plugin
        $columnsByPlugin = array();
        $meta = \Piwik\Plugins\API\API::getInstance()->getReportMetadata($idSite, $period, $date);
        foreach ($meta as $reportMeta) {
            // scan all *.get reports
            if ($reportMeta['action'] == 'get'
                && !isset($reportMeta['parameters'])
                && $reportMeta['module'] != 'API'
                && !empty($reportMeta['metrics'])
            ) {
                $plugin = $reportMeta['module'];
                $allMetrics = array_merge($reportMeta['metrics'], @$reportMeta['processedMetrics'] ?: array());
                foreach ($allMetrics as $column => $columnTranslation) {
                    // a metric from this report has been requested
                    if (isset($columnsMap[$column])
                        // or by default, return all metrics
                        || empty($columnsMap)
                    ) {
                        $columnsByPlugin[$plugin][] = $column;
                    }
                }
            }
        }
        krsort($columnsByPlugin);

        $mergedDataTable = false;
        $params = compact('idSite', 'period', 'date', 'segment', 'idGoal');
        foreach ($columnsByPlugin as $plugin => $columns) {
            // load the data
            $className = Request::getClassNameAPI($plugin);
            $params['columns'] = implode(',', $columns);
            $dataTable = Proxy::getInstance()->call($className, 'get', $params);

            $dataTable->filter(function (DataTable $table) {
                $table->clearQueuedFilters();
            });

            // merge reports
            if ($mergedDataTable === false) {
                $mergedDataTable = $dataTable;
            } else {
                $merger = new MergeDataTables();
                $merger->mergeDataTables($mergedDataTable, $dataTable);
            }
        }

        if (!empty($columnsMap)
            && !empty($mergedDataTable)
        ) {
            $mergedDataTable->queueFilter('ColumnDelete', array(false, array_keys($columnsMap)));
        }

        return $mergedDataTable;
    }

    /**
     * Given an API report to query (eg. "Referrers.getKeywords", and a Label (eg. "free%20software"),
     * this function will query the API for the previous days/weeks/etc. and will return
     * a ready to use data structure containing the metrics for the requested Label, along with enriched information (min/max values, etc.)
     *
     * @param int $idSite
     * @param string $period
     * @param Date $date
     * @param string $apiModule
     * @param string $apiAction
     * @param bool|string $label
     * @param bool|string $segment
     * @param bool|string $column
     * @param bool|string $language
     * @param bool|int $idGoal
     * @param bool|string $legendAppendMetric
     * @param bool|string $labelUseAbsoluteUrl
     * @return array
     */
    public function getRowEvolution($idSite, $period, $date, $apiModule, $apiAction, $label = false, $segment = false, $column = false, $language = false, $idGoal = false, $legendAppendMetric = true, $labelUseAbsoluteUrl = true)
    {
        $rowEvolution = new RowEvolution();
        return $rowEvolution->getRowEvolution($idSite, $period, $date, $apiModule, $apiAction, $label, $segment, $column,
            $language, $idGoal, $legendAppendMetric, $labelUseAbsoluteUrl);
    }

    public function getLastDate($date, $period)
    {
        $lastDate = Range::getLastDate($date, $period);

        return array_shift($lastDate);
    }

    /**
     * Performs multiple API requests at once and returns every result.
     *
     * @param array $urls The array of API requests.
     * @return array
     */
    public function getBulkRequest($urls)
    {
        if (empty($urls)) {
            return array();
        }

        $urls = array_map('urldecode', $urls);
        $urls = array_map(array('Piwik\Common', 'unsanitizeInputValue'), $urls);

        $result = array();
        foreach ($urls as $url) {
            $req = new Request($url . '&format=php&serialize=0');
            $result[] = $req->process();
        }
        return $result;
    }

    /**
     * Return true if plugin is activated, false otherwise
     *
     * @param string $pluginName
     * @return bool
     */
    public function isPluginActivated($pluginName)
    {
        Piwik::checkUserHasSomeViewAccess();
        return \Piwik\Plugin\Manager::getInstance()->isPluginActivated($pluginName);
    }

    /**
     * Given a segment, will return a list of the most used values for this particular segment.
     * @param $segmentName
     * @param $idSite
     * @throws \Exception
     * @return array
     */
    public function getSuggestedValuesForSegment($segmentName, $idSite)
    {
        if (empty(Config::getInstance()->General['enable_segment_suggested_values'])) {
            return array();
        }
        Piwik::checkUserHasViewAccess($idSite);

        $maxSuggestionsToReturn = 30;
        $segmentsMetadata = $this->getSegmentsMetadata($idSite, $_hideImplementationData = false);

        $segmentFound = false;
        foreach ($segmentsMetadata as $segmentMetadata) {
            if ($segmentMetadata['segment'] == $segmentName) {
                $segmentFound = $segmentMetadata;
                break;
            }
        }
        if (empty($segmentFound)) {
            throw new \Exception("Requested segment not found.");
        }

        // if segment has suggested values callback then return result from it instead
        if (isset($segmentFound['suggestedValuesCallback'])) {
            return call_user_func($segmentFound['suggestedValuesCallback'], $idSite, $maxSuggestionsToReturn);
        }

        // if period=range is disabled, do not proceed
        if (!Period\Factory::isPeriodEnabledForAPI('range')) {
            return array();
        }

        $startDate = Date::now()->subDay(60)->toString();
        $requestLastVisits = "method=Live.getLastVisitsDetails
        &idSite=$idSite
        &period=range
        &date=$startDate,today
        &format=original
        &serialize=0
        &flat=1";

        // Select non empty fields only
        // Note: this optimization has only a very minor impact
        $requestLastVisits .= "&segment=$segmentName" . urlencode('!=');

        // By default Live fetches all actions for all visitors, but we'd rather do this only when required
        if ($this->doesSegmentNeedActionsData($segmentName)) {
            $requestLastVisits .= "&filter_limit=400";
        } else {
            $requestLastVisits .= "&doNotFetchActions=1";
            $requestLastVisits .= "&filter_limit=800";
        }

        $request = new Request($requestLastVisits);
        $table = $request->process();
        if (empty($table)) {
            throw new \Exception("There was no data to suggest for $segmentName");
        }

        // Cleanup data to return the top suggested (non empty) labels for this segment
        $values = $table->getColumn($segmentName);

        // Select also flattened keys (custom variables "page" scope, page URLs for one visit, page titles for one visit)
        $valuesBis = $table->getColumnsStartingWith($segmentName . ColumnDelete::APPEND_TO_COLUMN_NAME_TO_KEEP);
        $values = array_merge($values, $valuesBis);

        $values = $this->getMostFrequentValues($values);

        $values = array_slice($values, 0, $maxSuggestionsToReturn);

        $values = array_map(array('Piwik\Common', 'unsanitizeInputValue'), $values);

        return $values;
    }

    /**
     * @param $segmentName
     * @return bool
     */
    protected function doesSegmentNeedActionsData($segmentName)
    {
        // If you update this, also update flattenVisitorDetailsArray
        $segmentsNeedActionsInfo = array('visitConvertedGoalId',
                                         'pageUrl', 'pageTitle', 'siteSearchKeyword',
                                         'entryPageTitle', 'entryPageUrl', 'exitPageTitle', 'exitPageUrl');
        $isCustomVariablePage = stripos($segmentName, 'customVariablePage') !== false;
        $isEventSegment = stripos($segmentName, 'event') !== false;
        $isContentSegment = stripos($segmentName, 'content') !== false;
        $doesSegmentNeedActionsInfo = in_array($segmentName, $segmentsNeedActionsInfo) || $isCustomVariablePage || $isEventSegment || $isContentSegment;
        return $doesSegmentNeedActionsInfo;
    }

    /**
     * @param $values
     * @param $value
     * @return array
     */
    private function getMostFrequentValues($values)
    {
        // remove false values (while keeping zeros)
        $values = array_filter($values, 'strlen');

        // array_count_values requires strings or integer, convert floats to string (mysqli)
        foreach ($values as &$value) {
            if (is_numeric($value)) {
                $value = (string)round($value, 3);
            }
        }
        // we have a list of all values. let's show the most frequently used first.
        $values = array_count_values($values);

        arsort($values);
        $values = array_keys($values);
        return $values;
    }
}

/**
 */
class Plugin extends \Piwik\Plugin
{
    public function __construct()
    {
        // this class is named 'Plugin', manually set the 'API' plugin
        parent::__construct($pluginName = 'API');
    }

    /**
     * @see Piwik\Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles'
        );
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/API/stylesheets/listAllAPI.less";
    }
}
