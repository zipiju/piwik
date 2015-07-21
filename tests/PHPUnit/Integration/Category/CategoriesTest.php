<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Integration\Category;

use Exception;
use Piwik\Access;
use Piwik\AuthResult;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\NoAccessException;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group Category
 * @group Categories
 */
class CategoriesTest extends IntegrationTestCase
{
    public function testGetAllCategoriesWithSubcategories_shouldFindCategories()
    {
        $all = $this->getAllCategoriesWithSubcategories();

        $this->assertSame(array(
            'General_Actions',
            'General_Visitors',
            'Dashboard_Dashboard',
            'General_MultiSitesSummary',
            'Referrers_Referrers',
            'Goals_Goals',
            'Goals_Ecommerce',
            'Live!',
            'ExampleUI_UiFramework'
        ), array_keys($all));
    }

    public function testGetAllCategoriesWithSubcategories_shouldFindSubcategories()
    {
        $all = $this->getAllCategoriesWithSubcategories();

        $this->assertTrue(5 < count($all['General_Actions']->getSubcategories()));
        $this->assertTrue(5 < count($all['General_Visitors']->getSubcategories()));
        $this->assertTrue($all['General_Actions']->hasSubcategory('General_Pages'));
    }

    private function getAllCategoriesWithSubcategories()
    {
        $categories = StaticContainer::get('Piwik\Category\Categories');
        return $categories->getAllCategoriesWithSubcategories();
    }

}
