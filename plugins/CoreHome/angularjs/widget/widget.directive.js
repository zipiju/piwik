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

    piwikWidget.$inject = ['piwik'];

    function piwikWidget(piwik){
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

                    function isThisWidgetIsInFirstRowOfPage()
                    {
                        var widgetsInFirstRow = angular.element('.reporting-page .row:first [piwik-widget]:nth-child(1)');
                        var elementScope = element.first().scope();

                        var i;
                        for (i = 0; i < widgetsInFirstRow.length; i++) {
                            if (elementScope === angular.element(widgetsInFirstRow[i]).scope()) {
                                return true;
                            }
                        }

                        return false;
                    }

                    // first widget of first row should not get margin-top:40px
                    scope.isInFirstRow = isThisWidgetIsInFirstRowOfPage();
                }
            }
        };
    }
})();