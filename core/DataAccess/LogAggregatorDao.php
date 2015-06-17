<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\DataAccess;

use Piwik\ArchiveProcessor\Parameters;
use Piwik\Common;
use Piwik\Db\Connection;
use Piwik\Metrics;
use Piwik\RankingQuery;
use Piwik\Tracker\GoalManager;

/**
 * TODO
 */
class LogAggregatorDao
{
    const LOG_VISIT_TABLE = 'log_visit';

    const LOG_ACTIONS_TABLE = 'log_link_visit_action';

    const LOG_CONVERSION_TABLE = "log_conversion";

    const REVENUE_SUBTOTAL_FIELD = 'revenue_subtotal';

    const REVENUE_TAX_FIELD = 'revenue_tax';

    const REVENUE_SHIPPING_FIELD = 'revenue_shipping';

    const REVENUE_DISCOUNT_FIELD = 'revenue_discount';

    const TOTAL_REVENUE_FIELD = 'revenue';

    const ITEMS_COUNT_FIELD = "items";

    const CONVERSION_DATETIME_FIELD = "server_time";

    const ACTION_DATETIME_FIELD = "server_time";

    const VISIT_DATETIME_FIELD = 'visit_last_action_time';

    const IDGOAL_FIELD = 'idgoal';

    const FIELDS_SEPARATOR = ", \n\t\t\t";

    /** @var Connection $db */
    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    public function generateQuery(Parameters $params, $select, $from, $where, $groupBy, $orderBy)
    {
        $bind = $this->getGeneralQueryBindParams($params);
        $query = $params->getSegment()->getSelectQuery($select, $from, $where, $bind, $orderBy, $groupBy);
        return $query;
    }

    /**
     * @param Parameters $params
     * @param array $dimensions
     * @param bool|false $where
     * @param array $additionalSelects
     * @param bool|array $metrics
     * @param bool|RankingQuery $rankingQuery
     * @return mixed
     */
    public function queryVisitsByDimension(Parameters $params, array $dimensions = array(), $where = false, array $additionalSelects = array(),
                                           $metrics = false, $rankingQuery = false)
    {
        $tableName = self::LOG_VISIT_TABLE;
        $availableMetrics = $this->getVisitsMetricFields();

        $select  = $this->getSelectStatement($dimensions, $tableName, $additionalSelects, $availableMetrics, $metrics);
        $from    = array($tableName);
        $where   = $this->getWhereStatement($params, $tableName, self::VISIT_DATETIME_FIELD, $where);
        $groupBy = $this->getGroupByStatement($dimensions, $tableName);
        $orderBy = false;

        if ($rankingQuery) {
            $orderBy = '`' . Metrics::INDEX_NB_VISITS . '` DESC';
        }

        $query = $this->generateQuery($params, $select, $from, $where, $groupBy, $orderBy);

        if ($rankingQuery) {
            unset($availableMetrics[Metrics::INDEX_MAX_ACTIONS]);
            $sumColumns = array_keys($availableMetrics);

            if ($metrics) {
                $sumColumns = array_intersect($sumColumns, $metrics);
            }

            $rankingQuery->addColumn($sumColumns, 'sum');
            if ($this->isMetricRequested(Metrics::INDEX_MAX_ACTIONS, $metrics)) {
                $rankingQuery->addColumn(Metrics::INDEX_MAX_ACTIONS, 'max');
            }

            return $rankingQuery->execute($query['sql'], $query['bind']);
        }

        return $this->db->query($query['sql'], $query['bind']);
    }

