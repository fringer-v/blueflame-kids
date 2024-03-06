<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

define('MAX_PARTICIPANTS', 5);

class Checkin extends BF_Controller {
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$this->start_session();
	}

	public function registration()
	{
		if (!$this->is_logged_in('parent'))
			return redirect('login');

		$current_period = $this->db_model->get_setting('current-period');
		$parent_row = $this->get_parent_row($this->par_login_id);

		$ci_top_form = new Form('ci_top_form', 'registration', 2, array('class'=>'input-table'));
		$go_back = imagebutton('go_back', '../img/bf-kids-logo3.png', ['style'=>'height: 34px; width: auto;']);
		$go_back->autoEchoOff();

		$par_left_col_size = 347;
		$par_right_col_size = 187;

		$ci_parent_form = new Form('ci_parent_form', 'registration');
		$ci_parent_form->addHidden('par_id', $parent_row['par_id']);
		$par_fullname = textinput('par_fullname', $parent_row['par_fullname'],
			['placeholder'=>'Name', 'style'=>'width: '.($par_left_col_size - 40).'px;',
				'onkeyup'=>'capitalizeName($(this), event); toggle_submit_parent();', 'before'=>$parent_row['par_fullname']]);
		$par_fullname->setRule('required|is_unique[bf_parents.par_fullname.par_id]', 'Name');
		$par_fullname->autoEchoOff();
		$par_fullname->setFormat(['clear-box'=>true]);
		$par_cellphone = new NumericField('par_cellphone', $parent_row['par_cellphone'],
			['style'=>'font-family: Monospace; width: '.($par_right_col_size - 38).'px;',
				'onkeyup'=>'toggle_submit_parent();', 'before'=>$parent_row['par_cellphone']]);
		$par_cellphone->setRule('required|is_phone|is_unique[bf_parents.par_cellphone.par_id]', 'Handy-Nr');
		$par_cellphone->setFormat(['clear-box'=>true]);

		$update_parent = button('update_parent', 'Sichern', ['class'=>'button-box button-green', 'style'=>'width: 154px; height: 34px; font-size: 18px; border-radius: 0;']);
		$update_parent->disable();
		$update_parent->autoEchoOff();

		$logout_parent = button('logout_parent', 'Logout', ['class'=>'button-box button-black', 'style'=>'width: 80px; height: 34px; font-size: 18px; border-radius: 0;']);
		$update_parent->autoEchoOff();

		$kid_left_col_size = 378;
		$kid_mid_col_size = 118;
		$kid_right_col_size = 40;

		$kid_id = hidden('kid_id');
		$kid_id->autoEchoOff();
		$kid_nr = hidden('kid_nr');
		$kid_nr->autoEchoOff();
		$kid_id_v = $kid_id->getValue();
		$kid_nr_v = $kid_nr->getValue();

		$submit_kid = button('submit_kid', 'Set Me!', ['style'=>'width: 154px; height: 34px; font-size: 18px; border-radius: 0;']);
		$submit_kid->disable();
		$submit_kid->autoEchoOff();

		$delete_kid = imagebutton('delete_kid', '../img/cross-box.png', ['style'=>'width: 24px; height: 24px;',
			'onclick'=>'return confirm("Wollen Sie dieses Kind wirklich abmelden?")']);
		$delete_kid->autoEchoOff();

		$kid_fullname = in('kid_fullname_'.$kid_nr_v);
		$kid_fullname->setRule('required', 'Namen des Kindes');

		if ($update_parent->submitted()) {
			$this->set_error($par_fullname->validate($ci_parent_form));
			if (!$this->have_error())
				$this->set_error($par_cellphone->validate($ci_parent_form));
			if (!$this->have_error()) {
				$data = array(
					'par_fullname' => $par_fullname->getValue(),
					'par_cellphone' => $par_cellphone->getValue()
				);
				$builder = $this->db->table('bf_parents');
				$builder->where('par_id', $this->par_login_id);
				$builder->update($data);
				$this->set_success($par_fullname->getValue().' geändert');
				return redirect('registration');
			}
		}

		if ($logout_parent->submitted()) {
			$this->set_parent_logged_out();
			return redirect('registration');
		}

		if ($submit_kid->submitted()) {
			$err_prefix = 'Kind '.$kid_nr_v.': ';
			$kid_birthday = in('kid_birthday_'.$kid_nr_v);
			$kid_birthday->setRule('required|is_valid_date', 'Geburtstag');
			$kid_notes = in('kid_notes_'.$kid_nr_v);
	
			if (empty($kid_fullname->getValue()))
				$this->set_error($err_prefix.'Bitte geben Sie den vollständigen Namen des Kindes ein');
			if (!$this->have_error())
				$this->set_error($kid_fullname->validate(null, $err_prefix));

			if (!$this->have_error())
				$this->set_error($kid_birthday->validate(null, $err_prefix));
			if (!$this->have_error()) {
				$bday = $kid_birthday->getDate();
				if (empty($bday))
					$this->set_error($err_prefix.'Geburtstag ist kein gültiges Datum');
				else {
					$year = (integer) $bday->format('Y');
					if ($year < 2003 || $year > 2022)
						$this->set_error($err_prefix.'Geburtstagsjahr nicht im gültigen Bereich');
				}
			}
			if (!$this->have_error()) {
				$kid_present_periods = 0;
				for ($p=0; $p<PERIOD_COUNT; $p++) {
					$present = in('present_'.$kid_nr_v.'_'.$p);
					if ($present->getValue()) {
						$kid_present_periods = set_bit($kid_present_periods, $p);
					}
				}

				$after_row = [
					'kid_fullname' => $kid_fullname->getValue(),
					'kid_birthday' => $kid_birthday->getDate('d.m.Y'),
					'kid_present_periods' => $kid_present_periods,
					'kid_notes' => $kid_notes->getValue()
				];
				if (empty($kid_id_v)) {
					$after_row['kid_parent_id'] = $this->session->par_login_id;
					$this->set_error($this->insert_kid($after_row, false,
						0, $err_prefix, $kid_id_v));
					if (!$this->have_error()) {
						$this->set_success($err_prefix.$kid_fullname->getValue().' angemeldet');
						return redirect('registration');
					}
				}
				else {
					$kid_row = $this->get_kid_row($kid_id_v);
					$after_row['kid_parent_id'] = $kid_row['kid_parent_id'];
					$this->set_error($this->modify_kid($kid_id_v, $kid_row, $after_row, false, [ ], 0, $err_prefix));
					if (!$this->have_error()) {
						$this->set_success($err_prefix.$kid_fullname->getValue().' geändert');
						return redirect('registration');
					}
				}
			}
		}

		if ($delete_kid->submitted()) {
			$err_prefix = 'Kind '.$kid_nr_v.': ';
			$this->remove_kid($kid_id_v);
			$this->set_success($err_prefix.$kid_fullname->getValue().' abgemeldet');
			return redirect('registration');
		}

		$this->header('Anmeldung', false);

		table(['class'=>'ipad-table registration-table', 'style'=>'padding-top: 2px; margin-left: auto; margin-right: auto;']);
		$ci_top_form->open();
		tr();
		td(['style'=>'background-color: black; color: white; text-align: center; font-size: 20px; padding: 8px 0px 4px 0px;']);
		out($go_back);
		br();
		out('Anmeldung');
		_td();
		_tr();
		_tr();
		$ci_top_form->close();
		if ($this->have_result()) {
			tr();
			td();
			$this->print_result();
			_td();
			_tr();
		}
		tr();
		td();
			$ci_parent_form->open();
			table(['style'=>'background-color: #649CBA; border-collapse: separate; border-spacing: 8px; width: 100%;']); // 649CBA 5FB8E5
			tr();
				th(['style'=>'font-size: 16px; padding: 0px; text-align: left; width: '.$par_left_col_size.'px'], 'Begleitperson:');
				th(['style'=>'font-size: 16px; padding: 0px; text-align: left; width: '.$par_right_col_size.'px'], 'Handy-Nr:');
			_tr();
			tr();
				td(['style'=>'padding: 0px; width: '.$par_left_col_size.'px'], $par_fullname);
				td(['style'=>'padding: 0px; width: '.$par_right_col_size.'px'], $par_cellphone);
			_tr();
			tr();
				td(['style'=>'font-size: 18px; padding: 0px; text-align: left; width: '.$par_left_col_size.'px'], b('E-Mail: ').$parent_row['par_email']);
				td(['style'=>'font-size: 18px; padding: 0px; text-align: left; width: '.$par_right_col_size.'px'], b('Kids-ID: ').$parent_row['par_code']);
			_tr();
			tr();
				td(['colspan'=>2, 'style'=>'padding: 0px; text-align: right']);
				out($logout_parent);
				nbsp();nbsp();
				out($update_parent);
				_td();
			_tr();
			_table();
			$ci_parent_form->close();
		_td();
		_tr();

		$kid_rows = $this->get_kids($this->par_login_id);
		$kid_i = 0;
		foreach ($kid_rows as $row) {
			$kid_i++;
			tr();
			td();
			$this->kid_form($kid_i, $row, $current_period,
				$kid_nr, $kid_id,
				$submit_kid, $delete_kid,
				$kid_left_col_size, $kid_mid_col_size, $kid_right_col_size);
			_td();
			_tr();
		}
		$kid_i++;
		tr();
		td();
		$this->kid_form($kid_i, ['kid_id'=>0, 'kid_fullname'=>'', 'kid_birthday'=>'', 'kid_present_periods'=>0, 'kid_notes'=>''], $current_period,
			$kid_nr, $kid_id,
			$submit_kid, $delete_kid,
			$kid_left_col_size, $kid_mid_col_size, $kid_right_col_size);
		_td();
		_tr();
		_table();
		script();
		out('
			function toggle_submit_parent(kid_i, period_count) {
				var changed = false;
				par_fullname = $("#par_fullname").val();
				par_cellphone = $("#par_cellphone").val();
				par_fullname_before = $("#par_fullname").attr("before");
				par_cellphone_before = $("#par_cellphone").attr("before");

				if (par_fullname != par_fullname_before)
					changed = true;
				if (par_cellphone != par_cellphone_before)
					changed = true;

				$("#update_parent").prop("disabled", !changed);
			}
			function toggle_submit_kid(kid_i, period_count) {
				var changed = false;
				kid_fullname = $("#kid_fullname_"+kid_i).val();
				kid_birthday = $("#kid_birthday_"+kid_i).val();
				kid_notes = $("#kid_notes_"+kid_i).val();
				kid_fullname_before = $("#kid_fullname_"+kid_i).attr("before");
				kid_birthday_before = $("#kid_birthday_"+kid_i).attr("before");
				kid_notes_before = $("#kid_notes_"+kid_i).attr("before");

				if (kid_fullname != kid_fullname_before)
					changed = true;
				if (kid_birthday != kid_birthday_before)
					changed = true;
				if (kid_notes != kid_notes_before)
					changed = true;
					
				var present, present_before;
				for (i=0; i<period_count; i++) {
					present = $("#present_"+kid_i+"_"+i).is(":checked");
					present_before = $("#present_"+kid_i+"_"+i).attr("before") == 1;
					if (present != present_before)
						changed = true;
				}
				
				$("#kid_checkin_form_"+kid_i+" #submit_kid").prop("disabled", !changed);
			}
		');
		_script();
		$this->footer();
		return '';
	}

	public function login()
	{
		$this->is_logged_in('parent');

		$current_period = $this->db_model->get_setting('current-period');

		$ci_top_form = new Form('ci_top_form', 'registration');

		$par_left_col_size = 347;
		$par_right_col_size = 187;

		$login_form = new Form('user_login', url('login'));
		$par_login_email = textinput('par_login_email', '',
			['placeholder'=>'E-Mail',  'inputmode'=>'email', 'style'=>'width: '.($par_left_col_size - 40).'px;', 'onkeypress'=>'login_press(event);', 'onkeyup'=>'login_keyup(event);']);
		$par_login_email->setAttribute('before', $par_login_email->getValue());
		$par_login_email->setRule('required|is_email');
		$par_login_email->autoEchoOff();
		$par_login_email->setFormat(['clear-box'=>true]);
		$par_login_email->persistent();

		$par_code = textinput('par_code', '',
			['placeholder'=>'Kids-ID', 'style'=>'width: '.($par_right_col_size - 40).'px;', 'onkeypress'=>'login_press(event);', 'onkeyup'=>'kidsID($(this)); login_keyup(event);']);
		$par_code->setAttribute('before', $par_code->getValue());
		$par_code->setRule('required');
		$par_code->setFormat(['clear-box'=>true]);

		$login = button('login', 'Anmelden',
			['class'=>'button-box button-black', 'style'=>'width: 154px; height: 34px; font-size: 18px; border-radius: 0;']);
		if ($par_login_email->isEmpty() || $par_code->isEmpty())
			$login->disable();
		$login->autoEchoOff();
		$send_kids_id = button('send_kids_id', 'Kids-ID per E-Mail Versenden',
			['class'=>'button-box button-black', 'style'=>'width: 280px; height: 34px; font-size: 18px; border-radius: 0;']);
		if ($par_login_email->isEmpty())
			$send_kids_id->disable();
		$send_kids_id->autoEchoOff();

		$register_form = new Form('register_form', url('login'));
		$par_fullname = textinput('par_fullname', '',
			['placeholder'=>'Vorname und Nachname', 'style'=>'width: '.($par_left_col_size - 40).'px;', 'onkeypress'=>'registration_press(event);', 'onkeyup'=>'capitalizeName($(this), event); registration_keyup(event);']);
		$par_fullname->setRule('required|is_unique[bf_parents.par_fullname]', 'Begleitperson Name');
		$par_fullname->setFormat(['clear-box'=>true]);

		$par_cellphone = textinput('par_cellphone', '',
			['placeholder'=>'Handy-Nr', 'inputmode'=>'tel', 'style'=>'width: '.($par_right_col_size - 40).'px;', 'onkeypress'=>'registration_press(event);', 'onkeyup'=>'registration_keyup(event);']);
		$par_cellphone->setRule('required|is_phone|is_unique[bf_parents.par_cellphone]', 'Begleitperson Handy-Nr');
		$par_cellphone->setFormat(['clear-box'=>true]);

		$par_email = textinput('par_email', '',
			['placeholder'=>'E-Mail', 'inputmode'=>'email', 'style'=>'width: '.($par_left_col_size - 40).'px;', 'onkeypress'=>'registration_press(event);', 'onkeyup'=>'registration_keyup(event);']);
		$par_email->setRule('required|is_email|is_unique[bf_parents.par_email]', 'Begleitperson E-Mail');
		$par_email->setFormat(['clear-box'=>true]);

		$register = button('register', 'Registrieren',
			['class'=>'button-box button-black', 'style'=>'width: 154px; height: 34px; font-size: 18px; border-radius: 0;']);
		if ($par_fullname->isEmpty() || $par_cellphone->isEmpty() || $par_email->isEmpty())
			$register->disable();
		$register->autoEchoOff();

		if ($login->submitted()) {
			$this->set_error($par_login_email->validate($login));
			if (!$this->have_error())
				$this->set_error($par_code->validate($login));
			if (!$this->have_error()) {
				$parent_row = $this->get_parent_row_by_email($par_login_email->getValue());
				if (empty($parent_row['par_id']))
					$this->set_error("Unbekannte E-Mail oder falsche Kids-ID");
				else {
					$code = $par_code->getValue();
					if ($code == $parent_row['par_code']) {
						$this->set_parent_logged_in($parent_row);
						return redirect("registration");
					}
					$this->set_error("Unbekannte E-Mail oder falsche Kids-ID");
				}
			}
		}

		if ($send_kids_id->submitted()) {
			$this->set_error($par_login_email->validate($login ));
			if (!$this->have_error()) {
				$parent_row = db_1_row('SELECT par_code, par_email, par_fullname FROM bf_parents WHERE par_email = ?', [ $par_login_email->getValue() ]);
				if (empty($parent_row)) {
					//$this->set_error('Die E-Mail-Adresse: '.b($par_email->getValue()).', ist unbekannt. '.
					//	'Bitte verwenden Sie das Registrierungsformular unten oder geben Sie eine andere E-Mail-Adresse ein.');
				}
				else {
					$this->send_email($parent_row['par_email'], "[BlueFlame Kids] Hier ist deine Kids-ID",
						"Hallo ".$parent_row['par_fullname'].",\r\n\r\n".
						"hier ist deine Kids-ID: ".$parent_row['par_code']."\r\n\r\n".
						"Mit dieser kannst du dich zusammen mit deiner E-Mail Adresse einloggen und ".
						"deine Kinder für das BlueFlame Conference Kinderprogramm anmelden.\r\n\r\n".
						"Dein BlueFlame Kids Team");
				}
				$this->set_success(['Eine E-Mail mit Ihrer Kids-ID wurde an '.b($par_email->getValue()).' gesendet. '.
					'Geben Sie diese unten ein, um sich anzumelden.']);
				return redirect("registration");
			}
		}

		if ($register->submitted()) {
			$this->set_error($par_fullname->validate($register_form));
			if (!$this->have_error())
				$this->set_error($par_cellphone->validate($register_form));
			if (!$this->have_error())
				$this->set_error($par_email->validate($register_form));
			if (!$this->have_error()) {
				$par_code = $this->get_parent_code();
				$parent['par_code'] = $par_code;
				$parent['par_email'] = $par_email->getValue();
				$parent['par_fullname'] = $par_fullname->getValue();
				$parent['par_cellphone'] = $par_cellphone->getValue();
				$builder = $this->db->table('bf_parents');
				$builder->insert($parent);

				$this->send_email($par_email->getValue(), "[BlueFlame Kids] Willkommen bei der BlueFlame Kids Anmeldung",
					"Hallo ".$par_fullname->getValue().",\r\n\r\n".
					"willkommen bei der BlueFlame Kids Anmeldung.\r\n\r\n".
					"Hier ist deine Kids-ID: ".$par_code."\r\n\r\n".
					"Mit dieser kannst du dich zusammen mit deiner E-Mail Adresse einloggen und ".
					"deine Kinder für das BlueFlame Conference Kinderprogramm anmelden.\r\n\r\n".
					"Dein BlueFlame Kids Team");

				$this->set_success(['Hallo '.$par_fullname->getValue().', willkommen der BlueFlame Kids Anmeldung.',
					'Eine E-Mail mit Ihrer Kids-ID wurde an '.b($par_email->getValue()).' gesendet. '.
					'Geben Sie diese unten ein, um sich anzumelden.']);
				$par_login_email->setValue($par_email->getValue());
				return redirect('registration');
			}
		}

		$this->header('Anmeldung', false);

		table(['class'=>'ipad-table registration-table', 'style'=>'padding-top: 2px; margin-left: auto; margin-right: auto;']);
		$ci_top_form->open();
		tr();
		td(['style'=>'background-color: black; color: white; text-align: center; font-size: 22px; padding: 8px 0px 4px 0px;']);
		img(['src'=>'../img/bf-kids-logo3.png', 'style'=>'height: 34px; width: auto;']);
		br();
		out('Anmeldung');
		_td();
		_tr();
		_tr();
		$ci_top_form->close();
		if ($this->have_result()) {
			tr();
			td(['style'=>'width: 560px;']);
			$this->print_result();
			_td();
			_tr();
		}
		tr();
		td();
			$login_form->open();

			table(['style'=>'border: 1px solid #446C81; background-color: #649CBA; border-collapse: separate; border-spacing: 8px; width: 100%;']);
			tr();
				th(['style'=>'font-size: 16px; color: white; padding: 0px; text-align: left; width: '.$par_left_col_size.'px'], 'E-Mail:');
				th(['style'=>'font-size: 16px; color: white; padding: 0px; text-align: left; width: '.$par_right_col_size.'px'], 'Kids-ID:');
			_tr();
			tr();
				td(['style'=>'padding: 0px; width: '.$par_left_col_size.'px'], $par_login_email);
				td(['style'=>'padding: 0px; width: '.$par_right_col_size.'px'], $par_code);
			_tr();
			tr();
				td(['colspan'=>2, 'style'=>'padding: 0px; text-align: right']);
				out($send_kids_id);
				span(['style'=>'font-size: 28px;'], ' ');
				out($login);
				_td();
			_tr();
			_table();

			/*
			table(['class'=>'mobile-table',
				'style'=>'border: 1px solid #009DDE; background-color: #649CBA; border-collapse: separate; border-spacing: 8px; width: 100%;']);
			tr(th(['style'=>'font-size: 16px; color: white; padding: 0px; text-align: left; width: '.$par_left_col_size.'px'], 'E-Mail:'));
			tr(td(['style'=>'padding: 0px; width: '.$par_left_col_size.'px'], $par_login_email));
			tr(th(['style'=>'font-size: 16px; color: white; padding: 0px; text-align: left; width: '.$par_right_col_size.'px'], 'Kids-ID:'));
			tr(td(['style'=>'padding: 0px; width: '.$par_right_col_size.'px'], $par_code));
			tr(td(['style'=>'padding: 0px; text-align: right'], $login));
			tr(td(['style'=>'padding: 0px; text-align: right'], $send_kids_id));
			_table();
			*/

			$login_form->close();
		_td();
		_tr();
		tr();
		td(['style'=>'text-align: center; white-space: normal; word-wrap: normal;']);
		out('Wenn Sie sich noch nicht als Begleitperson registriert haben, benutzen Sie das folgende Formular, um sich zu registrieren.');
		_td();
		tr(['style'=>'background-color: black; color: white;']);
		td(['style'=>'text-align: center; font-size: 20px; padding: 8px;']);
		out('Registrierung');
		_td();
		_tr();
		td();
			$register_form->open();

			table(['style'=>'border: 1px solid black; border-collapse: separate; border-spacing: 8px; background: #f1f1f1; width: 100%;']);
			tr();
				th(['style'=>'font-size: 16px; padding: 0px; text-align: left; width: '.$par_left_col_size.'px'], 'Begleitperson:');
				th(['style'=>'font-size: 16px; padding: 0px; text-align: left; width: '.$par_right_col_size.'px'], 'Handy-Nr:');
			_tr();
			tr();
				td(['style'=>'padding: 0px; width: '.$par_left_col_size.'px'], $par_fullname);
				td(['style'=>'padding: 0px; width: '.$par_right_col_size.'px'], $par_cellphone);
			_tr();
			tr();
				th(['style'=>'font-size: 16px; padding: 0px; text-align: left; width: '.$par_left_col_size.'px'], 'E-Mail:');
				th();
			_tr();
			tr();
				td(['style'=>'padding: 0px; width: '.$par_left_col_size.'px'], $par_email);
				td();
			_tr();
			tr();
				td(['colspan'=>2, 'style'=>'padding: 0px; text-align: right']);
				out($register);
				_td();
			_tr();
			_table();

			/*
			table(['class'=>'mobile-table',
				'style'=>'border: 1px solid black; border-collapse: separate; border-spacing: 8px; background: #f1f1f1; width: 100%;']);
			tr(th(['style'=>'font-size: 16px; padding: 0px; text-align: left; width: '.$par_left_col_size.'px'], 'Begleitperson:'));
			tr(td(['style'=>'padding: 0px; width: '.$par_left_col_size.'px'], $par_fullname));
			tr(th(['style'=>'font-size: 16px; padding: 0px; text-align: left; width: '.$par_right_col_size.'px'], 'Handy-Nr:'));
			tr(td(['style'=>'padding: 0px; width: '.$par_right_col_size.'px'], $par_cellphone));
			tr(th(['style'=>'font-size: 16px; padding: 0px; text-align: left; width: '.$par_left_col_size.'px'], 'E-Mail:'));
			tr(td(['style'=>'padding: 0px; width: '.$par_left_col_size.'px'], $par_email));
			tr(td(['style'=>'padding: 0px; text-align: right'], $register));
			_table();
			*/
			
			$register_form->close();
		_td();
		_table();
		script();
		out('
			function login_press(ev) {
				var par_login_email = $("#par_login_email").val();
				var par_code = $("#par_code").val();

				if (ev.key == "Enter") {
					if (par_login_email == "" || par_code == "") {
						ev.preventDefault();
						if (par_code == "")
							$("#par_code").focus();
						else
							$("#par_login_email").focus();
					}
				}
			}
			function login_keyup() {
				var par_login_email = $("#par_login_email").val();
				var par_code = $("#par_code").val();

				$("#login").prop("disabled", par_login_email == "" || par_code == "");
				$("#send_kids_id").prop("disabled", par_login_email == "");
			}
			function registration_press(ev) {
				par_fullname = $("#par_fullname").val();
				par_cellphone = $("#par_cellphone").val();
				par_email = $("#par_email").val();
				var filled_out = par_fullname != "" && par_cellphone != "" && par_email != "";

				if (ev.key == "Enter") {
					if (!filled_out) {
						ev.preventDefault();
						if (par_cellphone == "")
							$("#par_cellphone").focus();
						else if (par_email == "")
							$("#par_email").focus();
						else
							$("#par_fullname").focus();
					}
				}
			}
			function registration_keyup(ev) {
				par_fullname = $("#par_fullname").val();
				par_cellphone = $("#par_cellphone").val();
				par_email = $("#par_email").val();
				var filled_out = par_fullname != "" && par_cellphone != "" && par_email != "";

				$("#register").prop("disabled", !filled_out);
			}
		');
		_script();
		$this->footer();
		return '';
	}

	// https://github.com/PHPMailer/PHPMailer
	private function send_email($to, $subject, $message)
	{
		$headers = array(
			'From' => 'no_reply@blueflame-sh.de',
			'Reply-To' => 'kontakt@blueflame-sh.de',
			'X-Mailer' => 'PHP/' . phpversion()
		);
		
		//return mail($to, $subject, $message, $headers, '-fkontakt@blueflame-sh.de');
	}

	private function kid_form($kid_i, $row, $current_period,
		$kid_nr, $kid_id,
		$submit_kid, $delete_kid,
		$kid_left_col_size, $kid_mid_col_size, $kid_right_col_size)
	{
		$period_names = $GLOBALS['period_names'];

		$kid_nr->setValue($kid_i);
		$kid_id->setValue($row['kid_id']);

		$kid_checkin_form = new Form('kid_checkin_form_'.$kid_i, 'registration', 2, array('class'=>'input-table'));

		$kid_fullname = textinput('kid_fullname_'.$kid_i, $row['kid_fullname'],
			['placeholder'=>'Vorname und Nachname', 'style'=>'width: '.($kid_left_col_size - 40).'px;', 'before'=>$row['kid_fullname'],
				'onkeyup'=>'capitalizeName($(this), event); toggle_submit_kid('.$kid_i.', '.PERIOD_COUNT.');']);
		$kid_fullname->setFormat(['clear-box'=>true]);
		$kid_birthday = new NumericField('kid_birthday_'.$kid_i, $row['kid_birthday'],
			['placeholder'=>'DD.MM.JJJJ', 'style'=>'font-family: Monospace; width: '.($kid_mid_col_size + $kid_right_col_size - 40).'px;', 'before'=>$row['kid_birthday'],
				'onkeyup'=>'dateChanged($(this)); toggle_submit_kid('.$kid_i.', '.PERIOD_COUNT.');']);
		$kid_birthday->setFormat(['clear-box'=>true]);
		$kid_notes = textarea('kid_notes_'.$kid_i, $row['kid_notes'],
			['style'=>'width: 99%; height: 28px;', 'before'=>$row['kid_notes'],
				'onkeyup'=>'toggle_submit_kid('.$kid_i.', '.PERIOD_COUNT.');']);

		$kid_checkin_form->open();
		table(['style'=>'border: 1px solid black; border-collapse: separate; border-spacing: 0px; background: #f1f1f1; width: 100%;']);
		tr();
			th(['style'=>'font-size: 16px; padding: 6px 0px 0px 8px; text-align: left; width: '.$kid_left_col_size.'px;'], 'Kind '.$kid_i);
			th(['style'=>'font-size: 16px; padding: 6px 0px 0px 0px; text-align: left; width: '.($kid_mid_col_size).'px;'], 'Geburtstag:');
			td(['style'=>'padding: 0px; text-align: right; width: '.$kid_right_col_size.'px;']);
			if (!empty($row['kid_id']))
				out($delete_kid);
			_td();
		_tr();
		tr();
			td(['style'=>'padding: 3px 3px 3px 8px; width: '.$kid_left_col_size.'px'], $kid_fullname);
			td(['colspan'=>2, 'style'=>'padding: 3px 7px 3px 3px;'], $kid_birthday);
		_tr();
		tr();
		td(['colspan'=>3, 'style'=>'padding: 2px 8px 0px 8px;']);
			table(['style'=>'border: none; width: 100%;', 'class'=>'schedule-table']);
			tr();
			for ($p=0; $p<PERIOD_COUNT; $p++) {
				th(['style'=>'font-size: 14px; padding: 0px 0px 4px 0px; width: 20%;'.
					($p < $current_period ? 'color: grey;' : 'color: inherit;')]);
				label(['for'=>'present_'.$kid_i.'_'.$p]);
				out(str_replace(' ', '<br>', $period_names[$p]));
				_label();
				_th();
			}
			_tr();
			tr();
			for ($p=0; $p<PERIOD_COUNT; $p++) {
				td(['style'=>'padding: 3px 0px 0px 0px; width: 20%; '.
					($p < $current_period ? 'color: grey;' : 'color: inherit;')]);
				$present = checkbox('present_'.$kid_i.'_'.$p, bit_set($row['kid_present_periods'], $p), ['class'=>'img-checkbox',
					'before'=>bit_set($row['kid_present_periods'], $p), 'onchange'=>'toggle_submit_kid('.$kid_i.', '.PERIOD_COUNT.');']);
				if ($p < $current_period)
					$present->disable();
				out($present);
				label(['for'=>'present_'.$kid_i.'_'.$p]);
				nbsp(); nbsp(); nbsp(); nbsp(); nbsp();
				img(['src'=>'../img/box.png', 'style'=>'border: 1px solid '.($p < $current_period ? 'grey' : 'black').'; background-color: white; height: 16px; width: 16px;']);
				nbsp(); nbsp(); nbsp(); nbsp(); nbsp();
				_label();
				_td();
			}
			_tr();
			_table();
		_td();
		_tr();
		tr();
			th(['colspan'=>3, 'style'=>'font-size: 14px; padding: 0px 8px 3px 8px; text-align: left;'], 'Allergien und andere Besonderheiten des Kindes:');
		_tr();
		tr();
			td(['style'=>'padding: 3px 8px 3px 8px;' ], $kid_notes);
			td(['colspan'=>2, 'style'=>'padding: 0px 8px 8px 3px; text-align:right; vertical-align: bottom;']);
			if (empty($row['kid_id'])) {
				$submit_kid->setAttribute('class', 'button-box button-button-box button-blue');
				$submit_kid->setTitle('Anmelden');
			}
			else {
				$submit_kid->setAttribute('class', 'button-box button-green');
				$submit_kid->setTitle('Sichern');
				//td(['style'=>'padding: 3px 0px 0px 0px; text-align: left;']);
				//out($delete_kid);
				//_td();
			}
			out($submit_kid);
			_td();
		_tr();
		_table();
		out($kid_nr);
		out($kid_id);
		$kid_checkin_form->close();
	}
}			
