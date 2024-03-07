<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class ParticipantTable extends Table {
	public $hasButton = true;

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
			case 'kid_fullname':
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
				if ($this->hasButton)
					return '&nbsp;';
				break;
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
			case 'button_column':
				if ($this->hasButton)
					return [ 'style'=>'text-align: center; width: 32px;' ];
				break;
		}
		return null;
	}

	public function cellValue($field, $row) {
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];

		switch ($field) {
			case 'kid_number':
			case 'kid_fullname':
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
				if ($this->hasButton) {
					return a([ 'class'=>'button-black',
						'style'=>'display: block; color: white; height: 26px; width: 32px; text-align: center; line-height: 26px; border-radius: 6px;',
						'onclick'=>'$("#set_kid_id").val('.$row['kid_id'].'); $("#display_kid").submit();' ], out('&rarr;'))->html();
				}
				break;
		}
		return nix();
	}
}

define('MATCH_ALL', 0);
define('MATCH_DATE', 1);
define('MATCH_FULL_NAME', 2);
define('MATCH_GROUP', 3);
define('MATCH_KID_ID', 4);
define('MATCH_EMAIL', 5);
define('MATCH_NUMBER', 6);

class BF_Controller extends BaseController {
	public $stf_login_id = 0;
	public $stf_login_name = '';
	public $stf_login_tech = false;

	public $par_login_id = 0;
	public $par_login_code = '';

	public $error = "";
	public $warning = "";