    public function queryEcommerceItems(Parameters $params, $dimension)
    {
        $query = $this->generateQuery(
            $params,
            // SELECT ...
            implode(
                ', ',
                array(
                    "log_action.name AS label",
                    sprintf("log_conversion_item.%s AS labelIdAction", $dimension),
                    sprintf(
                        '%s AS `%d`',
                        self::getSqlRevenue('SUM(log_conversion_item.quantity * log_conversion_item.price)'),
                        Metrics::INDEX_ECOMMERCE_ITEM_REVENUE
                    ),
                    sprintf(
                        '%s AS `%d`',
                        self::getSqlRevenue('SUM(log_conversion_item.quantity)'),
                        Metrics::INDEX_ECOMMERCE_ITEM_QUANTITY
                    ),
                    sprintf(
                        '%s AS `%d`',
                        self::getSqlRevenue('SUM(log_conversion_item.price)'),
                        Metrics::INDEX_ECOMMERCE_ITEM_PRICE
                    ),
                    sprintf(
                        'COUNT(distinct log_conversion_item.idorder) AS `%d`',
                        Metrics::INDEX_ECOMMERCE_ORDERS
                    ),
                    sprintf(
                        'COUNT(distinct log_conversion_item.idvisit) AS `%d`',
                        Metrics::INDEX_NB_VISITS
                    ),
                    sprintf(
                        'CASE log_conversion_item.idorder WHEN \'0\' THEN %d ELSE %d END AS ecommerceType',
                        GoalManager::IDGOAL_CART,
                        GoalManager::IDGOAL_ORDER
                    )
                )
            ),

            // FROM ...
            array(
                "log_conversion_item",
                array(
                    "table" => "log_action",
                    "joinOn" => sprintf("log_conversion_item.%s = log_action.idaction", $dimension)
                )
            ),

            // WHERE ... AND ...
            implode(
                ' AND ',
                array(
                    'log_conversion_item.server_time >= ?',
                    'log_conversion_item.server_time <= ?',
                    'log_conversion_item.idsite IN (' . Common::getSqlStringFieldsArray($params->getIdSites()) . ')',
                    'log_conversion_item.deleted = 0'
                )
            ),

            // GROUP BY ...
            sprintf(
                "ecommerceType, log_conversion_item.%s",
                $dimension
            ),

            // ORDER ...
            false
        );

        return $this->db->query($query['sql'], $query['bind']);
    }

    /**
     * @param Parameters $params
     * @param $dimensions
     * @param string $where
     * @param array $additionalSelects
     * @param bool|array $metrics
     * @param RankingQuery $rankingQuery
     * @param bool|false $joinLogActionOnColumn
     * @return mixed
     */
    public function queryActionsByDimension(Parameters $params, $dimensions, $where = '', $additionalSelects = array(),
                                            $metrics = false, $rankingQuery = null, $joinLogActionOnColumn = false)
    {
        $tableName = self::LOG_ACTIONS_TABLE;
        $availableMetrics = $this->getActionsMetricFields();

        $select  = $this->getSelectStatement($dimensions, $tableName, $additionalSelects, $availableMetrics, $metrics);
        $from    = array($tableName);
        $where   = $this->getWhereStatement($params, $tableName, self::ACTION_DATETIME_FIELD, $where);
        $groupBy = $this->getGroupByStatement($dimensions, $tableName);
        $orderBy = false;

        if ($joinLogActionOnColumn !== false) {
            $multiJoin = is_array($joinLogActionOnColumn);
            if (!$multiJoin) {
                $joinLogActionOnColumn = array($joinLogActionOnColumn);
            }

            foreach ($joinLogActionOnColumn as $i => $joinColumn) {
                $tableAlias = 'log_action' . ($multiJoin ? $i + 1 : '');

                if (strpos($joinColumn, ' ') === false) {
                    $joinOn = $tableAlias . '.idaction = ' . $tableName . '.' . $joinColumn;
                } else {
                    // more complex join column like if (...)
                    $joinOn = $tableAlias . '.idaction = ' . $joinColumn;
                }

                $from[] = array(
                    'table'      => 'log_action',
                    'tableAlias' => $tableAlias,
                    'joinOn'     => $joinOn
                );
            }
        }

        if ($rankingQuery) {
            $orderBy = '`' . Metrics::INDEX_NB_ACTIONS . '` DESC';
        }

        $query = $this->generateQuery($params, $select, $from, $where, $groupBy, $orderBy);

        if ($rankingQuery !== null) {
            $sumColumns = array_keys($availableMetrics);
            if ($metrics) {
                $sumColumns = array_intersect($sumColumns, $metrics);
            }

            $rankingQuery->addColumn($sumColumns, 'sum');

            return $rankingQuery->execute($query['sql'], $query['bind']);
        }

        return $this->db->query($query['sql'], $query['bind']);
    }

    public function queryConversionsByDimension(Parameters $params, $dimensions = array(), $where = false, $additionalSelects = array())
    {
        $dimensions = array_merge(array(self::IDGOAL_FIELD), $dimensions);
        $tableName  = self::LOG_CONVERSION_TABLE;
        $availableMetrics = $this->getConversionsMetricFields();

        $select = $this->getSelectStatement($dimensions, $tableName, $additionalSelects, $availableMetrics);

        $from    = array($tableName);
        $where   = $this->getWhereStatement($params, $tableName, self::CONVERSION_DATETIME_FIELD, $where);
        $groupBy = $this->getGroupByStatement($dimensions, $tableName);
        $orderBy = false;
        $query   = $this->generateQuery($params, $select, $from, $where, $groupBy, $orderBy);

        return $this->db->query($query['sql'], $query['bind']);
    }

