/*!
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Example:
 * <div piwik-widget widget="widget"></div>
 */
(function () {
    angular.module('piwikApp').directive('piwikWidget', piwikWidget);

    piwikWidget.$inject = ['piwik', 'reportingPagesModel'];

    function piwikWidget(piwik, reportingPagesModel){
        return {
            restrict: 'A',
            scope: {
                widget: '=?',
                widgetUniqueId: '@',
                widgetParams: '=?',
                showName: '=?'
            },
            templateUrl: 'plugins/CoreHome/angularjs/widget/widget.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                return function (scope, element, attrs, ngModel) {
                    scope.showName = angular.isDefined(scope.showName) ? scope.showName : true;

                    function addParamsToUrl() {
                        if (scope.widgetParams && scope.widget && scope.widget.widget_url) {
                            scope.widget.widget_url += '&' + $.param(scope.widgetParams)
                        }
                    }

                    if (scope.widget) {
                        addParamsToUrl();
                    } else if (scope.widgetUniqueId) {
                        // TODO remove widgetUniqueId feature again
                        reportingPagesModel.findWidgetInAnyPage(scope.widgetUniqueId).then(function (widget) {
                            scope.widget = widget;
                        });
                    }
                };
            }
        };
    }
})();