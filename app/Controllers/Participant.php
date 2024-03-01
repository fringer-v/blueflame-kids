<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class ParticipantTable extends Table {
	private function getOrder($col) {
		if (str_contains($this->order_by, $col)) {
			if (str_endswith($this->order_by, ' DESC'))
				return '&uarr;';
			return '&darr;';
		}
		return '';
	}

	public function columnTitle($field) {
		switch ($field) {
			case 'kid_number':
				return $this->getOrder('kid_number').'Nr.';
			case 'kid_name':
				return $this->getOrder('kid_fullname').'Name';
			case 'kid_birthday':
				return 'Geburtstag';
			case 'age':
				return $this->getOrder('kid_birthday').'Alter';
			case 'kid_group_number':
				return 'Gruppe';
			case 'kid_call_status':
				return $this->getOrder('kid_registered').'Status';
			case 'button_column':
				return '&nbsp;';
		}
		return nix();
	}

	public function columnAttributes($field) {
		switch ($field) {
			case 'kid_birthday':
			case 'age':
			case 'kid_call_status':
			case 'kid_group_number':
				return [ 'style'=>'text-align: center;' ];
		}
		return null;
	}

	public function cellValue($field, $row) {
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];

		switch ($field) {
			case 'kid_number':
			case 'kid_name':
				$value = $row[$field];
				return $value;
			case 'kid_birthday':
				if (empty($row['kid_birthday']))
					return '';
				$ts = \DateTime::createFromFormat('Y-m-d', $row['kid_birthday']);
				return $ts->format('d.m.Y');
			case 'age':
				return get_age($row['kid_birthday']);
			case 'kid_group_number':
				$age_level = $row['kid_age_level'];
				$group_nr = $row[$field];
				if (empty($group_nr))
					$group_box = div([ 'style'=>'height: 22px; font-size: 16px' ], nbsp());
				else {
					$ages = $age_level_from[$age_level].' - '.$age_level_to[$age_level];
					$group_box = span([ 'class'=>'group-s g-'.$age_level ],
						span(['class'=>'group-number-s'], $group_nr), " ".$ages);
				}
				return $group_box;
			case 'kid_call_status': {
				if ($row['kid_registered'] == REG_BEING_FETCHED)
					return div(array('class'=>'yellow-box in-col'), 'Wird Abg.');
				if (!is_empty($row['kid_wc_time'])) {
					$out = div(array('class'=>'white-box in-col'), 'WC '.how_long_ago($row['kid_wc_time']));
					return $out;
				}
				$call_status = $row['kid_call_status'];
				if (is_empty($call_status))
					return nbsp();
				if ($call_status == CALL_CANCELLED)
					return div(array('class'=>'red-box in-col'), 'Ruf Aufh.');
				if ($call_status == CALL_COMPLETED)
					return div(array('class'=>'green-box in-col'), 'Ruf Been.');
				$tag = 'Ruf';
				$box = 'blue-box';
				if (arr_nvl($row, 'kid_call_escalation', 0) > 0) {
					$tag = 'Esk';
					$box = 'red-box';
				}
				if ($call_status == CALL_CALLED) {
					$tag = 'Ger';
					$box = 'green-box';
				}
				return div(array('class'=>$box.' in-col'), $tag.' '.how_long_ago($row['kid_call_start_time']));
			}
/*
			case 'kid_registered':
				if ($row[$field] == REG_YES) {
					if (is_empty($row['kid_wc_time']))
						return div(array('class'=>'green-box', 'style'=>'width: 56px; height: 22px;'), 'Ja');
					$out = div(array('class'=>'white-box', 'style'=>'width: 25px; height: 22px; font-size: 12px;'), 'WC');
					$out->add(" ");
					$out->add(div(array('class'=>'green-box', 'style'=>'width: 25px; height: 22px;'), 'Ja'));
					return $out;
				}
				if ($row[$field] == REG_BEING_FETCHED) {
					return div(array('class'=>'yellow-box', 'style'=>'width: 56px; height: 22px;'), 'Abg.');
				}
				return div(array('class'=>'red-box', 'style'=>'width: 56px; height: 22px;'), 'Nein');
*/
			case 'button_column':
				return a([ 'class'=>'button-black',
					'style'=>'display: block; color: white; height: 26px; width: 32px; text-align: center; line-height: 26px; border-radius: 6px;',
					'onclick'=>'$("#set_kid_id").val('.$row['kid_id'].'); $("#display_kid").submit();' ], out('&rarr;'))->html();
				//return submit('select', 'Bearbeiten', array('class'=>'button-black', 'onclick'=>'$("#set_kid_id").val('.$row['kid_id'].');'))->html();
		}
		return nix();
	}
}

class HistoryTable extends Table {
	public function columnTitle($field) {
		switch ($field) {
			case 'hst_action':
				return 'Art';
			case 'hst_timestamp':
				return 'Zeit';
			case 'stf_username':
				return 'Mitarb.';
			case 'hst_notes':
				return 'Einzelheiten';
		}
		return nix();
	}

	public function cellValue($field, $row) {
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];

		switch ($field) {
			case 'hst_action':
				switch ($row[$field]) {
					case CREATED:
						return 'Registriert';
					case REGISTER:
						return 'Angemeldet';
					case UNREGISTER:
						return 'Abgemeldet';
					case CALL:
						return TEXT_PENDING;
					case CANCELLED:
						return TEXT_CANCELLED;
					case ESCALATE:
						return TEXT_ESCALATED.' ('.$row['hst_escalation'].')';
					case CALLED:
						return TEXT_CALLED;
					case CALL_ENDED:
						return TEXT_COMPLETED;
					case GO_TO_WC:
						return 'Zum WC gegangen';
					case BACK_FROM_WC:
						return 'Von WC zurück';
					case BEING_FETCHED:
						return 'Wird abgeholt';
					case CHANGED_GROUP:
						return 'Gruppe geändert';
					case FETCH_CANCELLED:
						return 'Abholen abgebrochen';
					case NAME_CHANGED:
						return 'Name geändert';
					case BIRTHDAY_CHANGED:
						return 'Geburtstag geändert';
					case SUPERVISOR_CHANGED:
						return 'Begleiter gewechselt';
					case CELLPHONE_CHANGED:
						return 'Handy geändert';
					case NOTES_CHANGED:
						return 'Hinweise geändert';
					case SUPERVISOR_SET:
						return 'Begleiter gesetzt';
				}
				return '';
			case 'hst_timestamp':
				$date = date_create_from_format('Y-m-d H:i:s', $row[$field]);
				$date->setTime(0, 0, 0);
				$today = new \DateTime();
				$today->setTime(0, 0, 0);
				$diff = $today->diff($date);
				$diff = (integer) $diff->format("%R%a");

				$date = date_create_from_format('Y-m-d H:i:s', $row[$field]);
				switch($diff) {
				    case 0:
						return 'Heute '.$date->format('H:i');
				    case -1:
						return 'Gestern '.$date->format('H:i');
				    case -2:
						return 'Vorgestern '.$date->format('H:i');
				}
				return $date->format('d.m.Y H:i');
			case 'stf_username':
				return htmlentities($row[$field]);
			case 'hst_notes':
				$group_nr = $row['hst_group_number'];
				if ($group_nr > 0) {
					$age_level = $row['hst_age_level'];
					if (empty($group_nr))
						$group_box = div([ 'style'=>'height: 22px; font-size: 16px' ], nbsp());
					else {
						$ages = $age_level_from[$age_level].' - '.$age_level_to[$age_level];
						$group_box = span([ 'class'=>'group-s g-'.$age_level ],
							span(['class'=>'group-number-s'], $group_nr), " ".$ages);
					}
					$note = $group_box;
				}
				else
					$note = '';
				$val = $row['hst_notes'];
				if (!empty($val)) {
					if (!empty($note))
						$note .= ' ';
					$note .= str_replace(' -&gt; ', ' &rarr; ', htmlentities($val));
				}
				return trim($note);
		}
		return nix();
	}
}

