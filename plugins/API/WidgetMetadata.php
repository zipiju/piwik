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
        $this->moveWidgetsIntoCategories($categoryList, $widgetsList->getWidgetConfigs());

        return $this->buildPagesMetadata($categoryList);
    }

    public function getWidgetMetadata(CategoryList $categoryList, WidgetsList $widgetsList)
    {
        $this->moveWidgetsIntoCategories($categoryList, $widgetsList->getWidgetConfigs());

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

                $category    = $categoryList->getCategory($widget->getCategoryId());
                $subcategory = null;

                if (isset($category)) {
                    $subcategory = $category->getSubcategory($widget->getSubcategoryId());
                }

                $flat[] = $this->buildWidgetMetadata($widget, $category, $subcategory);
            }
        }

        usort($flat, array($this, 'sortWidgets'));

        return $flat;
    }

    public function buildWidgetMetadata(WidgetConfig $widget, $category, $subcategory)
    {
        $category = $category ? $this->buildCategoryMetadata($category) : null;
        $subcategory = $subcategory ? $this->buildSubcategoryMetadata($subcategory) : null;

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

            $children = array();
            foreach ($widget->getWidgetConfigs() as $widgetConfig) {
                $cat = $this->createCategory($widgetConfig->getCategoryId());
                $subcat = $this->createSubcategory($widgetConfig->getCategoryId(), $widgetConfig->getSubcategoryId());

                $child = $this->buildWidgetMetadata($widgetConfig, $cat, $subcat);
                $children[] = $child;
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

    private function buildCategoryMetadata(Category $category)
    {
        return array(
            'name'  => Piwik::translate($category->getId()),
            'order' => $category->getOrder(),
            'id'    => (string) $category->getId()
        );
    }

    private function buildSubcategoryMetadata(Subcategory $subcategory)
    {
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
    private function moveWidgetsIntoCategories($categoryList, $widgetConfigs)
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

            if ($categoryList->hasCategory($categoryId)) {
                $category = $categoryList->getCategory($categoryId);
            } else {
                $category = $this->createCategory($categoryId);
                $categoryList->addCategory($category);
            }

            if (!$subcategoryId) {
                continue;
            }

            if ($category->hasSubcategory($subcategoryId)) {
                $subcategory = $category->getSubcategory($subcategoryId);
            } else {
                $subcategory = $this->createSubcategory($categoryId, $subcategoryId);
                $category->addSubcategory($subcategory);
            }

            $subcategory->addWidgetConfig($widgetConfig);
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
     * @return array
     */
    private function buildPagesMetadata($categoryList)
    {
        // todo should be sorted by order
        $pages = array();

        foreach ($categoryList->getCategories() as $category) {
            foreach ($category->getSubcategories() as $subcategory) {
                $page = $this->buildPageMetadata($category, $subcategory);

                if (!empty($page['widgets'])) {
                    $pages[] = $page;
                }
            }
        }

        return $pages;
    }

    private function buildPageMetadata(Category $category, Subcategory $subcategory)
    {
        $ca = array(
            'uniqueId' => $category->getId() . '.' . $subcategory->getName(),
            'category' => $this->buildCategoryMetadata($category),
            'subcategory' => $this->buildSubcategoryMetadata($subcategory),
            'widgets' => array()
        );

        foreach ($subcategory->getWidgetConfigs() as $config) {
            $widget = $this->buildWidgetMetadata($config, $cat = null, $subcat = null);
            unset($widget['category']);
            unset($widget['subcategory']);

            $ca['widgets'][] = $widget;
        }

        return $ca;
    }

}