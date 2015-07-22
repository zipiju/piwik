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

    piwikWidget.$inject = ['piwik', 'piwikApi'];

    function piwikWidget(piwik, piwikApi){
        return {
            restrict: 'A',
            scope: {
                widget: '=',
                showName: '=?'
            },
            templateUrl: 'plugins/CoreHome/angularjs/widget/widget.directive.html?cb=' + piwik.cacheBuster,
            compile: function (element, attrs) {

                return function (scope, element, attrs, ngModel) {
                    scope.showName = angular.isDefined(scope.showName) ? scope.showName : true;

                    if (!scope.widget.middlewareParameters) {
                        scope.isEnabled = true;
                    } else {

                        var params = angular.copy(scope.widget.middlewareParameters);
                        piwikApi.fetch(params).then(function (response) {
                            scope.isEnabled = response;
                        });

                    }
                }
            }
        };
    }
})();