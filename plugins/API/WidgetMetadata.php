<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\API;

use Piwik\Category\CategoryList;
use Piwik\Piwik;
use Piwik\Report\ReportWidgetConfig;
use Piwik\Category\Category;
use Piwik\Category\Subcategory;
use Piwik\Widget\WidgetContainerConfig;
use Piwik\Widget\WidgetConfig;
use Piwik\Widget\WidgetsList;

class WidgetMetadata
{
    public function getPagesMetadata(CategoryList $categoryList, WidgetsList $widgetsList)
    {
        $this->createMissingCategoriesAndSubcategories($categoryList, $widgetsList->getWidgetConfigs());

        return $this->buildPagesMetadata($categoryList, $widgetsList);
    }

    public function getWidgetMetadata(CategoryList $categoryList, WidgetsList $widgetsList)
    {
        $this->createMissingCategoriesAndSubcategories($categoryList, $widgetsList->getWidgetConfigs());

        $flat = array();

        foreach ($widgetsList->getWidgetConfigs() as $widgetConfig) {

            /** @var WidgetConfig[] $widgets */
            $widgets = array($widgetConfig);
            if ($widgetConfig instanceof WidgetContainerConfig) {
                // so far we go only one level down, in theory these widgetConfigs could have again containers containing configs
                $widgets = array_merge($widgets, $widgetConfig->getWidgetConfigs());
            }

            foreach ($widgets as $widget) {
                // make sure to include only widgetizable widgets
                if (!$widget->isWidgetizeable() || !$widget->getName()) {
                    continue;
                }

                $flat[] = $this->buildWidgetMetadata($widget, $categoryList);
            }
        }

        usort($flat, array($this, 'sortWidgets'));

        return $flat;
    }

    public function buildWidgetMetadata(WidgetConfig $widget, CategoryList $categoryList)
    {
        $category    = $categoryList->getCategory($widget->getCategoryId());
        $subcategory = $category ? $category->getSubcategory($widget->getSubcategoryId()) : null;

        $category    = $this->buildCategoryMetadata($category);
        $subcategory = $this->buildSubcategoryMetadata($subcategory);

        $item = array(
            'name'        => Piwik::translate($widget->getName()),
            'category'    => $category,
            'subcategory' => $subcategory,
            'module'      => $widget->getModule(),
            'action'      => $widget->getAction(),
            'order'       => $widget->getOrder(),
            'parameters'  => $widget->getParameters(),
            'uniqueId'    => $widget->getUniqueId(),
        );

        $middleware = $widget->getMiddlewareParameters();

        if (!empty($middleware)) {
            $item['middlewareParameters'] = $middleware;
        }

        if ($widget instanceof ReportWidgetConfig) {
            $item['viewDataTable'] = $widget->getViewDataTable();
            $item['isReport'] = true;
        }

        if ($widget instanceof WidgetContainerConfig) {
            $item['layout'] = $widget->getLayout();
            $item['isContainer'] = true;

            // we do not want to create categories to the inital categoryList. Otherwise we'd maybe display more pages
            // etc.
            $subCategoryList = new CategoryList();
            $this->createMissingCategoriesAndSubcategories($subCategoryList, $widget->getWidgetConfigs());

            $children = array();
            foreach ($widget->getWidgetConfigs() as $widgetConfig) {
                $children[] = $this->buildWidgetMetadata($widgetConfig, $subCategoryList);
            }
            $item['widgets'] = $children;
        }

        return $item;
    }

    private function sortWidgets($widgetA, $widgetB) {
        $orderA = $widgetA['category']['order'];
        $orderB = $widgetB['category']['order'];

        if ($orderA === $orderB) {
            if (!empty($widgetA['subcategory']['order']) && !empty($widgetB['subcategory']['order'])) {

                $subOrderA = $widgetA['subcategory']['order'];
                $subOrderB = $widgetB['subcategory']['order'];

                if ($subOrderA === $subOrderB) {
                    return 0;
                }

                return $subOrderA > $subOrderB ? 1 : -1;

            } elseif (!empty($orderA)) {

                return 1;
            }

            return -1;
        }

        return $orderA > $orderB ? 1 : -1;
    }

