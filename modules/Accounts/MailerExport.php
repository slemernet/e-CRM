<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
 global $app_strings, $mod_strings, $currentModule, $theme, $adb, $current_language, $default_charset;
$image_path="themes/$theme/images/";

//Function added to convert line breaks to space in description during export
function br2nl_int($str) {
	$str = preg_replace("/(\r\n)/", ' ', $str);
	return $str;
}

if (isset($_SESSION['export_where'])) {
	$exportWhere = $_SESSION['export_where'];
} elseif (isset($_POST['exportwhere'])) {
	$exportWhere = stripslashes(htmlspecialchars_decode(vtlib_purify($_POST['exportwhere'])));
} else {
	$exportWhere = '';
}

$step = vtlib_purify($_REQUEST['step']);
$export_type = isset($_POST['export_type']) ? vtlib_purify($_POST['export_type']) : '';

function getStdContactFlds($queryFields, $adb, $valueArray) {
	$query = "SELECT fieldid, columnname, fieldlabel FROM vtiger_field WHERE tablename='vtiger_contactdetails' AND uitype='56' and vtiger_field.presence in (0,2)";
	$result = $adb->query($query, true, "Error: <BR>$query");
	for ($tmp=0; $tmp < $adb->num_rows($result); $tmp++) {
		$myData = $adb->fetchByAssoc($result);
		$queryFields[] = array(
			'columnname'=>$myData['columnname'],
			'uitype'=>'56',
			'fieldlabel'=>getTranslatedString($myData['fieldlabel'], 'Contacts'),
			'value'=> $valueArray
		);
	}
	return $queryFields;
}

