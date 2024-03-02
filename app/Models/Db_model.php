<?php
namespace App\Controllers;

use CodeIgniter\Model;

// The current database version:
// 24 - Allow various fields in participant to be NULL
// 25 - Added bf_locations table
// 26 - Added leader and coleader to groups
// 27 - Changed kid_registered field to status
// 41 - Changes for Blueflame 2020
// 42 - Added grp_size_hints
// 43 - Added bf_staff.stf_deleted
// 44 - Added bf_staff.stf_notes
// 45 - Added kid_reg_num, change kid_parent_firstname(50->120)
// 46 - Added change kid_parent_lastname(->120), kid_parent_cellphone(->120)
// 47 - kid_registered DEFAULT set to 0 (REG_NO)
define("DB_VERSION", 48);

class DB_model extends Model {
	private $settings = array();

	private $forge;

	private $meta_settings = array(
			"database-version" => array("integer", 0),	// type, default_value
			"current-period" => array("integer", 2),	// type, default_value
			"show-deleted-staff" => array("integer", 0)	// type, default_value
		);

	protected function initialize() {
		parent::initialize();
		//$this->db = db_connect();
		//$this->db->query("SET LOCAL time_zone='SYSTEM'");
		$this->forge = \Config\Database::forge();
	}

	public function get_setting($name) {
		if (empty($this->settings)) {
			if ($this->db->tableExists('bf_setting')) {
				$query = $this->db->query('SELECT stn_name, stn_value, stn_type FROM bf_setting');
				$settings = array();
				foreach ($query->getResult() as $row) {
					$val = (string) $row->stn_value;
					switch ($row->stn_type) {
						case "integer":
							$val = (integer) $val;
							break;
						case "boolean":
							$val = (boolean) $val;
							break;
					
					}
					$settings[$row->stn_name] = $val;
				}
				$this->settings = $settings;
			}				
		}
		if (array_key_exists($name, $this->settings))
			return $this->settings[$name];
		if (array_key_exists($name, $this->meta_settings))
			return $this->meta_settings[$name][1];
		fatal_error("Unknown setting: ".$name);
	}
		
	public function set_setting($name, $val) {
		if (!array_key_exists($name, $this->meta_settings))
			fatal_error("Unknown setting: ".$name);
		$type = $this->meta_settings[$name][0];
		if (gettype($val) != $type)
			fatal_error("Setting: incorrect type for: ".$name.", required type: ".$type);
		
		$sql = "INSERT INTO bf_setting (stn_name, stn_type, stn_value) VALUES (?, ? , ?)
			ON DUPLICATE KEY UPDATE stn_value=VALUES(stn_value);";
		$this->db->query($sql, array($name, $type, $val));
		$this->settings[$name] = $val;
	}

	public function up_to_date() {
		return $this->get_setting("database-version") == DB_VERSION;
	}

	public function update_database() {
		$fields = array(
			'id'=>array('type'=>'VARCHAR', 'constraint'=>'128'),
			'ip_address'=>array('type'=>'VARCHAR', 'constraint'=>'45'),
			'timestamp'=>array('type'=>'INTEGER', 'unsigned'=>true, 'default'=>0),
			'data'=>array('type'=>'BLOB')
		);
		$this->create_or_update_table('bf_sessions', $fields, array('timestamp'));

		$fields = array(
			'stn_name VARCHAR(40) NOT NULL PRIMARY KEY',
			'stn_type'=>array('type'=>'VARCHAR', 'constraint'=>'10'),
			'stn_value'=>array('type'=>'VARCHAR', 'constraint'=>'400')
		);
		$this->create_or_update_table('bf_setting', $fields);

		$fields = array(
			'stf_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'stf_username'=>array('type'=>'VARCHAR', 'constraint'=>'100', 'unique'=>true),
			'stf_fullname'=>array('type'=>'VARCHAR', 'constraint'=>'200', 'unique'=>true),
			'stf_privs'=>array('type'=>'INTEGER', 'unsigned'=>true, 'default'=>0),
			'stf_password VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL',
			'stf_role'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'default'=>0),
			'stf_registered'=>array('type'=>'BOOLEAN', 'default'=>false),
			'stf_loginallowed'=>array('type'=>'BOOLEAN', 'default'=>true),
			'stf_technician'=>array('type'=>'BOOLEAN', 'default'=>false),
			'stf_deleted'=>array('type'=>'BOOLEAN', 'default'=>false),
			'stf_notes'=>array('type'=>'VARCHAR', 'constraint'=>'500'),
			'stf_reserved_age_level'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'stf_reserved_group_number'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'stf_reserved_count'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'INDEX stf_reserved (stf_reserved_age_level, stf_reserved_count)'
		);
		$this->create_or_update_table('bf_staff', $fields);