    /**
     * @param Category|null $category
     * @return array
     */
    private function buildCategoryMetadata($category)
    {
        if (!isset($category)) {
            return null;
        }

        return array(
            'name'  => Piwik::translate($category->getId()),
            'order' => $category->getOrder(),
            'id'    => (string) $category->getId()
        );
    }

    /**
     * @param Subcategory|null $subcategory
     * @return array
     */
    private function buildSubcategoryMetadata($subcategory)
    {
        if (!isset($subcategory)) {
            return null;
        }

        return array(
            'name'  => Piwik::translate($subcategory->getName()),
            'order' => $subcategory->getOrder(),
            'id'    => (string) $subcategory->getId()
        );
    }

    /**
     * @param CategoryList $categoryList
     * @param WidgetConfig[] $widgetConfigs
     */
    private function createMissingCategoriesAndSubcategories($categoryList, $widgetConfigs)
    {
        // move reports into categories/subcategories and create missing ones if needed
        foreach ($widgetConfigs as $widgetConfig) {
            $categoryId    = $widgetConfig->getCategoryId();
            $subcategoryId = $widgetConfig->getSubcategoryId();

            if (!$categoryId) {
                continue;
            }

            if ($widgetConfig instanceof WidgetContainerConfig && !$widgetConfig->getWidgetConfigs()) {
                // if a container does not contain any widgets, ignore it
                continue;
            }

            if (!$categoryList->hasCategory($categoryId)) {
                $categoryList->addCategory($this->createCategory($categoryId));
            }

            if (!$subcategoryId) {
                continue;
            }

            $category = $categoryList->getCategory($categoryId);

            if (!$category->hasSubcategory($subcategoryId)) {
                $category->addSubcategory($this->createSubcategory($categoryId, $subcategoryId));
            }
        }
    }

    private function createCategory($categoryId)
    {
        $category = new Category();
        $category->setId($categoryId);
        return $category;
    }

    private function createSubcategory($categoryId, $subcategoryId)
    {
        $subcategory = new Subcategory();
        $subcategory->setCategoryId($categoryId);
        $subcategory->setId($subcategoryId);
        return $subcategory;
    }

    /**
     * @param CategoryList $categoryList
     * @param WidgetsList $widgetsList
     * @return array
     */
    private function buildPagesMetadata(CategoryList $categoryList, WidgetsList $widgetsList)
    {
        $pages = array();

        $all = array();
        foreach ($widgetsList->getWidgetConfigs() as $config) {
            $key = $config->getCategoryId() . '||' . $config->getSubcategoryId();

            if (!isset($all[$key])) {
                $all[$key] = array();
            }

            $all[$key][] = $config;
        }

        foreach ($categoryList->getCategories() as $category) {
            foreach ($category->getSubcategories() as $subcategory) {
                $key = $category->getId() . '||' . $subcategory->getId();

                if (!empty($all[$key])) {
                    $pages[] = $this->buildPageMetadata($category, $subcategory, $all[$key]);
                }
            }
        }

        return $pages;
    }

    private function buildPageMetadata(Category $category, Subcategory $subcategory, $widgetConfigs)
    {
        $ca = array(
            'uniqueId' => $category->getId() . '.' . $subcategory->getName(),
            'category' => $this->buildCategoryMetadata($category),
            'subcategory' => $this->buildSubcategoryMetadata($subcategory),
            'widgets' => array()
        );

        // an empty categoryList will prevent that category metadata is created for a widget since we will remove
        // it afterwards anyway. buildWidgetMetadata() will not find a category / subcategory and therefore
        // set [category] = null, [subcategory] = null which we will then remove completely.
        $categoryList = new CategoryList();

        foreach ($widgetConfigs as $config) {
            $widget = $this->buildWidgetMetadata($config, $categoryList);
            unset($widget['category']);
            unset($widget['subcategory']);

            $ca['widgets'][] = $widget;
        }

        return $ca;
    }

}