class Participant extends BF_Controller {
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$this->db_model = model('db_model');
	}

	public function index()
	{
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];
		$group_colors = $GLOBALS['group_colors'];

		if (!$this->authorize_staff())
			return '';

		$read_only = !is_empty($this->session->stf_login_tech);

		$kid_page = in('kid_page', 1);
		$kid_page->persistent();
		$hst_page = in('hst_page', 1);
		$hst_page->persistent();

		$display_kid = new Form('display_kid', 'participant', 1, [ 'class'=>'input-table' ]);
		$set_kid_id = $display_kid->addHidden('set_kid_id');
		$kid_filter = $display_kid->addTextInput('kid_filter', '', '', [ 'placeholder'=>'Suchfilter' ]);
		$kid_filter->persistent();
		$clear_filter = $display_kid->addSubmit('clear_filter', 'X',
			[ 'class'=>'button-black', 'onclick'=>'$("#kid_filter").val(""); kids_list(); return false;' ]);
		list($current_period, $nr_of_groups, $group_limits) = $this->get_group_data(-1);
		$group_options = [ '#'=>'' ];
		for ($a=0; $a<AGE_LEVEL_COUNT; $a++) {
			$group_nr = arr_nvl($nr_of_groups, $a, 0);
			for ($i=1; $i<=$group_nr; $i++) {
				$group_options[substr($group_colors[$a], 0, 1).$i] = $group_colors[$a].' '.$i;
			}
		}
		$select_group = $display_kid->addSelect('select_group', '', $group_options, '#', [ 'onchange'=>'get_group_parts(); return false;' ]);

		$update_kid = new Form('update_kid', 'participant', 2, [ 'class'=>'input-table', 'style'=>'width: 100%;' ]);
		if ($read_only)
			$update_kid->disable();
		$kid_id = $update_kid->addHidden('kid_id');
		$kid_id->persistent();
		$kid_id_v = $kid_id->getValue();

		if ($set_kid_id->submitted()) {
			$kid_id->setValue($set_kid_id->getValue());
			$hst_page->setValue(1);
			return redirect("participant");
		}

		$kid_row = $this->get_kid_row($kid_id_v);

		$co_kid_list = [];
		if (!empty($kid_id_v)) {
			$co_kid_list = db_array_2('SELECT kid_id, kid_fullname as kid_name FROM bf_kids WHERE '.
				'kid_id != ? AND kid_parent_id = ? ORDER BY kid_birthday DESC, kid_name',
				[ $kid_id_v, $kid_row['kid_parent_id'] ]);
		}

		// DECLARE: An u. Abmeldung -----------------
		$register_data = $update_kid->addRow('');
		$group_list = new AsyncLoader('register_group_list', 'participant/getgroups?tab=register', [ 'grp_arg'=>'""', 'action'=>'""' ] );
		$update_kid->addRow($group_list->html());

		$parent = table(['style'=>'width: 100%; background-color: lightgrey;']);
		$parent->add(tr(
			th([ 'style'=>'padding: 5px;' ], 'Begleitperson:'), td([ 'style'=>'padding: 5px;' ], $kid_row['par_fullname']),
			th([ 'style'=>'padding: 5px;' ], 'Kids-ID:'), td([ 'style'=>'padding: 5px;' ], $kid_row['par_code'])));
		$parent->add(tr(
			th([ 'style'=>'padding: 5px;' ], 'Handy-Nr:'), td([ 'style'=>'padding: 5px;' ], $kid_row['par_cellphone']),
			th([ 'style'=>'padding: 5px;' ], 'E-Mail:'), td([ 'style'=>'padding: 5px;' ], $kid_row['par_email'])));
		if (!empty($co_kid_list)) {
			$parent->add(tr(
				th([ 'style'=>'padding: 5px;' ], a([ 'onclick'=>'get_parent_parts();' ], 'Mit Registriert:')), 
				td([ 'style'=>'padding: 5px;', 'colspan'=>'3' ], $this->link_list('participant?set_kid_id=', $co_kid_list))
				));
			/*
			$co_reg = b();
			$co_reg->add(a([ 'onclick'=>'get_parent_parts();' ], 'Mit Registriert'));
			$co_reg->add(': ');
			$co_reg->add(_b());
			$co_reg->add($this->link_list('participant?set_kid_id=', $co_kid_list));
			$f1 = $update_kid->addField('', $co_reg);
			$f1->setFormat([ 'nolabel'=>true, 'colspan'=>'*', 'style'=>'white-space: normal; max-width: 600px;' ]);
			*/
		}
		$parent->add(_table());
		$parent_cell = $update_kid->addRow($parent);
		$parent_cell->setFormat([ 'nolabel'=>true, 'colspan'=>'*', 'style'=>'padding: 5px 5px;' ]);

		$go_to_wc = $update_kid->addSubmit('go_to_wc', 'WC', [ 'class'=>'button-white wc' ]);
		$back_from_wc = $update_kid->addSubmit('back_from_wc', 'WC', [ 'class'=>'button-white wc strike-thru' ]);
		$being_fetched = $update_kid->addSubmit('being_fetched', 'Wird Abgeholt', [ 'class'=>'button-yellow register' ]);
		$cancel_fetch = $update_kid->addSubmit('cancel_fetch', 'Abholen Abbrechen', [ 'class'=>'button-yellow register' ]);
		$unregister = $update_kid->addSubmit('unregister', 'Abmelden', [ 'class'=>'button-red register' ]);
		$register = $update_kid->addSubmit('register', 'Anmelden', [ 'class'=>'button-green register' ]);

		$update_kid->createGroup('tab_register');

		// DECLARE: Registrierung u. Ändern ---------
		$number1 = $update_kid->addField('Kid-Nr');
		$number1->setFormat([ 'colspan'=>'2' ]);
		$kid_fullname = $update_kid->addTextInput('kid_fullname', 'Name',
			$kid_row['kid_fullname'], [ 'placeholder'=>'Name', 'onkeyup'=>'capitalize($(this));', 'style'=>'width: 500px' ]);
		$kid_fullname->setRule('required');
		$kid_fullname->setFormat([ 'colspan'=>'2' ]);
		$kid_birthday = $update_kid->addTextInput('kid_birthday', 'Geburtstag',
			$kid_row['kid_birthday'], [ 'placeholder'=>'DD.MM.JJJJ' ]);
		$kid_birthday->setRule('is_valid_date');
		$kid_parent_id = $update_kid->addHidden('kid_parent_id', $kid_row['kid_parent_id']);
		$age_field = $update_kid->addSpace();

		$group_list = new AsyncLoader('modify_group_list', 'participant/getgroups?tab=modify', [ 'grp_arg'=>'""', 'action'=>'""' ] );
		$update_kid->addRow($group_list->html());

		$kid_notes = $update_kid->addTextArea('kid_notes', 'Hinweise', $kid_row['kid_notes'],
			[ 'style'=>'height: 24px;' ]);
		$kid_notes->setFormat([ 'colspan'=>'2' ]);

		$parent_code = new TextInput('kid_parent_code', $kid_row['par_code'], [ 'style'=>'width: 120px',
			'onkeyup'=>'parent_code_changed($(this), $(\'#kid_parent_id\'), $(\'#kid_parent_name\'), $(\'#kid_parent_cellphone\'), $(\'#kid_parent_email\'));' ]);
		$parent_code->setForm($update_kid);
		$parent = table(['style'=>'width: 100%; background-color: lightgrey;']);
		$parent->add(tr(
			th([ 'style'=>'padding: 5px;' ], 'Begleitperson:'), td([ 'id'=>'kid_parent_name', 'style'=>'padding: 5px;' ], $kid_row['par_fullname']),
			th([ 'style'=>'padding: 5px;' ], 'Kids-ID:'), td([ 'style'=>'padding: 5px;' ], $parent_code)));
		$parent->add(tr(
			th([ 'style'=>'padding: 5px;' ], 'Handy-Nr:'), td([ 'id'=>'kid_parent_cellphone', 'style'=>'padding: 5px;' ], $kid_row['par_cellphone']),
			th([ 'style'=>'padding: 5px;' ], 'E-Mail:'), td([ 'id'=>'kid_parent_email', 'style'=>'padding: 5px;' ], $kid_row['par_email'])));
		$parent->add(_table());
		$parent_cell = $update_kid->addRow($parent);
		$parent_cell->setFormat([ 'nolabel'=>true, 'colspan'=>'*', 'style'=>'padding: 5px 5px;' ]);

		$save_kid = $update_kid->addSubmit('save_kid', 'Änderung Sichern', [ 'class'=>'button-green' ]);
		$new_kid = $update_kid->addSubmit('new_kid', 'Kind Registrieren', [ 'class'=>'button-green' ]);
		$clear_nr_name = $update_kid->addSubmit('clear_no_name', 'Geschwister Aufnehmen...', [ 'class'=>'button-blue' ]); // DEFUKT
		$clear_kid = $update_kid->addSubmit('clear_kid', '', [ 'class'=>'button-black' ]);

		$update_kid->createGroup('tab_modify');

		// DECLARE: Eltern Ruf ----------------------
		$number3 = $update_kid->addField('Kid-Nr');
		$number3->setFormat([ 'colspan'=>'2' ]);
		$update_kid->addField('Name', $kid_row['kid_fullname']);
		$update_kid->addSpace();
		$update_kid->addField('Geburtstag', $kid_row['kid_birthday']);
		$call_kid_age = $update_kid->addSpace();

		$parent = table(['style'=>'width: 100%; background-color: lightgrey;']);
		$parent->add(tr(
			th([ 'style'=>'padding: 5px;' ], 'Begleitperson:'), td([ 'style'=>'padding: 5px;' ], $kid_row['par_fullname']),
			th([ 'style'=>'padding: 5px;' ], 'Kids-ID:'), td([ 'style'=>'padding: 5px;' ], $kid_row['par_code'])));
		$parent->add(tr(
			th([ 'style'=>'padding: 5px;' ], 'Handy-Nr:'), td([ 'style'=>'padding: 5px;' ], $kid_row['par_cellphone']),
			th([ 'style'=>'padding: 5px;' ], 'E-Mail:'), td([ 'style'=>'padding: 5px;' ], $kid_row['par_email'])));
		$parent->add(_table());
		$parent_cell = $update_kid->addRow($parent);
		$parent_cell->setFormat([ 'nolabel'=>true, 'colspan'=>'*', 'style'=>'padding: 5px 5px;' ]);

		$parent_comment = $update_kid->addTextInput('parent_comment', 'Kommentar', '', [ 'style'=>'width: 494px;' ]);
		$parent_comment->setFormat([ 'colspan'=>'2' ]);

		$escallate = $update_kid->addSubmit('escallate', 'Eskalieren', [ 'class'=>'button-blue' ]);
		$call_super = $update_kid->addSubmit('call_super', 'Ruf Eltern', [ 'class'=>'button-blue' ]);
		if ($kid_row['kid_registered'] == REG_NO)
			$call_super->disable();
		$cancel_super = $update_kid->addSubmit('cancel_super', 'Ruf Aufheben ', [ 'class'=>'button-red' ]);

		$update_kid->createGroup('tab_parent');
		// ---------------------------------

		if ($clear_kid->submitted()) {
			$kid_id->setValue(0);
			return redirect("participant");
		}

		if ($clear_nr_name->submitted()) {
			$kid_row['kid_id'] = '';
			$kid_row['kid_number'] = '';
			$kid_row['kid_fullname'] = '';
			$kid_row['kid_birthday'] = null;
			$kid_id->setValue('');
			$kid_fullname->setValue('');
			$kid_birthday->setValue('');
			$kid_id_v = 0;
		}

		if ($new_kid->submitted() || $save_kid->submitted()) {
			$this->error = $update_kid->validate('tab_modify');
			if (is_empty($this->error)) {
				$staff_row = $this->get_staff_row($this->session->stf_login_id);
				$group_reserved = if_empty($staff_row['stf_reserved_count'], 0) > 0;

				$data = [
					'kid_fullname' => $kid_fullname->getValue(),
					'kid_birthday' => $kid_birthday->getDate('d.m.Y'),
					'kid_parent_id' => $kid_parent_id->getValue(),
					'kid_notes' => $kid_notes->getValue()
				];
				if ($group_reserved) {
					$data['kid_age_level'] = $staff_row['stf_reserved_age_level'];
					$data['kid_group_number'] = $staff_row['stf_reserved_group_number'];
					if ((integer) $kid_row['kid_registered'] != REG_BEING_FETCHED)
						$data['kid_registered'] = REG_YES;
				}

				if (is_empty($kid_id_v)) {
					$this->set_error($this->insert_kid($data, $group_reserved,
						$this->session->stf_login_id, '', $kid_id_v));
					if (!$this->have_error()) {
							$kid_filter->setValue('');
							$kid_page->setValue(1);
							$kid_id->setValue($kid_id_v);
							$this->set_success($kid_fullname->getValue().' aufgenommen');
							return redirect("participant");
					}
				}
				else {
					$this->set_error($this->modify_kid($kid_id_v, $kid_row, $data, $group_reserved,
						[ 'par_code' => $kid_row['par_code'], 'par_fullname' => $kid_row['par_fullname'] ], $this->session->stf_login_id));
					if (!$this->have_error()) {
						$this->set_success($kid_fullname->getValue().' geändert');
						return redirect("participant");
					}
				}
			}
		}

		if (!is_empty($kid_id_v)) {
			if ($register->submitted() || $unregister->submitted() ||
				$being_fetched->submitted() || $cancel_fetch->submitted()) {
				
				$data = [ ];
				$history = [
					'hst_kid_id'=>$kid_id_v,
					'hst_stf_id'=> $this->session->stf_login_id ];

				$staff_row = $this->get_staff_row($this->session->stf_login_id);
				$group_reserved = if_empty($staff_row['stf_reserved_count'], 0) > 0;
				
				if ($register->submitted()) {
					if (!$group_reserved)
						$this->error = 'Bitte wählen sie eine Gruppe aus';
					else if ($kid_row['kid_registered'] == REG_NO) {
						$data['kid_age_level'] = $staff_row['stf_reserved_age_level'];
						$data['kid_group_number'] = $staff_row['stf_reserved_group_number'];
						$data['kid_registered'] = REG_YES;
						$history['hst_action'] = REGISTER;
						$history['hst_age_level'] = $staff_row['stf_reserved_age_level'];
						$history['hst_group_number'] = $staff_row['stf_reserved_group_number'];
						$comment = 'angemeldet';
					}
				}
				else if ($unregister->submitted()) {
					if ($kid_row['kid_registered'] == REG_YES ||
						$kid_row['kid_registered'] == REG_BEING_FETCHED) {
						$data['kid_age_level'] = null;
						$data['kid_group_number'] = null;
						$data['kid_registered'] = REG_NO;
						$history['hst_action'] = UNREGISTER;
						//$history['hst_age_level'] = $staff_row['stf_reserved_age_level'];
						//$history['hst_group_number'] = $staff_row['stf_reserved_group_number'];
						$comment = 'abgemeldet';
					}
				}
				else if ($being_fetched->submitted()) {
					if ($kid_row['kid_registered'] == REG_YES) {
						$data['kid_registered'] = REG_BEING_FETCHED;
						$history['hst_action'] = BEING_FETCHED;
						$comment = 'wird abgeholt';
					}
				}
				else if ($cancel_fetch->submitted()) {
					if ($kid_row['kid_registered'] == REG_BEING_FETCHED) {
						$data['kid_registered'] = REG_YES;
						$history['hst_action'] = FETCH_CANCELLED;
						$comment = 'erneut angemeldet';
					}
				}

				if (!empty($data)) {
					$builder = $this->db->table('bf_kids');
					if (!is_empty($kid_row['kid_call_status'])) {
						// Cancel the call!
						$builder = $this->db->table('bf_history');
						$builder->insert(array(
							'hst_kid_id'=>$kid_id_v,
							'hst_stf_id'=>$this->session->stf_login_id,
							'hst_action'=>CANCELLED,
							'hst_escalation'=>0));

						$builder->set('kid_call_status', CALL_NOCALL);
						$builder->set('kid_call_escalation', 0);
						$builder->set('kid_call_start_time', 'NOW()', false);
					}
					if (!is_empty($kid_row['kid_wc_time']))
						$builder->set('kid_wc_time', null);

					$builder->set('kid_modifytime', 'NOW()', false);
					$builder->where('kid_id', $kid_id_v);
					$builder->update($data);

//... add current group to all history...
					$builder = $this->db->table('bf_history');
					$builder->insert($history);
					if ($group_reserved && $history['hst_action'] == REGISTER)
						$this->unreserve_group($staff_row['stf_reserved_age_level'], $staff_row['stf_reserved_group_number']);

					$this->set_success($kid_fullname->getValue().' '.$comment);
					return redirect("participant");
				}
			}

			$call_status = $kid_row['kid_call_status'];
			if ($call_super->submitted() || $cancel_super->submitted()) {
				$sql = 'UPDATE bf_kids SET kid_call_escalation = 0, ';
				if (is_empty($call_status) || $call_status == CALL_CANCELLED || $call_status == CALL_COMPLETED) {
					$action = CALL;
					$sql .= 'kid_call_status = '.CALL_PENDING.', ';
					$sql .= 'kid_call_start_time = NOW(), ';
					$sql .= 'kid_call_change_time = NOW() ';
					$msg = 'gerufen';
				}
				else {
					if ($call_status == CALL_PENDING) {
						$action = CANCELLED;
						$sql .= 'kid_call_status = '.CALL_CANCELLED.', ';
					}
					else { // CALL_CALLED
						$action = CALL_ENDED;
						$sql .= 'kid_call_status = '.CALL_COMPLETED.', ';
					}
					$sql .= 'kid_call_change_time = NOW() ';
					$msg = 'ruf aufgehoben';
				}
				$sql .= 'WHERE kid_id = ?';
				$this->db->query($sql, [ $kid_id_v ]);

				$builder = $this->db->table('bf_history');
				$builder->insert([
					'hst_kid_id'=>$kid_id_v,
					'hst_stf_id'=>$this->session->stf_login_id,
					'hst_action'=>$action,
					'hst_escalation'=>0,
					'hst_notes'=>$parent_comment->getValue()]);

				$this->set_success($kid_row['par_fullname'].' '.$msg);
				$parent_comment->setValue('');
				return redirect("participant");
			}
			
			if ($escallate->submitted()) {
				$call_esc = $kid_row['kid_call_escalation']+1;
				if (!is_empty($call_status) && $call_status != CALL_CANCELLED && $call_status != CALL_COMPLETED) {
					$sql = 'UPDATE bf_kids SET
						kid_call_status = '.CALL_PENDING.',
						kid_call_escalation = ?,
						kid_call_change_time = NOW()
					WHERE kid_id = ?'; 
					$this->db->query($sql, array($call_esc, $kid_id_v));

					$builder = $this->db->table('bf_history');
					$builder->insert(array(
						'hst_kid_id'=>$kid_id_v,
						'hst_stf_id'=>$this->session->stf_login_id,
						'hst_action'=>ESCALATE,
						'hst_escalation'=>$call_esc,
						'hst_notes'=>$parent_comment->getValue()));

					$this->set_success($kid_row['par_fullname'].' ruf eskaliert');
					$parent_comment->setValue('');
					return redirect("participant");
				}
			}
			
			if ($go_to_wc->submitted() || $back_from_wc->submitted()) {
				if (is_empty($kid_row['kid_wc_time'])) {
					$action = GO_TO_WC;
					$msg = 'ging nach WC';
					$sql = 'UPDATE bf_kids SET kid_wc_time = NOW() WHERE kid_id = ?';
				}
				else {
					$action = BACK_FROM_WC;
					$msg = 'zurück von WC';
					$sql = 'UPDATE bf_kids SET kid_wc_time = NULL WHERE kid_id = ?';
				}
				$this->db->query($sql, array($kid_id_v));

				$builder = $this->db->table('bf_history');
				$builder->insert([
					'hst_kid_id'=>$kid_id_v,
					'hst_stf_id'=>$this->session->stf_login_id,
					'hst_action'=>$action]);

				$this->set_success($kid_row['par_fullname'].' '.$msg);
				return redirect("participant");
			}
		}

		if (empty($kid_id_v)) {
			$clear_kid->setValue('Clear');
			$clear_nr_name->hide();
			$save_kid->hide();
			$go_to_wc->hide();
			$back_from_wc->hide();
			$being_fetched->hide();
			$cancel_fetch->hide();
			$unregister->hide();
			$register->hide();
			$cancel_super->hide();
			$call_super->hide();
			$escallate->hide();
			$reg_field = '';
			$call_field = '';
			$age_field->setValue(div(array('id' => 'kid_age'), ''));
			$curr_age = null;
		}
		else {
			$clear_kid->setValue('Kind Registrieren...');
			// Not using this button, so always hidden!
			$clear_nr_name->hide();

			$new_kid->hide();
			if ($kid_row['kid_registered'] == REG_YES) {
				if (is_empty($kid_row['kid_wc_time'])) {
					$reg_field = out("");
					$back_from_wc->hide();
				}
				else {
					$reg_field = div(array('class'=>'white-box'), 'WC '.how_long_ago($kid_row['kid_wc_time']));
					$reg_field->add(" ");
					$go_to_wc->hide();
				}
				$reg_field->add(div(array('class'=>'green-box'), 'Angemeldet'));
				$cancel_fetch->hide();
				$register->hide();
			}
			else if ($kid_row['kid_registered'] == REG_BEING_FETCHED) {
				$reg_field = div(array('class'=>'yellow-box'), 'Wird abgeholt');
				$go_to_wc->hide();
				$back_from_wc->hide();
				$being_fetched->hide();
				$register->hide();
			}
			else {
				$reg_field = div(array('class'=>'red-box'), 'Abgemeldet');
				$go_to_wc->hide();
				$back_from_wc->hide();
				$being_fetched->hide();
				$cancel_fetch->hide();
				$unregister->hide();
			}

			$call_status = $kid_row['kid_call_status'];
			if (is_empty($call_status) || $call_status == CALL_CANCELLED || $call_status == CALL_COMPLETED) {
				if ($call_status == CALL_CANCELLED)
					$call_field = div(array('class'=>'red-box'), TEXT_CANCELLED);
				else if ($call_status == CALL_COMPLETED)
					$call_field = div(array('class'=>'green-box'), TEXT_COMPLETED);
				else
					$call_field = '';
				$escallate->hide();
				$cancel_super->hide();
			}
			else {
				$str = how_long_ago($kid_row['kid_call_start_time']);
				if (!is_empty($kid_row['kid_call_escalation']))
					$str .= ' ('.$kid_row['kid_call_escalation'].')';
				if ($kid_row['kid_call_status'] == CALL_CALLED) {
					$cancel_super->setValue('Ruf Beenden');
					$call_field = div(array('class'=>'green-box', 'style'=>'width: 210px;'), TEXT_CALLED.': '.$str);
				}
				else {
					$cancel_super->setValue('Ruf Aufheben');
					if (!is_empty($kid_row['kid_call_escalation']))
						$call_field = div(array('class'=>'red-box', 'style'=>'width: 210px;'), TEXT_ESCALATED.': '.$str);
					else
						$call_field = div(array('class'=>'blue-box', 'style'=>'width: 210px;'), TEXT_PENDING.': '.$str);
				}
				$call_super->hide();
			}

			$history_list_loader = new AsyncLoader('history_list', 'participant/gethistory');

			$curr_age = get_age($kid_birthday->getDate());
			$age_field->setValue(div(array('id' => 'kid_age'), is_null($curr_age) ? '&nbsp;-' : b(nbsp().$curr_age." Jahre")));

			$call_kid_age->setValue($curr_age." Jahre");
		}

		$numbers = b($kid_row['kid_number']);
		if (!empty($kid_row['kid_reg_num']))
			$numbers .= ' (AN-'.$kid_row['kid_reg_num'].')';
		$status_line = table([ 'width'=>'100%' ],
			tr(td($numbers),
			td([ 'align'=>'center' ], $call_field),
			td([ 'align'=>'right', 'style'=>'white-space: nowrap;' ], $reg_field)));
		$number1->setValue($status_line);
		$number3->setValue($status_line);

		// -- DRAW: An u. Abmeldung -----------------
		$bday = '';
		$unreg_group = ''; // Info for Abholkarte
		$card_color = 'bg-white';
		if (!is_null($curr_age)) {
			$attr = [ 'class'=>'group-s',
				'style'=>'width: 32px; min-width: 32px; height: 28px; font-size: 24px; text-align: center;' ];
			if ($kid_row['kid_registered'] == REG_NO || empty($kid_row['kid_group_number'])) {
				$attr['class'] .= ' g-'.$this->get_age_level($curr_age);
			}
			else {
				$attr['style'] .= ' background-color: lightgrey; color: white;';
				//$unreg_group = span([ 'class'=>'group-s',
				//	'style'=>'width: 40px; min-width: 40px; height: 28px; font-size: 24px; text-align: center; color: black;' ],
				//	substr($group_colors[$kid_row['kid_age_level']], 0, 1).$kid_row['kid_group_number']);
				$unreg_group = span([ 'class'=>'group-number-l g-'.$kid_row['kid_age_level'] ], $kid_row['kid_group_number']);
				$card_color = 'g---'.$kid_row['kid_age_level'];
			}
			$bday = span($attr, $curr_age);
			$bday .= ' '.$kid_row['kid_birthday'];
		}
		$child_data = table([ 'width'=>'100%', 'class' => $card_color, 'style'=>
			'border: 1px solid black; font-weight: bold;' ]);
		$numbers = '';
		if (!empty($kid_row['kid_reg_num']))
			$numbers = 'AN-'.$kid_row['kid_reg_num'];
		$child_data->add(tr());
		$child_data->add(td([ 'style'=>'padding: 5px 5px 5px 10px; font-size: 20px; '],
			'# '.$kid_row['kid_number']));
		$child_data->add(td([ 'style'=>'text-align: right; padding: 5px 10px 5px 10px; font-size: 16px;'], $numbers));
		$child_data->add(_tr());

		$child_data->add(tr(td([ 'colspan'=>2, 'style'=>'padding: 5px 5px 5px 10px; font-size: 20px; '],
			$kid_row['kid_fullname'])));
		$child_data->add(tr());
		$child_data->add(td([ 'style'=>'padding: 5px 5px 5px 10px; font-size: 18px;'], $bday));
		$child_data->add(td([ 'align'=>'right', 'style'=>'padding-right: 10px; ' ], $unreg_group));
		$child_data->add(_tr());
		$child_data->add(_table());

		$status_line = table([ 'width'=>'100%' ],
			td([ 'align'=>'left', 'style'=>'white-space: nowrap; padding: 0px;' ], $call_field),
			td([ 'align'=>'right', 'style'=>'white-space: nowrap; padding: 0px;' ], $reg_field));

		$reg_data = table([ 'width'=>'100%' ]);
		$reg_data->add(tr());
		$reg_data->add(td([ 'rowspan'=>3, 'valign'=>'top', 'style'=>'width: 40%;' ], $child_data));
		$reg_data->add(td([ 'colspan'=>2, 'valign'=>'top', 'style'=>'padding-left: 10px;' ], $status_line));
		$reg_data->add(_tr());
		if (!empty($kid_row['kid_notes'])) {
			$f1 = $update_kid->addField('', $kid_row['kid_notes']);
			$f1->setFormat([ 'nolabel'=>true, 'colspan'=>'*', 'style'=>'white-space: pre-wrap; max-width: 600px; font-size: 14px;' ]);
			$reg_data->add(tr(td([ 'rowspan'=>'2', 'colspan'=>'2', 'valign'=>'top', 
				'style'=>'padding-left: 10px; white-space: pre-wrap;  font-size: 14px;' ],
				b('Hinweise: ').$kid_row['kid_notes'])));
		}
		$reg_data->add(_table());
		$register_data->setValue($reg_data);

		//$parent = $kid_row['par_fullname'];
		//$tel = $kid_row['par_cellphone'];
		//$perc = empty($call_field) ? ((strlen($parent) > 25 || strlen($tel) > 25) ? 40 : 48) : 40;
		//$reg_data->add(tr(th(['align'=>'right' ], 'Begleitperson:'),
		//	td(['style'=>'padding-left: 10px; white-space: normal;' ], $parent)));
		//$reg_data->add(tr(th(['align'=>'right' ], 'Handy-Nr:'),
		//	td(['style'=>'padding-left: 10px; white-space: normal;' ], $tel)));

		$kids_list_loader = new AsyncLoader('kids_list', 'participant/getkids', [ 'kid_filter' ]);

		$kid_tab = in('kid_tab', 'register');
		$kid_tab->persistent();

		// Generate page ------------------------------------------
		$this->header('Kinder');

		table([ 'style'=>'border-collapse: collapse;' ]);
		tr();

		td(array('class'=>'left-panel', 'style'=>'width: 604px;', 'align'=>'left', 'valign'=>'top', 'rowspan'=>2));
			$display_kid->open();
			table([ 'class'=>'input-table' ]);
			tr(td(table(tr(td($kid_filter),
				td(nbsp()), td($clear_filter),
				td(nbsp().b('Gruppe:').nbsp()), td($select_group)))));
			tr(td($kids_list_loader->html()));
			_table(); // 
			$display_kid->close();
		_td();

		td([ 'align'=>'left', 'valign'=>'top', 'style'=>'height: 100px;' ]);
			table([ 'style'=>'border-collapse: collapse; margin-right: 5px;' ]);
			tbody();
			tr();

			td(array('width'=>'33.33%'), div($this->tabAttr($kid_tab, 'register', 'margin-left: 2px; margin-right: 2px;'), 'An u. Abmeldung'));
			td(array('width'=>'33.33%'), div($this->tabAttr($kid_tab, 'modify', 'margin-right: 2px;'), 'Registrierung u. Ändern'));
			td(array('width'=>'33.33%'), div($this->tabAttr($kid_tab, 'parent', 'margin-left: 2px;'), 'Eltern Ruf'));
			_tr();
			tr();
			td(array('colspan'=>3, 'align'=>'left'));
				$update_kid->open();
				table(array('style'=>'border-collapse: collapse; min-width:638px;'));
				tbody();
					tr();
					td(array('style'=>'border: 1px solid black; padding: 10px 5px;'));
					div($this->tabContentAttr($kid_tab, 'register'));
					$update_kid->show('tab_register');
					_div();
					div($this->tabContentAttr($kid_tab, 'modify'));
					$update_kid->show('tab_modify');
					_div();
					div($this->tabContentAttr($kid_tab, 'parent'));
					$update_kid->show('tab_parent');
					_div();
					_td();
					_tr();
				_tbody();
				_table();
				$update_kid->close();
			_td();
			_tr();
			tr();
			td([ 'colspan'=>3 ]);
			$this->print_result();
			_td();
			_tr();
			_tbody();
			_table();
		_td();
		_tr();
		if (isset($history_list_loader)) {
			tr(td([ 'align'=>'left', 'valign'=>'top' ], $history_list_loader->html()));
		}
		else {
			tr(td(nbsp()));
		}
		_table();

		script();
		out('
			function get_parent_parts() {
				var code = $("#kid_parent_code").val().trim();
				if (code.length > 0) {
					$("#kid_filter").val("@".code);
					kids_list();
				}
			}
		');
		out('
			function get_group_parts() {
				var grp = $("#select_group").val().trim();
				if (grp.length > 0 && grp != "#") {
					$("#kid_filter").val("#"+grp);
					$("#select_group").val("#");
					kids_list();
				}
			}
		');
		// Dummy function, because this tab does not have a load function:
		out('
			function parent_group_list() {
			}
		');
		out('
			function birthday_changed() {
				var new_value = dateChanged($("#kid_birthday"));
				var age = getAge(new_value);
				if (age < 0)
					$("#kid_age").html("&nbsp;-");
				else
					$("#kid_age").html("&nbsp;<b>"+age+" Jahre</b>");
			}
			$("#kid_birthday").keyup(birthday_changed);
		');
		out('
			function poll_groups() {
				var tab = "";
				if ($("#tab_content_modify").css("display") == "block")
					tab = "modify";
				else if ($("#tab_content_register").css("display") == "block")
					tab = "register";
				else
					return;
				var args = "tab="+tab+"&gpa="+$("#"+tab+"_groups_per_age").val()+"&cnt="+$("#history_count").val();
				$.getScript("participant/pollgroups?"+args);
			}
		');
		out('
			function parent_code_changed(text_input, kid_parent_id, kid_parent_name, kid_parent_cellphone, kid_parent_email) {
				var start = text_input.get(0).selectionStart;
				var end = text_input.get(0).selectionEnd;
				var value = text_input.val();
				var new_value = "";
				var ch;

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

				if (value != new_value) {
					text_input.val(new_value);
					text_input.get(0).setSelectionRange(start, end);
				}

				if (new_value.length == 4) {
					$.getJSON("participant/getparent?code="+new_value,
						function(data) {
							kid_parent_id.val(data["par_id"]);
							kid_parent_name.html(data["par_fullname"]);
							kid_parent_cellphone.html(data["par_cellphone"]);
							kid_parent_email.html(data["par_email"]);
						}
					);
				}
			}
		');
		out('window.setInterval(poll_groups, 5000);');
		_script();
		$this->footer();
		return '';
	}

	private function tabAttr($kid_tab, $tab, $style) {
		$attr = array('id'=>'tab_selector_'.$tab);
		if ($kid_tab->getValue() == $tab)
			$attr['class'] = 'participant-tabs active';
		else
			$attr['class'] = 'participant-tabs';
		$attr['onclick'] = 'showTab("'.$tab.'"); '.$tab.'_group_list();';
		$attr['style'] = $style;
		return $attr;
	}

	private function tabContentAttr($kid_tab, $tab) {
		$attr = array('id'=>'tab_content_'.$tab);
		if ($kid_tab->getValue() == $tab)
			$attr['style'] = 'display: block;';
		else
			$attr['style'] = 'display: none';
		return $attr;
	}

	public function getparent() {
		if ($this->authorize_staff())
			$par_code = in('code', '')->getValue();
		else
			$par_code = '';

		$parent_row = $this->get_parent_row_by_code($par_code);
		return json_encode($parent_row);
	}

	public function getkids() {
		$group_colors = $GLOBALS['group_colors'];

		if (!$this->authorize_staff())
			return '';

		$builder = $this->db->table('bf_kids');
		$builder->where('kid_call_status IN ('.CALL_CANCELLED.', '.CALL_COMPLETED.') AND ADDTIME(kid_call_change_time, "'.CALL_ENDED_DISPLAY_TIME.'") <= NOW()');
		$builder->update([ 'kid_call_status'=>CALL_NOCALL ]);

		$kid_filter = in('kid_filter');
		$kid_filter->persistent();
		$kid_page = in('kid_page', 1);
		$kid_page->persistent();
		$kid_tab = in('kid_tab', 'modify');
		$kid_tab->persistent();
		
		$kid_filter_v = trim($kid_filter->getValue());
		$qtype = 0;
		if (empty($kid_filter_v)) {
			$kid_filter_v = '%';
			$order_by = 'kid_modifytime DESC';
		}
		else {
			$order_by = 'kid_fullname';
			if (preg_match('/^[0-9]{1,2}\.([0-9]{1,2}(\.[0-9]{0,4})?)?$/', $kid_filter_v)) {
				$qtype = 1;
				$args = explode('.', $kid_filter_v);
				for ($i=sizeof($args)-1; $i>=0; $i--) {
					if (empty($args[$i]))
						array_pop($args);
					else
						$args[$i] = (integer) $args[$i];
				}
			}
			else if (preg_match('/^[A-Za-z]+ [A-Za-z]+$/', $kid_filter_v)) {
				$qtype = 2;
				$args = explode(' ', $kid_filter_v);
				$args[0] .= '%';
				$args[1] .= '%';
			}
			else if (str_startswith($kid_filter_v, '@')) {
				$qtype = 3;
				$kid_filter_v = '%'.str_right($kid_filter_v, '@').'%';
			}
			else if (str_startswith($kid_filter_v, '#')) {
				$qtype = 4;
				$kid_filter_v = str_right($kid_filter_v, '#');
			}
			else if (is_numeric($kid_filter_v)) {
				$qtype = 5; // kid_number
				if (substr($kid_filter_v, 0, 1) == '1')
					$qtype = 6; // kid_reg_num
				$kid_filter_v = $kid_filter_v.'%';
			}
			else
				$kid_filter_v = '%'.$kid_filter_v.'%';
		}
		if ($kid_tab->getValue() == 'parent')
			$order_by = 'calling,kid_registered DESC,kid_call_start_time DESC';

		$sql = 'SELECT SQL_CALC_FOUND_ROWS kid_id, kid_number, kid_fullname as kid_name, kid_call_escalation,
			kid_birthday, "age", kid_age_level, kid_group_number, kid_call_status, kid_registered, kid_wc_time, "button_column",
			IF(kid_call_status = '.CALL_PENDING.' OR kid_call_status = '.CALL_CALLED.', 0, 1) calling, kid_call_start_time
			FROM bf_kids LEFT OUTER JOIN bf_parents ON kid_parent_id = par_id WHERE ';
		if ($qtype == 1) {
			// Date
			$sql .= 'DAY(kid_birthday) = ? ';
			if (count($args) > 1)
				$sql .= 'AND MONTH(kid_birthday) = ? ';
			if (count($args) > 2) {
				if ((integer) $args[2])
					$args[2] = 2000 + (integer) $args[2];
				$sql .= 'AND YEAR(kid_birthday) = ? ';
			}
		}
		else if ($qtype == 2) {
			// First_Last
			$sql .= 'kid_fullname LIKE ?';
		}
		else if ($qtype == 3) {
			// Registered with, search parents:
			$sql .= 'CONCAT(par_code, " ", par_fullname, " ", par_email) LIKE ?';
			$args = [ $kid_filter_v ];

			$order_by = 'kid_birthday DESC, kid_name';
		}
		else if ($qtype == 4) {
			// Search group:
			$age_str = strtoupper(substr($kid_filter_v, 0, 1));
			$num_str = strtoupper(substr($kid_filter_v, 1));
			$a = -1;
			foreach ($group_colors as $age=>$color) {
				if ($age_str == substr($color, 0, 1)) {
					$a = $age;
					break;
				}
			}
			$i = -1;
			if (is_int_val($num_str))
				$i = (integer) $num_str;
			$sql .= 'kid_age_level = ? AND kid_group_number = ?';
			$args = [ $a, $i ];
		}
		else {
			if ($qtype == 5)
				$sql .= 'CONCAT(kid_number, "") LIKE ?';
			else if ($qtype == 6)
				$sql .= 'CONCAT(kid_reg_num, "") LIKE ?';
			else {
				$sql .= 'CONCAT(kid_number, "$", kid_fullname,
						IFNULL(par_code, ""), "$", IFNULL(par_fullname, ""), "$", IFNULL(par_email, "")) LIKE ?';
			}
			$args = [ $kid_filter_v ];
		}

		$kid_table = new ParticipantTable($sql, $args,
			array('class'=>'details-table participant-table', 'style'=>'width: 600px;'));
		$kid_table->setPagination('participant?kid_page=', 20, $kid_page);
		$kid_table->setOrderBy($order_by);

		table(array('style'=>'border-collapse: collapse;'));
		tr(td($kid_table->paginationHtml()));
		tr(td($kid_table->html()));
		_table();
		return '';
	}

	private function get_kid_group_data($kid_id_v)
	{
		list($current_period, $nr_of_groups,
			$total_limit, $total_count, $total_limits, $total_counts,
			$group_limits, $group_counts) = $this->get_period_data(-1);

		$reserve_counts = db_array_2('SELECT CONCAT(stf_reserved_age_level, "_", stf_reserved_group_number),
			SUM(stf_reserved_count)
			FROM bf_staff WHERE stf_reserved_count > 0 AND stf_reserved_group_number > 0
			GROUP BY stf_reserved_age_level, stf_reserved_group_number');
		foreach ($reserve_counts as $group=>$count) {
			$age = str_left($group, '_');
			$num = str_right($group, '_');
			if (arr_nvl($nr_of_groups, $age, 0) < $num)
				$nr_of_groups[$age] = $num;
		}
		
		$kid_row = $this->get_kid_row($kid_id_v);

		$staff_row = $this->get_staff_row($this->session->stf_login_id);

		return [ $current_period, $nr_of_groups,
			$total_limit, $total_count, $total_limits, $total_counts,
			$group_limits, $group_counts,
			$reserve_counts, $kid_row, $staff_row ];
	}

	public function getgroups() {
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];

		if (!$this->authorize_staff())
			return '';

		$read_only = !is_empty($this->session->stf_login_tech);

		$grp_arg = in('grp_arg');
		$grp_arg_v = $grp_arg->getValue();
		$kid_age_level = str_left($grp_arg_v, '_');
		$kid_group_number = str_right($grp_arg_v, '_');
	
		$action = in('action');
		$action_v = $action->getValue();

		if (!empty($action_v)) {
			switch ($action_v) {
				case 'reserve':
					$this->reserve_group($kid_age_level, $kid_group_number);
					break;
				case 'unreserve':
					$this->unreserve_groups($kid_age_level, $kid_group_number);
					break;
			}
		}

		$kid_id = in('kid_id');
		$kid_id->persistent();
		$kid_id_v = $kid_id->getValue();

		$tab = in('tab');
		$tab_v = $tab->getValue();

		list($current_period, $nr_of_groups,
			$total_limit, $total_count, $total_limits, $total_counts,
			$group_limits, $group_counts,
			$reserve_counts, $kid_row, $staff_row) = $this->get_kid_group_data($kid_id_v);
		table([ 'style'=>'border-spacing: 0;' ]);
		$groups_per_age = ''; 
		for ($a=0; $a<AGE_LEVEL_COUNT; $a++) {
			$group_nr = arr_nvl($nr_of_groups, $a, 0);
			$groups_per_age .= $a.'_'.$group_nr.':';
			if (empty($group_nr))
				continue;
			tr();
			th([ 'style'=>'padding: 0px 2px;', 'align'=>'right' ], $age_level_from[$a].' - '.$age_level_to[$a].':');
			for ($i=1; $i<=$group_nr; $i++) {
				td( [ 'style'=>'padding: 0px 2px;' ] );

				$my_reserve_count = if_empty($staff_row['stf_reserved_count'], 0);
				$reserve_onclick = $tab_v.'_group_list("'.$a.'_'.$i.'", "reserve");';
				if ($staff_row['stf_reserved_age_level'] == $a &&
					$staff_row['stf_reserved_group_number'] == $i &&
					$my_reserve_count > 0) {
					$table_onclick = '';
					$onclick = $tab_v.'_group_list("'.$a.'_'.$i.'", "unreserve");';
					$vis = '';
				}
				else {
					$table_onclick = $reserve_onclick;
					$onclick = '';
					$reserve_onclick = '';
					$vis = ' visibility: hidden;';
				}
				$reserve_box = span([ 'class'=>'group-number',
					'style'=>'background-color: white; border-radius: 0px; width: 18px;'.$vis ],
					$staff_row['stf_reserved_count']);

				$enable_groups = false;
				if ($tab_v == 'register') {
					if ($kid_row['kid_registered'] == REG_NO)
						$enable_groups = true;
				}
				else if ($tab_v == 'modify') {
					if ($kid_row['kid_registered'] != REG_NO)
						$enable_groups = true;
				}

				if (!$enable_groups || $read_only) {
					$table_onclick = '';
					$onclick = '';
					$reserve_onclick = '';
				}

				$opa = '';
				if (!empty($vis) &&
					($kid_row['kid_age_level'] != $a || $kid_row['kid_group_number'] != $i))
					$opa = 'opacity: 0.5;';

				$r_count = arr_nvl($reserve_counts, $a.'_'.$i, 0);
				$count = arr_nvl($group_counts, $a.'_'.$i, 0) + $r_count;
				$r_count -= $my_reserve_count;
				$limit = if_empty(arr_nvl($group_limits, $a.'_'.$i, 0), DEFAULT_GROUP_SIZE);

				// GROUP BOX:
				table([ 'class'=>'participant-group g-'.$a, 'onclick'=>$table_onclick, 'style'=>$opa ]);
				tr();
				$row_style = 'padding: 2px 0px 0px 0px; text-align: center;';
				td([ 'onclick'=>$onclick, 'style'=>$row_style ], span([ 'class'=>'group-number' ], $i));
				td([ 'id'=>$tab_v.'_group_c_'.$a.'_'.$i, 'onclick'=>$onclick, 'style'=>$row_style.' min-width: 24px;' ], $count > 0 ? $count : '-');
				td([ 'onclick'=>$reserve_onclick, 'style'=>$row_style ], $reserve_box);
				_tr();
				tr();
				$row_style = 'padding: 0px 0px 2px 0px; font-size: 14px; text-align: center;';
				td([ 'onclick'=>$onclick, 'style'=>$row_style  ], nbsp());
				td([ 'id'=>$tab_v.'_group_l_'.$a.'_'.$i, 'onclick'=>$onclick, 'style'=>$row_style  ], $limit);
				td([ 'id'=>$tab_v.'_group_r_'.$a.'_'.$i, 'onclick'=>$reserve_onclick, 'style'=>$row_style  ], $r_count > 0 ? $r_count : '');
				_tr();
				_table();
				_td();
			}
			_tr();
		}
		_table();
		if ($kid_row['kid_group_number'] > 0)
			$groups_per_age .= $kid_row['kid_age_level'].'_'.$kid_row['kid_group_number'];
		hidden($tab_v.'_groups_per_age', $groups_per_age);
		return '';
	}

	public function gethistory() {
		if (!$this->authorize_staff())
			return '';

		$kid_id = in('kid_id');
		$kid_id->persistent();
		$kid_id_v = $kid_id->getValue();
		$hst_page = in('hst_page', 1);
		$hst_page->persistent();

		$history_table = new HistoryTable('SELECT SQL_CALC_FOUND_ROWS hst_action, hst_timestamp,
			stf_username, hst_escalation, hst_age_level, hst_group_number, hst_notes
			FROM bf_history LEFT JOIN bf_staff ON stf_id = hst_stf_id
			WHERE hst_kid_id = ? ORDER BY hst_timestamp DESC',
			[ $kid_id_v ], [ 'class'=>'details-table history-table' ]);
		$history_table->setPagination('participant?hst_page=', 10, $hst_page);

		table(array('style'=>'border-collapse: collapse;'));
		tr(td([ 'align'=>'left', 'valign'=>'top' ], $history_table->paginationHtml()));
		tr(td([ 'align'=>'left', 'valign'=>'top', 'style'=>'padding: 0px 20px 20px 0px;' ], $history_table->html()));
		_table();
		hidden('history_count', $history_table->getRowCount());
		return '';
	}

	public function pollgroups() {
		if (!$this->authorize_staff())
			return '';

		$kid_id = in('kid_id');
		$kid_id->persistent();
		$kid_id_v = $kid_id->getValue();

		$tab = in('tab');
		$tab_v = $tab->getValue();

		$gpa = in('gpa');
		$gpa_v = $gpa->getValue();

		$cnt = in('cnt');
		$cnt_v = $cnt->getValue();

		list($current_period, $nr_of_groups,
			$total_limit, $total_count, $total_limits, $total_counts,
			$group_limits, $group_counts,
			$reserve_counts, $kid_row, $staff_row) = $this->get_kid_group_data($kid_id_v);

		$groups_per_age = ''; 
		for ($a=0; $a<AGE_LEVEL_COUNT; $a++) {
			$group_nr = arr_nvl($nr_of_groups, $a, 0);
			$groups_per_age .= $a.'_'.$group_nr.':';
			if (empty($group_nr))
				continue;
			for ($i=1; $i<=$group_nr; $i++) {
				$my_reserve_count = if_empty($staff_row['stf_reserved_count'], 0);
				$r_count = arr_nvl($reserve_counts, $a.'_'.$i, 0);
				$count = arr_nvl($group_counts, $a.'_'.$i, 0) + $r_count;
				$r_count -= $my_reserve_count;
				$limit = if_empty(arr_nvl($group_limits, $a.'_'.$i, 0), DEFAULT_GROUP_SIZE);
				out('$("#'.$tab_v.'_group_c_'.$a.'_'.$i.'").html("'.($count > 0 ? $count : '-').'");');
				out('$("#'.$tab_v.'_group_r_'.$a.'_'.$i.'").html("'.($r_count > 0 ? $r_count : '').'");');
				out('$("#'.$tab_v.'_group_l_'.$a.'_'.$i.'").html("'.$limit.'");');
			}
		}
		if ($kid_row['kid_group_number'] > 0)
			$groups_per_age .= $kid_row['kid_age_level'].'_'.$kid_row['kid_group_number'];
		if ($gpa_v != $groups_per_age) {
			out($tab_v.'_group_list();');
			out('history_list();');
		}
		else {
			$history_count = (integer) db_1_value('SELECT COUNT(*) FROM bf_history WHERE hst_kid_id = ?', [ $kid_id_v ]);
			if ($cnt_v != $history_count)
				out('history_list();');
		}
		return '';
	}
}
