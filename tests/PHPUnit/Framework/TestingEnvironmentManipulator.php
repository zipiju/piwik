<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Framework;

use Piwik\Application\Environment;
use Piwik\Application\EnvironmentManipulator;
use Piwik\Application\Kernel\GlobalSettingsProvider;
use Piwik\Application\Kernel\PluginList;
use Piwik\Config;
use Piwik\DbHelper;
use Piwik\Option;
use Piwik\Plugin;

class FakePluginList extends PluginList
{
    private $plugins;

    public function __construct(GlobalSettingsProvider $globalSettingsProvider, $plugins)
    {
        parent::__construct($globalSettingsProvider);

        $this->plugins = $this->sortPlugins($plugins);
    }

    public function getActivatedPlugins()
    {
        return $this->plugins;
    }
}

/**
 * Manipulates an environment for tests.
 */
class TestingEnvironmentManipulator implements EnvironmentManipulator
{
    /**
     * Set only in PHPUnit bootstrap. For PHPUnit tests, we only create one DB connection that is shared among
     * all environments.
     *
     * @var Environment
     */
    public static $rootEnvironment = null;

    /**
     * @var string[]
     */
    public static $extraPluginsToLoad = array();

    /**
     * @var TestingEnvironmentVariables
     */
    private $vars;

    private $globalObservers;

    private $connectWithoutDatabase;

    public function __construct(TestingEnvironmentVariables $testingEnvironment, array $globalObservers = array(), $connectWithoutDatabase = false)
    {
        $this->vars = $testingEnvironment;
        $this->globalObservers = $globalObservers;
        $this->connectWithoutDatabase = $connectWithoutDatabase;
    }

    public function makeGlobalSettingsProvider()
    {
        return new GlobalSettingsProvider($this->vars->configFileGlobal, $this->vars->configFileLocal, $this->vars->configFileCommon);
    }

    public function makePluginList(GlobalSettingsProvider $globalSettingsProvider)
    {
        return new FakePluginList($globalSettingsProvider, $this->getPluginsToLoadDuringTest());
    }

    public function beforeContainerCreated()
    {
        if ($this->vars->queryParamOverride) {
            foreach ($this->vars->queryParamOverride as $key => $value) {
                $_GET[$key] = $value;
            }
        }

        if ($this->vars->globalsOverride) {
            foreach ($this->vars->globalsOverride as $key => $value) {
                $GLOBALS[$key] = $value;
            }
        }

        if ($this->vars->hostOverride) {
            \Piwik\Url::setHost($this->vars->hostOverride);
        }

        if ($this->vars->useXhprof) {
            \Piwik\Profiler::setupProfilerXHProf($mainRun = false, $setupDuringTracking = true);
        }

        \Piwik\Cache\Backend\File::$invalidateOpCacheBeforeRead = true;
    }

    public function onEnvironmentBootstrapped()
    {
        if (empty($_GET['ignoreClearAllViewDataTableParameters'])) { // TODO: should use testingEnvironment variable, not query param
            try {
                \Piwik\ViewDataTable\Manager::clearAllViewDataTableParameters();
            } catch (\Exception $ex) {
                // ignore (in case DB is not setup)
            }
        }

        if ($this->vars->optionsOverride) {
            try {
                foreach ($this->vars->optionsOverride as $name => $value) {
                    Option::set($name, $value);
                }
            } catch (\Exception $ex) {
                // ignore (in case DB is not setup)
            }
        }

        \Piwik\Plugins\CoreVisualizations\Visualizations\Cloud::$debugDisableShuffle = true;
        \Piwik\Visualization\Sparkline::$enableSparklineImages = false;
        \Piwik\Plugins\ExampleUI\API::$disableRandomness = true;

        if ($this->vars->deleteArchiveTables
            && !$this->vars->_archivingTablesDeleted
        ) {
            $this->vars->_archivingTablesDeleted = true;
            DbHelper::deleteArchiveTables();
        }
    }

    public function getExtraDefinitions()
    {
        $testVarDefinitionSource = new TestingEnvironmentVariablesDefinitionSource($this->vars);

        $diConfig = array();
        if (self::$rootEnvironment) {
            $diConfig['db.connection'] = self::$rootEnvironment->getContainer()->get('db.connection');
        }
        if ($this->connectWithoutDatabase) {
            $diConfig['Piwik\Config'] = \DI\decorate(function (Config $previous) {
                $previous->database['dbname'] = null;
                return $previous;
            });
        }

        // Apply DI config from the fixture
        $testDiConfig = array();
        if ($this->vars->fixtureClass) {
            $fixtureClass = $this->vars->fixtureClass;
            if (class_exists($fixtureClass)) {
                /** @var Fixture $fixture */
                $fixture = new $fixtureClass;
                $testDiConfig = $fixture->provideContainerConfig();
            }
        }

        if ($this->vars->testCaseClass) {
            $testCaseClass = $this->vars->testCaseClass;
            if (class_exists($testCaseClass)) {
                $testCase = new $testCaseClass();

                if (method_exists($testCase, 'provideContainerConfigBeforeClass')) {
                    $testDiConfig = array_merge($testDiConfig, $testCaseClass::provideContainerConfigBeforeClass());
                }

                if (method_exists($testCase, 'provideContainerConfig')) {
                    $testDiConfig = array_merge($testDiConfig, $testCase->provideContainerConfig());
                }
            }
        }

        return array(
            $diConfig,
            $testVarDefinitionSource,
            $testDiConfig,
            array('observers.global' => \DI\add($this->globalObservers)),
        );
    }

    public function getExtraEnvironments()
    {
        return array('test');
    }

    private function getPluginsToLoadDuringTest()
    {
        $plugins = $this->vars->getCoreAndSupportedPlugins();

        // make sure the plugin that executed this method is included in the plugins to load
        $extraPlugins = array_merge(
            self::$extraPluginsToLoad,
            $this->vars->pluginsToLoad ?: array(),
            array(
                Plugin::getPluginNameFromBacktrace(debug_backtrace()),
                Plugin::getPluginNameFromNamespace($this->vars->testCaseClass),
                Plugin::getPluginNameFromNamespace(get_called_class())
            )
        );
        foreach ($extraPlugins as $pluginName) {
            if (empty($pluginName)) {
                continue;
            }

            if (in_array($pluginName, $plugins)) {
                continue;
            }

            $plugins[] = $pluginName;
        }

        return $plugins;
    }
}