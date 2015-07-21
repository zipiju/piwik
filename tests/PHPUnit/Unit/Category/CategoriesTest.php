<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Tests\Unit\Category;

use Piwik\Tests\Framework\Mock\Category\Categories;
use Piwik\Category\Category;
use Piwik\Category\Subcategory;
use Piwik\Container\StaticContainer;

require_once PIWIK_INCLUDE_PATH . '/tests/PHPUnit/Framework/Mock/Category/Categeories.php';

/**
 * @group Category
 * @group Categories
 */
class CategoriesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Categories
     */
    private $categories;

    public function setUp()
    {
        $pluginManager = StaticContainer::get('Piwik\Plugin\Manager');
        $this->categories = new Categories($pluginManager);
    }

    public function test_getAllCategoriesWithSubcategories_shouldMergeCategoriesAndSubcategories()
    {
        $this->categories->setCategories(array(
            $this->createCategory('General_Visits', 'Visits'),
            $this->createCategory('General_Actions'),
            $this->createCategory('Goals_Goals'),
            $this->createCategory('Goals_Ecommerce', 'Ecommerce'),
            $this->createCategory('Referrers_Referrers'),
        ));
        $this->categories->setSubcategories(array(
            $subcat1 = $this->createSubcategory('General_Actions', 'General_Pages'),
            $subcat2 = $this->createSubcategory('Goals_Goals', 'General_Overview'),
            $subcat3 = $this->createSubcategory('General_Actions', 'Actions_Downloads'),
            $subcat4 = $this->createSubcategory('General_AnyThingNotExist', 'General_MySubcategoryId'),
            $subcat5 = $this->createSubcategory('General_Visits', 'Visits'),
            $subcat6 = $this->createSubcategory('Goals_Goals', '4'),
            $subcat7 = $this->createSubcategory('General_Visits', 'General_Engagement'),
            $subcat8 = $this->createSubcategory('Goals_Ecommerce', 'General_Overview'),
        ));

        /** @var Category[] $all */
        $all = $this->categories->getAllCategoriesWithSubcategories();

        $categoryNames = array(
            'General_Visits',
            'General_Actions',
            'Goals_Goals',
            'Goals_Ecommerce',
            'Referrers_Referrers',
            'General_AnyThingNotExist' // should be created dynamically as none exists
        );
        $this->assertSame($categoryNames, array_keys($all));

        $this->assertSame(array($subcat5, $subcat7), $all['General_Visits']->getSubcategories());
        $this->assertSame(array($subcat1, $subcat3), $all['General_Actions']->getSubcategories());
        $this->assertSame(array($subcat2, $subcat6), $all['Goals_Goals']->getSubcategories());
        $this->assertSame(array($subcat8), $all['Goals_Ecommerce']->getSubcategories());
        $this->assertSame(array(), $all['Referrers_Referrers']->getSubcategories());
        $this->assertSame(array($subcat4), $all['General_AnyThingNotExist']->getSubcategories());

        // make sure id was actually set
        $this->assertSame('General_AnyThingNotExist', $all['General_AnyThingNotExist']->getId());
    }

    private function createCategory($categoryId, $categoryName = '')
    {
        $config = new Category();
        $config->setId($categoryId);
        $config->setName($categoryName);

        return $config;
    }

    private function createSubcategory($categoryId, $subcategoryId)
    {
        $config = new Subcategory();
        $config->setId($subcategoryId);
        $config->setCategoryId($categoryId);

        return $config;
    }
}
