<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Goals\Reports;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Plugins\Goals\API;
use Piwik\Plugins\Goals\Goals;
use Piwik\Plugins\Goals\Pages;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Site;
use Piwik\Widget\WidgetsList;

class Get extends Base
{
    protected function init()
    {
        parent::init();

        $this->name = Piwik::translate('Goals_Goals');
        $this->processedMetrics = array('conversion_rate');
        $this->documentation = ''; // TODO
        $this->order = 1;
        $this->orderGoal = 50;
        $this->metrics = array('nb_conversions', 'nb_visits_converted', 'revenue');
        $this->parameters = null;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $idSite  = $this->getIdSite();
        $goals   = API::getInstance()->getGoals($idSite);
        $reports = Goals::getReportsWithGoalMetrics();

        $page = new Pages($factory, $reports);

        $widgetsList->addWidgets($page->createGoalsOverviewPage($goals));

        if ($this->isEcommerceEnabled($idSite)) {
            $widgetsList->addWidgets($page->createEcommerceOverviewPage());
            $widgetsList->addWidgets($page->createEcommerceSalesPage());
        }

        foreach ($goals as $goal) {
            $widgetsList->addWidgets($page->createGoalDetailPage($goal));
        }
    }

    private function getIdSite()
    {
        return Common::getRequestVar('idSite', null, 'int');
    }

    private function isEcommerceEnabled($idSite)
    {
        if (!Plugin\Manager::getInstance()->isPluginActivated('Ecommerce')) {
            return false;
        }

        $site = new Site($idSite);
        return $site->isEcommerceEnabled();
    }

    public function configureView(ViewDataTable $view)
    {
        if ($view->isViewDataTableId(Sparklines::ID)) {
            $idSite = $this->getIdSite();
            $isEcommerceEnabled = $this->isEcommerceEnabled($idSite);

            $idGoal = Common::getRequestVar('idGoal', 0, 'int');

            $formatter = new Formatter();
            $view->config->filters[] = function (DataTable $table) use ($formatter, $idSite) {
                $firstRow = $table->getFirstRow();
                if ($firstRow) {
                    $revenue = $firstRow->getColumn('revenue');
                    $firstRow->setColumn('revenue', $formatter->getPrettyMoney($revenue, $idSite));
                }
            };

            $view->config->addTranslations(array(
                'nb_visits' => Piwik::translate('VisitsSummary_NbVisitsDescription'),
                'nb_conversions' => Piwik::translate('Goals_ConversionsDescription'),
                'nb_visits_converted' => Piwik::translate('General_NVisits'),
                'conversion_rate' => Piwik::translate('Goals_OverallConversionRate'),
                'revenue' => Piwik::translate('Goals_OverallRevenue'),
            ));


            $allowMultiple = Common::getRequestVar('allow_multiple', 0, 'int');

            if ($allowMultiple) {
                $view->config->addSparklineMetricsToDisplay(array('nb_conversions', 'nb_visits_converted'));
            } else {
                $view->config->addSparklineMetricsToDisplay(array('nb_conversions'));
            }

            $view->config->addSparklineMetricsToDisplay(array('conversion_rate'));

            if (empty($idGoal)) {
                // goals overview sparklines below evolution graph

                if ($isEcommerceEnabled) {
                    // TODO this should be done in Ecommerce plugin
                    $view->config->addSparklineMetricsToDisplay(array('revenue'));
                }

            } else {
                $onlySummary = Common::getRequestVar('only_summary', 0, 'int');

                if ($onlySummary) {
                    // in Goals Overview we list an overview for each goal....
                    $view->config->addTranslation('conversion_rate', Piwik::translate('Goals_ConversionRate'));

                } elseif ($isEcommerceEnabled) {
                    // in Goals detail page...
                    // TODO this should be done in Ecommerce plugin
                    $view->config->addSparklineMetricsToDisplay(array('revenue'));
                }
            }
        } else if ($view->isViewDataTableId(Evolution::ID)) {
            if (empty($view->config->columns_to_display)) {
                $view->config->columns_to_display = array('nb_conversions');
            }
        }
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        if (!$this->isEnabled()) {
            return;
        }

        parent::configureReportMetadata($availableReports, $infos);

        $this->addReportMetadataForEachGoal($availableReports, $infos, function ($goal) {
            return Piwik::translate('Goals_GoalX', $goal['name']);
        });
    }
}
