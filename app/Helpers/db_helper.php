<?php
namespace App\Controllers;

function db_array_2($sql, $sqlargs = array()) {
	$db = db_connect();

	$query = $db->query($sql, $sqlargs);
	$fields = $query->getFieldNames();
	$result = array();
	while ($row = $query->getUnbufferedRow('array')) {
		if (count($fields) == 1)
			$result[$row[$fields[0]]] = $row[$fields[0]];
		else
			$result[$row[$fields[0]]] = $row[$fields[1]];
	}
	return $result;
}

function db_1_value($sql, $sqlargs = array()) {
	$db = db_connect();

	$query = $db->query($sql, $sqlargs);
	$row = $query->getRowArray();
	if (isset($row))
		return reset($row);
	return null;
}

// Returns an array or rows, indexed by the first column
function db_array_n($sql, $sqlargs = array()) {
	$db = db_connect();

	$query = $db->query($sql, $sqlargs);
	$result = array();
	while ($row = $query->getUnbufferedRow('array')) {
		$result[reset($row)] = $row;
	}
	return $result;
}

// Return an array or rows
function db_row_array($sql, $sqlargs = array()) {
	$db = db_connect();

	$query = $db->query($sql, $sqlargs);
	$result = array();
	while ($row = $query->getUnbufferedRow('array')) {
		$result[] = $row;
	}
	return $result;
}

function db_insert($table, $data, $modify_time = '') {
	$db = db_connect();

	// Turn database debug off:
	$orig_db_debug = $db->db_debug;
	$db->db_debug = false;

	$builder = $db->table($table);
	if (!empty($modify_time))
		$builder->set($modify_time, 'NOW()', false);
	$builder->insert($data);
	if ($db->error()['code'] == 1062)
		return 0;
	$id_v = $db->insertID();

	$db->db_debug = $orig_db_debug;
	return $id_v;
}

function db_escape($inp) {
	if (is_array($inp))
		return array_map(__METHOD__, $inp);

	if (!empty($inp) && is_string($inp)) {
		return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
	}

	return $inp;
}