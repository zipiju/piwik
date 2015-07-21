/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Example:
 * <div piwik-widget-by-dimension-container container="widget"></div>
 */
(function () {
    angular.module('piwikApp').directive('piwikWidgetByDimensionContainer', piwikWidgetContainer);

    piwikWidgetContainer.$inject = ['piwik', '$filter'];

    function piwikWidgetContainer(piwik, $filter){
        return {
            restrict: 'A',
            scope: {
                container: '='
            },
            templateUrl: 'plugins/CoreHome/angularjs/widget-bydimension-container/widget-bydimension-container.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                return function (scope, element, attrs, ngModel) {

                    var widgetsSorted = $filter('orderBy')(scope.container.widgets, 'order');
                    var widgetsByCategory = {};

                    angular.forEach(widgetsSorted, function (widget) {
                        var category = widget.subcategory.name;

                        if (!widgetsByCategory[category]) {
                            widgetsByCategory[category] = {name: category, order: widget.order, widgets: []};
                        }

                        widgetsByCategory[category].widgets.push(widget);
                    });

                    // only an array can be sorted
                    var finalWidgetsByCategory = [];
                    angular.forEach(widgetsByCategory, function (category) {
                        finalWidgetsByCategory.push(category);
                    });

                    scope.widgetsByCategory = $filter('orderBy')(finalWidgetsByCategory, 'order');

                    scope.selectWidget = function (widget) {
                        scope.selectedWidget = widget;
                    }

                    if (widgetsSorted && widgetsSorted.length) {
                        scope.selectWidget(widgetsSorted[0]);
                    }
                };
            }
        };
    }
})();