/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
(function () {
    angular.module('piwikApp').factory('reportingMenuModel', reportingMenuModelService);

    reportingMenuModelService.$inject = ['$filter', '$q', 'piwikApi', 'reportingPagesModel', '$location'];

    function reportingMenuModelService ($filter, $q, piwikApi, reportingPagesModel, $location) {

        // those sites are going to be displayed
        var model = {
            menu: [],
            selected: [],
            fetchMenuItems: fetchMenuItems,
            reloadMenuItems: reloadMenuItems,
            findSubcategory: findSubcategory
        };

        return model;

        function findSubcategory(categoryId, subcategoryId)
        {
            var foundCategory = null;
            var foundSubcategory = null;

            angular.forEach(model.menu, function (category) {
                if (category.id !== categoryId) {
                    return;
                }
                angular.forEach(category.subcategories, function (subcategory) {
                    if (subcategory.id === subcategoryId) {
                        foundCategory = category;
                        foundSubcategory = subcategory;
                    }
                });
            });

            return {category: foundCategory, subcategory: foundSubcategory};
        }

        function buildMenuFromPages(pages)
        {
            var menu = [];

            var activeCategory = $location.search().category;
            var activeSubcategory = $location.search().subcategory;

            var categoriesHandled = {};
            angular.forEach(pages, function (page, key) {
                var category   = page.category;
                var categoryId = category.id;

                if (categoriesHandled[categoryId]) {
                    return;
                }

                categoriesHandled[categoryId] = true;

                if (activeCategory && category.id === activeCategory) {
                    // this doesn't really belong here but placed it here for convenience
                    category.active = true;
                    category.hover  = true;
                }

                category.subcategories = [];

                var goalsGroup = false;

                angular.forEach(pages, function (page, key) {
                    if (page.category.id === categoryId) {
                        var subcategory = page.subcategory;

                        if (subcategory.id === activeSubcategory) {
                            subcategory.active = true;
                        }

                        if (page.widgets && page.widgets[0] && page.widgets[0].parameters.idGoal && page.category.id === 'Goals_Goals') {
                            // we handle a goal
                            if (!goalsGroup) {
                                goalsGroup = angular.copy(subcategory);
                                goalsGroup.name = $filter('translate')('Goals_ChooseGoal');
                                goalsGroup.isGroup = true;
                                goalsGroup.subcategories = [];
                                goalsGroup.order = 10;
                            }

                            if (subcategory.active) {
                                goalsGroup.name = subcategory.name;
                            }

                            var goalId = page.widgets[0].parameters.idGoal;
                            subcategory.tooltip = subcategory.name + ' (id = ' + goalId + ' )';

                            goalsGroup.subcategories.push(subcategory);
                            return;
                        }

                        category.subcategories.push(subcategory);
                    }
                });

                if (goalsGroup && goalsGroup.subcategories && goalsGroup.subcategories.length <= 3) {
                    angular.forEach(goalsGroup.subcategories, function (subcategory) {
                        category.subcategories.push(subcategory);
                    });
                } else if(goalsGroup) {
                    category.subcategories.push(goalsGroup);
                }

                category.subcategories = sortMenuItems(category.subcategories);

                menu.push(category);

                return menu;
            });

            menu = sortMenuItems(menu);

            return menu;
        }

        function sortMenuItems(menu) {
            return $filter('orderBy')(menu, 'order');
        };

        function reloadMenuItems()
        {
            var pagesPromise = reportingPagesModel.reloadAllPages();
            return pagesPromise.then(function (pages) {
                model.menu = buildMenuFromPages(pages);
            });
        }

        function fetchMenuItems()
        {
            var pagesPromise = reportingPagesModel.getAllPages();

            return pagesPromise.then(function (pages) {
                model.menu = buildMenuFromPages(pages);

                return model.menu;
            });
        }
    }
})();