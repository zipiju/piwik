/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Renders any kind of widget. If you have a widget and you want to have it rendered, use this directive. It will
 * display a name on top and the actual widget below. It can handle any kind of widget, no matter whether it is a
 * regular widget or a container.
 *
 * @param {Object} widget       a widget object as returned by the WidgetMetadata API.
 * @param {Object} widget.middlewareParameters If present, we will request a URL using the given parameters and only
 *                                             if this URL returns a JSON `true` the widget will be shown. Otherwise
 *                                             The widget won't be shown.
 * @param {String} containerId  If you do not have a widget object but a containerId we will find the correct widget
 *                              object based on the given containerId. Be aware that we might not find the widget if
 *                              it is for example not available for the current user or period/date.
 * @param {Boolean} widgetized  true if the widget is widgetized (eg in Dashboard or exported). In this case we will add
 *                              a URL parameter widget=1 to all widgets. Eg sparklines will be then displayed one after
 *                              another (vertically aligned) instead of two next to each other.
 *
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