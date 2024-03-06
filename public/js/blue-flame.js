
function doLogin() {
	var pwd = $('#stf_password').val();
	if (pwd != '')
		$('#stf_md5_pwd').val(MD5(pwd+'129-3026-19-2089'));
	$('#stf_password').val('');
}

function loadPage(target, page, field1 = null, field2 = null, field3 = null, field4 = null) {
	var params = {};
	
	if (field1) {
		var obj = $("#"+field1);
		params[obj.attr("id")] = obj.val();
	}
	if (field2) {
		var obj = $("#"+field2);
		params[obj.attr("id")] = obj.val();
	}
	if (field3) {
		var obj = $("#"+field3);
		params[obj.attr("id")] = obj.val();
	}
	if (field4) {
		var obj = $("#"+field4);
		params[obj.attr("id")] = obj.val();
	}
	$("#"+target).load(page, params);
}

function showTab(which_tab) {
	//hide all settings
	$('[id*="tab_content_"]').hide();

	//show the selected group
	$('#tab_content_'+which_tab).show();
	
	//reset all the "show X settings group" buttons to the default style
	$('[id*="tab_selector_"]').attr('class', 'kids-tabs');

	//highlight the selected one
	$('#tab_selector_'+which_tab).attr('class', 'kids-tabs active active');

	$("#kids_list").load("kids/getkids?kid_tab="+which_tab);

	return false;
}

function getAge(value) {
	var curr_d = new Date(Date.now());
	var birth = value.split(".");

	if (birth.length == 3) {
		var b_year = parseInt(birth[2]);
		var b_mon = parseInt(birth[1]);
		var b_day = parseInt(birth[0]);

		if (b_year != NaN && b_mon != NaN && b_day != NaN &&
			((b_year > 1950 && b_year < curr_d.getFullYear()) ||
			 (b_year >= 0 && b_year < (curr_d.getFullYear()-2000)))) {

			if (b_year >= 0 && b_year < (curr_d.getFullYear()-2000))
				b_year += 2000;

			var year_diff = curr_d.getFullYear() - b_year;
   			var month_diff = curr_d.getMonth() - (b_mon-1);
    		var day_diff = curr_d.getDate() - b_day;
   		 	if (month_diff < 0 || (month_diff == 0 && day_diff < 0))
				year_diff--;

			return year_diff;
		}
	}
	return -1;
}

function correctDate(old_date, delete_key) {
	var date_parts;
	var clean_date = "";
	
	var dot_cnt = 0;
	for (var i=0; i<old_date.length; i++) {
		if (old_date[i] >= '0' && old_date[i] <= '9')
			clean_date += old_date[i];
		else if (old_date[i] == ".") {
			if (dot_cnt == 2)
				break;
			dot_cnt++;
			clean_date += ".";
		}
	}
	if (clean_date == "." || clean_date == "..")
		return "";

	if (dot_cnt == 0) {
		if (clean_date.length > 2) {
			var d = clean_date;
			clean_date = clean_date.substr(0, 2) + "." + clean_date.substr(2, 2);
			if (d.length > 4)
				clean_date += "." + d.substr(4);
		}
	}
	
	date_parts = clean_date.split(".");

	if (date_parts.length > 0) {
		date_parts[0] = date_parts[0].substring(0, 2);
		var val = parseInt(date_parts[0]);
		if (date_parts[0] == "")
			new_date = "";
		else if (val > 31) {
			if (val >= 40)
				new_date = date_parts[0][0] + ".";
			else
				new_date = date_parts[0][0];
		}
		else if (val == 0)
			new_date = "0";
		else if (val > 3 || date_parts[0].length == 2)
			new_date = date_parts[0] + ".";
		else
			new_date = date_parts[0];
	}
	if (date_parts.length > 1) {
		if (!new_date.endsWith("."))
			new_date +=  ".";
		date_parts[1] = date_parts[1].substring(0, 2);
		var val = parseInt(date_parts[1]);
		if (date_parts[1] == "") {
			if (date_parts.length > 2 && date_parts[2].length > 0)
				new_date +=  ".";
		}
		else if (val > 12)
			new_date += date_parts[1][0];
		else if (val == 0)
			new_date += "0";
		else if (val > 1 || date_parts[1].length == 2)
			new_date += date_parts[1] + ".";
		else
			new_date += date_parts[1];
	}

	if (date_parts.length > 2) {
		date_parts[2] = date_parts[2].substring(0, 4);
		if (!new_date.endsWith("."))
			new_date +=  ".";
		var val = parseInt(date_parts[2]);
		if (date_parts[2] == "")
			return new_date;
		if (val > 201 && val < 1900)
			return new_date + "20";
		if (val > 2020)
			return new_date + "20";
		if (val > 20 && val < 200)
			return new_date + "2";
		if ((val > 2 && val < 19) ||
			(val <= 2 && date_parts[2].length == 2)) {
			new_date += "20";
			if (val < 10)
				new_date += "0";
			new_date += val.toString();
		}
		else
			new_date += date_parts[2];
	}

	if (delete_key && new_date == old_date + ".")
		new_date = old_date.substring(0, old_date.length-1);

	return new_date;
}

