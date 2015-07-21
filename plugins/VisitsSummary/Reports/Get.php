<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitsSummary\Reports;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\DataTable\DataTableInterface;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreHome\Columns\Metrics\ActionsPerVisit;
use Piwik\Plugins\CoreHome\Columns\Metrics\AverageTimeOnSite;
use Piwik\Plugins\CoreHome\Columns\Metrics\BounceRate;
use Piwik\Plugins\CoreHome\Columns\UserId;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Report\ReportWidgetFactory;
use Piwik\SettingsPiwik;
use Piwik\Site;
use Piwik\Widget\WidgetsList;

class Get extends \Piwik\Plugin\Report
{
    private $usersColumn = 'nb_users';

    protected function init()
    {
        parent::init();
        $this->categoryId    = 'General_Visitors';
        $this->name          = Piwik::translate('VisitsSummary_VisitsSummary');
        $this->documentation = ''; // TODO
        $this->processedMetrics = array(
            new BounceRate(),
            new ActionsPerVisit(),
            new AverageTimeOnSite()
        );
        $this->metrics       = array(
            'nb_uniq_visitors',
            'nb_visits',
            $this->usersColumn,
            'nb_actions',
            'max_actions'
        );
        $this->subcategoryId = 'General_Overview';
        // Used to process metrics, not displayed/used directly
//								'sum_visit_length',
//								'nb_visits_converted',
        $this->order = 1;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidget(
            $factory->createWidget()
                ->setName('VisitsSummary_WidgetLastVisits')
                ->forceViewDataTable(Evolution::ID)
                ->setAction('getEvolutionGraph')
                ->setOrder(5)
        );

        $widgetsList->addWidget(
            $factory->createWidget()
                ->setName('VisitsSummary_WidgetVisits')
                ->forceViewDataTable(Sparklines::ID)
                ->setOrder(10)
        );
    }

    public function configureView(ViewDataTable $view)
    {
        if ($view->isViewDataTableId(Sparklines::ID)) {
            $view->requestConfig->apiMethodToRequestDataTable = 'API.get';
            $view->config->sparkline_metrics_to_display = $this->getSparklineColumns();
            $view->config->addTranslations($this->getSparklineTranslations());
            $view->config->filters[] = function (DataTable $table) use ($view) {
                $firstRow = $table->getFirstRow();

                if (($firstRow->getColumn('nb_pageviews')
                    + $firstRow->getColumn('nb_downloads')
                    + $firstRow->getColumn('nb_outlinks')) == 0
                    && $firstRow->getColumn('nb_actions') > 0) {
                    $view->config->removeSparklineMetricToDisplay(array('nb_downloads', 'nb_uniq_downloads'));
                    $view->config->removeSparklineMetricToDisplay(array('nb_outlinks', 'nb_uniq_outlinks'));
                    $view->config->removeSparklineMetricToDisplay(array('nb_pageviews', 'nb_uniq_pageviews'));
                    $view->config->removeSparklineMetricToDisplay(array('nb_searches', 'nb_keywords'));
                } else {
                    $view->config->removeSparklineMetricToDisplay(array('nb_actions'));
                }

                $nbUsers = $firstRow->getColumn('nb_users');
                if (!is_numeric($nbUsers) || 0 >= $nbUsers) {
                    $view->config->removeSparklineMetricToDisplay(array('nb_users'), '');
                }

                $avgGenerationTime = $firstRow->getColumn('avg_time_generation');
                if (false === $avgGenerationTime) {
                    // fix avgGenerationTime is not formatted if value is false
                    /** @var Formatter $formatter */
                    $formatter = StaticContainer::get('Piwik\Metrics\Formatter');
                    $avgGenerationTime = $formatter->getPrettyTimeFromSeconds($avgGenerationTime, true);
                    $firstRow->setColumn('avg_time_generation', $avgGenerationTime);
                }
            };
        }
    }