    /**
     * Returns general bind parameters for all log aggregation queries. This includes the datetime
     * start of entities, datetime end of entities and IDs of all sites.
     *
     * @return array
     */
    protected function getGeneralQueryBindParams(Parameters $params)
    {
        $bind = array($params->getDateStart()->getDateStartUTC(), $params->getDateEnd()->getDateEndUTC());
        $bind = array_merge($bind, $params->getIdSites());

        return $bind;
    }

    protected function getSelectStatement($dimensions, $tableName, $additionalSelects, array $availableMetrics, $requestedMetrics = false)
    {
        $dimensionsToSelect = $this->getDimensionsToSelect($dimensions, $additionalSelects);

        $selects = array_merge(
            $this->getSelectDimensions($dimensionsToSelect, $tableName),
            $this->getSelectsMetrics($availableMetrics, $requestedMetrics),
            !empty($additionalSelects) ? $additionalSelects : array()
        );

        $select = implode(self::FIELDS_SEPARATOR, $selects);
        return $select;
    }

    protected function getSelectsMetrics($metricsAvailable, $metricsRequested = false)
    {
        $selects = array();

        foreach ($metricsAvailable as $metricId => $statement) {
            if ($this->isMetricRequested($metricId, $metricsRequested)) {
                $aliasAs   = $this->getSelectAliasAs($metricId);
                $selects[] = $statement . $aliasAs;
            }
        }

        return $selects;
    }

    /**
     * Will return the subset of $dimensions that are not found in $additionalSelects
     *
     * @param $dimensions
     * @param array $additionalSelects
     * @return array
     */
    protected function getDimensionsToSelect($dimensions, $additionalSelects)
    {
        if (empty($additionalSelects)) {
            return $dimensions;
        }

        $dimensionsToSelect = array();
        foreach ($dimensions as $selectAs => $dimension) {
            $asAlias = $this->getSelectAliasAs($dimension);
            foreach ($additionalSelects as $additionalSelect) {
                if (strpos($additionalSelect, $asAlias) === false) {
                    $dimensionsToSelect[$selectAs] = $dimension;
                }
            }
        }

        $dimensionsToSelect = array_unique($dimensionsToSelect);
        return $dimensionsToSelect;
    }

    /**
     * Returns the dimensions array, where
     * (1) the table name is prepended to the field
     * (2) the "AS `label` " is appended to the field
     *
     * @param $dimensions
     * @param $tableName
     * @param bool $appendSelectAs
     * @return mixed
     */
    protected function getSelectDimensions($dimensions, $tableName, $appendSelectAs = true)
    {
        foreach ($dimensions as $selectAs => &$field) {
            $selectAsString = $field;

            if (!is_numeric($selectAs)) {
                $selectAsString = $selectAs;
            } else {
                // if function, do not alias or prefix
                if ($this->isFieldFunctionOrComplexExpression($field)) {
                    $selectAsString = $appendSelectAs = false;
                }
            }

            $isKnownField = !in_array($field, array('referrer_data'));

            if ($selectAsString == $field && $isKnownField) {
                $field = $this->prefixColumn($field, $tableName);
            }

            if ($appendSelectAs && $selectAsString) {
                $field = $this->prefixColumn($field, $tableName) . $this->getSelectAliasAs($selectAsString);
            }
        }

        return $dimensions;
    }

    /**
     * Prefixes a column name with a table name if not already done.
     *
     * @param string $column eg, 'location_provider'
     * @param string $tableName eg, 'log_visit'
     * @return string eg, 'log_visit.location_provider'
     */
    private function prefixColumn($column, $tableName)
    {
        if (strpos($column, '.') === false) {
            return $tableName . '.' . $column;
        } else {
            return $column;
        }
    }

    protected function isFieldFunctionOrComplexExpression($field)
    {
        return strpos($field, "(") !== false
        || strpos($field, "CASE") !== false;
    }

    protected function getSelectAliasAs($metricId)
    {
        return " AS `" . $metricId . "`";
    }

    protected function isMetricRequested($metricId, $metricsRequested)
    {
        // do not process INDEX_NB_UNIQ_FINGERPRINTS unless specifically asked for
        if ($metricsRequested === false) {
            if ($metricId == Metrics::INDEX_NB_UNIQ_FINGERPRINTS) {
                return false;
            }
            return true;
        }
        return in_array($metricId, $metricsRequested);
    }

