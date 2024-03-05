<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

define('MAX_PARTICIPANTS', 5);

class Registration extends BF_Controller {
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$this->start_session();
	}

	public function prompted_login($pwd) {
		if (!$this->is_logged_in('staff'))
			return false;
		$staff_row = $this->get_staff_row($this->session->stf_login_id);
		$pwd = md5($pwd.'129-3026-19-2089');
		if (!password_verify($pwd, $staff_row['stf_password']))
			return false;
		$this->set_staff_logged_in($staff_row);
		return true;
	}

	public function index()
	{
		$this->header('iPad Registrierung', false);

		$reg_top_form = new Form('reg_top_form', 'ipad', 2, array('class'=>'input-table'));
		$reg_back = $reg_top_form->addHidden('reg_back');
		if (!empty($reg_back->getValue())) {
			if ($this->prompted_login($reg_back->getValue())) {
				if (empty($this->session->ses_prev_page) ||
					$this->session->ses_prev_page == 'ipad' ||
					$this->session->ses_prev_page == 'checkin')
					return redirect("kids");
				return redirect($this->session->ses_prev_page);
			}
			return redirect('ipad');
		}
		$reg_login = $reg_top_form->addHidden('reg_login');
		if (!empty($reg_login->getValue())) {
			$this->prompted_login($reg_login->getValue());
			return redirect('ipad');
		}

		$reg_part = in('reg_part', 1);
		$reg_part->persistent();

		$reg_kids = in('reg_kids', [ ]);
		$reg_kids->persistent();

		$reg_supervision = in('reg_supervision', [ ]);
		$reg_supervision->persistent();

		$reg_top_form->open();
		table([ 'style'=>'width: 100%;' ]);
		tr([ 'class'=>'topnav' ]);
		td([ 'style'=>'width: 3px; padding: 0;' ], nbsp());
		td([ 'style'=>'width: 200px; text-align: left; padding: 4px 2px;' ]);
		button('back', img([ 'src'=>base_url('/img/bf-kids-logo3.png'), 'style'=>'height: 34px; width: auto;']),
			[ 'class'=>'button-box', 'style'=>'border: 1px solid #ffbd4d; height: 40px; font-size: 18px;', 'onclick'=>'do_back(); return false;' ]);
		_td();
		td([ 'style'=>'text-align: center; padding: 4px 2px;' ]);
		button('reload', img([ 'src'=>'../img/reload.png',
			'style'=>'height: 28px; width: auto; position: relative; bottom: -3px; left: -1px']),
			[ 'class'=>'button-box button-lightgrey', 'style'=>'height: 40px; font-size: 18px;', 'onclick'=>'do_reload(); return false;' ]);
		_td();
		td([ 'style'=>'width: 200px; text-align: right; padding: 4px 0px;' ]);
		button('complete', 'Abschließen',
			[ 'class'=>'button-box', 'style'=>'border: 1px solid #ffbd4d; height: 40px; font-size: 18px;', 'onclick'=>'do_complete(); return false;' ]);
		_td();
		td([ 'style'=>'width: 3px; padding: 0;' ], nbsp());
		_tr();
		_table();
		$reg_top_form->close();

		tag('iframe', [ 'id'=>'content-iframe', 'src'=>'ipad/iframe', 'style'=>'width: 100%; height: 400px; border: 0;' ], '');

		script();
		out('
			function do_login_prompt(user) {
				return prompt("Bitte geben Sie das Passwort für "+user+" ein:", "");
			}
			function do_login() {
				do {
					password = do_login_prompt("Registrierung");
				}
				while (password == null);
				$("#reg_login").val(password);
				$("#reg_top_form").submit();
			}
			function do_back() {
				password = do_login_prompt("'.$this->stf_login_name.'");
				if (!password)
					return;
				$("#reg_back").val(password);
				$("#reg_top_form").submit();
			}
			function do_reload() {
				var content = $("#content-iframe").contents();
				var form = content.find("#reg_iframe_form");
				if (form == null || form.length == 0)
					$("#content-iframe").attr("src", "ipad/iframe");
				else
					form.submit();
			}
			function do_complete() {
				var content = $("#content-iframe").contents();
				var stat_rest = parseInt(content.find("#reg_before").val().split("|")[0]);
				var stat_part = 0;
				if (stat_rest) {
					var reg_now = content.find("#kid_fullname").val()+"|"+
						content.find("#kid_birthday").val()+"|"+content.find("#kid_parent_firstname").val()+"|"+
						content.find("#kid_parent_lastname").val()+"|"+content.find("#kid_parent_cellphone").val()+"|"+
						content.find("#kid_notes").val();
					stat_part = iPadStatus(content.find("#reg_before").val(), reg_now);
				}
				if (!stat_rest || stat_part == 2 || stat_part == 4) {
					if (!confirm("Wollen Sie wirklich die Registrierung abschließen, nicht alle Eingaben sind abgeschlossen?"))
						return;
				}

				content.find("#reg_complete").val(1);
				content.find("#reg_iframe_form").submit();
			}
		');
		//if (!$this->is_logged_in('parent'))
		//	out('$(document).on("load", setTimeout(do_login, 500));');
		_script();

		$this->footer();
		return '';
	}

	private function rows_equal($r1, $r2) {
		if ($r1['kid_fullname'] != $r2['kid_fullname'])
			return false;
		if ($r1['kid_birthday'] != $r2['kid_birthday'])
			return false;
		if ($r1['kid_notes'] != $r2['kid_notes'])
			return false;
		return true;
	}

	private function link($reg_part, $i)
	{
		$attr = [ 'class'=>'menu-item', 'onclick'=>'$("#reg_set_part").val('.$i.'); $("#reg_iframe_form").submit();' ];
		if ($reg_part == $i)
			$attr['selected'] = null;
		return $attr;
	}

	private function get_reg_status($edit_part)
	{
		if (empty($edit_part['kid_fullname'])) {
			if (!empty($edit_part['kid_birthday']))
				return 2;
			return 1;
		}

		$db_part = $this->get_kid_row_by_name($edit_part['kid_fullname']);
		if (empty($db_part)) {
			return 2;
		}

		if ($db_part['kid_registered'] == REG_NO && empty($db_part['kid_group_number'])) {
			if ($this->rows_equal($edit_part, $db_part))
				return 3;
			return 4;
		}

		return 5;
	}

	/*
	public function update_parents($reg_kids_v, $reg_part_v, $reg_supervision_v)
	{
		for ($i=1; $i<=count($reg_kids_v); $i++) {
			$kid_id = $reg_kids_v[$i]['kid_id'];
			if ($i != $reg_part_v && !empty($kid_id))  {
				$this->update_parent($kid_id, $reg_kids_v[$i] + $reg_supervision_v);
			}
		}
	}
	*/

	public function any_empty($part_row)
	{
		return empty($part_row['kid_fullname']) ||
			empty($part_row['kid_lastname']) ||
			empty($part_row['kid_birthday']);
	}

	public function set_default_lastname($reg_kids, $reg_kids_v, $reg_part_v, $kid_empty_row)
	{
		if (!isset($reg_kids_v[$reg_part_v]))
			$reg_kids_v[$reg_part_v] = $kid_empty_row;

		if (empty($reg_kids_v[$reg_part_v]['kid_lastname'])) {
			// Set child default surname:
			$last_name = '';
			for ($i=count($reg_kids_v); $i>=1; $i--) {
				if ($i != $reg_part_v && !empty($reg_kids_v[$i]['kid_lastname']))  {
					$last_name = $reg_kids_v[$i]['kid_lastname'];
					break;
				}
			}
			$reg_kids_v[$reg_part_v]['kid_lastname'] = $last_name;
		}

		// Remove extras:
		for ($i=count($reg_kids_v); $i>$reg_part_v; $i--) {
			if (empty($reg_kids_v[$i]['kid_fullname']) &&
				empty($reg_kids_v[$i]['kid_birthday']) &&
				empty($reg_kids_v[$i]['kid_notes']))
				$reg_kids_v[$i] = $kid_empty_row;
		}

		$reg_kids->setValue($reg_kids_v);
	}

	public function iframe()
	{
		//if (!$this->authorize_staff())
		//	return;

		//$read_only = !is_empty($this->session->stf_login_tech);
		//if ($read_only)
		//	return;
		$this->header('iPad Registrierung', false);
		
		$reg_part = in('reg_part', 1);
		$reg_part->persistent();

		$reg_kids = in('reg_kids', [ ]);
		$reg_kids->persistent();

		$reg_supervision = in('reg_supervision', [ ]);
		$reg_supervision->persistent();

		$kid_empty_row = [ 'kid_id'=>0, 'kid_fullname'=>'', 'kid_birthday'=>'', 'kid_notes'=>'' ];

		$reg_part_v = $reg_part->getValue();
		$reg_kids_v = $reg_kids->getValue();
		$reg_supervision_v = if_empty($reg_supervision->getValue(),
			[ 'kid_parent_firstname'=>'', 'kid_parent_lastname'=>'', 'kid_parent_cellphone'=>'' ]);

		if (isset($_POST['kid_fullname']) &&
			isset($_POST['kid_birthday']) &&
			isset($_POST['kid_notes'])) {
			// Save the POST data:
			// Don't allow the name to be set to a name we already have!:
			$found = false;
			for ($i=1; $i<=count($reg_kids_v); $i++) {
				if ($i != $reg_part_v &&
					strtolower($reg_kids_v[$i]['kid_fullname']) == strtolower(trim($_POST['kid_fullname']))) {
					$found = true;
					break;
				}
			}
			if ($found) {
				if (!isset($reg_kids_v[$reg_part_v]['kid_fullname'])) {
					$reg_kids_v[$reg_part_v]['kid_fullname'] = '';
					$_POST['kid_fullname'] = '';
				}
			}
			else {
				$reg_kids_v[$reg_part_v]['kid_fullname'] = trim($_POST['kid_fullname']);
			}
			$reg_kids_v[$reg_part_v]['kid_birthday'] = trim($_POST['kid_birthday']);
			$reg_kids_v[$reg_part_v]['kid_notes'] = trim($_POST['kid_notes']);
			$reg_kids->setValue($reg_kids_v);
			$reg_supervision->setValue($reg_supervision_v);
		}

		$reg_iframe_form = new Form('reg_iframe_form', 'iframe', 2, array('class'=>'input-table'));
		$reg_before = $reg_iframe_form->addHidden('reg_before');
		$reg_complete = $reg_iframe_form->addHidden('reg_complete');
		$reg_set_part = $reg_iframe_form->addHidden('reg_set_part');

		if ($reg_complete->getValue() == 1) {
			$reg_part->setValue(1);
			$reg_kids->setValue([ ]);
			$reg_supervision->setValue([ ]);
			return redirect("ipad/iframe");
		}

		$reg_set_part_v = $reg_set_part->getValue();
		if ($reg_set_part_v < 0 && $reg_set_part_v > MAX_PARTICIPANTS)
			$reg_set_part_v = 0;

		$edit_part = arr_nvl($reg_kids_v, $reg_part_v, $kid_empty_row) + $reg_supervision_v;

		if (!empty($reg_set_part_v) && $this->any_empty($edit_part)) {
			// May leave a partially empty tab:
			$reg_part->setValue($reg_set_part_v);
			$this->set_default_lastname($reg_kids, $reg_kids_v, $reg_set_part_v, $kid_empty_row);
			return redirect("ipad/iframe");
		}

		$kid_fullname = textinput('kid_fullname', $edit_part['kid_fullname'],
			[ 'placeholder'=>'Name', 'style'=>'width: 160px;', 'onkeyup'=>'capitalize($(this));' ]);
		$kid_fullname->setFormat([ 'clear-box'=>true ]);
		$kid_fullname->setRule('required');
		$kid_birthday = new NumericField('kid_birthday', $edit_part['kid_birthday'],
			[ 'placeholder'=>'DD.MM.JJJJ', 'style'=>'font-family: Monospace; width: 120px;', 'onkeyup'=>'dateChanged($(this));' ]);
		$kid_birthday->setFormat([ 'clear-box'=>true ]);
		$kid_birthday->setRule('is_valid_date', 'Geburtstag');

		$kid_parent_firstname = textinput('kid_parent_firstname', $edit_part['kid_parent_firstname'],
			[ 'placeholder'=>'Vorname', 'style'=>'width: 160px;', 'onkeyup'=>'capitalize($(this));' ]);
		$kid_parent_firstname->setFormat([ 'clear-box'=>true ]);
		$kid_parent_lastname = textinput('kid_parent_lastname', $edit_part['kid_parent_lastname'],
			[ 'placeholder'=>'Nachname', 'style'=>'width: 220px;', 'onkeyup'=>'capitalize($(this));' ]);
		$kid_parent_lastname->setFormat([ 'clear-box'=>true ]);
		$kid_parent_cellphone = new NumericField('kid_parent_cellphone', $edit_part['kid_parent_cellphone'],
			[ 'style'=>'width: 220px; font-family: Monospace;' ]);
		$kid_parent_cellphone->setFormat([ 'clear-box'=>true ]);

		$kid_notes = textarea('kid_notes', $edit_part['kid_notes'], [ 'style'=>'width: 98%;' ]);

		$register = button('register', 'Registrieren', [ 'class'=>'button-box button-green', 'style'=>'width: 100%; height: 48px; font-size: 24px;' ]);
		$register->disable();

		if ($register->submitted() || !empty($reg_set_part_v)) {
			$this->error = $kid_birthday->validate();
			if (empty($this->error)) {
				$bday = str_to_date($kid_birthday->getValue());
				if (empty($bday))
					$this->error = "Geburtstag ist kein gültiges Datum";
				else {
					$year = (integer) $bday->format('Y');
					if ($year < 2003 || $year > 2017)
						$this->error = "Geburtstag ist kein gültiges Datum";
				}
			}
			if (empty($this->error)) {
				$reg_kids_v[$reg_part_v]['kid_birthday'] = $bday->format('d.m.Y');
				$reg_kids->setValue($reg_kids_v);
				$edit_part = arr_nvl($reg_kids_v, $reg_part_v, $kid_empty_row) + $reg_supervision_v;
				if (!empty($edit_part['kid_fullname'])) {
					$db_part = $this->get_kid_row_by_name($edit_part['kid_fullname']);
					if (!empty($edit_part['kid_id'])) {
						$db_part_by_id = $this->get_kid_row($edit_part['kid_id']);
						if (empty($db_part))
							// Name was not found, change the name...
							$db_part = $db_part_by_id;
						else {
							if ($db_part['kid_id'] != $db_part_by_id['kid_id']) {
								// Revert name completely:
								$this->error = $edit_part['kid_fullname'].' ist bereits registriert';
								$_POST['kid_fullname'] = $db_part_by_id['kid_fullname'];
								$edit_part['kid_fullname'] = $db_part_by_id['kid_fullname'];
								$reg_kids_v[$reg_part_v]['kid_fullname'] = $db_part_by_id['kid_fullname'];
								$reg_kids->setValue($reg_kids_v);
								goto end_of_edit;
							}
						}
					}
					unset($edit_part['kid_id']);
					if (empty($db_part)) {
						$this->set_error($this->insert_kid($edit_part, false,
							0, '', $kid_id_v));
						if (!$this->have_error()) {
							$reg_kids_v[$reg_part_v]['kid_id'] = $kid_id_v;
							$reg_kids->setValue($reg_kids_v);
							//$this->update_parents($reg_kids_v, $reg_part_v, $reg_supervision_v);
							$this->set_success($edit_part['kid_fullname'].' registriert');
							// Move to next tab:
							$reg_part_v++;
							if ($reg_part_v > MAX_PARTICIPANTS)
								$reg_part_v = MAX_PARTICIPANTS;
							$reg_part->setValue($reg_part_v);
							$this->set_default_lastname($reg_kids, $reg_kids_v, $reg_part_v, $kid_empty_row);
							if (!empty($reg_set_part_v)) {
								$reg_part->setValue($reg_set_part_v);
								$this->set_default_lastname($reg_kids, $reg_kids_v,
									$reg_set_part_v, $kid_empty_row);
							}
							return redirect("ipad/iframe");
						}
						else {
							$reg_kids_v[$reg_part_v]['kid_id'] = 0;
							$reg_kids->setValue($reg_kids_v);
						}
					}
					else {
						if (!empty($reg_set_part_v) && $this->rows_equal($edit_part, $db_part)) {
							// No change, just change tab:
							$reg_part->setValue($reg_set_part_v);
							$this->set_default_lastname($reg_kids, $reg_kids_v,
							$reg_set_part_v, $kid_empty_row);
							return redirect("ipad/iframe");
						}
						if ($db_part['kid_registered'] == REG_NO && empty($db_part['kid_group_number'])) {
							$this->modify_kid($db_part['kid_id'], $db_part, $edit_part, false,
								$before_parent, 0);
							$reg_kids_v[$reg_part_v]['kid_id'] = $db_part['kid_id'];
							$reg_kids->setValue($reg_kids_v);
							//$this->update_parents($reg_kids_v, $reg_part_v, $reg_supervision_v);
							$this->set_success($edit_part['kid_fullname'].' geändert');
							if (!empty($reg_set_part_v)) {
								$reg_part->setValue($reg_set_part_v);
								$this->set_default_lastname($reg_kids, $reg_kids_v,
									$reg_set_part_v, $kid_empty_row);
							}
							return redirect("ipad/iframe");
						}
						else {
							$reg_kids_v[$reg_part_v]['kid_id'] = 0;
							$reg_kids->setValue($reg_kids_v);
							$this->error = $edit_part['kid_fullname'].' ist bereits angemeldet';
						}
					}
					end_of_edit:;
				}
			}
		}

		$reg_iframe_form->open();

		div(array('class'=>'topnav'));
		table();
		tr();
		td([ 'style'=>'width: 3px; padding: 0;' ], nbsp());
		$stat_part = 1;
		$stat_rest = 1;
		for ($i=1; $i<=MAX_PARTICIPANTS; $i++) {
			$attr = [ 'id'=>'reg_status_'.$i, 'style'=>'width: 100%;' ];
			$status = $this->get_reg_status(arr_nvl($reg_kids_v, $i, $kid_empty_row) + $reg_supervision_v);
			switch ($status) {
				case 1: $box = div($attr + [ 'class'=>'grey-box' ], nbsp()); break;
				case 2: $box = div($attr + [ 'class'=>'yellow-box' ], 'Wird registriert'); break;
				case 3: $box = div($attr + [ 'class'=>'green-box' ], 'Registriert'); break;
				case 4: $box = div($attr + [ 'class'=>'yellow-box' ], 'Wird geändert'); break;
				case 5: $box = div($attr + [ 'class'=>'red-box' ], 'Angemeldet'); break;
			}
			td([ 'width'=>(100/MAX_PARTICIPANTS).'%', 'style'=>'height: 22px; padding: 0px 2px 5px 2px;' ], $box);
			td([ 'width'=>(100/MAX_PARTICIPANTS).'%', 'style'=>'width: 3px; padding: 0;' ], nbsp());
			if ($reg_part_v == $i)
				$stat_part = $status;
			else if ($status == 2 || $status == 4)
				$stat_rest = 0;
				
		}
		$before = $stat_rest.'|'.$stat_part.'|'.$kid_fullname->getValue().'|'.
			$kid_birthday->getValue().'|'.$kid_parent_firstname->getValue().'|'.$kid_parent_lastname->getValue().'|'.
			$kid_parent_cellphone->getValue().'|'.$kid_notes->getValue();
		$reg_before->setValue($before);

		_tr();
		_tr();
		tr([ 'style'=>'border-bottom: 1px solid black; padding: 8px 16px;' ]);
		td([ 'style'=>'width: 3px; padding: 0;' ], nbsp());
		for ($i=1; $i<=MAX_PARTICIPANTS; $i++) {
			$part_row = arr_nvl($reg_kids_v, $i, $kid_empty_row);
			$fname = arr_nvl($part_row, 'kid_fullname', '');
			$lname = '';
			if (empty($fname)) {
				$fname = $lname;
				$lname = '';
			}
			if (!empty($fname)) {
				if (strlen($fname) + strlen($lname) > 14) {
					if (empty($lname))
						$tab_title = substr($fname, 0, 12).'...';
					else {
						if (strlen($fname) <= 12)
							$tab_title = $fname.' '.substr($lname, 0, 1).".";
						else
							$tab_title = substr($fname, 0, 9).'... '.substr($lname, 0, 1).".";
					}
				}
				else
					$tab_title = $fname.' '.$lname;
			}
			else
				$tab_title = 'Kind '.$i;
			td([ 'id'=>'reg_tab_'.$i, 'width'=>(100/MAX_PARTICIPANTS).'%' ] + $this->link($reg_part_v, $i), $tab_title);
			td([ 'width'=>(100/MAX_PARTICIPANTS).'%', 'style'=>'width: 3px; padding: 0;' ], nbsp());
		}
		_tr();
		_table();
		_div();

		table([ 'class'=>'ipad-table', 'style'=>'padding-top: 2px;' ]);
		tr();
		td(nbsp().b('Kind '.$reg_part_v.':'));
		td(nbsp().b('Begleitperson:'));
		_tr();
		tr();
		td();
			table([ 'style'=>'border: 1px solid black; border-collapse: separate; border-spacing: 5px;' ]);
			tr();
			td($kid_fullname);
			td('');
			_tr();
			tr();
			td([ 'style'=>'text-align: right;' ], 'Geburtstag:');
			td($kid_birthday);
			_tr();
			_table();
		_td();
		td();
			table([ 'style'=>'border: 1px solid black; border-collapse: separate; border-spacing: 5px;' ]);
			tr();
			td($kid_parent_firstname);
			td($kid_parent_lastname);
			_tr();
			tr();
			td([ 'style'=>'text-align: right;' ], 'Handy-Nr:');
			td($kid_parent_cellphone);
			_tr();
			_table();
		_td();
		_tr();
		tr(td(nbsp().'Allergien und andere Besonderheiten des Kindes:'));
		tr();
		td([ 'colspan'=>2 ]);
			table([ 'style'=>'width: 100%;' ]);
			tr();
			td([ 'style'=>'width: 75%;' ], $kid_notes);
			td([ 'valign'=>'top', 'align'=>'right', 'style'=>'width: 25%;' ], $register);
			_tr();
			_table();
		_td();
		_tr();
		tr();
		td([ 'colspan'=>2 ]);
		$this->print_result();
		_td();
		_tr();
		_table();

		$reg_iframe_form->close();

		script();
		out('
			function reg_changed() {
				var reg_now = $("#kid_fullname").val()+"|"+$("#kid_birthday").val()+"|"+
					$("#kid_parent_firstname").val()+"|"+$("#kid_parent_lastname").val()+"|"+
					$("#kid_parent_cellphone").val()+"|"+$("#kid_notes").val();
				iPadRegistrationChanged(
					'.$reg_part_v.',
					$("#reg_before").val(),
					reg_now,
					$("#reg_status_'.$reg_part_v.'"),
					$("#reg_tab_'.$reg_part_v.'"),
					$("#register")
				);
			}
			$("#kid_fullname").keyup(reg_changed);
			$("#kid_birthday").keyup(reg_changed);
			$("#kid_notes").keyup(reg_changed);
			$("#kid_parent_firstname").keyup(reg_changed);
			$("#kid_parent_lastname").keyup(reg_changed);
			$("#kid_parent_cellphone").keyup(reg_changed);
			reg_changed();
		');
		_script();
		$this->footer();
		return '';
	}
}
