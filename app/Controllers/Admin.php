<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

// IMPORT 2018 -test data:
//include_once('/Users/build/Documents/BLUE-FLAME/import/kids-2018.php');

class Admin extends BF_Controller {
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$this->start_session();
	}

	private function import_staff($first_name, $last_name, $availability, $work_time, $activity)
	{
		$first_name = ucfirst($first_name);
		$last_name = ucfirst($last_name);
	
		$availability = str_replace('Mittwoch', 'MI', $availability);
		$availability = str_replace('Donnerstag', 'DO', $availability);
		$availability = str_replace('Freitag', 'FR', $availability);
		$availability = str_replace('Samstag', 'SA', $availability);
		$availability = str_replace('Sonntag', 'SO', $availability);

		$stf_notes = $availability;
		if (!empty($work_time) && !str_contains($activity, $work_time))
			$stf_notes .= ' | '.$work_time;
		if (!empty($activity))
			$stf_notes .= ' | '.$activity;

		$stf_id = db_1_value("SELECT stf_id FROM bf_staff WHERE stf_fullname = ?", [ $first_name.' '.$last_name ]);
		if (empty($stf_id)) {
			$short_name = str_left($first_name, '-');
			$ex = db_1_value("SELECT stf_id FROM bf_staff WHERE stf_username = ?", [ $short_name ]);
			if (!empty($ex)) {
				$short_name = str_left($first_name, '-').' '.substr($last_name, 0, 1);
				$ex = db_1_value("SELECT stf_id FROM bf_staff WHERE stf_username = ?", [ $short_name ]);
				if (!empty($ex)) {
					bugout("SHORT NAME EXISTS:", $short_name, $first_name.' '.$last_name);
					return;
				}
			}
		}

		$present = [];
		for ($p=0; $p<PERIOD_COUNT; $p++)
			$present[$p] = false;

		$is_leader = false;
		$age_level_0 = false;
		$age_level_1 = false;
		$age_level_2 = false;

		$role = ROLE_NONE;
		if (str_contains($activity, 'Ordner'))
			$role = ROLE_OFFICIAL;
		else if (str_contains($activity, 'Kleingruppe') || str_contains($activity, 'mit Kindern') ||
			str_contains($activity, 'mit oder für Kinder') || str_contains($activity, 'KG-Leitung') ||
			str_contains($activity, 'KG Leitung') || str_contains($activity, 'Spiele'))
			$role = ROLE_GROUP_LEADER;
		else if (str_contains($activity, 'Ordner'))
			$role = ROLE_OFFICIAL;
		else if (str_contains($activity, 'Abmeldung'))
			$role = ROLE_REGISTRATION;
		else if (str_contains($activity, 'Büro'))
			$role = ROLE_OFFICE;
		else if (str_contains($activity, 'Mutter') || str_contains($activity, 'Eltern') ||
			str_contains($activity, 'Deko') || str_contains($activity, 'Abbau') ||
			str_contains($activity, 'Band'))
			$role = ROLE_OTHER;
		else if (str_contains($activity, 'Technik') || str_contains($activity, 'Lichttechnik'))
			$role = ROLE_TECHNICIAN;
		else if (str_contains($activity, 'Leitung') || str_contains($activity, 'leitung'))
			$role = ROLE_MANAGEMENT;

		if (str_contains($activity, '4-5')) {
			$role = ROLE_GROUP_LEADER;
			$age_level_0 = true;
		}
		if (str_contains($activity, '6-8')) {
			$role = ROLE_GROUP_LEADER;
			$age_level_1 = true;
		}
		if (str_contains($activity, '9-11')) {
			$role = ROLE_GROUP_LEADER;
			$age_level_2 = true;
		}

		if (str_contains($activity, 'S1') || str_contains($activity, '+1') || str_contains($activity, '/1'))
			$present[0] = true;
		if (str_contains($activity, 'S2') || str_contains($activity, '+2') || str_contains($activity, '/2'))
			$present[1] = true;
		if (str_contains($activity, 'S3') || str_contains($activity, '+3') || str_contains($activity, '/3'))
			$present[2] = true;
		if (str_contains($activity, 'S4') || str_contains($activity, '+4') || str_contains($activity, '/4'))
			$present[3] = true;
		if (str_contains($activity, 'S5') || str_contains($activity, '+5') || str_contains($activity, '/5'))
			$present[4] = true;

		if (empty($stf_id)) {
			$data = [
				'stf_username' => $short_name,
				'stf_fullname' => $first_name.' '.$last_name,
				'stf_role' => $role,
				'stf_loginallowed' => false,
				'stf_technician' => false,
				'stf_notes' => $stf_notes ];
			$builder = $this->db->table('bf_staff');
			$builder->insert($data);
			$stf_id = $this->db->insertID();
		}
		else {
			$data = [
				'stf_deleted' => false,
				'stf_notes' => $stf_notes ];
			if ($role != ROLE_NONE) {
				$data['stf_role'] = $role;
				if ($role != ROLE_GROUP_LEADER)
					$this->cancel_group_leader($stf_id, false);
			}
			$builder = $this->db->table('bf_staff');
			$builder->where('stf_id', $stf_id);
			$builder->update($data);
		}

		$found = false;
		for ($p=0; $p<PERIOD_COUNT; $p++) {
			if ($present[$p]) {
				$found = true;
				break;
			}
		}

		if ($found) {
			$periods = db_array_n('SELECT per_period, per_present FROM bf_period WHERE per_staff_id=?', [ $stf_id ]);
			for ($p=0; $p<PERIOD_COUNT; $p++) {
				$data = [
					'per_staff_id' => $stf_id,
					'per_period' => $p,
					'per_present' => $present[$p],
					'per_is_leader' => $present[$p] ? $is_leader : false,
					'per_age_level_0' => $present[$p] ? $age_level_0 : false,
					'per_age_level_1' => $present[$p] ? $age_level_1 : false,
					'per_age_level_2' => $present[$p] ? $age_level_2 : false ];
				if (isset($periods[$p])) {
					$builder = $this->db->table('bf_period');
					$builder->where('per_staff_id', $stf_id);
					$builder->where('per_period', $p);
					$builder->update($data);
				}
				else {
					$builder = $this->db->table('bf_period');
					$builder->insert($data);
				}
			}
		}
	}

	private function export_table($table_name, $filename)
	{
		if (($handle = fopen($filename, 'a')) === false) {
			die('Cannot open: '.file);
			return;
		}

		$fields = $this->db->list_fields($table_name);
		
		$line = '';
		foreach ($fields as $field) {
			$line = str_listappend($line, $field, "\t");
		}
		fwrite($handle, $line."\n");

		$rows = db_array_n('SELECT * FROM '.$table_name, [ ]);

		foreach ($rows as $row) {
			$line = '';
			foreach ($fields as $field) {
				$val = $row[$field];
				$val = str_replace("\\", "\\\\", $val);
				$val = str_replace("\n", "\\n", $val);
				$val = str_replace("\r", "\\r", $val);
				$val = str_replace("\t", "\\t", $val);
				$line = str_listappend($line, $val, "\t");
			}
			fwrite($handle, $line."\n");
		}

		fclose($handle);
	}

	private function export_kids($path)
	{
		$i = 2;
		for (;;) {
			$file = $i.'-export-kids.csv';
			if (!file_exists($path.$file))
				break;
			$i++;
		}
		$this->export_table('bf_kids', $path.$file);
		$this->export_table('bf_history', $path.$i.'-export-history.csv');
	}

	private function all_to_and($parents, $delim)
	{
		$parents = str_replace(' oder ', '~', $parents);
		$parents = str_replace(' und ', '~', $parents);
		$parents = str_replace(' & ', '~', $parents);
		$parents = str_replace('& ', '~', $parents);
		$parents = str_replace(' &', '~', $parents);
		$parents = str_replace('&', '~', $parents);
		$parents = str_replace(' + ', '~', $parents);
		$parents = str_replace('+ ', '~', $parents);
		$parents = str_replace(' +', '~', $parents);
		$parents = str_replace('+', '~', $parents);
		$parents = str_replace(' , ', '~', $parents);
		$parents = str_replace(', ', '~', $parents);
		$parents = str_replace(' ,', '~', $parents);
		$parents = str_replace(',', '~', $parents);

		$parents = str_replace('~', $delim, $parents);
		return $parents;		
	}

	public function index()
	{
		$period_names = $GLOBALS['period_names'];
		//$kids_import = $GLOBALS['kids_import'];
		//$history_import = $GLOBALS['history_import'];
		
		$imp_exp_path = '/home/ec2-user/';
		$imp_exp_path = '/Users/build/Documents/BLUE-FLAME/';

		if (!$this->authorize_staff())
			return '';

		$current_period = $this->db_model->get_setting('current-period');
		$show_deleted_staff = $this->db_model->get_setting('show-deleted-staff');

		$select_period_form = new Form('select_period_form', '', 1, [ 'class'=>'input-table' ]);
		$set_current_period = $select_period_form->addSelect('set_current_period', '',
			$period_names, $current_period, [ 'onchange'=>'this.form.submit()' ]);
		if ($set_current_period->submitted()) {
			$current_period = $set_current_period->getValue();
			$this->db_model->set_setting('current-period', (integer) $current_period);
			return redirect("admin");
		}

		$import_staff_form = new Form('import_staff_form', '', 1, [ 'class'=>'input-table' ]);
		$staff_import_file = $import_staff_form->addTextInput('staff_import_file', 'Import File', 'Mitarbeiter.csv');
		$import_staff = $import_staff_form->addSubmit('import_staff', 'Mitarbeiter Importieren', ['class'=>'button-black']);

		if ($import_staff->submitted()) {
			$import_file = $staff_import_file->getValue();
			if (str_contains($import_file, '/'))
				die('Wrong file name: '.$import_file);
			if (!file_exists($imp_exp_path.'import/'.$import_file))
				die('File not found: '.$imp_exp_path.'import/'.$import_file);

			setlocale(LC_ALL, 'de_DE.utf-8');
			$import_data = csv_to_array($imp_exp_path.'import/'.$import_file);

			$builder = $this->db->table('bf_staff');
			$builder->where('stf_username NOT IN ("Admin", "sa", "Registrierung", "Tech")');
			$builder->update([ 'stf_deleted'=>1 ]);

			foreach ($import_data as $import_row) {
				$this->import_staff($import_row['Vorname'], $import_row['Nachname'],
					$import_row['Verfuegbarkeit'], $import_row['Arbeitszeit'], $import_row['Zielaufgabe']);
			}

			// For those that are still deleted, remove presense and group leadership:
			$deleted_list = db_array_2("SELECT stf_id FROM bf_staff WHERE stf_deleted = TRUE", [ ]);
			foreach ($deleted_list as $stf_id) {
				$this->cancel_group_leader($stf_id, true);
			}

			return redirect("admin");
		}

		$import_kids_form = new Form('import_kids_form', '', 1, [ 'class'=>'input-table' ]);
		$kids_import_file = $import_kids_form->addTextInput('kids_import_file', 'Import File', 'Kids(1).csv');
		$import_kids = $import_kids_form->addSubmit('import_kids', 'Kinder Importieren', ['class'=>'button-black']);

		if ($import_kids->submitted()) {
			$import_file = $kids_import_file->getValue();
			if (str_contains($import_file, '/'))
				die('Wrong file name: '.$import_file);
			if (!file_exists($imp_exp_path.'import/'.$import_file))
				die('File not found: '.$imp_exp_path.'import/'.$import_file);

			$this->export_kids($imp_exp_path.'export/');

			// Empty the tables:
			$this->db->query('DELETE FROM bf_kids', [ ]);
			$this->db->query('DELETE FROM bf_history', [ ]);

			setlocale(LC_ALL, 'de_DE.utf-8');
			$import_data = csv_to_array($imp_exp_path.'import/'.$import_file);

			foreach ($import_data as $import_row) {
				$an_number = (integer) $import_row['AN-Nummer'];
				$parents = $this->all_to_and($import_row['Vorname der Eltern'], ' & ');
				$tels = $this->all_to_and($import_row['Handy der Eltern'], ' | ');
				
				$data = [
					'kid_number'=>$an_number-800,
					'kid_reg_num'=>$an_number,
					'kid_fullname'=>$import_row['Name des Kindes'],
					'kid_birthday'=>str_to_date($import_row['Geburtstag des Kindes'])->format('Y-m-d'),
					'kid_notes'=>$import_row['Zusätzliche Informationen'],
					'kid_parent_firstname'=>$parents,
					'kid_parent_lastname'=>$import_row['Nachname der Eltern'],
					'kid_parent_cellphone'=>$tels
				];
				$builder = $this->db->table('bf_kids');
				$builder->insert($data);
			}

			/*
			// IMPORT 2018 -test data:
			foreach ($kids_import as $kid) {
				$data = [
					'kid_id'=>$kid[0],
					'kid_number'=>$kid[1],
					'kid_fullname'=>$kid[2],
					'kid_birthday'=>$kid[4],
					'kid_notes'=>$kid[19],
					'kid_parent_firstname'=>$kid[6],
					'kid_parent_lastname'=>$kid[7],
					'kid_parent_cellphone'=>$kid[8]
				];
				$this->db->insert('bf_kids', $data);
			}

			$age = 0;
			foreach ($history_import as $hist) {
				$data = [
					'hst_kid_id'=>$hist[1],
					'hst_stf_id'=>$hist[2],
					'hst_timestamp'=>$hist[3],
					'hst_action'=>$hist[4],
					'hst_escalation'=>$hist[5],
					'hst_notes'=>$hist[7],
					'hst_age_level'=>$age,
					'hst_group_number'=>1
				];
				$this->db->insert('bf_history', $data);
				$age++;
				if ($age == 3) $age = 0;
			}
			*/

			return redirect("admin");
		}
	
		$show_deleted_form = new Form('show_deleted_form', '', 1, [ 'class'=>'input-table' ]);
		$set_how_deleted = $show_deleted_form->addSelect('set_how_deleted', '',
			[ 0=>'', 1=>'Show Deleted', 2=>'Show Only Deleted'], $show_deleted_staff, [ 'onchange'=>'this.form.submit()' ]);
		if ($set_how_deleted->submitted()) {
			$show_deleted_staff = $set_how_deleted->getValue();
			$this->db_model->set_setting('show-deleted-staff', (integer) $show_deleted_staff);
			return redirect("admin");
		}
	
		$this->header('Database update');
		table(array('style'=>'border-collapse: collapse;'));

		tr();
		td([ 'class'=>'left-panel', 'align'=>'left', 'valign'=>'top' ]);
		out("Select the current session:");
		$select_period_form->show();
		_td();
		_tr();

		tr(td(hr()));

		tr();
		td();
		$import_staff_form->show();
		_td();
		_tr();

		tr(td(hr()));

		tr();
		td();
		$import_kids_form->show();
		_td();
		_tr();

		tr(td(hr()));

		tr();
		td([ 'class'=>'left-panel', 'align'=>'left', 'valign'=>'top' ]);
		out("Show Deleted Staff:");
		$show_deleted_form->show();
		_td();
		_tr();

		_table();
		$this->footer();
		return '';
	}
}
