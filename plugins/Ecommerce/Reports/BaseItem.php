<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Ecommerce\Reports;

use Piwik\API\Request;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\Goals\Goals;
use Piwik\Plugins\Goals\Columns\Metrics\AveragePrice;
use Piwik\Plugins\Goals\Columns\Metrics\AverageQuantity;
use Piwik\Plugins\Goals\Columns\Metrics\ProductConversionRate;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

abstract class BaseItem extends Base
{
    protected $defaultSortColumn = 'revenue';

    protected function init()
    {
        parent::init();
        $this->processedMetrics = array(
            new AveragePrice(),
            new AverageQuantity(),
            new ProductConversionRate()
        );
        $this->metrics = array(
            'revenue', 'quantity', 'orders', 'nb_visits'
        );
    }

    public function getMetrics()
    {
        $metrics = parent::getMetrics();
        $metrics['revenue'] = Piwik::translate('General_ProductRevenue');
        $metrics['orders']  = Piwik::translate('General_UniquePurchases');
        return $metrics;
    }

    public function getMetricsDocumentation()
    {
        if ($this->isAbandonedCart()) {
            return array(
                'revenue'         => Piwik::translate('Goals_ColumnRevenueDocumentation',
                                            Piwik::translate('Goals_DocumentationRevenueGeneratedByProductSales')),
                'quantity'        => Piwik::translate('Goals_ColumnQuantityDocumentation', $this->name),
                'orders'          => Piwik::translate('Goals_ColumnOrdersDocumentation', $this->name),
                'avg_price'       => Piwik::translate('Goals_ColumnAveragePriceDocumentation', $this->name),
                'avg_quantity'    => Piwik::translate('Goals_ColumnAverageQuantityDocumentation', $this->name),
                'nb_visits'       => Piwik::translate('Goals_ColumnVisitsProductDocumentation', $this->name),
                'conversion_rate' => Piwik::translate('Goals_ColumnConversionRateProductDocumentation', $this->name),
            );
        }

        return array();
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $conversions = $this->getConversionForGoal(Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_ORDER);
        $cartNbConversions = $this->getConversionForGoal(Piwik::LABEL_ID_GOAL_IS_ECOMMERCE_CART);
        $preloadAbandonedCart = $cartNbConversions !== false && $conversions == 0;

        $ecommerceCustomParams = array();
        if ($preloadAbandonedCart) {
            $ecommerceCustomParams['abandonedCarts'] = '1';
        } else {
            $ecommerceCustomParams['abandonedCarts'] = '0';
        }

        $widgetsList->addToContainerWidget('Products', $factory->createWidget()->setParameters($ecommerceCustomParams));
    }

    private function getConversionForGoal($idGoal = '')
    {
        $period = Common::getRequestVar('period', '', 'string');
        $date   = Common::getRequestVar('date', '', 'string');
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (!$period || !$date || !$idSite) {
            return false;
        }

        $datatable = Request::processRequest('Goals.get', array(
            'idGoal' => $idGoal,
            'period' => $period,
            'date' => $date,
            'idSite' => $idSite,
            'serialize' => 0,
            'segment' => false
        ));

        $dataRow = $datatable->getFirstRow();

        if (!$dataRow) {
            return false;
        }

        return $dataRow->getColumn('nb_conversions');
    }

    public function configureView(ViewDataTable $view)
    {
        $idSite = Common::getRequestVar('idSite');

        $view->config->show_ecommerce = true;
        $view->config->show_table     = false;
        $view->config->show_all_views_icons      = false;
        $view->config->show_exclude_low_population = false;
        $view->config->show_table_all_columns      = false;

        if (!($view instanceof Evolution)) {
            $moneyColumns = array('revenue');
            $formatter    = array(new Formatter(), 'getPrettyMoney');
            $view->config->filters[] = array('ColumnCallbackReplace', array($moneyColumns, $formatter, array($idSite)));
        }

        $view->requestConfig->filter_limit       = 10;
        $view->requestConfig->filter_sort_column = 'revenue';
        $view->requestConfig->filter_sort_order  = 'desc';

        $view->config->custom_parameters['isFooterExpandedInDashboard'] = true;

        // set columns/translations which differ based on viewDataTable TODO: shouldn't have to do this check...
        // amount of reports should be dynamic, but metadata should be static
        $columns = array_merge($this->getMetrics(), $this->getProcessedMetrics());
        $columnsOrdered = array('label', 'revenue', 'quantity', 'orders', 'avg_price', 'avg_quantity',
                                'nb_visits', 'conversion_rate');

        // handle old case where viewDataTable is set to ecommerceOrder/ecommerceAbandonedCart. in this case, we
        // set abandonedCarts accordingly and remove the ecommerceOrder/ecommerceAbandonedCart as viewDataTable.
        $viewDataTable = Common::getRequestVar('viewDataTable', '');
        if ($viewDataTable == 'ecommerceOrder') {
            $view->config->custom_parameters['viewDataTable'] = 'table';
            $abandonedCart = false;
        } else if ($viewDataTable == 'ecommerceAbandonedCart') {
            $view->config->custom_parameters['viewDataTable'] = 'table';
            $abandonedCart = true;
        } else {
            $abandonedCart = $this->isAbandonedCart();
        }

        if ($abandonedCart) {
            $columns['abandoned_carts'] = Piwik::translate('General_AbandonedCarts');
            $columns['revenue'] = Piwik::translate('Goals_LeftInCart', $columns['revenue']);
            $columns['quantity'] = Piwik::translate('Goals_LeftInCart', $columns['quantity']);
            $columns['avg_quantity'] = Piwik::translate('Goals_LeftInCart', $columns['avg_quantity']);
            unset($columns['orders']);
            unset($columns['conversion_rate']);

            $columnsOrdered = array('label', 'revenue', 'quantity', 'avg_price', 'avg_quantity', 'nb_visits',
                                    'abandoned_carts');

            $view->config->custom_parameters['abandonedCarts'] = '1';
        } else {
            $view->config->custom_parameters['abandonedCarts'] = '0';
        }

        $view->requestConfig->request_parameters_to_modify['abandonedCarts'] = $view->config->custom_parameters['abandonedCarts'];

        $translations = array_merge(array('label' => $this->name), $columns);

        $view->config->addTranslations($translations);
        $view->config->columns_to_display = $columnsOrdered;
    }

    private function isAbandonedCart()
    {
        return Common::getRequestVar('abandonedCarts', '0', 'string') == 1;
    }
}
