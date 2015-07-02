<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VisitTime\Widgets\Subcategories;

use Piwik\Widget\Subcategory;

class TimesSubcategory extends Subcategory
{
    protected $categoryId = 'General_Visitors';
    protected $id = 'VisitTime_SubmenuTimes';
    protected $order = 35;

}
