<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Widget;

use Piwik\Container\StaticContainer;
use Piwik\Plugin\Manager as PluginManager;

/**
 * Base type for metric metadata classes that describe aggregated metrics. These metrics are
 * computed in the backend data store and are aggregated in PHP when Piwik archives period reports.
 *
 * Note: This class is a placeholder. It will be filled out at a later date. Right now, only
 * processed metrics can be defined this way.
 */
class Category
{
    protected $id = '';
    protected $name = '';

    /**
     * @var Subcategory[]
     */
    protected $subcategories = array();

    protected $order = 99;

    public function getOrder()
    {
        return $this->order;
    }

    public function getName()
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        return $this->id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function addSubcategory(Subcategory $subcategory)
    {
        $this->subcategories[$subcategory->getId()] = $subcategory;
    }

    public function hasSubcategory($subcategoryId)
    {
        return isset($this->subcategories[$subcategoryId]);
    }

    public function getSubcategory($subcategoryId)
    {
        if ($this->hasSubcategory($subcategoryId)) {
            return $this->subcategories[$subcategoryId];
        }
    }

    public function getSubcategories()
    {
        return $this->subcategories;
    }

    public function hasSubCategories()
    {
        return !empty($this->subcategories);
    }

    /** @return \Piwik\Widget\Category[] */
    private static function getAllCategories()
    {
        $manager = PluginManager::getInstance();
        // todo move to Piwik\Widget\Category
        $categories = $manager->findMultipleComponents('Widgets/Categories', '\\Piwik\\Widget\\Category');

        $instances = array();
        foreach ($categories as $category) {
            $cat = StaticContainer::get($category);
            $instances[$cat->getName()] = $cat;
        }

        return $instances;
    }

    /**
     * @return \Piwik\Widget\Category[]
     */
    public static function getAllCategoriesWithSubcategories()
    {
        $categories    = Category::getAllCategories();
        $subcategories = Subcategory::getAllSubcategories();

        // move subcategories into categories
        foreach ($subcategories as $subcategory) {
            $category = $subcategory->getCategoryId();

            if (!$category) {
                continue;
            }

            if (!isset($categories[$category])) {
                $categories[$category] = new Category();
                $categories[$category]->setId($category);
            }

            $categories[$category]->addSubcategory($subcategory);
        }

        return $categories;
    }
}