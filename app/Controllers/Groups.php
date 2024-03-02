<?php
namespace App\Controllers;

use App\Controllers\BF_Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class GroupsTable extends Table {
	public function columnTitle($field) {
		switch ($field) {
			case 'grp_age_level':
				return 'Altersgruppe';
			case 'stf_fullname':
				return 'Leiter';
			case 'workers_column':
				return 'Mitarbeiter';
			case 'grp_count':
				return 'Teiln.';
			case 'button_column':
				return '&nbsp;';
		}
		return nix();
	}

	public function cellValue($field, $row) {
		switch ($field) {
			case 'grp_name':
			case 'loc_name':
			case 'grp_count':
				return $row[$field];
			case 'grp_from_age':
				return if_empty($row['grp_from_age'], '').' - '.if_empty($row['grp_to_age'], '');
			case 'button_column':
				return submit('select', 'Bearbeiten', array('class'=>'button-black',
					'onclick'=>'$("#set_grp_id").val('.$row['grp_id'].');'))->html();
		}
		return nix();
	}
}

class Groups extends BF_Controller {
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$this->start_session();
	}

	public function index()
	{
		$period_names = $GLOBALS['period_names'];
		$period_dates = $GLOBALS['period_dates'];
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];

		if (!$this->authorize_staff())
			return '';

		$current_period = $this->db_model->get_setting('current-period');

		$this->header('Kleingruppen');

		$history = db_row_array('
			SELECT hst_kid_id, hst_action, hst_timestamp, hst_age_level, hst_group_number,
				kid_birthday
			FROM bf_history, bf_kids
			WHERE hst_kid_id = kid_id AND hst_action IN (?, ?, ?)
			ORDER BY hst_timestamp, hst_id',
			[ CREATED, REGISTER, CALLED ]);
		$kids = []; // = [ $p=>$age, ... ]
		$call = array_fill(0, PERIOD_COUNT, array_fill(0, AGE_LEVEL_COUNT, 0));;
		foreach ($history as $hist) {
			for ($p=0; $p<PERIOD_COUNT; $p++) {
				if (date_create_from_format('Y-m-d H:i:s', $hist['hst_timestamp']) < $period_dates[$p]) {
					break;
				}
			}
			$age = get_age($hist['kid_birthday']);
			$age_level = $this->get_age_level($age);

			if ($p==PERIOD_COUNT)
				continue;
			switch ($hist['hst_action']) {
				case CREATED:
				case REGISTER:
					if (empty($hist['hst_group_number']))
						break;
					if (!isset($kids[$hist['hst_kid_id']]))
						$kids[$hist['hst_kid_id']] = array_fill(0, PERIOD_COUNT, -1);
					$kids[$hist['hst_kid_id']][$p] = $hist['hst_age_level'];
					break;
				case CALLED:
					$call[$p][$age_level]++;
					break;
			}
		}

		table( [ 'style'=>'border-collapse: collapse; width: 100%;' ] );
		tr();
		td(array('class'=>'left-panel', 'align'=>'left', 'valign'=>'top'));
			table( [ 'style'=>'width: 100%;' ] );
			for ($p=0; $p<PERIOD_COUNT; $p++) {
				if ($p > 0)
					tr(td([ 'style'=>'height: 10px' ]));
				tr();
				td([ 'class'=>'group-header' ]);
				b($period_names[$p]);
				if ($p >= $current_period) {
					nbsp();
					nbsp();
					nbsp();
					href('groups/prints?session='.$p, img([ 'src'=>'../img/print-50.png',
						'style'=>'height: 20px; width: auto; position: relative; bottom: -5px;']));
				}
				_td();
				_tr();
				tr();
				td();
				if ($p >= $current_period) {
					$async_loader = new AsyncLoader('group_list_'.$p, 'groups/getgrouplist?period='.$p,
						[ 'age_level', 'args', 'action' ] );
					$async_loader->html();
				}
				else {
					list($current_period, $nr_of_groups, $group_limits) = $this->get_group_data($p);
					$ages = array_fill(0, AGE_LEVEL_COUNT, array_fill(0, PERIOD_COUNT, 0));
					$total = array_fill(0, AGE_LEVEL_COUNT, 0);
					foreach ($kids as $kid) {
						if ($kid[$p] >= 0) {
							$found = false;
							for ($q=0; $q<$p; $q++) {
								if ($kid[$q] >= 0) {
									$found = true;
									// More accurate is $kid[$q] not $kid[$p]
									// But, so the number make sense we will just allways
									// allocate the kids to the same age level in previous sessions!
									$ages[$kid[$p]][$q]++;
								}
							}
							if (!$found)
								$ages[$kid[$p]][$p]++;
							$total[$kid[$p]]++;
						}
					}

					table();
					tr();
					td('Gruppe');
					td('Anzahl');
					td('Calls');
					td('Gesamt');
					td('1. Anmeldungen');
					for ($q=0; $q<$p; $q++) {
						td('Session '.($q+1));
					}
					_tr();
					for ($a=0; $a<AGE_LEVEL_COUNT; $a++) {
						tr();
						td(b($age_level_from[$a].' - '.$age_level_to[$a]));
						td($nr_of_groups[$a]);
						td($call[$p][$a]);
						td($total[$a]);
						td($ages[$a][$p]);
						for ($q=0; $q<$p; $q++) {
							td($ages[$a][$q]);
						}
						_tr();
					}
					_table();
				}
				_td();
				_tr();
			}
			_table();
		_td();
		_tr();
		_table();

		$this->footer();
		return '';
	}

	private function leaders_and_helpers($p, $include_fullname = false)
	{
		$sql = 'SELECT CONCAT(per_age_level, "_", per_group_number), per_staff_id';
		$sql .= $include_fullname ? ', stf_fullname ' : ' ';
		$sql .= 'FROM bf_period, bf_staff WHERE per_staff_id = stf_id AND stf_role = ? AND
				per_period = ? AND per_is_leader = TRUE AND per_group_number > 0';
		if ($include_fullname)
			$group_leaders = db_array_n($sql, [ ROLE_GROUP_LEADER, $p ]);
		else
			$group_leaders = db_array_2($sql, [ ROLE_GROUP_LEADER, $p ]);

		$staff_name = $include_fullname ? 'stf_fullname' : 'stf_username';
		$sql = 'SELECT CONCAT(p1.per_age_level, "_", p1.per_group_number) grp,
			GROUP_CONCAT(DISTINCT stf_id ORDER BY '.$staff_name.' SEPARATOR ",") helper_ids,
			GROUP_CONCAT(DISTINCT '.$staff_name.' ORDER BY '.$staff_name.' SEPARATOR ",") helper_names
			FROM bf_period p1
			JOIN bf_period p2 ON p2.per_my_leader_id = p1.per_staff_id AND p2.per_period = ? AND
				IF (p1.per_age_level = 0, p2.per_age_level_0,
					IF (p1.per_age_level = 1, p2.per_age_level_1, p2.per_age_level_2))
			JOIN bf_staff ON p2.per_staff_id = stf_id AND stf_role = ?
			WHERE p1.per_period = ? AND p1.per_is_leader = TRUE AND p1.per_group_number > 0
			GROUP BY p1.per_age_level, p1.per_group_number';
		$group_helpers = db_array_n($sql, [ $p, ROLE_GROUP_LEADER, $p ]);
		
		return [ $group_leaders, $group_helpers ];
	}

	public function getgrouplist()
	{
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];
		$all_roles = $GLOBALS['all_roles'];
		$extended_roles = $GLOBALS['extended_roles'];

		if (!$this->authorize_staff())
			return '';

		$read_only = !is_empty($this->session->stf_login_tech);

		$period = in('period');
		$p = $period->getValue();
		if ($p < 0)
			$p = 0;
		if ($p >= PERIOD_COUNT)
			$p = PERIOD_COUNT-1;

		$display_groups = new Form('display_groups_'.$p, 'groups');

		$age_level = $display_groups->addHidden('age_level');
		$age = $age_level->getValue();
		if ($age < 0)
			$age = 0;
		if ($age >= AGE_LEVEL_COUNT)
			$age = AGE_LEVEL_COUNT-1;
	
		$arguments = $display_groups->addHidden('args');
		$args = $arguments->getValue();
		$action = $display_groups->addHidden('action');
		$act = $action->getValue();

		list($current_period, $nr_of_groups,
			$total_limit, $total_count, $total_limits, $total_counts,
			$group_limits, $group_counts) = $this->get_period_data($p);

		if (!empty($act)) {
			$size_hints = [];
			for ($i=1; $i<=arr_nvl($nr_of_groups, $age, 0); $i++) {
				$size_hints[] = if_empty(arr_nvl($group_limits, $age.'_'.$i, 0), DEFAULT_GROUP_SIZE);
			}
			switch ($act) {
				case "add-group":
					$nr_of_groups[$age] = arr_nvl($nr_of_groups, $age, 0) + 1;
					$limit = if_empty(arr_nvl($group_limits, $age.'_'.$nr_of_groups[$age], 0), DEFAULT_GROUP_SIZE);
					$total_limits[$age] += $limit;
					$total_limit += $limit;
					$data = array(
						'grp_period' => $p,
						'grp_age_level' => $age,
						'grp_count' => $nr_of_groups[$age],
						'grp_size_hints' => implode(',', $size_hints)
					);
					$builder = $this->db->table('bf_groups');
					$builder->replace($data);
					break;
				case "remove-group":
					$cnt = arr_nvl($nr_of_groups, $age, 0);
					if ($cnt > 0) {
						// Get the limit of the group we are removing, and substract from totals:
						$limit = if_empty(arr_nvl($group_limits, $age.'_'.$cnt, 0), DEFAULT_GROUP_SIZE);
						unset($group_limits[$age.'_'.$cnt]);
						unset($size_hints[$cnt - 1]);
						$total_limits[$age] -= $limit;
						$total_limit -= $limit;
						$nr_of_groups[$age] = $cnt - 1;
						$data = array(
							'grp_period' => $p,
							'grp_age_level' => $age,
							'grp_count' => $nr_of_groups[$age],
							'grp_size_hints' => implode(',', $size_hints)
						);
						$builder = $this->db->table('bf_groups');
						$builder->replace($data);
					}
					break;
				case "set-leader":
					$group_number = str_left($args, '_');
					if ($group_number >= 1 && $group_number <= $nr_of_groups[$age]) {
						$staff_id = str_right($args, '_');
						// Remove the current leader of the group:
						$this->db->query('UPDATE bf_period SET per_age_level = 0, per_group_number = 0
							WHERE per_period = ? AND per_age_level = ? AND per_group_number = ?',
							[ $p, $age, $group_number ]);
						// Set the new leader
						$this->db->query('UPDATE bf_period SET per_age_level = ?, per_group_number = ?
							WHERE per_period = ? AND per_staff_id = ?',
							[ $age, $group_number, $p, $staff_id ]);
					}
					break;					
				case "set-limit":
					$group_number = str_left($args, '_');
					if ($group_number >= 1 && $group_number <= $nr_of_groups[$age]) {
						$limit = str_right($args, '_');
						$delta = $limit - $size_hints[$group_number-1];
						$size_hints[$group_number-1] = $limit;
						$group_limits[$age.'_'.$group_number] = $limit;
						$total_limits[$age] += $delta;
						$total_limit += $delta;
						$data = array(
							'grp_period' => $p,
							'grp_age_level' => $age,
							'grp_count' => $nr_of_groups[$age],
							'grp_size_hints' => implode(',', $size_hints)
						);
						$builder = $this->db->table('bf_groups');
						$builder->replace($data);
					}
					break;					
			}
		}

		list($group_leaders, $group_helpers) = $this->leaders_and_helpers($p);

		$period_leaders = db_row_array('SELECT stf_id, stf_username, per_group_number,
			CONCAT(per_age_level_0, per_age_level_1, per_age_level_2) ages
			FROM bf_period, bf_staff WHERE per_staff_id = stf_id AND stf_role = ? AND
				per_period = ? AND per_is_leader = TRUE
				GROUP BY stf_username ORDER BY stf_username',
			[ ROLE_GROUP_LEADER, $p ]);

		$i = 0;
		table( [ 'width'=>'100%', 'style'=>'margin-top: 4px;' ] );
		tr();
		td([ 'id'=>'total_'.$p, 'style'=>'width: 58px; font-size: 18px;', 'align'=>'center' ],
			if_empty($total_count, '-').'/'.if_empty($total_limit, 0));
		td();
		foreach ($period_leaders as $period_leader) {
			$val = '';
			if ($i > 0)
				$val = ' ';
			$st = 'border: 1px solid black; padding: 4px 8px; display: inline-block;';
			if (empty($period_leader['per_group_number'])) {
				$val .= div( [ 'style'=>$st ] );
				$d = '-';
			}
			else {
				$val .= div( [ 'style'=>$st.' color: grey; border-color: grey;' ] );
				$d = '--';
			}
			$val .= a( [ 'href'=>'staff?set_stf_id='.$period_leader['stf_id'] ] );
			$val .= $period_leader['stf_username'];
			$val .= _a();
			if ((integer) substr($period_leader['ages'], 0, 1) > 0)
				$val .= ' '.span(['class'=>'group g'.$d.'0', 'style'=>'height: 8px; width: 5px;'], '');
			if ((integer) substr($period_leader['ages'], 1, 1) > 0)
				$val .= ' '.span(['class'=>'group g'.$d.'1', 'style'=>'height: 8px; width: 5px;'], '');
			if ((integer) substr($period_leader['ages'], 2, 1) > 0)
				$val .= ' '.span(['class'=>'group g'.$d.'2', 'style'=>'height: 8px; width: 5px;'], '');
			$val .= _div();
			out($val);
			$i++;
		}
		_td();
		_tr();
		_table();
		$display_groups->open();
		table([ 'style'=>'border-spacing: 5px;' ]);
		for ($a=0; $a<AGE_LEVEL_COUNT; $a++) {
			tr();
			td([ 'style'=>'width: 58px; font-size: 18px;', 'align'=>'center' ],
				div([ 'style'=>'padding: 0px 0px 4px 0px;' ], b($age_level_from[$a].' - '.$age_level_to[$a])),
				div([ 'id'=>'sum_'.$p.'_'.$a ],
					if_empty(arr_nvl($total_counts, $a, 0), '-').'/'.if_empty(arr_nvl($total_limits, $a, 0), 0))
				);
			$max_group_nr = arr_nvl($nr_of_groups, $a, 0);
			for ($i=1; $i<=$max_group_nr; $i++) {
				if ($read_only)
					$set_group_limit = '';
				else
					$set_group_limit = 'set_limit_'.$p.'('.$a.','.$i.');';
				td();
				table([ 'class'=>'group g-'.$a ]);
				tr();
				td([ 'onclick'=>$set_group_limit ], span(['class'=>'group-number'], $i));
				$rows = db_array_n("SELECT stf_id, per_group_number, stf_username
					FROM bf_staff, bf_period
					WHERE stf_id = per_staff_id AND stf_role = ? AND per_is_leader = TRUE AND
						per_period = ? AND per_age_level_$a = TRUE
						ORDER BY stf_username", [ ROLE_GROUP_LEADER, $p ]);
				$leaders = [ 0=>'' ];
				$group_leader = arr_nvl($group_leaders, $a.'_'.$i, 0);
				foreach ($rows as $id => $row) {
					if ($id == $group_leader)
						$pre = '';
					else if ($row['per_group_number'])
						$pre = '&#x2717; ';
					else
						$pre = '';
						
					$leaders[$id] = $pre.$row['stf_username'];
				}
				$select = select('select_leader', $leaders, $group_leader,
					[ 'onchange'=>'$("#age_level").val('.$a.');
						$("#args").val("'.$i.'_" + $(this).val());
						$("#action").val("set-leader");
						group_list_'.$p.'();' ]);
				if ($read_only)
					$select->disable();
				td([ 'class'=>'input-table' ], $select);
				_tr();
				tr();
				$count = arr_nvl($group_counts, $a.'_'.$i, 0);
				$limit = arr_nvl($group_limits, $a.'_'.$i, 0);
				$count_and_limit = if_empty($count, '-').'/'.if_empty($limit, DEFAULT_GROUP_SIZE);
				td([ 'id'=>'usage_'.$p.'_'.$a.'_'.$i,
					'style'=>'text-align: center; font-size: 18px; font-weight: bold;',
					'onclick'=>$set_group_limit ], $count_and_limit);
				$helpers = arr_nvl($group_helpers, $a.'_'.$i, []);
				if (empty($helpers))
					td(nbsp());
				else
					td($this->link_list('staff?set_stf_id=',
						explode(',', $helpers['helper_ids']), explode(',', $helpers['helper_names'])));
				_tr();
				_table();
				_td();
			}
			td();
			table([ 'spacing'=>'0', 'style'=>'position: relative; top: -2px;' ]);
			$enable = !$read_only;
			tr(td([ 'style'=>($enable ? 'background-color: black; color: white;' : 'background-color: darkgrey; color: grey;').' border: 1px solid black; text-align: center; font-weight: bold;
				width: 22px; height: 22px; font-size: 20px;',
				'onclick'=> $enable ? '$("#age_level").val('.$a.'); $("#action").val("add-group"); group_list_'.$p.'();' : '' ], '+'));
			tr(td(''));
			if ($enable)
				$enable = $max_group_nr > 0 && arr_nvl($group_leaders, $a.'_'.$max_group_nr, 0) == 0 && arr_nvl($group_counts, $a.'_'.$max_group_nr, 0) == 0;
			tr(td([ 'style'=>($enable ? 'border: 1px solid black;' : 'border: 1px solid grey; color: grey;').' text-align: center; font-weight: bold;
				width: 22px; height: 22px;  font-size: 20px;',
				'onclick'=> $enable ? '$("#age_level").val('.$a.'); $("#action").val("remove-group"); group_list_'.$p.'();' : '' ], '-'));
			_table();
			_td();
			_tr();
		}
		_table();
		script();
		out('
			function set_limit_'.$p.'(age, grp) {
				var gname = "Rot";
				if (age == 1)
					gname = "Blau";
				else
					gname = "Gelb";
				var text = "Gib bitte die neue Obergrenze der Kleingruppe "+gname+" "+grp+" ein:"
				var new_limit = prompt(text, "");
				if (new_limit != null) {
					var val = parseInt(new_limit);
					if (!isNaN(val) && val > 2 && val < 200) {
						$("#age_level").val(age);
						$("#args").val(String(grp)+"_"+String(val));
						$("#action").val("set-limit");
						group_list_'.$p.'();
					}
				}
			}
		');
		if ($current_period == $p) {
			out('
				function poll_group_data() {
				console.log("poll!");
					$.getScript("groups/pollgroupdata");
				}
			');
			out('window.setInterval(poll_group_data, 5000);');
		}
		_script();
		$display_groups->close();
		return '';
	}

	private function print_group($a, $i) {
		$members = db_row_array('SELECT k.*, p.*
			FROM bf_kids k LEFT OUTER JOIN bf_parents p ON kid_parent_id = par_id
			WHERE kid_age_level = ? AND kid_group_number = ?
			ORDER BY kid_id', [ $a, $i ]);

		if (empty($members)) {
			div([ 'style'=>'font-weight: bold; padding: 4px;' ], 'Anwesend:');
			$add_lines = 5;
		}
		else {
			table([ 'class'=>'printable-table', 'style' => 'width: 720px; padding: 5px;' ]);
			tr();
			th([ 'style'=> 'width: 20px;' ], 'Nr.');
			th([ 'style'=> 'text-align: center; width: 20px;' ], 'Anw.');
			th('Name');
			th('Begleitperson');
			th([ 'style'=> 'width: 40%;' ], 'Hinweise');
			_tr();
			$box = div([ 'style'=>'margin: 0 auto; border: 1px solid black; background-color: white; height: 20px; width: 20px;' ], '');
			foreach ($members as $member) {
				tr();
				td([ 'style'=> 'width: 20px; white-space: nowrap;' ], $member['kid_number']);

				td([ 'style'=> 'text-align: center; width: 20px; white-space: nowrap;' ], $box);

				$name = $member['kid_fullname'];
				if (!empty($member['kid_birthday']))
					$name .= ' ('.get_age($member['kid_birthday']).')';
				td([ 'style'=> 'white-space: nowrap;' ],$name);

				$name = $member['par_fullname'];
				if (!empty($member['par_cellphone']))
					$name .= ' ('.$member['par_cellphone'].')';
				td([ 'style'=> 'white-space: nowrap;' ], $name);

				td([ 'style'=> 'width: 40%;' ], $member['kid_notes']);
				_tr();
			}
			_table();

			div([ 'style'=>'font-weight: bold; padding: 4px;' ], 'Auch anwesend:');
			$add_lines = 3;
		}

		// Add a table to enter kids in the group but not listed.
		table([ 'class'=>'printable-table', 'style' => 'width: 720px; padding: 5px;' ]);
		tr();
		td([ 'style'=> 'background-color: white; width: 20px; font-weight: bold;' ], 'Nr.');
		td([ 'style'=> 'background-color: white;' ], b('Name').' (falls Nr. nicht bekannt)');
		td([ 'style'=> 'background-color: white; width: 20px; font-weight: bold;' ], 'Nr.');
		td([ 'style'=> 'background-color: white;' ], b('Name').' (falls Nr. nicht bekannt)');
		_tr();
		$box = div([ 'style'=> 'margin: 0 auto; border: 1px solid black; background-color: white; height: 20px; width: 48px;' ], '');
		for ($i=0; $i<$add_lines; $i++) {
			tr();
			td($box);
			td('');
			td($box);
			td('');
			_tr();
		}
		_table();
	}

	// <div style='page-break-after:always'></div>
	// $print_group = 'window.location = "groups/prints?group='.$p.'_'.$a.'_'.$i.'";';
	public function prints() {
		$period_names = $GLOBALS['period_names'];
		$age_level_from = $GLOBALS['age_level_from'];
		$age_level_to = $GLOBALS['age_level_to'];
		$all_roles = $GLOBALS['all_roles'];
		$extended_roles = $GLOBALS['extended_roles'];
		$group_colors = $GLOBALS['group_colors'];

		if (!$this->authorize_staff())
			return '';

		$period = in('session');
		$p = (integer) $period->getValue();
		if ($p < 0)
			$p = 0;
		if ($p >= PERIOD_COUNT)
			$p = PERIOD_COUNT-1;

		list($current_period, $nr_of_groups,
			$total_limit, $total_count, $total_limits, $total_counts,
			$group_limits, $group_counts) = $this->get_period_data($p);

		list($group_leaders, $group_helpers) = $this->leaders_and_helpers($p, true);

		$all_empty = $current_period != $p || empty($total_count);
		
		$this->head($period_names[$p]);
		tag('body');

		if ($all_empty) {
			h1($period_names[$p]);
			table([ 'class'=>'printable-table', 'style' => 'width: 720px; padding: 5px;' ]);
			tr();
			th('Gruppe');
			th('Obergrenze');
			th('Leiter');
			th('Co-Leiter');
			_tr();
		}

		for ($a=0; $a<AGE_LEVEL_COUNT; $a++) {
			$group_nr = arr_nvl($nr_of_groups, $a, 0);
			for ($i=1; $i<=$group_nr; $i++) {
				$group_leader = arr_nvl($group_leaders, $a.'_'.$i, []);
				$helpers = arr_nvl($group_helpers, $a.'_'.$i, []);

				$group_name = $group_colors[$a].' '.$i.' ('.$age_level_from[$a].' - '.$age_level_to[$a].')';

				$limit = if_empty(arr_nvl($group_limits, $a.'_'.$i, 0), DEFAULT_GROUP_SIZE);
				$leader = a([ 'href'=>'../staff?set_stf_id='.arr_nvl($group_leader, 'per_staff_id') ],
					arr_nvl($group_leader, 'stf_fullname'));
				$co_leader = $this->link_list('../staff?set_stf_id=',
					explode(',', arr_nvl($helpers, 'helper_ids', '')), explode(',', arr_nvl($helpers, 'helper_names', '')));

				if ($all_empty) {
					tr();
					td($group_name);
					td($limit);
					td($leader);
					td($co_leader);
					_tr();
				}
				else {
					$print_group = new Form('print_group', 'groups', 1, array('class'=>'output-table'));
					$print_group->addField('Gruppe', b($group_name));
					$print_group->addField('Obergrenze', $limit.' ('.arr_nvl($group_counts, $a.'_'.$i, 0).' angemeldet)');
					$print_group->addField('Leiter', $leader);
					$print_group->addField('Co-Leiter', $co_leader);

					$print_group->show();
					$this->print_group($a, $i);
					br();
					div([ 'style'=>'page-break-after: always;' ], '');
				}
			}
		}

		if ($all_empty)
			_table();

		_tag('body');
		_tag('html');
		return '';
	}

	public function pollgroupdata() {
		if (!$this->authorize_staff())
			return '';

		list($current_period, $nr_of_groups,
			$total_limit, $total_count, $total_limits, $total_counts,
			$group_limits, $group_counts) = $this->get_period_data(-1);

		for ($a=0; $a<AGE_LEVEL_COUNT; $a++) {
			$group_nr = arr_nvl($nr_of_groups, $a, 0);
			if (empty($group_nr))
				continue;
			for ($i=1; $i<=$group_nr; $i++) {
				$count = arr_nvl($group_counts, $a.'_'.$i, 0);
				$limit = arr_nvl($group_limits, $a.'_'.$i, 0);
				$count_and_limit = if_empty($count, '-').'/'.if_empty($limit, DEFAULT_GROUP_SIZE);
				out('$("#usage_'.$current_period.'_'.$a.'_'.$i.'").html("'.$count_and_limit.'");');
			}
			$sum = if_empty(arr_nvl($total_counts, $a, 0), '-').'/'.if_empty(arr_nvl($total_limits, $a, 0), 0);
			out('$("#sum_'.$current_period.'_'.$a.'").html("'.$sum .'");');
		}
		$total = if_empty($total_count, '-').'/'.if_empty($total_limit, 0);
		out('$("#total_'.$current_period.'").html("'.$total.'");');
		return '';
	}
}