	protected $db = null;
	protected $session = null;

	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$this->db = db_connect();
		$this->db_model = model('Db_model');
	}
	
	public function start_session()
	{
		$this->session = session();
		//$this->db->query("SET time_zone = '+02:00'");
	}

	public function get_age_level($curr_age)
	{
		$age_level_to = $GLOBALS['age_level_to'];
		$age_level_from = $GLOBALS['age_level_from'];

		if ($curr_age <= $age_level_to[AGE_LEVEL_0])
			return AGE_LEVEL_0;
		if ($curr_age >= $age_level_from[AGE_LEVEL_2])
			return AGE_LEVEL_2;
		return AGE_LEVEL_1;
	}

	public function get_kid_row($kid_id) {
		$kid_row = null;
		if (!empty($kid_id) && is_numeric($kid_id)) {
			$query = $this->db->query('SELECT kid_id, kid_number, kid_reg_num, kid_fullname,
				DATE_FORMAT(kid_birthday, "%d.%m.%Y") AS kid_birthday, kid_present_periods,
				kid_registered, kid_parent_id, par_code, par_fullname, par_cellphone, par_email,
				kid_notes, kid_age_level, kid_group_number,
				kid_call_status, kid_call_escalation, kid_call_start_time, kid_call_change_time,
				kid_wc_time
				FROM bf_kids LEFT OUTER JOIN bf_parents ON kid_parent_id = par_id
				WHERE kid_id=?', array($kid_id));
			$kid_row = $query->getRowArray();
		}
		if (empty($kid_row)) {
			$kid_row = [ 'kid_id'=>'', 'kid_number'=>'', 'kid_reg_num'=>0, 'kid_fullname'=>'',
				'kid_birthday'=>'', 'kid_present_periods'=>0,
				'kid_registered'=>REG_NO, 'kid_parent_id'=>'', 'par_code'=>'', 'par_cellphone'=>'', 'par_fullname'=>'', 'par_cellphone'=>'', 'par_email'=>'',
				'kid_notes'=>'', 'kid_age_level'=>'', 'kid_group_number'=>'',
				'kid_call_status'=>'', 'kid_call_escalation'=>'', 'kid_call_start_time'=>'', 'kid_call_change_time'=>'',
				'kid_wc_time'=>''
			];
		}
		return $kid_row;
	}

	public function get_kids($par_id) {
		$query = $this->db->query('SELECT kid_id, kid_number, kid_reg_num, kid_fullname,
			DATE_FORMAT(kid_birthday, "%d.%m.%Y") AS kid_birthday, kid_present_periods,
			kid_registered, kid_parent_id, par_code, par_fullname, par_cellphone, par_email,
			kid_notes, kid_age_level, kid_group_number,
			kid_call_status, kid_call_escalation, kid_call_start_time, kid_call_change_time,
			kid_wc_time
			FROM bf_kids LEFT OUTER JOIN bf_parents ON kid_parent_id = par_id
			WHERE kid_parent_id=? ORDER BY kid_id ASC', [$par_id]);
		return $query->getResultArray();
	}

	public function get_kid_row_by_name($kid_fullname) {
		$query = $this->db->query('SELECT kid_id, kid_number, kid_reg_num, kid_fullname,
			DATE_FORMAT(kid_birthday, "%d.%m.%Y") AS kid_birthday, kid_present_periods,
			kid_registered, kid_parent_id, par_code, par_fullname, par_cellphone, par_email,
			kid_notes, kid_age_level, kid_group_number,
			kid_call_status, kid_call_escalation, kid_call_start_time, kid_call_change_time,
			kid_wc_time
			FROM bf_kids LEFT OUTER JOIN bf_parents ON kid_parent_id = par_id
			WHERE kid_fullname=?', [ $kid_fullname ]);
		$kid_row = $query->getRowArray();
		return $kid_row;
	}

	public function get_staff_row($stf_id) {
		if (is_empty($stf_id) || !is_numeric($stf_id))
			return array('stf_id'=>'', 'stf_username'=>'', 'stf_fullname'=>'', 'stf_password'=>'',
				'stf_reserved_age_level'=>0, 'stf_reserved_group_number'=>0, 'stf_reserved_count'=>0,
				'stf_role'=>ROLE_NONE, 'stf_registered'=>0, 'stf_loginallowed'=>'', 'stf_technician'=>0,
				'stf_notes'=>'' );

		$query = $this->db->query('SELECT s1.stf_id, s1.stf_username, s1.stf_fullname, s1.stf_password,
			s1.stf_reserved_age_level, s1.stf_reserved_group_number, s1.stf_reserved_count,
			s1.stf_role, s1.stf_registered, s1.stf_loginallowed, s1.stf_technician, s1.stf_notes,
			GROUP_CONCAT(DISTINCT s2.stf_id ORDER BY s2.stf_username SEPARATOR ",") team_ids,
			GROUP_CONCAT(DISTINCT s2.stf_username ORDER BY s2.stf_username SEPARATOR ",") team_names
			FROM bf_staff s1
			LEFT OUTER JOIN bf_period ON s1.stf_id = per_my_leader_id
			LEFT OUTER JOIN bf_staff s2 ON s2.stf_id = per_staff_id
			WHERE s1.stf_id=?
			GROUP BY s1.stf_id',
			array($stf_id));
		return $query->getRowArray();
	}

	public function get_staff_row_by_username($stf_username) {
		$query = $this->db->query(
			'SELECT stf_id, stf_username, stf_fullname, stf_password, '.
			'stf_registered, stf_loginallowed, stf_technician '.
			'FROM bf_staff WHERE stf_username=?', [ $stf_username ]);
		$staff_row = $query->getRowArray();
		return $staff_row;
	}

	private function empty_parent_row() {
		return array('par_id'=>'', 'par_code'=>'', 'par_email'=>'', 'par_fullname'=>'',
			'par_cellphone'=>'' );
	}

	public function get_parent_row($par_id) {
		$row = null;
		if (!is_empty($par_id) && is_numeric($par_id)) {
			$query = $this->db->query('SELECT par_id, par_code, par_email, par_fullname, par_cellphone
				FROM bf_parents WHERE par_id=?', [ $par_id ]);
			$row = $query->getRowArray();
		}
		if (empty($row))
			return $this->empty_parent_row();
		return $row;
	}

	public function get_parent_row_by_email($email) {
		$row = null;
		if (!empty($email)) {
			$query = $this->db->query('SELECT par_id, par_code, par_email, par_fullname, par_cellphone
				FROM bf_parents WHERE par_email=?', [ $email ]);
			$row = $query->getRowArray();
		}
		if (empty($row))
			return $this->empty_parent_row();
		return $row;
	}

	public function get_parent_row_by_code($par_code) {
		$row = null;
		if (!empty($par_code)) {
			$query = $this->db->query('SELECT par_id, par_code, par_email, par_fullname, par_cellphone
				FROM bf_parents WHERE par_code=?', [ $par_code ]);
			$row = $query->getRowArray();
		}
		if (empty($row))
			return $this->empty_parent_row();
		return $row;
	}

	public function get_parent_code()
	{
		do {
			// Do not include these bacause ambiguous: I O
			// Do not inclued these because they are the letters of a group:
			// B - BLAU, G - GELP, R - ROT
			$code = substr('ACDEFHJKLMNPQSTUVWXYZ', rand(0, 20), 1);
			$code .= substr('0123456789', rand(0, 9), 1);
			$code .= substr('ABCDEFGHJKLMNPQRSTUVWXYZ', rand(0, 23), 1);
			$code .= substr('0123456789', rand(0, 9), 1);
			$par_id = (int) db_1_value('SELECT par_id FROM bf_parents WHERE par_code = ?', [ $code ]);
		} while (!empty($par_id));
		return $code;
	}

	public function reserve_group($age, $num)
	{
		if (empty($num))
			return;

		$builder = $this->db->table('bf_staff');
		$builder->set('stf_registered', 1); // Unregistered users cannot reserve!
		$builder->set('stf_reserved_count',
			'IF(stf_reserved_age_level = '.$age.' AND stf_reserved_group_number = '.$num.', stf_reserved_count+1, 1)', false);
		$builder->set('stf_reserved_age_level', $age);
		$builder->set('stf_reserved_group_number', $num);
		$builder->where('stf_id', $this->session->stf_login_id);
		$builder->update();
	}

	public function unreserve_groups($age, $num)
	{
		$builder = $this->db->table('bf_staff');
		$builder->set('stf_reserved_age_level', null);
		$builder->set('stf_reserved_group_number', null);
		$builder->set('stf_reserved_count', 0);
		$builder->where('stf_id', $this->session->stf_login_id);
		$builder->where('stf_reserved_age_level', $age);
		$builder->where('stf_reserved_group_number', $num);
		$builder->update();
	}

	public function unreserve_group($age, $num)
	{
		$builder = $this->db->table('bf_staff');
		$builder->set('stf_reserved_age_level', 'IF (stf_reserved_count = 1, NULL, stf_reserved_age_level)', false);
		$builder->set('stf_reserved_group_number', 'IF (stf_reserved_count = 1, NULL, stf_reserved_group_number)', false);
		$builder->set('stf_reserved_count', 'stf_reserved_count-1', false);
		$builder->where('stf_id', $this->session->stf_login_id);
		$builder->where('stf_reserved_age_level', $age);
		$builder->where('stf_reserved_group_number', $num);
		$builder->update();
	}

	public function kid_exists($kid_name) {
		$count = (integer) db_1_value('SELECT COUNT(*) FROM bf_kids WHERE kid_fullname = ?',
			[ $kid_name ]);
		return $count > 0;
	}

	public function insert_kid($after_row, $group_reserved,
		$hst_stf_id, $err_prefix, &$kid_id_v)
	{
		$kid_id_v = 0;

		if ($this->kid_exists($after_row['kid_fullname']))
			return $err_prefix.$after_row['kid_fullname'].' ist bereits registriert';

		$insert_row = $after_row;
		$insert_row['kid_registered'] = $group_reserved ? REG_YES : REG_NO;
		$insert_row['kid_birthday'] = str_to_date($after_row['kid_birthday'])->format('Y-m-d');
		$insert_row['kid_create_stf_id'] = $this->session->stf_login_id;

		do {
			$kid_number = (integer) db_1_value('SELECT MAX(kid_number) FROM bf_kids');
			$kid_number = $kid_number < 100 ? 100 : $kid_number+1;
			$insert_row['kid_number'] = $kid_number;
			$kid_id_v = db_insert('bf_kids', $insert_row, 'kid_modifytime');
		}
		while (empty($kid_id_v));

		$history = [ 'hst_stf_id'=> $hst_stf_id ];
		$history['hst_kid_id'] = $kid_id_v;
		if ($group_reserved) {
			$history['hst_action'] = REGISTER;
			$history['hst_age_level'] = $after_row['kid_age_level'];
			$history['hst_group_number'] = $after_row['kid_group_number'];
			$builder = $this->db->table('bf_history');
			$builder->insert($history);
			$this->unreserve_group($after_row['kid_age_level'], $after_row['kid_group_number']);
		}
		else {
			$history['hst_action'] = CREATED;
			$builder = $this->db->table('bf_history');
			$builder->insert($history);
		}

		return '';
	}

	public function remove_kid($kid_id_v)
	{
		$builder = $this->db->table('bf_history');
		$builder->where('hst_kid_id', $kid_id_v);
		$builder->delete();

		$builder = $this->db->table('bf_kids');
		$builder->where('kid_id', $kid_id_v);
		$builder->delete();
	}

	public function modify_kid($kid_id_v, $before_row, $after_row, $group_reserved,
		$before_parent, $hst_stf_id, $err_prefix = '')
	{
		if ($before_row['kid_fullname'] != $after_row['kid_fullname']) {
			if ($this->kid_exists($after_row['kid_fullname']))
				return $err_prefix.$after_row['kid_fullname'].' ist bereits registriert';
		}

		$update_row = $after_row;
		if (isset($after_row['kid_birthday']))
			$update_row['kid_birthday'] = str_to_date($after_row['kid_birthday'])->format('Y-m-d');
		$update_row['kid_modify_stf_id'] = $this->session->stf_login_id;

		$builder = $this->db->table('bf_kids');
		$builder->set('kid_modifytime', 'NOW()', false);
		$builder->where('kid_id', $kid_id_v);
		$builder->update($update_row);

		$history = [ 'hst_stf_id'=> $hst_stf_id ];
		$history['hst_kid_id'] = $kid_id_v;

		if ($before_row['kid_fullname'] != $after_row['kid_fullname']) {
			$history['hst_action'] = NAME_CHANGED;
			$history['hst_notes'] = $before_row['kid_fullname'].
				' -> '.$after_row['kid_fullname'];
			$builder = $this->db->table('bf_history');
			$builder->insert($history);
		}

		if ($before_row['kid_birthday'] != $after_row['kid_birthday']) {
			$history['hst_action'] = BIRTHDAY_CHANGED;
			$history['hst_notes'] = $before_row['kid_birthday'].' -> '.$after_row['kid_birthday'];
			$builder = $this->db->table('bf_history');
			$builder->insert($history);
		}

		if ($before_row['kid_parent_id'] != $after_row['kid_parent_id']) {
			$after_parent = $this->get_parent_row($after_row['kid_parent_id']);
			if (empty($after_parent))
				$after_row['kid_parent_id'] = $before_row['kid_parent_id'];
			else {
				if (empty($before_parent['par_code'])) {
					$history['hst_action'] = SUPERVISOR_SET;
					$b_sup = '';
				}
				else {
					$history['hst_action'] = SUPERVISOR_CHANGED;
					$b_sup = $before_parent['par_code'];
					if (!empty($before_parent['par_fullname']))
						$b_sup .= ' ('.$before_parent['par_fullname'].')';
				}
				
				$a_sup = $after_parent['par_code'];
				if (!empty($after_parent['par_fullname']))
					$a_sup .= ' ('.$after_parent['par_fullname'].')';

				$history['hst_notes'] = $b_sup.' -> '.$a_sup;

				$builder = $this->db->table('bf_history');
				$builder->insert($history);
			}
		}

		//if ($before_row['kid_parent_cellphone'] != $after_row['kid_parent_cellphone']) {
		//	$history['hst_action'] = CELLPHONE_CHANGED;
		//	$history['hst_notes'] = $before_row['kid_parent_cellphone'].' -> '.$after_row['kid_parent_cellphone'];
		//	$builder = $this->db->table('bf_history');
		//	$builder->insert($history);
		//}

		if ($before_row['kid_notes'] != $after_row['kid_notes']) {
			$history['hst_action'] = NOTES_CHANGED;
			if (empty($before_row['kid_notes']))
				$history['hst_notes'] = ' -> "'.$after_row['kid_notes'].'"';
			else {
				if (empty(trim($after_row['kid_notes'])))
					$history['hst_notes'] = '"'.$before_row['kid_notes'].'" -> ""';
				else
					$history['hst_notes'] = '"'.$before_row['kid_notes'].'" -> "..."';
			}
			$builder = $this->db->table('bf_history');
			$builder->insert($history);
		}

		if ($group_reserved) {
			$history['hst_action'] = ($before_row['kid_registered'] != REG_NO || $before_row['kid_group_number'] > 0) ? CHANGED_GROUP : REGISTER;
			$history['hst_age_level'] = $after_row['kid_age_level'];
			$history['hst_group_number'] = $after_row['kid_group_number'];
			unset($history['hst_notes']);
			$builder = $this->db->table('bf_history');
			$builder->insert($history);
			$this->unreserve_group($after_row['kid_age_level'], $after_row['kid_group_number']);
		}
		
		return '';
	}

	public function kids_table($kid_filter_v, $order_by = '', $kid_page = null)
	{
		$group_colors = $GLOBALS['group_colors'];

		$qtype = MATCH_ALL;
		if (empty($kid_filter_v)) {
			$kid_filter_v = '%';
			$order_by = 'kid_modifytime DESC';
		}
		else {
			$order_by = 'kid_fullname';
			if (preg_match('/^[0-9]{1,2}\.([0-9]{1,2}(\.[0-9]{0,4})?)?$/', $kid_filter_v)) {
				$qtype = MATCH_DATE;
				$args = explode('.', $kid_filter_v);
				for ($i=sizeof($args)-1; $i>=0; $i--) {
					if (empty($args[$i]))
						array_pop($args);
					else
						$args[$i] = (integer) $args[$i];
				}
			}
			else if (preg_match('/^[[:alpha:]]+(\ [[:alpha:]]+)+$/', $kid_filter_v)) {
				$qtype = MATCH_FULL_NAME;
				$parts = explode(' ', $kid_filter_v);
				$whole = implode('% ', $parts).'%';
				$args = [ $whole, $whole ];
			}
			else if (preg_match('/^[BGYbgy][0-9]+$/', $kid_filter_v)) {
				$qtype = MATCH_GROUP;
			}
			else if (preg_match('/^[ACDEFHJKLMNPQSTUVWXYZ][0-9]([A-Z][0-9]?)?$/', $kid_filter_v)) {
				$qtype = MATCH_KID_ID;
			}
			else if (str_contains($kid_filter_v, '@')) {
				$qtype = MATCH_EMAIL;
			}
			else if (is_numeric($kid_filter_v)) {
				$qtype = MATCH_NUMBER;
			}
			else
				$kid_filter_v = '%'.$kid_filter_v.'%';
		}

		$sql = 'SELECT SQL_CALC_FOUND_ROWS kid_id, kid_number, kid_fullname, kid_call_escalation,
			kid_birthday, "age", kid_age_level, kid_group_number, kid_call_status, kid_registered, kid_wc_time, "button_column",
			IF(kid_call_status = '.CALL_PENDING.' OR kid_call_status = '.CALL_CALLED.', 0, 1) calling, kid_call_start_time
			FROM bf_kids LEFT OUTER JOIN bf_parents ON kid_parent_id = par_id WHERE ';
		if ($qtype == MATCH_DATE) {
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
		else if ($qtype == MATCH_FULL_NAME) {
			// First_Last
			$sql .= '(kid_fullname LIKE ? OR par_fullname LIKE ?)';
		}
		else if ($qtype == MATCH_GROUP) {
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
		else if ($qtype == MATCH_KID_ID) {
			$sql .= 'par_code LIKE ?';
			$args = [ $kid_filter_v.'%' ];
		}
		else if ($qtype == MATCH_EMAIL) {
			// Registered with, search parents:
			$sql .= 'par_email LIKE ?';
			$args = [ '%'.$kid_filter_v.'%' ];
		}
		else if ($qtype == MATCH_NUMBER) {
			$sql .= '(kid_number LIKE ? OR par_cellphone LIKE ?)';
			$args = [ kid_filter_v.'%', '%'.$kid_filter_v.'%' ];
		}
		else {
			$sql .= 'CONCAT(kid_fullname, "$", kid_fullname, "$", par_fullname, "$", par_email) LIKE ?';
			$args = [ '%'.$kid_filter_v.'%' ];
		}

		$kid_table = new ParticipantTable($sql, $args,
			array('class'=>'details-table kids-table', 'style'=>'width: 600px;'));
		$kid_table->setPagination('kids?kid_page=', 20, $kid_page);
		$kid_table->setOrderBy($order_by);
		return $kid_table;
}

	public function cancel_group_leader($stf_id, $remove_presence)
	{
		$data = [ 'per_is_leader' => 0 ];
		if ($remove_presence)
			$data['per_present'] = false;
		$builder = $this->db->table('bf_period');
		$builder->where('per_staff_id', $stf_id);
		$builder->update($data);

		$builder = $this->db->table('bf_period');
		$data = [ 'per_my_leader_id' => null ];
		$builder->where('per_my_leader_id', $stf_id);
		$builder->update($data);
	}

	public function get_group_data($p)
	{
		$current_period = $this->db_model->get_setting('current-period');
		if ($p == -1)
			$p = $current_period;

		$nr_of_groups = array_fill(0, AGE_LEVEL_COUNT, 0);
		$group_limits = [];

		$groups = db_row_array('SELECT grp_age_level, grp_count, grp_size_hints
			FROM bf_groups WHERE grp_period = ? ORDER BY grp_period, grp_age_level', [ $p ]);

		foreach ($groups as $group) {
			$nr_of_groups[$group['grp_age_level']] = $group['grp_count'];
			$limits = explode(',', $group['grp_size_hints']);
			if (!empty($limits)) {
				for ($i=1; $i<=count($limits); $i++)
					$group_limits[$group['grp_age_level'].'_'.$i] = if_empty($limits[$i-1], 0);
			}
		}
		
		return [ $current_period, $nr_of_groups, $group_limits ];
	}

	public function get_period_data($p)
	{
		list($current_period, $nr_of_groups, $group_limits) = $this->get_group_data($p);
		if ($p == -1)
			$p = $current_period;

		$total_limit = 0;
		$total_count = 0;
		$total_limits = [];
		$total_counts = [];
		$group_counts = [];

		// Number of kids in each group:
		if ($p == $current_period) {
			$group_counts = db_array_2('SELECT CONCAT(kid_age_level, "_", kid_group_number),
				COUNT(DISTINCT kid_id)
				FROM bf_kids WHERE kid_group_number > 0 GROUP BY kid_age_level, kid_group_number');
			foreach ($group_counts as $group=>$count) {
				$age = str_left($group, '_');
				$num = str_right($group, '_');
				if (arr_nvl($nr_of_groups, $age, 0) < $num)
					$nr_of_groups[$age] = $num;
			}
		}

		for ($a=0; $a<AGE_LEVEL_COUNT; $a++) {
			$a_limit = 0;
			$a_count = 0;
			$max_group_nr = arr_nvl($nr_of_groups, $a, 0);
			for ($i=1; $i<=$max_group_nr; $i++) {
				$a_limit += if_empty(arr_nvl($group_limits, $a.'_'.$i, 0), DEFAULT_GROUP_SIZE);
				$a_count += if_empty(arr_nvl($group_counts, $a.'_'.$i, 0), 0);
			}
			$total_limits[$a] = $a_limit;
			$total_counts[$a] = $a_count;
			$total_limit += $a_limit;
			$total_count += $a_count;
		}
		return [ $current_period, $nr_of_groups,
			$total_limit, $total_count, $total_limits, $total_counts,
			$group_limits, $group_counts ];
	}

	public function is_logged_in($as = 'staff') {
		$url = current_url();
		$page = str_right($url, base_url());
		if (str_startswith($page, "/"))
			$page = str_right($page, "/");
		if (str_contains($page, "index.php/"))
			$page = str_right($page, "index.php/");
		$page = str_left($page, "/");

		if (!$this->session->has('ses_prev_page'))
			$this->session->set('ses_prev_page', $page);
		if (!$this->session->has('ses_curr_page') || $this->session->ses_curr_page != $page) {
			$this->session->set('ses_prev_page', $this->session->ses_curr_page);
			$this->session->set('ses_curr_page', $page);
		}

		$this->stf_login_id = 0;
		$this->stf_login_name = 0;
		$this->par_login_id = 0;
		$this->par_login_code = '';
		if ($this->session->has('stf_login_id') && $this->session->stf_login_id > 0) {
			$this->stf_login_id = $this->session->stf_login_id;
			$this->stf_login_name = $this->session->stf_login_name;
		}
		if ($this->session->has('par_login_id') && $this->session->par_login_id > 0) {
			$this->par_login_id = $this->session->par_login_id;
			$this->par_login_code = $this->session->par_login_code;
		}

		// Logged in as staff member:
		if ($as == 'staff' && $this->stf_login_id > 0)
			return true;

		// Logged in as a parent:
		return $this->par_login_id > 0;
	}

	public function set_staff_logged_in($staff_row) {
		$this->session->set('stf_login_id', $staff_row['stf_id']);
		$this->session->set('stf_login_name', $staff_row['stf_fullname']);
		$this->session->set('stf_login_tech', $staff_row['stf_technician']);
		$this->db->query('UPDATE bf_staff SET stf_registered = 1 WHERE stf_id = ?',
			array($staff_row['stf_id']));
		$this->stf_login_id = $this->session->stf_login_id;
		$this->stf_login_name = $this->session->stf_login_name;
		$this->stf_login_tech = $this->session->stf_login_tech;
	}

	public function set_parent_logged_in($parent_row) {
		$this->session->set('par_login_id', $parent_row['par_id']);
		$this->session->set('par_login_code', $parent_row['par_code']);
		$this->par_login_id = $this->session->par_login_id;
		$this->par_login_code = $this->session->par_login_code;
	}

	public function set_parent_logged_out() {
		$this->session->set('par_login_id', 0);
		$this->session->set('par_login_code', '');
		$this->par_login_id = 0;
		$this->par_login_code = '';
	}

	public function authorize_staff() {
		if ($this->is_logged_in('staff')) {
			return true;
		}

		$this->header('Redirect');
		script();
		out('window.parent.location = "admin-login";');
		_script();
		$this->footer();

		return false;
	}

	private function link($target, $selected)
	{
		$attr = array('class'=>'menu-item', 'onclick'=>'window.location=\''.$target.'\';');
		if ($selected)
			$attr['selected'] = null;
		return $attr;
	}

	public function head($title) {
		out('<!DOCTYPE html>');
		tag('html');
		tag('head');
		tag('meta', [ 'http-equiv'=>'Content-Type', 'content'=>'text/html; charset=utf-8' ]);
		tag('meta', [ 'name'=>'apple-mobile-web-app-capable', 'content'=>'yes' ]);
		tag('meta', [ 'name'=>'apple-mobile-web-app-status-bar-style', 'content'=>'black' ]);
		tag('link', array('href'=>base_url('/css/blue-flame.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
		tag('title', "BlueFlame Kids: ".$title);
		script(base_url('/js/jquery.js'));
		script(base_url('/js/blue-flame.js'));
		_tag('head');
	}

	public function header($title, $menu = true) {
		$this->head($title);
		$body_attr = [ ];
		$table_attr = [ 'style'=>'width: 100%; border-collapse: collapse; border: 0px;' ];
		if ($this->session != null &&
			($this->session->ses_curr_page == 'login' || $this->session->ses_curr_page == 'registration')) {
			$body_attr = [ 'style'=>'background-color: #D9E3E8;' ]; // A9C2D6 ECECEC
			$table_attr['class'] = 'registration-body';
		}
		tag('body', $body_attr);

		table($table_attr);
		tr();
		td([ 'style'=>'padding: 0px; text-align: center; vertical-align: top;' ]);

		if ($menu) {
			if ($title == 'Database update') {
				$kid_count = '-';
				$stf_count = '-';
			}
			else {
				$kid_count = (integer) db_1_value('SELECT COUNT(*) FROM bf_kids WHERE kid_registered != '.REG_NO);
				$stf_count = (integer) db_1_value('SELECT COUNT(*) FROM bf_staff WHERE stf_registered = 1');
			}

			div(array('class'=>'topnav'));
			table();
			tr(array('style'=>'height: 12px;'));
			if ($title != 'Login') {
				td(array('rowspan'=>'2', 'valign'=>'bottom', 'style'=>'padding: 0px 3px 2px 10px; border-bottom: 1px solid black;'));
				img([ 'src'=>base_url('/img/bf-kids-logo2.png'), 'style'=>'height: 40px; width: auto; position: relative; bottom: -2px;']);
				_td();
			}
			td(array('colspan'=>'10'));
			td(array('rowspan'=>'2', 'valign'=>'bottom', 'style'=>'width: 100%; border-bottom: 1px solid black;'));
			_td();
			td(array('colspan'=>'4'));
			_tr();
			tr(array('style'=>'border-bottom: 1px solid black; padding: 8px 16px;'));
			td(array('style'=>'width: 3px; padding: 0;'), nbsp());
			td($this->link('kids', $title == 'Kinder'), 'Kinder ('.$kid_count.')');
			td(array('style'=>'width: 3px; padding: 0;'), nbsp());
			td($this->link('parents', $title == 'Begleitpersonen'), 'Begleitpersonen');
			td(array('style'=>'width: 3px; padding: 0;'), nbsp());
			td($this->link('calllist', $title == 'Rufliste'), 'Rufliste');
			td(array('style'=>'width: 3px; padding: 0;'), nbsp());
			td($this->link('staff', $title == 'Mitarbeiter'), 'Mitarbeiter ('.$stf_count.')');
			td(array('style'=>'width: 3px; padding: 0;'), nbsp());
			td($this->link('groups', $title == 'Kleingruppen'), 'Kleingruppen');

			//td(array('style'=>'width: 3px; padding: 0;'), nbsp());
			//td($this->link('ipad', $title == 'iPad Registrierung'), 'iPad Registrierung');
			td(array('style'=>'width: 3px; padding: 0;'), nbsp());
			td($this->link('registration', $title == 'Mobile Checkin'), 'Mobile Checkin');

			hidden('login_full_name', $this->stf_login_name);
			if ($title != 'Login') {
				$attr = $this->link('admin-login?action=logout', false);
				$attr['id'] = 'logout_menu_item';
				$attr['onmouseover'] = 'mouseOverLogout(this);';
				$attr['onmouseout'] = 'mouseOutLogout(this, $(\'#login_full_name\'));';
				td($attr, $this->stf_login_name);
			}
			else
				td();
			td(array('style'=>'width: 3px; padding: 0;'), nbsp());
			_tr();
			_table();
			_div();

			// Little padding before content:
			div([ 'style'=>'height: 20px;' ], nbsp());
		}
	}

	public function footer($js_src = "") {
		_td();
		_tr();
		_table();

		_tag('body');
		if (!empty($js_src))
			script($js_src);
		_tag('html');
	}
	
	public function have_error() {
		return !empty($this->error);
	}

	public function have_result() {
		return !empty($this->error) ||
			!empty($this->warning) ||
			!empty($this->session->bf_success);
	}

	public function print_result() {
		if (!empty($this->error))
			print_error($this->error);
		if (!empty($this->warning))
			print_warning($this->warning);
		if (!empty($this->session->bf_success)) {
			print_success($this->session->bf_success);
			// Only display this feedback once:
			$this->session->set('bf_success', '');
		}
	}

	public function set_error($message) {
		$this->error = $message;
	}

	public function set_success($message) {
		if ($this->session != null)
			$this->session->set('bf_success', $message);
	}

	// ---------------------------
	public function link_list($page, $ids, $names = null)
	{
		$out = out('');
		foreach ($ids as $id => $name) {
			if (!$out->isempty())
				$out->add(', ');
			if (!empty($names)) {
				$new_name = $names[$id];
				$id = $name;
				$name = $new_name;
			}
			$out->add(a([ 'href'=>$page.$id ], $name));
		}
		return $out;
	}
	
	// Builder functions:
	
}