		$fields = array(
			'per_staff_id'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>false),
			'per_period'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>false),
			'per_age_level'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'per_group_number'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'per_location_id'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>true),
			'per_present'=>array('type'=>'BOOLEAN', 'default'=>false, 'null'=>false),
			'per_is_leader'=>array('type'=>'BOOLEAN', 'default'=>false, 'null'=>false),
			'per_my_leader_id'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>true),
			'per_age_level_0'=>array('type'=>'BOOLEAN', 'default'=>false, 'null'=>false),
			'per_age_level_1'=>array('type'=>'BOOLEAN', 'default'=>false, 'null'=>false),
			'per_age_level_2'=>array('type'=>'BOOLEAN', 'default'=>false, 'null'=>false),
			'PRIMARY KEY per_primary_key (per_staff_id, per_period)'
		);
		$this->create_or_update_table('bf_period', $fields);

		$fields = array(
			'kid_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'kid_number'=>array('type'=>'INTEGER', 'unsigned'=>true, 'unique'=>true, 'null'=>false),
			'kid_reg_num'=>array('type'=>'INTEGER', 'unsigned'=>true, 'unique'=>true, 'null'=>true),
			'kid_fullname'=>array('type'=>'VARCHAR', 'constraint'=>'100', 'null'=>true),
			'kid_birthday'=>array('type'=>'DATE', 'null'=>true),
			'kid_present_periods'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>false, 'default'=>0), // Uses bits PERIOD_FRIDAY ... PERIOD_SUNDAY
			'kid_registered'=>array('type'=>'TINYINT', 'unsigned'=>true, 'null'=>false, 'default'=>REG_NO),
			'kid_registered_periods'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>false, 'default'=>0), // Uses bits PERIOD_FRIDAY ... PERIOD_SUNDAY
			'kid_parent_id'=>array('type'=>'INTEGER', 'unsigned'=>true),
			'kid_age_level'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'kid_group_number'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'kid_createtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
			'kid_modifytime'=>array('type'=>'DATETIME', 'null'=>false),
			'kid_create_stf_id'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>true),
			'kid_modify_stf_id'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>true),
			'kid_call_status'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'kid_call_escalation'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'kid_call_start_time'=>array('type'=>'DATETIME', 'null'=>true),
			'kid_call_change_time'=>array('type'=>'DATETIME', 'null'=>true),
			'kid_wc_time'=>array('type'=>'DATETIME', 'null'=>true), // Not null means WC!
			'kid_notes'=>array('type'=>'TEXT'),
			'UNIQUE INDEX kid_name_index (kid_fullname)',
			'INDEX kid_group_index (kid_age_level, kid_group_number)',
			'INDEX kid_call_status_index (kid_call_status, kid_call_change_time)'
		);
		$this->create_or_update_table('bf_kids', $fields);

		$fields = array(
			'par_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'par_code'=>array('type'=>'VARCHAR', 'constraint'=>'40', 'unique'=>true),
			'par_email'=>array('type'=>'VARCHAR', 'constraint'=>'200', 'null'=>true, 'unique'=>true),
			'par_fullname'=>array('type'=>'VARCHAR', 'constraint'=>'200', 'unique'=>true),
			'par_cellphone'=>array('type'=>'VARCHAR', 'constraint'=>'120', 'null'=>true),
			'par_password VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL'
		);
		$this->create_or_update_table('bf_parents', $fields);

		$fields = array(
			'reg_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'reg_parent_id'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>false),
			'reg_kid_id'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>false, 'default'=>0),
			'reg_kid_fullname'=>array('type'=>'VARCHAR', 'constraint'=>'100', 'null'=>true),
			'reg_kid_birthday'=>array('type'=>'DATE', 'null'=>true),
			'reg_kid_present_periods'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>false, 'default'=>0), // Uses bits PERIOD_FRIDAY ... PERIOD_SUNDAY
		);
		$this->create_or_update_table('bf_register_kids', $fields);

		$fields = array(
			'grp_period'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>false),
			'grp_age_level'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>false),
			'grp_count'=>array('type'=>'INTEGER', 'unsigned'=>true, 'null'=>true),
			'grp_size_hints'=>array('type'=>'VARCHAR', 'constraint'=>'400', 'null'=>true), /* Comma separated list! */
			'PRIMARY KEY grp_primary_key (grp_period, grp_age_level)'
		);
		$this->create_or_update_table('bf_groups', $fields); // Kleingruppe

		$fields = array(
			'hst_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'hst_kid_id'=>array('type'=>'INTEGER', 'unsigned'=>true),
			'hst_stf_id'=>array('type'=>'INTEGER', 'unsigned'=>true),
			'hst_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
			'hst_action'=>array('type'=>'SMALLINT', 'unsigned'=>true),
			'hst_escalation'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'hst_age_level'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'hst_group_number'=>array('type'=>'SMALLINT', 'unsigned'=>true, 'null'=>true),
			'hst_notes'=>array('type'=>'TEXT'),
			'INDEX hst_kid_id_timestamp_index (hst_kid_id, hst_timestamp)'
		);
		$this->create_or_update_table('bf_history', $fields);

		$fields = array(
			'loc_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
			'loc_name'=>array('type'=>'VARCHAR', 'constraint'=>'100', 'unique'=>true)
		);
		$this->create_or_update_table('bf_locations', $fields);

		// Login sa, pwd: sasa:		
		$this->add_staff('sa', 'System Admin', '$2y$10$csj8Jqmp0vV6RWQzpHdWZ.j5mzA.f79wUixba6IGWPIr/vGC3FXLe',1,0);
		/*
		drop table bf_groups;
		drop table bf_history;
		drop table bf_kids;
		drop table bf_locations;
		drop table bf_parents;
		drop table bf_period;
		drop table bf_register_kids;
		drop table bf_sessions;
		drop table bf_setting;
		drop table bf_staff;

		$this->add_staff('Admin', 'Administrator', '$2y$10$orVZz8QD6iuSqg7G//Rvm.OFWFxFEQ1fSFFuc8H2Kn5bJYqRZ7FZW',1,0);
		$this->add_staff('Paul','Paul McCullagh','$2y$10$libvYITOuwrLptOCTOJ3quuFWfnzmTDIHLi.V4BlBwGzAnseJbrW.',1,0);
		$this->add_staff('Andrea','Andrea McCullagh','$2y$10$/WwXV9sFN5mf.VDe9YlwB.UCtUYznnyR8cnUsqXzq4X/SzhChuIR6',1,0);
		$this->add_staff('Jessica ','Jessica McCullagh','$2y$10$ftjqgeTiSNRLKzpbaY.z9ukwbpWRu/RZyK8xTBuXxWrHnv7ghzcDa',1,0);
		$this->add_staff('Daniel','Daniel Maurer','$2y$10$I55mJJP9RVUvrg7YH6zH7.3OuyrL6ZfExETKnr4EqEKeq0h92Jz/q',1,0);
		$this->add_staff('Technik','Technik','$2y$10$nX0Cb.lJJHbf1IqofA84Ee3yhGw7rHUChjHAxZcd9VwsRo0FGZRSO',1,1);
		$this->add_staff('jan_t','jan thomsen','$2y$10$MBRPYlu7hizAudyEVVlLW.iXMKkTguQ4hc3ODnetDtnkwQRPzFmqG',1,0);
		$this->add_staff('maske','Janina Maske ','$2y$10$Ghz9L1Pud7MwCOuRZWgm9uV.LpIXM.7Gv4d.obYEr31dJOsTFvo36',1,1);
		$this->add_staff('Monika ','Monika Sievers');
		$this->add_staff('Christiane ','Christiane Leistner');
		$this->add_staff('Melinda ','Melinda Alisch');
		$this->add_staff('Patrice ','Patrice George');
		$this->add_staff('Bärbel ','Bärbel Löffel-Schröder');
		$this->add_staff('Anna ','Anna Lehnert Martinez');
		$this->add_staff('Jutta ','Jutta Kramer');
		$this->add_staff('Marc ','Marc Kröckel');
		$this->add_staff('Petra G.','Petra G.');
		$this->add_staff('Ella ','Ella Gilgen');
		$this->add_staff('Wolfgang ','Wolfgang Makowski');
		$this->add_staff('Gerhart ','Gerhart Kramer');
		$this->add_staff('Marius ','Marius Wunner');
		$this->add_staff('Daniel I.','Daniel Illmann');
		$this->add_staff('Debora ','Debora Koch');
		$this->add_staff('Julia ','Julia Zech');
		$this->add_staff('Amos ','Amos Riegraf');
		$this->add_staff('Sarah B.','Sarah B.');
		$this->add_staff('Björn H.','Björn Hammerich');
		$this->add_staff('Benjamin ','Benjamin Staats');
		$this->add_staff('Hannes ','Hannes Olszewski');
		$this->add_staff('Jeanette ','Jeanette Ingwersen');
		$this->add_staff('Leo ','Leo Thiel');
		$this->add_staff('Kim Merle ','Kim Merle Johannsen');
		$this->add_staff('Björn S.','Björn Sperber');
		$this->add_staff('Luise ','Luise Baumgarten');
		$this->add_staff('Martin ','Martin Grünhagel');
		$this->add_staff('Daniela ','Daniela Zender');
		$this->add_staff('Tabea K.','Tabea Katzenmaier');
		$this->add_staff('Anna K.','Anna Katharina Kopatz');
		$this->add_staff('Niklas ','Niklas Braun');
		$this->add_staff('Tim E.','Tim E.');
		$this->add_staff('Sonja ','Sonja Christensen');
		$this->add_staff('Oliver ','Oliver Kraatz');
		$this->add_staff('Shayleen ','Shayleen Zavazava');
		$this->add_staff('Michael ','Michael Staben');
		$this->add_staff('Detlef ','Detlef Tietgen');
		$this->add_staff('Erin ','Erin Cahue');
		$this->add_staff('Sarah ','Sarah Steinweh');
		$this->add_staff('Alexandre ','Alexandre Barbosa');
		$this->add_staff('Birgit M.','Birgit Makowski');
		$this->add_staff('Winnie ','Winnie Wei Wei Lüttgens');
		$this->add_staff('Lukas T.','Lukas T.');
		$this->add_staff('Tabea ','Tabea Dunzik');
		$this->add_staff('Lilly ','Lilly Burg');
		$this->add_staff('Birgit F.','Birgit Flader');
		$this->add_staff('Fabian ','Fabian Gladigau');
		$this->add_staff('Karoline ','Karoline Sanchez');
		$this->add_staff('Tizia ','Tizia Geske');
		$this->add_staff('Samuel ','Samuel Sanchez');
		$this->add_staff('Nkaya ','Nkaya Ugne');
		$this->add_staff('Daniel S.','Daniel Sanchez');
		$this->add_staff('John ','John Odigie');
		$this->add_staff('Annemarie Q.','Annemarie Q.');
		$this->add_staff('Joy G.','Joy G.');
		$this->add_staff('Susanne ','Susanne Hustadt');
		$this->add_staff('Svenja ','Svenja Richardon');
		$this->add_staff('Eugen ','Eugen Illg');
		$this->add_staff('Cathrin ','Cathrin Illg');
		$this->add_staff('Bettina ','Bettina Bieritz');
		$this->add_staff('Wilfried K.','Wilfried Kemkes');
		$this->add_staff('Petra','Petra Grundmann');
		$this->add_staff('Maria','Maria Illmann');
		$this->add_staff('Karin','Karin Bärhold');
		$this->add_staff('Mario','Mario Bärhold');
		$this->add_staff('Ingrid','Ingrid Fingerhut-Wortmann');
		$this->add_staff('Moritz','Moritz Drescher');
		$this->add_staff('Sabrina','Sabrina Schuldt');
		$this->add_staff('Katrina','Katrina Zöller');
		$this->add_staff('Dorett','Dorett Maurer');
		$this->add_staff('Klevi Muka','Klevi Muka');
		$this->add_staff('Michelle','Michelle Staats');
		$this->add_staff('Jutta N','Jutta N');
		$this->add_staff('Johanna M','Johanna M');
		$this->add_staff('Insa Schulze','Insa Schulze');
		$this->add_staff('Patte G','Patte G');
		*/

		$this->add_location('Buurndeel');
		$this->add_location('Thronsaal');
		$this->add_location('Raum A (alter Thronsaal)');
		$this->add_location('Raum B (Catering Zelt)');
		$this->add_location('Ranger');

		$this->set_setting('database-version', DB_VERSION);

		$tables = db_array_2('SHOW TABLES');
		foreach ($tables as $table) {
			if (str_startswith($table, 'old_'.DB_VERSION.'_')) {
				$this->forge->dropTable($table);
			}
		}
	}

	public function add_staff($username, $fullname, $password = '', $loginallowed = 0, $technician = 0) {
		$count = (integer) db_1_value('SELECT COUNT(*) FROM bf_staff WHERE stf_username = ?', array($username));
		if ($count == 0)
			$this->db->query('INSERT bf_staff (stf_username, stf_fullname, stf_password, stf_loginallowed, stf_technician) VALUES (?, ?, ?, ?, ?)',
				array($username, $fullname, $password, $loginallowed, $technician));
	}

	public function add_location($loc_name) {
		$count = (integer) db_1_value('SELECT COUNT(*) FROM bf_locations WHERE loc_name = ?', array($loc_name));
		if ($count == 0)
			$this->db->query('INSERT bf_locations (loc_name) VALUES (?)',
				array($loc_name));
	}

	public function create_or_update_table($table_name, $fields, $keys = array()) {
		$new_table = 'new_'.DB_VERSION.'_'.$table_name;
		$old_table = 'old_'.DB_VERSION.'_'.$table_name;
		
		$current_exists = $this->db->tableExists($table_name);
		$new_exists = $this->db->tableExists($new_table);
		$old_exists = $this->db->tableExists($old_table);

		if (!$current_exists && !$old_exists && !$new_exists)
			// New table:
			$this->createTable($table_name, $fields, $keys);
		else {
			if (!$old_exists) {
				$this->createTable($new_table, $fields, $keys);
				$builder = $this->db->table($new_table);
				$builder->truncate();
				$new_exists = true;
			
				// Copy data:
				$fields = $this->db->getFieldNames($table_name);
				$new_fields = $this->db->getFieldNames($new_table);
				$fields = array_intersect($fields, $new_fields);

				$sql = 'INSERT INTO '.$new_table.' ('.implode(",", $fields).') ';
				$sql .= 'SELECT '.implode(",", $fields).' FROM '.$table_name;
				$this->db->query($sql);

				// Current to old:
				$this->forge->renameTable($table_name, $old_table);
			}
			
			if ($new_exists) {
				// New to current:
				$this->forge->renameTable($new_table, $table_name);
			}
		}
	}

	public function createTable($table_name, $fields, $keys = array()) {
		foreach ($fields as $field => $details) {
			if (is_array($details))
				$this->forge->addField(array($field=>$details));
			else
				$this->forge->addField($details);
		}	
		foreach ($keys as $key) {
			$this->forge->addKey($key);
		}
		$attributes = array('ENGINE' => 'InnoDB');
		$this->forge->createTable($table_name, true, $attributes);
	}
}

?>
