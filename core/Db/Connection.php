<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Db;

use Exception;
use Piwik\Config;
use Psr\Log\LoggerInterface;

class Connection
{
    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    private $adapter;

    /**
     * @var LoggerInterface
     */
    public function __construct($adapter, LoggerInterface $logger)
    {
        $this->adapter = $adapter;
        $this->logger = $logger;
    }


    /**
     * Executes an unprepared SQL query. Recommended for DDL statements like `CREATE`,
     * `DROP` and `ALTER`. The return value is DBMS-specific. For MySQLI, it returns the
     * number of rows affected. For PDO, it returns a
     * [Zend_Db_Statement](http://framework.zend.com/manual/1.12/en/zend.db.statement.html) object.
     *
     * @param string $sql The SQL query.
     * @throws \Exception If there is an error in the SQL.
     * @return integer|\Zend_Db_Statement
     */
    public function exec($sql)
    {
        /** @var \Zend_Db_Adapter_Abstract $db */
        $profiler = $this->adapter->getProfiler();
        $q = $profiler->queryStart($sql, \Zend_Db_Profiler::INSERT);

        try {
            $this->logSql(__FUNCTION__, $sql);

            $return = $this->adapter->exec($sql);
        } catch (Exception $ex) {
            self::logExtraInfoIfDeadlock($ex);
            throw $ex;
        }

        $profiler->queryEnd($q);

        return $return;
    }

    /**
     * Executes an SQL query and returns the [Zend_Db_Statement](http://framework.zend.com/manual/1.12/en/zend.db.statement.html)
     * for the query.
     *
     * This method is meant for non-query SQL statements like `INSERT` and `UPDATE. If you want to fetch
     * data from the DB you should use one of the fetch... functions.
     *
     * @param string $sql The SQL query.
     * @param array $parameters Parameters to bind in the query, eg, `array(param1 => value1, param2 => value2)`.
     * @throws \Exception If there is a problem with the SQL or bind parameters.
     * @return \Zend_Db_Statement
     */
    public function query($sql, $parameters = array())
    {
        try {
            $this->logSql(__FUNCTION__, $sql, $parameters);

            return $this->adapter->query($sql, $parameters);
        } catch (Exception $ex) {
            self::logExtraInfoIfDeadlock($ex);
            throw $ex;
        }
    }

    /**
     * Executes an SQL `SELECT` statement and returns all fetched rows from the result set.
     *
     * @param string $sql The SQL query.
     * @param array $parameters Parameters to bind in the query, eg, `array(param1 => value1, param2 => value2)`.
     * @throws \Exception If there is a problem with the SQL or bind parameters.
     * @return array The fetched rows, each element is an associative array mapping column names
     *               with column values.
     */
    public function fetchAll($sql, $parameters = array())
    {
        try {
            self::logSql(__FUNCTION__, $sql, $parameters);

            return $this->adapter->fetchAll($sql, $parameters);
        } catch (Exception $ex) {
            self::logExtraInfoIfDeadlock($ex);
            throw $ex;
        }
    }

    /**
     * Executes an SQL `SELECT` statement and returns the first row of the result set.
     *
     * @param string $sql The SQL query.
     * @param array $parameters Parameters to bind in the query, eg, `array(param1 => value1, param2 => value2)`.
     * @throws \Exception If there is a problem with the SQL or bind parameters.
     * @return array The fetched row, each element is an associative array mapping column names
     *               with column values.
     */
    public function fetchRow($sql, $parameters = array())
    {
        try {
            self::logSql(__FUNCTION__, $sql, $parameters);

            return $this->adapter->fetchRow($sql, $parameters);
        } catch (Exception $ex) {
            self::logExtraInfoIfDeadlock($ex);
            throw $ex;
        }
    }

    /**
     * Executes an SQL `SELECT` statement and returns the first column value of the first
     * row in the result set.
     *
     * @param string $sql The SQL query.
     * @param array $parameters Parameters to bind in the query, eg, `array(param1 => value1, param2 => value2)`.
     * @throws \Exception If there is a problem with the SQL or bind parameters.
     * @return string
     */
    public function fetchOne($sql, $parameters = array())
    {
        try {
            self::logSql(__FUNCTION__, $sql, $parameters);

            return $this->adapter->fetchOne($sql, $parameters);
        } catch (Exception $ex) {
            self::logExtraInfoIfDeadlock($ex);
            throw $ex;
        }
    }

    /**
     * Executes an SQL `SELECT` statement and returns the entire result set indexed by the first
     * selected field.
     *
     * @param string $sql The SQL query.
     * @param array $parameters Parameters to bind in the query, eg, `array(param1 => value1, param2 => value2)`.
     * @throws \Exception If there is a problem with the SQL or bind parameters.
     * @return array eg,
     *               ```
     *               array('col1value1' => array('col2' => '...', 'col3' => ...),
     *                     'col1value2' => array('col2' => '...', 'col3' => ...))
     *               ```
     */
    public function fetchAssoc($sql, $parameters = array())
    {
        try {
            self::logSql(__FUNCTION__, $sql, $parameters);

            return $this->adapter->fetchAssoc($sql, $parameters);
        } catch (Exception $ex) {
            self::logExtraInfoIfDeadlock($ex);
            throw $ex;
        }
    }

    /**
     * @var \Zend_Db_Adapter_Abstract
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    private function logExtraInfoIfDeadlock($ex)
    {
        if ($this->adapter->isErrNo($ex, 1213)) {
            $deadlockInfo = self::fetchAll("SHOW ENGINE INNODB STATUS");

            // log using exception so backtrace appears in log output
            $this->logger->debug(new Exception("Encountered deadlock: " . print_r($deadlockInfo, true)));
        }
    }

    private function logSql($functionName, $sql, $parameters = array())
    {
        if (\Piwik\Db::$logQueries === false
            || @Config::getInstance()->Debug['log_sql_queries'] != 1
        ) {
            return;
        }

        // NOTE: at the moment we don't log parameters in order to avoid sensitive information leaks
        $this->logger->debug("Db::{func}() executing SQL: {sql}", array('func' => $functionName, 'sql' => $sql));
    }
}