if ($step == 'ask') {
	require_once 'Smarty_setup.php';
	$smarty = new vtigerCRM_Smarty;
	if (isset($tool_buttons)==false) {
		$tool_buttons = Button_Check($currentModule);
	}
	$smarty->assign('CHECK', $tool_buttons);
	$smarty->assign('SINGLE_MOD', getTranslatedString('SINGLE_'.$currentModule));
	$smarty->assign('THEME', $theme);
	$valueArray = array(
		'' => $mod_strings['LBL_MAILER_EXPORT_IGNORE'],
		'0' => $mod_strings['LBL_MAILER_EXPORT_NOTCHECKED'],
		'1' => $mod_strings['LBL_MAILER_EXPORT_CHECKED']
	);
	$smarty->assign('MOD', return_module_language($current_language, 'Accounts'));
	$smarty->assign('CMOD', $mod_strings);
	$smarty->assign('APP', $app_strings);
	$smarty->assign('IMAGE_PATH', $image_path);
	$smarty->assign('MODULE', $currentModule);
	$smarty->assign('EXPORTWHERE', $exportWhere);
	$queryFields = array();
	// get the Contacts CF fields
	$cfquery = "SELECT columnname,fieldlabel,uitype FROM vtiger_field WHERE tablename='vtiger_contactscf' and vtiger_field.presence in (0,2)";
	$result = $adb->query($cfquery, true, "Error: <BR>$cfquery");
	for ($tmp=0; $tmp < $adb->num_rows($result); $tmp++) {
		$cfTmp = $adb->fetchByAssoc($result);
		$cfColName=$cfTmp['columnname'];
		if ($cfTmp['uitype'] == 1) {
			$queryFields[$tmp] = $cfTmp;
		} elseif ($cfTmp['uitype'] == 15) {
			$queryFields[$tmp] = $cfTmp;
			$queryFields[$tmp]['value'][''] = $mod_strings['LBL_MAILER_EXPORT_IGNORE'];
			$cfValues = 'SELECT '.$cfColName.",".$cfColName.'id FROM vtiger_'.$cfColName;
			$resVal = $adb->query($cfValues, true, "Error: <BR>$cfValues");
			for ($tmp1=0; $tmp1 < $adb->num_rows($resVal); $tmp1++) {
				$cfTmp=$adb->fetchByAssoc($resVal);
				$queryFields[$tmp]['value'][$cfTmp[$cfColName]] = $cfTmp[$cfColName];
			}
		} elseif ($cfTmp['uitype'] == 56) {
			$queryFields[$tmp] = $cfTmp;
			$queryFields[$tmp]['value'] = $valueArray;
		}
	}
	// now add the standard fields
	$queryFields = getStdContactFlds($queryFields, $adb, $valueArray);
	// get the list of fields
	$fieldList = '';
	foreach ($queryFields as $myField) {
		if (strlen($fieldList) > 0) {
			$fieldList .= ',';
		}
		$fieldList .= $myField['columnname'];
	}
	$smarty->assign('FIELDLIST', $fieldList);
	// and their types
	$typeList ="";
	foreach ($queryFields as $myField) {
		if (strlen($typeList) > 0) {
			$typeList .= ',';
		}
		$typeList .= $myField['uitype'];
	}
	$smarty->assign('TYPELIST', $typeList);
	$smarty->assign('QUERYFIELDS', $queryFields);
	$smarty->display('MailerExport.tpl');
} else {
	$crmAccEntityTable = CRMEntity::getcrmEntityTableAlias('Accounts');
	$crmEntityTable = CRMEntity::getcrmEntityTableAlias('Contacts', true);
	$exquery = array();
	$content = '';
	$fields = explode(",", $_POST['fieldlist']);
	$types = explode(",", $_POST['typelist']);
	if (($export_type == 'email') || ($export_type == 'emailplus')) {
		$where = '';
		if (count($fields) > 0) {
			$where .= getExpWhereClause($fields, $types);
		}
		$exquery[0] = "SELECT vtiger_crmentity.crmid, contactdetails.contactid, contactdetails.salutation,
			contactdetails.firstname, contactdetails.lastname, contactdetails.email
			FROM vtiger_account
			INNER JOIN ".$crmAccEntityTable." on vtiger_crmentity.crmid=vtiger_account.accountid
			INNER JOIN vtiger_accountbillads ON vtiger_account.accountid=vtiger_accountbillads.accountaddressid
			INNER JOIN vtiger_accountshipads ON vtiger_account.accountid=vtiger_accountshipads.accountaddressid
			INNER JOIN vtiger_accountscf ON vtiger_account.accountid = vtiger_accountscf.accountid
			INNER JOIN vtiger_contactdetails contactdetails ON vtiger_account.accountid = contactdetails.accountid
			INNER JOIN ".$crmEntityTable." contactdetails_crmentity ON contactdetails.contactid = contactdetails_crmentity.crmid
			INNER JOIN vtiger_contactscf contactscf ON contactscf.contactid = contactdetails.contactid
			LEFT JOIN vtiger_users ON vtiger_crmentity.smownerid=vtiger_users.id
			LEFT JOIN vtiger_groups ON vtiger_crmentity.smownerid=vtiger_groups.groupid
			WHERE vtiger_crmentity.deleted=0 AND contactdetails_crmentity.deleted=0 AND contactdetails.email != '' ".$where;

		if (strlen($exportWhere)) {
			$exquery[0] .= ' AND '.$exportWhere;
		}

		if ($export_type == 'emailplus') {
			$exquery[1] = "SELECT vtiger_crmentity.crmid, contactdetails.contactid, contactdetails.salutation,
			contactdetails.firstname, contactdetails.lastname, vtiger_account.email1
			FROM vtiger_account
			INNER JOIN ".$crmAccEntityTable." ON vtiger_crmentity.crmid=vtiger_account.accountid
			INNER JOIN vtiger_accountbillads ON vtiger_account.accountid=vtiger_accountbillads.accountaddressid
			INNER JOIN vtiger_accountshipads ON vtiger_account.accountid=vtiger_accountshipads.accountaddressid
			INNER JOIN vtiger_accountscf ON vtiger_account.accountid = vtiger_accountscf.accountid
			INNER JOIN vtiger_contactdetails contactdetails ON vtiger_account.accountid = contactdetails.accountid
			INNER JOIN ".$crmEntityTable." contactdetails_crmentity ON contactdetails.contactid = contactdetails_crmentity.crmid
			INNER JOIN vtiger_contactscf contactscf ON contactscf.contactid = contactdetails.contactid
			LEFT JOIN vtiger_users ON vtiger_crmentity.smownerid=vtiger_users.id
			LEFT JOIN vtiger_groups ON vtiger_crmentity.smownerid=vtiger_groups.groupid
			WHERE vtiger_crmentity.deleted=0 AND contactdetails_crmentity.deleted=0
				AND contactdetails.email = '' AND vtiger_account.email1 != '' ".$where;

			if (strlen($exportWhere)) {
				$exquery[1] .= ' AND '.$exportWhere;
			}
		}
	} elseif ($export_type == 'full') {
		$where = '';
		if (count($fields) > 0) {
			$where .= getExpWhereClause($fields, $types);
		}
		$exquery[0] = 'SELECT vtiger_crmentity.crmid, contactdetails.contactid, contactdetails.salutation,
			contactdetails.firstname, contactdetails.lastname, contactdetails.email, vtiger_account.accountname,
			vtiger_account.phone, vtiger_account.website, vtiger_accountshipads.ship_street,
			vtiger_accountshipads.ship_code, vtiger_accountshipads.ship_city, vtiger_accountshipads.ship_state,
			vtiger_accountshipads.ship_country, vtiger_accountbillads.bill_street, vtiger_accountbillads.bill_code,
			vtiger_accountbillads.bill_city, vtiger_accountbillads.bill_state, vtiger_accountbillads.bill_country
			FROM vtiger_account
			INNER JOIN '.$crmAccEntityTable.' ON vtiger_crmentity.crmid=vtiger_account.accountid
			LEFT JOIN vtiger_accountbillads ON vtiger_account.accountid=vtiger_accountbillads.accountaddressid
			LEFT JOIN vtiger_accountshipads ON vtiger_account.accountid=vtiger_accountshipads.accountaddressid
			INNER JOIN vtiger_accountscf ON vtiger_account.accountid = vtiger_accountscf.accountid
			INNER JOIN vtiger_contactdetails contactdetails ON vtiger_account.accountid = contactdetails.accountid
			INNER JOIN '.$crmEntityTable.' contactdetails_crmentity ON contactdetails.contactid = contactdetails_crmentity.crmid
			INNER JOIN vtiger_contactscf contactscf ON contactscf.contactid = contactdetails.contactid
			LEFT JOIN vtiger_users ON vtiger_crmentity.smownerid=vtiger_users.id
			LEFT JOIN vtiger_groups ON vtiger_crmentity.smownerid=vtiger_groups.groupid
			WHERE vtiger_crmentity.deleted=0 AND contactdetails_crmentity.deleted=0 '.$where;

		if (strlen($exportWhere)) {
			$exquery[0] .= ' AND '.$exportWhere;
		}
	}
	// Get field labels of the fields selected in the above export queries. If other fields are added above then the array must be updated accordingly
	$fld_label_arr = array(
		'salutation'=>getTranslatedString('Salutation', 'Contacts'),
		'lastname'=>getTranslatedString('Last Name', 'Contacts'),
		'firstname'=>getTranslatedString('First Name', 'Contacts'),
		'email'=>getTranslatedString('Email', 'Contacts'),
		'accountname'=>getTranslatedString('Account Name', 'Accounts'),
		'phone'=>getTranslatedString('Phone', 'Accounts'),
		'website'=>getTranslatedString('Website', 'Accounts'),
		'ship_street'=>getTranslatedString('Shipping Address', 'Accounts'),
		'ship_code'=>getTranslatedString('Shipping Code', 'Accounts'),
		'ship_city'=>getTranslatedString('Shipping City', 'Accounts'),
		'ship_state'=>getTranslatedString('Shipping State', 'Accounts'),
		'ship_country'=>getTranslatedString('Shipping Country', 'Accounts'),
		'bill_street'=>getTranslatedString('Billing Address', 'Accounts'),
		'bill_code'=>getTranslatedString('Billing Code', 'Accounts'),
		'bill_city'=>getTranslatedString('Billing City', 'Accounts'),
		'bill_state'=>getTranslatedString('Billing State', 'Accounts'),
		'bill_country'=>getTranslatedString('Billing Country', 'Accounts')
	);

	for ($temp = 0; $temp < count($exquery); $temp++) {
		$result = $adb->query($exquery[$temp], true, 'Error exporting: <BR>'.$exquery[$temp]);
		if ($temp == 0) { // We only need the headers for first query
			$fields_array = $adb->getFieldsArray($result);
			// Walk through the array and replace any cf_* with the content of the name array, index is the cf_ var name
			for ($arraywalk = 0; $arraywalk < count($fields_array); $arraywalk++) {
				if (strstr($fields_array[$arraywalk], 'vtiger_cf_')) {
					$fields_array[$arraywalk] = $name[$fields_array[$arraywalk]];
				} else {
					$fields_array[$arraywalk] = (!empty($fld_label_arr[$fields_array[$arraywalk]]))?$fld_label_arr[$fields_array[$arraywalk]]:$fields_array[$arraywalk];
				}
			}
			$header = implode("\",\"", array_values($fields_array));
			$header = "\"" .$header;
			$header .= "\"\r\n";
			$content .= $header;
			$column_list = implode(",", array_values($fields_array));
		}
		while ($val = $adb->fetchByAssoc($result, -1, false)) {
			$new_arr = array();
			// foreach (array_values($val) as $value)
			foreach ($val as $key => $value) {
				$value=br2nl_int($value);
				$new_arr[] = preg_replace("/\"/", "\"\"", $value);
			}
			$line = implode("\",\"", $new_arr);
			$line = "\"" .$line;
			$line .= "\"\r\n";
			$content .= $line;
		}
	}
	header("Content-Disposition: inline; filename=MailerExport.csv");
	header("Content-Type: text/csv; charset=".$default_charset);
	header("Expires: Mon, 26 Jul 2007 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Content-Length: ".strlen($content));
	print $content;
	exit();
}

function getExpWhereClause($fields, $types) {
	global $adb;
	$where_cond = '';
	foreach ($fields as $key => $myField) {
		if (strlen($_POST[$myField]) > 0) {
			// type 1 should use a LIKE search
			if ($types[$key] == 1) {
				$equals = " LIKE '";
				$postfix = "%'";
			} else {
				$equals = " = '";
				$postfix = "'";
			}
			// is customer field
			if (substr($myField, 0, 3) == 'cf_') {
				$where_cond .= ' AND contactscf.'.$myField.$equals.$adb->sql_escape_string($_POST[$myField]).$postfix;
			} else {
				$where_cond .= ' AND contactdetails.'.$myField.$equals.$adb->sql_escape_string($_POST[$myField]).$postfix;
			}
		}
	}
	return $where_cond;
}
?>