function dateChanged(field) {
	var start = field.get(0).selectionStart;
	var end = field.get(0).selectionEnd;
	var value = field.val();
	const key = event.key;
	var new_value = correctDate(value, key === "Backspace" || key === "Delete");
	if (value != new_value) {
		field.val(new_value);
		// Check if the character was just removed:
		if (value.substr(0, start-1) + value.substr(start) == new_value) {
			// If so the cursor position must be set back!
			start--;
			end--;
		}
		if (start+1 < value.length)
			field.get(0).setSelectionRange(start, end);
	}
	return new_value;
}

function listAppend(list, value, sep) {
	if (list.length != 0)
		list += sep;
	return list + value;
}

function capitalizeName(field, ev) {
	var start = field.get(0).selectionStart;
	var end = field.get(0).selectionEnd;
	var value = field.val();
	var new_value = value;

	if (value.length > 0 && start == value.length && ev.key.length == 1) {
		var i = value.length-1;

		while (i >= 0 &&  value.charAt(i) != " ")
			i--;
		if (i < value.length-1) {
			word = value.substr(i+1);
			if (word != "v" && word != "vo" && word != "von") {
				word = word.charAt(0).toUpperCase() + word.slice(1);
				new_value = value.substr(0, i+1) + word;
			}
		}
	}
		
	if (value != new_value) {
		field.val(new_value);
		field.get(0).setSelectionRange(start, end);
	}
}

function allCaps(field, max_len = 0) {
	var start = field.get(0).selectionStart;
	var end = field.get(0).selectionEnd;
	var value = field.val();
	var new_value = "";

	for (var i=0; i<value.length && (max_len == 0 || i<max_len); i++) {
		new_value += value.charAt(i).toUpperCase();
	}

	if (value != new_value) {
		field.val(new_value);
		field.get(0).setSelectionRange(start, end);
	}
}

function kidsID(field) {
	var start = field.get(0).selectionStart;
	var end = field.get(0).selectionEnd;
	var value = field.val();
	var new_value = "";
	var ch;

	if (start == value.length) {
		for (var i=0; i<value.length && i<4; i++) {
			ch = value.charAt(i);
			if (i == 0 || i == 2) {
				if (ch >= "a" && ch <= "z")
					new_value += ch.toUpperCase();
				else if (ch >= "A" && ch <= "Z")
					new_value += ch;
				else
					break;
			}
			else {
				if (ch >= "0" && ch <= "9")
					new_value += ch;
				else
					break;
			}	
		}
	}
	else
		new_value = value.substr(0, 4).toUpperCase();

	if (value != new_value) {
		field.val(new_value);
		field.get(0).setSelectionRange(start, end);
	}
	
	return new_value;
}

function mouseOverLogout(object) {
	var $this = $(object);
	var w = $this.css("width");
	$this.css('min-width', w);
	$this.html('Logout');
}

function mouseOutLogout(object, value) {
	var $this = $(object);
	$this.html(value.val());
}

function toggleStaffPage(period_count, current_period, role, group_leader)
{
	toggleRole(role, group_leader);
	for (var i=0; i<5; i++)
		toggleSchedule(i, false, current_period);
}

