
function fieldDep_AddDays(change_field, action_field, new_value, old_value, parameters) {
	var datesep = '-';
	if (new_value.indexOf('-')>=0) {
		datesep='-';
	} else if (new_value.indexOf('.')>=0) {
		datesep='.';
	} else if (new_value.indexOf('/')>=0) {
		datesep='/';
	}
	var date=splitDateVal(new_value);
	var dd = parseInt(date[0]);
	var mm = parseInt(date[1]);
	var y = parseInt(date[2]);
	var currDate=new Date();
	currDate.setFullYear(y);
	currDate.setMonth(mm-1);
	currDate.setDate(dd);
	currDate.setDate(currDate.getDate() + parseInt(parameters[0]));
	dd = currDate.getDate();
	dd = (dd>9 ? '' : '0') + dd;
	mm = currDate.getMonth() + 1;
	mm = (mm>9 ? '' : '0') + mm;
	y = currDate.getFullYear();
	var fulldate = '';
	switch (userDateFormat) {
	case 'mm-dd-yyyy':
		fulldate = mm+datesep+dd+datesep+y;
		break;
	case 'dd-mm-yyyy':
		fulldate = dd+datesep+mm+datesep+y;
		break;
	case 'yyyy-mm-dd':
		fulldate = y+datesep+mm+datesep+dd;
		break;
	}
	document.getElementsByName(action_field).item(0).value=fulldate;
}

function fieldDep_SubDays(change_field, action_field, new_value, old_value, parameters) {
	parameters[0] = -1*parseInt(parameters[0]);
	fieldDep_AddDays(change_field, action_field, new_value, old_value, parameters)
}

function fieldDep_OnlyNumbers(change_field, action_field, new_value, old_value, parameters) {
alert('ddj');
}

function fieldDep_OnlyLetters(change_field, action_field, new_value, old_value, parameters) {

}

function fieldDep_GetField(change_field, action_field, new_value, old_value, parameters) {

}

function fieldDep_AssignNewValue(change_field, action_field, new_value, old_value, parameters) {

}

function fieldDep_Format(change_field, action_field, new_value, old_value, parameters) {

}

function fieldDep_ChangeLabel(change_field, action_field, new_value, old_value, parameters) {

}

