<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class StaffTable extends Table {
	public function columnTitle($field) {
		switch ($field) {
			case 'stf_username':
				return 'Name';
			case 'stf_role':
				return 'Aufgabe';
			case 'is_present':
				return 'Anw.';
			case 'stf_registered':
				return 'Angem.';
			case 'button_column':
				return '&nbsp;';
		}
		return nix();
	}

	public function columnAttributes($field) {
		switch ($field) {
			case 'is_present':
				return [ 'style'=>'text-align: center;' ];
		}
		return null;
	}

	public function cellValue($field, $row) {
		$all_roles = $GLOBALS['all_roles'];
		$group_colors = $GLOBALS['group_colors'];

		switch ($field) {
			case 'stf_username':
				return $row[$field].' ('.$row['stf_fullname'].')';
			case 'stf_role':
				$val = '';
				$present = '';
				$roles = [];
				switch ($row['stf_role']) {
					case ROLE_GROUP_LEADER:
						if ($row['age_level_0'] > 0)
							$val .= span(['class'=>'group g-0', 'style'=>'height: 8px; width: 5px;'], '').' ';
						if ($row['age_level_1'] > 0)
							$val .= span(['class'=>'group g-1', 'style'=>'height: 8px; width: 5px;'], '').' ';
						if ($row['age_level_2'] > 0)
							$val .= span(['class'=>'group g-2', 'style'=>'height: 8px; width: 5px;'], '').' ';
						if ($row['is_leader'] > 0)
							$roles[] = b('Teamleiter');
						if (!empty($row['my_leaders']))
							$roles[] = 'Team: '.b($row['my_leaders']);
						break;
					default:
						if (!empty($all_roles[$row['stf_role']]))
							$roles[] = b($all_roles[$row['stf_role']]);
						break;
				}
				$val .= implode(', ', $roles);
				if (!empty($row['stf_reserved_group_number'])) {
					if (!empty($val))
						$val .= ', ';
					$val .= 'Reserviert: '.substr($group_colors[$row['stf_reserved_age_level']], 0, 1).$row['stf_reserved_group_number'];
					if ($row['stf_reserved_count'] > 1)
						$val .= ' ('.$row['stf_reserved_count'].')';
				}
				if (empty($val))
					$val = $all_roles[$row['stf_role']];
				return $val;
			case 'is_present':
				if ($row['all_periods']) {
					if (empty($row['is_present']))
						$val = '&#x2717;';
					else if ($row['is_present'] == PERIOD_COUNT)
						$val = '&#x2713;';
					else
						$val = $row['is_present'];
				}
				else {
					if (empty($row['is_present']))
						$val = '&#x2717;';
					else
						$val = '&#x2713;';
				}
				return $val;
			case 'stf_registered':
				if ($row[$field] == 1)
					return div(array('class'=>'green-box', 'style'=>'width: 56px; height: 22px;'), 'Ja');
				return div(array('class'=>'red-box', 'style'=>'width: 56px; height: 22px;'), 'Nein');
			case 'button_column':
				return a([ 'class'=>'button-black',
					'style'=>'display: block; color: white; height: 24px; width: 32px; text-align: center; line-height: 26px; border-radius: 6px;',
					'onclick'=>'$("#set_stf_id").val('.$row['stf_id'].'); $("#display_staff").submit();' ], out('&rarr;'))->html();
				//return submit('select', 'Bearbeiten', array('class'=>'button-black', 'onclick'=>'$("#set_stf_id").val('.$row['stf_id'].');'))->html();
		}
		return nix();
	}
}