    private function getSparklineTranslations()
    {
        $translations = array(
            'nb_actions' => 'NbActionsDescription',
            'nb_visits' => 'NbVisitsDescription',
            'nb_users' => 'NbUsersDescription',
            'nb_uniq_visitors' => 'NbUniqueVisitors',
            'avg_time_generation' => 'AverageGenerationTime',
            'avg_time_on_site' => 'AverageVisitDuration',
            'max_actions' => 'MaxNbActions',
            'nb_actions_per_visit' => 'NbActionsPerVisit',
            'nb_downloads' => 'NbDownloadsDescription',
            'nb_uniq_downloads' => 'NbUniqueDownloadsDescription',
            'nb_outlinks' => 'NbOutlinksDescription',
            'nb_uniq_outlinks' => 'NbUniqueOutlinksDescription',
            'nb_keywords' => 'NbKeywordsDescription',
            'nb_searches' => 'NbSearchesDescription',
            'nb_pageviews' => 'NbPageviewsDescription',
            'nb_uniq_pageviews' => 'NbUniquePageviewsDescription',
            'bounce_rate' => 'NbVisitsBounced',
        );

        foreach ($translations as $metric => $key) {
            $translations[$metric] = Piwik::translate('VisitsSummary_' . $key);
        }

        return $translations;
    }

    private function getSparklineColumns()
    {
        $currentPeriod = Common::getRequestVar('period');
        $currentIdSite = Common::getRequestVar('idSite');
        $currentDate   = Common::getRequestVar('date');
        $displayUniqueVisitors = SettingsPiwik::isUniqueVisitorsEnabled($currentPeriod);

        // todo in theory this should be done in Actions Plugin, but then it'll be hard to change the "order"
        $isActionPluginEnabled = Common::isActionsPluginEnabled();

        $columns = array(
            $displayUniqueVisitors ? array('nb_visits', 'nb_uniq_visitors') : array('nb_visits'),
        );

        if ($isActionPluginEnabled) {
            $columns[] = array('nb_actions'); // either actions or pageviews will be displayed
            $columns[] = array('nb_pageviews', 'nb_uniq_pageviews');
        } else {
            $columns[] = array(); // make sure to still create a div on the right side for this, just leave it empty
        }

        $userId = new UserId();
        if ($userId->isUsedInAtLeastOneSite($currentIdSite, $currentPeriod, $currentDate)) {
            $columns[] = array('nb_users');
            $columns[] = array();
        }

        $columns[] = array('avg_time_on_site');

        $idSite = Common::getRequestVar('idSite');
        if ($isActionPluginEnabled && Site::isSiteSearchEnabledFor($idSite)) {
            $columns[] = array('nb_searches', 'nb_keywords');
        } else {
            $columns[] = array(); // make sure to still create a div on the right side for this, just leave it empty
        }

        $columns[] = array('bounce_rate');

        if ($isActionPluginEnabled) {
            $columns[] = array('nb_downloads', 'nb_uniq_downloads');
            $columns[] = array('nb_actions_per_visit');
            $columns[] = array('nb_outlinks', 'nb_uniq_outlinks');
            $columns[] = array('avg_time_generation');
            $columns[] = array('max_actions');
        }

        return $columns;
    }

    public function getMetrics()
    {
        $metrics = parent::getMetrics();

        $metrics['max_actions'] = Piwik::translate('General_ColumnMaxActions');

        return $metrics;
    }

    public function getProcessedMetrics()
    {
        $metrics = parent::getProcessedMetrics();

        $metrics['avg_time_on_site'] = Piwik::translate('General_VisitDuration');

        return $metrics;
    }

    public function removeUsersFromProcessedReport(&$response)
    {
        if (!empty($response['metadata']['metrics'][$this->usersColumn])) {
            unset($response['metadata']['metrics'][$this->usersColumn]);
        }

        if (!empty($response['metadata']['metricsDocumentation'][$this->usersColumn])) {
            unset($response['metadata']['metricsDocumentation'][$this->usersColumn]);
        }

        if (!empty($response['columns'][$this->usersColumn])) {
            unset($response['columns'][$this->usersColumn]);
        }

        if (!empty($response['reportData'])) {
            $dataTable = $response['reportData'];
            $dataTable->deleteColumn($this->usersColumn, true);
        }
    }

}