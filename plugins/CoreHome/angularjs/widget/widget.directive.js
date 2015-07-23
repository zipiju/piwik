/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Example:
 * <div piwik-widget widget="widget"></div>
 * <div piwik-widget containerid="widgetGoalsOverview"></div> // in this case we will find the correct widget automatically
 * <div piwik-widget widget="widget" widetized="true"></div> // disables rating feature, no initial headline
 */
(function () {
    angular.module('piwikApp').directive('piwikWidget', piwikWidget);

    piwikWidget.$inject = ['piwik', 'piwikApi'];

    function piwikWidget(piwik, piwikApi){

        function findContainerWidget(containerId, scope) {
            widgetsHelper.getAvailableWidgets(function (categorizedWidgets) {

                angular.forEach(categorizedWidgets, function (widgets) {
                    angular.forEach(widgets, function (widget) {

                        if (widget && widget.isContainer && widget.parameters.containerId === containerId) {
                            widget = angular.copy(widget);
                            if (scope.widgetized) {
                                widget.isFirstInPage = '1';
                                widget.parameters.widget = '1';
                                angular.forEach(widget.widgets, function (widget) {
                                    widget.parameters.widget = '1';
                                });
                            }
                            scope.widget = widget;
                            applyMiddleware(scope);
                        }
                    });
                });

            });
        }

        function applyMiddleware(scope)
        {
            if (!scope.widget.middlewareParameters) {
                scope.$eval('view.showWidget = true');
            } else {
                var params = angular.copy(scope.widget.middlewareParameters);
                piwikApi.fetch(params).then(function (response) {
                    var enabled = response ? 'true' : 'false';
                    scope.$eval('view.showWidget = ' + enabled);
                });
            }
        }

        return {
            restrict: 'A',
            scope: {
                widget: '=?',
                widgetized: '=?',
                containerid: '='
            },
            templateUrl: 'plugins/CoreHome/angularjs/widget/widget.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                return function (scope, element, attrs, ngModel) {
                    if (scope.widget) {
                        applyMiddleware(scope);
                    } else if (attrs.containerid) {
                        findContainerWidget(attrs.containerid, scope);
                    }
                }
            }
        };
    }
})();