class Staff extends BF_Controller {
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$this->start_session();
	}

	private function period_val($periods, $p, $val_name)
	{
		if (isset($periods[$p])) {
			return $periods[$p][$val_name];
		}
		return false;
	}

	public function index()
	{
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];
		$all_roles = $GLOBALS['all_roles'];
		$extended_roles = $GLOBALS['extended_roles'];
		$period_names = $GLOBALS['period_names'];
		
		if (!$this->authorize_staff())
			return '';

		$read_only = !is_empty($this->session->stf_login_tech);

		$current_period = $this->db_model->get_setting('current-period');

		//$filter_staff = new Form('filter_staff', 'staff?stf_page=1', 1, array('class'=>'input-table'));
		$display_staff = new Form('display_staff', 'staff', 1, array('class'=>'input-table'));
		$set_stf_id = $display_staff->addHidden('set_stf_id');
		$stf_filter = $display_staff->addTextInput('stf_filter', '', '', [ 'placeholder'=>'Suchfilter', 'style'=>'width: 110px;' ]);
		$stf_filter->persistent();
		$stf_page = in('stf_page', 1);
		$stf_page->persistent();
		$clear_filter = $display_staff->addSubmit('clear_filter', 'X',
			[ 'class'=>'button-black', 'onclick'=>'$("#stf_filter").val(""); staff_list(); return false;' ]);
		$stf_select_role = $display_staff->addSelect('stf_select_role', '', $extended_roles, 0, [ 'onchange'=>'staff_list(); return false;' ]);
		$stf_select_role->persistent();
		$stf_select_period = $display_staff->addSelect('stf_select_period', '', [ -1 => '']  + $period_names, -1, [ 'onchange'=>'staff_list(); return false;' ]);
		$stf_select_period->persistent();
		$filter_options = [ 0=>'', 1=>'Anwesend', 2=>'Angemeldet', 3=>'Abwesend' ];
		$stf_presence = $display_staff->addSelect('stf_presence', '', $filter_options, 0, [ 'onchange'=>'staff_list(); return false;' ]);
		$stf_presence->persistent();

		$update_staff = new Form('update_staff', 'staff', 2, array('class'=>'input-table'));
		if ($read_only)
			$update_staff->disable();
		$stf_id = $update_staff->addHidden('stf_id');
		$stf_id->persistent();

		if ($set_stf_id->submitted()) {
			$stf_id->setValue($set_stf_id->getValue());
			return redirect("staff");
		}

		$stf_id_v = $stf_id->getValue();
		$staff_row = $this->get_staff_row($stf_id_v);
		// Fields
		if (!is_empty($stf_id_v)) {
			$stf_registered = $update_staff->addField('Status');
			if ($staff_row['stf_registered'])
				$stf_registered->setValue(div(array('class'=>'green-box'), 'Angemeldet'));
			else
				$stf_registered->setValue(div(array('class'=>'red-box'), 'Abgemeldet'));
			$update_staff->addSpace();
		}
		$stf_username = $update_staff->addTextInput('stf_username', 'Kurzname', $staff_row['stf_username'], [ 'maxlength'=>'9', 'style'=>'width: 100px' ]);
		$stf_fullname = $update_staff->addTextInput('stf_fullname', 'Name', $staff_row['stf_fullname']);
		if (is_empty($stf_id_v) || $this->stf_login_id == $stf_id_v ||
			$this->stf_login_name == "Administrator" || $this->stf_login_name == "System Admin") {
			$stf_password = $update_staff->addPassword('stf_password', 'Passwort', '');
			$confirm_password = $update_staff->addPassword('confirm_password', 'Passwort wiederholen');
			$confirm_password->setRule('matches[stf_password]');
		}

		$stf_role = $update_staff->addSelect('stf_role', 'Aufgabe', $all_roles, $staff_row['stf_role'],
				[ 'onchange'=>'toggleRole($(this).val(), '.ROLE_GROUP_LEADER.')' ]);

		if (empty($staff_row['team_names']))
			$update_staff->addSpace();
		else
			$update_staff->addField('Teammitglieder', $this->link_list('staff?set_stf_id=',
						explode(',', $staff_row['team_ids']), explode(',', $staff_row['team_names'])));

		$f1 = $update_staff->addField('Notizen', $staff_row['stf_notes']);
		$f1->setFormat([ 'colspan'=>'*' ]);

		$periods = db_array_n('SELECT per_period, per_age_level, per_group_number, per_location_id,
			per_present, per_is_leader, per_my_leader_id, per_age_level_0, per_age_level_1, per_age_level_2
			FROM bf_period WHERE per_staff_id=?', [ $stf_id_v ]);
		$schedule = table(['class'=>'schedule-table']);

		// Headers:
		$schedule->add(tr());
		$schedule->add(th([ 'class'=>'row-header' ], ''));
		for ($p=0; $p<PERIOD_COUNT; $p++) {
			$schedule->add(th([ 'style'=>($p < $current_period ? 'color: grey;' : 'color: inherit;') ],
				str_replace(' ', '<br>', $period_names[$p])));
		}
		$schedule->add(_tr());

		// Present:
		$present = [];
		$schedule->add(tr());
		$schedule->add(th([ 'class'=>'row-header' ], 'Anwesend:'));
		for ($p=0; $p<PERIOD_COUNT; $p++) {
			$present[$p] = checkbox('present_'.$p, $this->period_val($periods, $p, 'per_present'),
				[ 'onchange'=>'toggleSchedule('.$p.', false, '.$current_period.')' ]);
			if ($p < $current_period || $read_only)
				$present[$p]->disable();
			$schedule->add(td($present[$p]));
		}
		$schedule->add(_tr());

		// Leader:
		$leader = [];
		$schedule->add(tr([ 'id'=>'group-row' ]));
		$schedule->add(th([ 'class'=>'row-header' ], 'Teamleiter:'));
		for ($p=0; $p<PERIOD_COUNT; $p++) {
			$leader[$p] = checkbox('leader_'.$p, $this->period_val($periods, $p, 'per_is_leader'),
				[ 'onchange'=>'toggleSchedule('.$p.', false, '.$current_period.')' ]);
			if ($p < $current_period || $read_only)
				$leader[$p]->disable();
			$schedule->add(td(['style'=>'min-width: 120px;'], $leader[$p]));
		}
		$schedule->add(_tr());

		// Coleader of:
		$my_leader = [];
		$schedule->add(tr([ 'id'=>'group-row' ]));
		$schedule->add(th([ 'class'=>'row-header' ], 'Im Team von:'));
		for ($p=0; $p<PERIOD_COUNT; $p++) {
			// Include a mark showing if a leader already has a co-leader
			$leaders = [ 0=>'' ] + db_array_2('SELECT stf_id, stf_username FROM bf_staff, bf_period
				WHERE per_staff_id = stf_id AND per_period = ? AND
				per_present = TRUE AND per_is_leader = TRUE AND stf_id != ?', [ $p, $stf_id_v ]);
			if (sizeof($leaders) > 1) {
				$my_leader[$p] = select('my_leader_'.$p, $leaders, $this->period_val($periods, $p, 'per_my_leader_id'),
					[ 'onchange'=>'toggleSchedule('.$p.', true, '.$current_period.')' ]);
				if ($p < $current_period || $read_only)
					$my_leader[$p]->disable();
			}
			else
				$my_leader[$p] = b('-');
			$schedule->add(td($my_leader[$p]));
		}
		$schedule->add(_tr());

		// Age levels:
		$groups = [];
		for ($i=0; $i<AGE_LEVEL_COUNT; $i++) {
			$schedule->add(tr([ 'id'=>'group-row' ]));
			$schedule->add(th([ 'class'=>'row-header' ], $age_level_from[$i].' - '.$age_level_to[$i].':'));
			$groups[$i] = [];
			for ($p=0; $p<PERIOD_COUNT; $p++) {
				$groups[$i][$p] = checkbox('groups_'.$i.'_'.$p, $this->period_val($periods, $p, 'per_age_level_'.$i));
				if ($p < $current_period || $read_only)
					$groups[$i][$p]->disable();
				$schedule->add(td($groups[$i][$p]));
			}
			$schedule->add(_tr());
		}

		// The Group of the user:
		$my_groups = db_array_2('SELECT p1.per_period, CONCAT(p1.per_age_level, "_", p1.per_group_number)
			FROM bf_period p1
			JOIN bf_period p2 ON p1.per_staff_id = p2.per_my_leader_id AND p1.per_period = p2.per_period
			WHERE p2.per_staff_id = ? AND p1.per_group_number > 0 AND
				IF (p1.per_age_level = 0, p2.per_age_level_0,
					IF (p1.per_age_level = 1, p2.per_age_level_1, p2.per_age_level_2)) AND
				p1.per_present = TRUE AND p2.per_present = TRUE AND p1.per_is_leader = TRUE', [ $stf_id_v ]);

		$schedule->add(tr([ 'id'=>'group-row' ]));
		$schedule->add(th([ 'class'=>'row-header' ], 'Gruppe:'));
		for ($p=0; $p<PERIOD_COUNT; $p++) {
			$age_level = $this->period_val($periods, $p, 'per_age_level');
			$group_nr = $this->period_val($periods, $p, 'per_group_number');
			if (empty($group_nr)) {
				if (isset($my_groups[$p])) {
					$age_level = str_left($my_groups[$p], '_');
					$group_nr = str_right($my_groups[$p], '_');
				}
			}
			if (empty($group_nr))
				$group_box = div([ 'id'=>'my_group_'.$p, 'style'=>'height: 32px; font-size: 20px' ], nbsp());
			else {
				$ages = $age_level_from[$age_level].' - '.$age_level_to[$age_level];
				$group_box = span([ 'id'=>'my_group_'.$p, 'class'=>'group-s g-'.$age_level ],
					span(['class'=>'group-number-s'], $group_nr), " ".$ages);
			}
			$schedule->add(td($group_box));
		}

		$schedule->add(_table());
		$update_staff->addRow($schedule);

		$stf_loginallowed = $update_staff->addCheckbox('stf_loginallowed',
			'Die Mitarbeiter darf sich bei dieser Anwendung anmelden', $staff_row['stf_loginallowed']);
		$stf_loginallowed->setFormat([ 'colspan'=>'*' ]);
		$stf_technician = $update_staff->addCheckbox('stf_technician',
			'Die Mitarbeiter darf nur auf die Rufliste zugreifen', $staff_row['stf_technician']);
		$stf_technician->setFormat([ 'colspan'=>'*' ]);

		// Rules
		$stf_username->setRule('required|is_unique[bf_staff.stf_username.stf_id]|maxlength[12]');
		$stf_fullname->setRule('required|is_unique[bf_staff.stf_fullname.stf_id]');

		// Buttons:
		if (is_empty($stf_id_v)) {
			$save_staff = $update_staff->addSubmit('save_staff', 'Mitarbeiter Hinzufügen', ['class'=>'button-black']);
			$clear_staff = $update_staff->addSubmit('clear_staff', 'Clear', ['class'=>'button-black']);
		}
		else {
			$save_staff = $update_staff->addSubmit('save_staff', 'Änderung Sichern', array('class'=>'button-black'));
			$clear_staff = $update_staff->addSubmit('clear_staff', 'Weiteres Aufnehmen...', array('class'=>'button-black'));
			if ($staff_row['stf_registered'])
				$reg_unregister = $update_staff->addSubmit('reg_unregister', 'Abmelden', array('class'=>'button-red'));
			else
				$reg_unregister = $update_staff->addSubmit('reg_unregister', 'Anmelden', array('class'=>'button-green'));
		}

		if ($clear_staff->submitted()) {
			$stf_id->setValue(0);
			return redirect("staff");
		}

		if ($save_staff->submitted()) {
			$pwd = isset($stf_password) ? $stf_password->getValue() : '';

			$this->error = $update_staff->validate();

			if (is_empty($this->error)) {
				if (!is_empty($pwd))
					$pwd = password_hash(strtolower(md5($pwd.'129-3026-19-2089')), PASSWORD_DEFAULT);
				$role = $stf_role->getValue();
				$data = array(
					'stf_username' => $stf_username->getValue(),
					'stf_fullname' => $stf_fullname->getValue(),
					'stf_role' => $role,
					'stf_loginallowed' => $stf_loginallowed->getValue(),
					'stf_technician' => $stf_technician->getValue()
				);
				if (is_empty($stf_id_v)) {
					$data['stf_password'] = $pwd;
					$builder = $this->db->table('bf_staff');
					$builder->insert($data);
					$stf_id_v = $this->db->insertID();
					$stf_id->setValue($stf_id_v);
					$this->set_success($stf_fullname->getValue().' hinzugefügt');
				}
				else {
					if (!is_empty($pwd))
						$data['stf_password'] = $pwd;

					$builder = $this->db->table('bf_staff');
					$builder->where('stf_id', $stf_id_v);
					$builder->update($data);

					$this->set_success($stf_fullname->getValue().' geändert');
				}

				for ($p=0; $p<PERIOD_COUNT; $p++) {
					$p_p = $present[$p]->getValue();
					$l_p = $leader[$p]->getValue();
					$my_l = 0;
					$data = [
						'per_staff_id' => $stf_id_v,
						'per_period' => $p,
						'per_present' => $p_p,
					];
					
					if ($role == ROLE_GROUP_LEADER) {
						if ($p_p && !$l_p && $my_leader[$p] instanceof Select)
							$my_l = $my_leader[$p]->getValue();
						$data['per_is_leader'] = $l_p;
						$data['per_my_leader_id'] = $my_l;
						$data['per_age_level_0'] = $groups[0][$p]->getValue();
						$data['per_age_level_1'] = $groups[1][$p]->getValue();
						$data['per_age_level_2'] = $groups[2][$p]->getValue();
					}

					if (isset($periods[$p])) {
						$builder = $this->db->table('bf_period');
						$builder->where('per_staff_id', $stf_id_v);
						$builder->where('per_period', $p);
						$builder->update($data);
					}
					else {
						$builder = $this->db->table('bf_period');
						$builder->insert($data);
					}
				}

				if ($role != ROLE_GROUP_LEADER) {
					$this->cancel_group_leader($stf_id_v, false);
				}

				return redirect("staff");
			}
		}

		if (!is_empty($stf_id_v) && $reg_unregister->submitted()) {
			$registered = !$staff_row['stf_registered'];

			$builder = $this->db->table('bf_staff');
			$builder->set('stf_registered', $registered);
			if (!$registered) {
				$builder->set('stf_reserved_age_level', null);
				$builder->set('stf_reserved_group_number', null);
				$builder->set('stf_reserved_count', 0);
			}
			$builder->where('stf_id', $stf_id_v);
			$builder->update();

			$this->set_success($stf_fullname->getValue().' '.($registered ? 'angemeldet' : 'abgemeldet'));
			return redirect("staff");
		}

		$staff_list_loader = new AsyncLoader('staff_list', 'staff/getstaff',
			[ 'stf_filter', 'stf_select_role', 'stf_select_period', 'stf_presence' ]);

		// Generate page ------------------------------------------
		$this->header('Mitarbeiter');

		table(array('style'=>'border-collapse: collapse;'));
		tr();
		td(array('class'=>'left-panel', 'align'=>'left', 'valign'=>'top'));
			$display_staff->open();
			table([ 'class'=>'input-table' ]);
			tr(td(table(tr(td($stf_filter),
				td(nbsp()), td($clear_filter),
				td(nbsp()), td($stf_select_role),
				td(nbsp()), td($stf_select_period),
				td(nbsp()), td($stf_presence)))));
			tr();
			td();
			$staff_list_loader->html();
			//$this->getstaff();
			_td();
			_tr();
			_table();
			$display_staff->close();
		_td();
		td(array('align'=>'left', 'valign'=>'top'));
			table(array('style'=>'border-collapse: collapse; margin-right: 5px; min-width: 640px;'));
			tbody();
			tr();
			td(array('style'=>'border: 1px solid black; padding: 10px 5px;'));
			$update_staff->show();
			_td();
			_tr();
			tr();
			td();
			$this->print_result();
			_td();
			_tr();
			_tbody();
			_table();
		_td();
		_tr();
		_table();

		if (!$read_only) {
			script();
			out('toggleStaffPage('.PERIOD_COUNT.', '.$current_period.', $("#stf_role").val(), '.ROLE_GROUP_LEADER.');');
			_script();
		}
		$this->footer();
		return '';
	}
	
	public function getstaff() {
		if (!$this->authorize_staff())
			return '';

		$stf_page = in('stf_page', 1);
		$stf_page->persistent();
		$stf_filter = in('stf_filter', '');
		$stf_filter->persistent();
		$stf_filter_v = trim($stf_filter->getValue());
		$stf_select_role = in('stf_select_role', '');
		$stf_select_role->persistent();
		$stf_select_period = in('stf_select_period', '');
		$stf_select_period->persistent();
		$stf_presence = in('stf_presence', 0);
		$stf_presence->persistent();

		$where = '';
		if ($stf_select_period->getValue() == -1) {
			// No period
			$select_list = 'SELECT TRUE all_periods, s1.stf_id, s1.stf_username, s1.stf_fullname,
				s1.stf_reserved_age_level, s1.stf_reserved_group_number, s1.stf_reserved_count,
				s1.stf_role, SUM(per_present) is_present, s1.stf_registered, "button_column", SUM(per_is_leader) is_leader,
				GROUP_CONCAT(DISTINCT s2.stf_username ORDER BY s2.stf_username SEPARATOR ", ") my_leaders,
				SUM(per_age_level_0) age_level_0, SUM(per_age_level_1) age_level_1, SUM(per_age_level_2) age_level_2 ';
			$on = '';
		}
		else {
			$select_list = 'SELECT FALSE all_periods, s1.stf_id, s1.stf_username, s1.stf_fullname,
				s1.stf_reserved_age_level, s1.stf_reserved_group_number, s1.stf_reserved_count,
				s1.stf_role, per_present is_present, s1.stf_registered, "button_column", per_is_leader is_leader,
				s2.stf_fullname my_leaders,
				per_age_level_0 age_level_0, per_age_level_1 age_level_1, per_age_level_2 age_level_2 ';
			$on = ' AND per_period = '.$stf_select_period->getValue().' ';
		}
		$sql = 'FROM bf_staff s1
				LEFT OUTER JOIN bf_period ON per_staff_id = s1.stf_id '.$on;
		$sql .= 'LEFT OUTER JOIN bf_staff s2 ON per_my_leader_id = s2.stf_id ';

		switch ($this->db_model->get_setting('show-deleted-staff')) {
			case 0:
				$where = 's1.stf_deleted = FALSE ';
				break;
			case 1:
				break;
			case 2:
				$where = 's1.stf_deleted = TRUE ';
				break;
		}

		if (!empty($stf_filter_v)) {
			if (!empty($where))
				$where .= 'AND ';
			$where .= 's1.stf_fullname LIKE "%'.db_escape($stf_filter_v).'%" ';
		}
		switch ($stf_select_role->getValue()) {
			case ROLE_NONE:
				break;
			case ROLE_GROUP_LEADER:
			case ROLE_OFFICIAL:
			case ROLE_TECHNICIAN:
			case ROLE_REGISTRATION:
			case ROLE_MANAGEMENT:
			case ROLE_OFFICE:
			case ROLE_OTHER:
				if (!empty($where))
					$where .= 'AND ';
				$where .= 's1.stf_role = '.$stf_select_role->getValue().' ';
				break;
			case EXT_ROLE_TEAM_LEADER:
				$having = 'is_leader > 0 ';
				break;
			case EXT_ROLE_TEAM_COLEADER:
				$having = 'my_leaders IS NOT NULL ';
				break;
		}
		switch ($stf_presence->getValue()) {
			case 1:
				if (!empty($where))
					$where .= 'AND ';
				$where .= 'per_present != 0 ';
				break;
			case 2:
				if (!empty($where))
					$where .= 'AND ';
				$where .= 's1.stf_registered != 0 ';
				break;
			case 3:
				if (!empty($where))
					$where .= 'AND ';
				$where .= 'per_present != 0 AND s1.stf_registered = 0 ';
				break;
		}
		if (!empty($where))
			$sql .= 'WHERE '.$where;
		$sql .= 'GROUP BY s1.stf_id';
		if (!empty($having))
			$sql .= ' HAVING '.$having;
		$staff_list = new StaffTable($select_list.$sql, [], [ 'class'=>'details-table no-wrap-table', 'style'=>'width: 600px;' ]);
		$staff_list->setPageQuery('SELECT s1.stf_username, SUM(per_is_leader) is_leader, '.
			'GROUP_CONCAT(DISTINCT s2.stf_username ORDER BY s2.stf_username SEPARATOR ", ") my_leaders '.$sql);
		$staff_list->setPagination('staff?stf_page=', 21, $stf_page);
		$staff_list->setOrderBy('stf_username');

		table(array('style'=>'border-collapse: collapse;'));
		tr(td($staff_list->paginationHtml()));
		tr(td($staff_list->html()));
		_table();
		return '';
	}
}