    protected function getWhereStatement(Parameters $params, $tableName, $datetimeField, $extraWhere = false)
    {
        $where = "$tableName.$datetimeField >= ?
				AND $tableName.$datetimeField <= ?
				AND $tableName.idsite IN (". Common::getSqlStringFieldsArray($params->getIdSites()) . ")";

        if (!empty($extraWhere)) {
            $extraWhere = sprintf($extraWhere, $tableName, $tableName);
            $where     .= ' AND ' . $extraWhere;
        }

        return $where;
    }

    protected function getGroupByStatement($dimensions, $tableName)
    {
        $dimensions = $this->getSelectDimensions($dimensions, $tableName, $appendSelectAs = false);
        $groupBy    = implode(", ", $dimensions);

        return $groupBy;
    }

    protected function getActionsMetricFields()
    {
        return array(
            Metrics::INDEX_NB_VISITS        => "count(distinct " . self::LOG_ACTIONS_TABLE . ".idvisit)",
            Metrics::INDEX_NB_UNIQ_VISITORS => "count(distinct " . self::LOG_ACTIONS_TABLE . ".idvisitor)",
            Metrics::INDEX_NB_ACTIONS       => "count(*)",
        );
    }

    protected function getVisitsMetricFields()
    {
        return array(
            Metrics::INDEX_NB_UNIQ_VISITORS               => "count(distinct " . self::LOG_VISIT_TABLE . ".idvisitor)",
            Metrics::INDEX_NB_UNIQ_FINGERPRINTS           => "count(distinct " . self::LOG_VISIT_TABLE . ".config_id)",
            Metrics::INDEX_NB_VISITS                      => "count(*)",
            Metrics::INDEX_NB_ACTIONS                     => "sum(" . self::LOG_VISIT_TABLE . ".visit_total_actions)",
            Metrics::INDEX_MAX_ACTIONS                    => "max(" . self::LOG_VISIT_TABLE . ".visit_total_actions)",
            Metrics::INDEX_SUM_VISIT_LENGTH               => "sum(" . self::LOG_VISIT_TABLE . ".visit_total_time)",
            Metrics::INDEX_BOUNCE_COUNT                   => "sum(case " . self::LOG_VISIT_TABLE . ".visit_total_actions when 1 then 1 when 0 then 1 else 0 end)",
            Metrics::INDEX_NB_VISITS_CONVERTED            => "sum(case " . self::LOG_VISIT_TABLE . ".visit_goal_converted when 1 then 1 else 0 end)",
            Metrics::INDEX_NB_USERS                       => "count(distinct " . self::LOG_VISIT_TABLE . ".user_id)",
        );
    }

    public static function getConversionsMetricFields()
    {
        return array(
            Metrics::INDEX_GOAL_NB_CONVERSIONS             => "count(*)",
            Metrics::INDEX_GOAL_NB_VISITS_CONVERTED        => "count(distinct " . self::LOG_CONVERSION_TABLE . ".idvisit)",
            Metrics::INDEX_GOAL_REVENUE                    => self::getSqlConversionRevenueSum(self::TOTAL_REVENUE_FIELD),
            Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SUBTOTAL => self::getSqlConversionRevenueSum(self::REVENUE_SUBTOTAL_FIELD),
            Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_TAX      => self::getSqlConversionRevenueSum(self::REVENUE_TAX_FIELD),
            Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_SHIPPING => self::getSqlConversionRevenueSum(self::REVENUE_SHIPPING_FIELD),
            Metrics::INDEX_GOAL_ECOMMERCE_REVENUE_DISCOUNT => self::getSqlConversionRevenueSum(self::REVENUE_DISCOUNT_FIELD),
            Metrics::INDEX_GOAL_ECOMMERCE_ITEMS            => "SUM(" . self::LOG_CONVERSION_TABLE . "." . self::ITEMS_COUNT_FIELD . ")",
        );
    }

    private static function getSqlConversionRevenueSum($field)
    {
        return self::getSqlRevenue('SUM(' . self::LOG_CONVERSION_TABLE . '.' . $field . ')');
    }

    public static function getSqlRevenue($field)
    {
        return "ROUND(" . $field . "," . GoalManager::REVENUE_PRECISION . ")";
    }

    /**
     * @return Connection
     */
    public function getDbConnection()
    {
        return $this->db;
    }
}