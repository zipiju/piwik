<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Tests\Framework\Mock\Category;

use Piwik\Category;

/**
 * FakeCategories for UnitTests
 * @since 3.0.0
 */
class Categories extends Category\Categories
{
    private $categories;
    private $subcategories;

    public function setCategories($categories)
    {
        $cats = array();

        foreach ($categories as $category) {
            $cats[$category->getId()] = $category;
        }

        $this->categories = $cats;;
    }

    public function setSubcategories($subcategories)
    {
        $this->subcategories = $subcategories;
    }

    protected function getAllCategories()
    {
        if ($this->categories) {
            return $this->categories;
        }

        return parent::getAllCategories();
    }

    protected function getAllSubcategories()
    {
        if ($this->subcategories) {
            return $this->subcategories;
        }

        return parent::getAllSubcategories();
    }

}