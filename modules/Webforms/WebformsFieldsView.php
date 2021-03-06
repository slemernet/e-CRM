<?php
/*+********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ********************************************************************************/
global $app_strings, $mod_strings, $current_language, $currentModule, $theme,$current_user,$adb,$log;

require_once 'Smarty_setup.php';
require_once 'modules/Webforms/Webforms.php';
require_once 'modules/Webforms/model/WebformsModel.php';

Webforms::checkAdminAccess($current_user);
$webformFields=Webforms::getFieldInfos($_REQUEST['targetmodule']);

$smarty = new vtigerCRM_Smarty();
$smarty->assign('WEBFORM', new Webforms_Model());
$smarty->assign('WEBFORMFIELDS', $webformFields);
$smarty->assign('THEME', $theme);
$smarty->assign('MOD', $mod_strings);
$smarty->assign('APP', $app_strings);
$smarty->assign('MODULE', $currentModule);
if (!isset($tool_buttons)) {
	$tool_buttons = Button_Check($currentModule);
}
$smarty->assign('CHECK', $tool_buttons);
$smarty->assign('IMAGE_PATH', "themes/$theme/images/");
$smarty->assign('CALENDAR_LANG', 'en');
$smarty->assign('LANGUAGE', $current_language);
$smarty->assign('DATE_FORMAT', $current_user->date_format);
$smarty->assign('CAL_DATE_FORMAT', parse_calendardate($app_strings['NTC_DATE_FORMAT']));
$smarty->display(vtlib_getModuleTemplate($currentModule, 'FieldsView.tpl'));
?>
