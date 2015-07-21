<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Unit\Category;

use Piwik\Cache;
use Piwik\Category\Subcategory;
use Piwik\Widget\WidgetConfig;

/**
 * @group Category
 * @group Subcategory
 */
class SubcategoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Subcategory
     */
    private $subcategory;

    public function setUp()
    {
        parent::setUp();
        $this->subcategory = new Subcategory();
    }

    public function test_categoryId_set_get()
    {
        $this->subcategory->setCategoryId('testCategory');

        $this->assertSame('testCategory', $this->subcategory->getCategoryId());
    }

    public function test_getCategoryId_shouldBeEmptyStringByDefault()
    {
        $this->assertSame('', $this->subcategory->getCategoryId());
    }

    public function test_name_set_get()
    {
        $this->subcategory->setName('testName');

        $this->assertSame('testName', $this->subcategory->getName());
    }

    public function test_getName_shouldBeEmptyStringByDefault()
    {
        $this->assertSame('', $this->subcategory->getName());
    }

    public function test_getName_ShouldDefaultToId_IfNoNameIsSet()
    {
        $this->subcategory->setId('myTestId');

        $this->assertSame('myTestId', $this->subcategory->getName());
        $this->assertSame('myTestId', $this->subcategory->getId());
    }

    public function test_order_set_get()
    {
        $this->subcategory->setOrder(99);
        $this->assertSame(99, $this->subcategory->getOrder());

        $this->subcategory->setOrder('98');
        $this->assertSame(98, $this->subcategory->getOrder());
    }

    public function test_getOrder_shouldReturnADefaultValue()
    {
        $this->assertSame(99, $this->subcategory->getOrder());
    }

    public function test_id_set_get()
    {
        $this->subcategory->setId('myCustomId');
        $this->assertSame('myCustomId', $this->subcategory->getId());
    }

    public function test_getId_shouldBeEmptyStringByDefault()
    {
        $this->assertSame('', $this->subcategory->getId());
    }

    public function test_getWidgetConfigs_ShouldReturnAnEmptyArray_ByDefault()
    {
        $this->assertSame(array(), $this->subcategory->getWidgetConfigs());
    }

    public function test_addWidgetConfig_ShouldActuallyAddTheConfig()
    {
        $config1 = $this->createWidgetConfig('widgetName1');
        $config2 = $this->createWidgetConfig('widgetName2');

        $this->subcategory->addWidgetConfig($config1);
        $this->subcategory->addWidgetConfig($config2);

        $this->assertSame(array($config1, $config2), $this->subcategory->getWidgetConfigs());
    }

    private function createWidgetConfig($widgetName)
    {
        $config = new WidgetConfig();
        $config->setName($widgetName);

        return $config;
    }
}