function toggleRole(role, group_leader)
{
	if (role == group_leader)
		$('[id="group-row"]').show();
	else
		$('[id="group-row"]').hide();
}

function toggleSchedule(i, my_leader_changed, current_period)
{
	var present = $('#present_'+i).is(":checked");
	var leader = $('#leader_'+i).is(":checked");
	var obj;

	if (present && i >= current_period) {
		if (my_leader_changed) {
			if ($('#my_leader_'+i).val() == 0) {
				$('#leader_'+i).removeAttr('checked');
				$('#leader_'+i).removeAttr('disabled');
			}
			else
				$('#leader_'+i).attr('disabled', '');
		}
		else {
			$('#leader_'+i).removeAttr('disabled');
			if (leader) {
				$('#my_leader_'+i).val(0);
				$('#my_leader_'+i).attr('disabled', '');
			}
			else
				$('#my_leader_'+i).removeAttr('disabled');
		}
		$('#groups_0_'+i).removeAttr('disabled');
		$('#groups_1_'+i).removeAttr('disabled');
		$('#groups_2_'+i).removeAttr('disabled');
		$('#my_group_'+i).css('visibility', 'visible');
	}
	else {
		$('#leader_'+i).attr('disabled', '');
		$('#my_leader_'+i).attr('disabled', '');
		$('#groups_0_'+i).attr('disabled', '');
		$('#groups_1_'+i).attr('disabled', '');
		$('#groups_2_'+i).attr('disabled', '');
		$('#my_group_'+i).css('visibility', 'hidden');
	}
}

function iPadStatus(before_data, now_data)
{
	var before_list = before_data.split("|");
	var now_list = now_data.split("|");
	var before_stat = parseInt(before_list[1]);

	var something_changed = false;
	for (var i=0; i<now_list.length; i++) {
		if (before_list[2+i] != now_list[i].trim()) {
			something_changed = true;
			break;
		}
	}

	if (!something_changed)
		return before_stat;

	var fname = now_list[0].trim();
	var lname = now_list[1].trim();
	var bday = now_list[2].trim();
	if (fname.length == 0) {
		if (bday.length != 0)
			return 2;
		return 1;
	}
	
	if (lname.length == 0)
		return 2;

	if (before_stat == 1 || before_stat == 2 || before_stat == 5)
		return 2;

	return 4;
}

function iPadRegistrationChanged(tab, before_data, now_data, status_div, reg_tab, reg_button)
{
	var now_list = now_data.split("|");
	var fname = now_list[0].trim();
	var lname = now_list[1].trim();
	var tab_title;
	if (fname.length > 0) {
		if (fname.length + lname.length > 14) {
			if (lname.length == 0)
				tab_title = fname.substr(0, 12)+'...';
			else {
				if (fname.length <= 12)
					tab_title = fname+" "+lname.substr(0, 1)+".";
				else
					tab_title = fname.substr(0, 9)+"... "+lname.substr(0, 1)+".";
			}
		}
		else
			tab_title = fname+" "+lname;
	}
	else
		tab_title = "Kind "+tab.toString();
	reg_tab.html(tab_title);

	var stat = iPadStatus(before_data, now_data);
	status_div.removeClass();
	switch (stat) {
		case 1: status_div.addClass("grey-box"); status_div.html("&nbsp;"); break;
		case 2: status_div.addClass("yellow-box"); status_div.html("Wird registriert"); break;
		case 3: status_div.addClass("green-box"); status_div.html("Registriert"); break;
		case 4: status_div.addClass("yellow-box"); status_div.html("Wird geändert"); break;
		case 5: status_div.addClass("red-box"); status_div.html("Angemeldet"); break;
	}

	var disabled = false;
	for (var i=0; i<now_list.length-1; i++) {
		if (now_list[i].trim().length == 0) {
			disabled = true;
			break;
		}
	}
	if (!disabled)
		disabled = stat == 3 || stat == 5 || stat == 1;

	reg_button.prop("disabled", disabled);
	if (stat == 4)
		reg_button.attr("value", "Ändern");
	else
		reg_button.attr("value", "Registrieren");
}
