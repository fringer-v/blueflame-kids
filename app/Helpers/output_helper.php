<?php
namespace App\Controllers;

/**
 * Output Class
 *
 * This class, along with the 'class factory' method out() are used to safely generate
 * HTML code. It works as follows:
 *
 * - The first arg to out() is a format string, followed by any number of parameters
 * - The sequence: [] in the format string will be replaced by the corresponding parameter
 * - A parameter can either be a string or another instance of Output
 * - All string parameters will have special HTML characters escaped
 * - An Output instance can be printed by calling show()
 * - You can append further strings / instances of Output to an existing instance with
 *   the add() method
 *
 * The format string should always be a hardcoded, static string. 
 *
 * Note: if the result string is never requested from the Output instance (with html()),
 * the string will automatically be printed in the __destruct() method.
 *
 * This is so you can print directly by instantiating an instance of Output without assigning
 * it to a variable.
 * (ie. you can use out('hello world');, you dont need to do out('hello world')->show(); )
 * If you ever need to create an instance of Output that never gets used, make sure to
 * explicitly call the destroy() method;
 *
 */

class BaseOutput {
	private $auto_echo = true;
	private $hidden = false;

	public function __destruct() {
		// If this instance has not been shown then show it now.
		// This is so you can print with out('text');
		// rather than typing out('text')->show();
		if ($this->auto_echo)
			$this->show();
	}
	
	// Destroy the object without printing it
	public function destroy() {
		$this->hidden = true;
		$this->__destruct();
	}
	
	public function autoEchoOff() {
		$this->auto_echo = false;
	}

	public function autoEchoOn() {
		$this->auto_echo = true;
	}

	public function autoEcho() {
		return $this->auto_echo;
	}

	public function html() {
		$this->auto_echo = false;
		if ($this->hidden)
			return '';
		return $this->output();
	}

	public function hide() {
		$this->hidden = true;
	}

	public function isHidden() {
		return $this->hidden;
	}

	public function show() {
		if (!$this->hidden)
			echo $this->html();
	}

	// This way you can append instances of Output to strings
	public function __toString() {
		return $this->html();
	}

	// Override this to produce the output:
	public function output() {
		return '';
	}
}

class Output extends BaseOutput {
	private $output = "";
	
	//note that $format should always be a hardcoded, static string
	public function __construct($format, $params = array()) {
		if ($params == null) {
			$params = array();
		}
		else if (!is_array($params)) {
			// Treat a single non-array parameter as an array with one entry
			$params = array($params);
		}

		$fmts = explode("[]", $format);
		if (count($params) !== count($fmts) - 1) {
			fatal_error('Number of placeholders does not match number of parameters, format string: '.$format);
			return;
		}

		$this->output = $fmts[0];
		for ($i = 1; $i < count($fmts); $i++) {
			$param = $params[$i-1];
			if ($param instanceof Output)
				$param = $param->html();
			else
				$param = htmlspecialchars($param, ENT_QUOTES);
			$this->output .= $param;
			$this->output .= $fmts[$i];
		}
	}
	
	//append strings or more output instances which will be printed after this one
	public function add(/*item1, item2, ....*/){
		foreach (func_get_args() as $item) {
			$this->output .= $item;
		}

		return $this;
	}

	public function output() {
		return $this->output;
	}

	public function isempty() {
		return empty($this->output);
	}
}

function clear_persistent_values($prefix)
{
	foreach($_SESSION as $key => $value) {
		if (str_startswith($key, $prefix.'.')) {
			unset($_SESSION[$key ]);
		}	
	}
}

/*
 * note that $format should always be a hardcoded, static string
 * usage: 
 *		out($format [,$param1] [,$param2] ...);
 */
function out($format) {
	return new Output($format, array_slice(func_get_args(), 1));
}

function print_message_box($msg, $class, $attr = null) {
	if (gettype($msg) == "string")
		$msg = explode("\n", $msg);

	if (gettype($msg) == "array") {
		if (empty($attr))
			$attr = [ ];
		$attr['class'] = 'message-box '.$class;
		$out = div($attr);
		$i = 0;
		foreach ($msg as $m) {
			if ($i != 0)
				$out->add(br());
			$i++;
			$out->add($m);
		}
		$out->add(_div());
	}
	else
		$out = div(array('class'=>'message-box '.$class), $msg);
	return $out;
}

function print_error($message, $attr = null) {
	return print_message_box($message, "error-box", $attr);
}

function print_warning($message, $attr = null) {
	return print_message_box($message, "warning-box", $attr);
}

function print_success($message, $attr = null) {
	return print_message_box($message, "success-box", $attr);
}

function print_info($message, $attr = null) {
	return print_message_box($message, "info-box", $attr);
}

class Nix {
}

function nix() {
	return new Nix();
}

class Table {
	private $sql;
	private $sqlargs;
	private $attributes;
	private $page_url = '';
	private $per_page = 0;
	private $curr_page = null;
	private $query = null;
	protected $order_by = null;
	private $page_sql = null;
	private $row_count = null;

	public function __construct($sql = '', $sqlargs = array(), $attributes = array()) {
		$this->sql = $sql;
		$this->sqlargs = $sqlargs;
		$this->attributes = $attributes;
	}

	public function setPageQuery($pq) {
		$this->page_sql = $pq;
	}

	public function setPagination($page_url, $per_page, $curr_page) {
		if (empty($this->page_sql) && !str_startswith($this->sql, "SELECT SQL_CALC_FOUND_ROWS "))
			fatal_error('SQL must begin with SQL_CALC_FOUND_ROWS for pagination');
		if (str_contains($this->sql, "LIMIT"))
			fatal_error('SQL may not include LIMIT for pagination');
		$this->page_url = $page_url;
		$this->per_page = (integer) $per_page;
		$this->curr_page = $curr_page;
		if ($curr_page != null)
			$dummy = $curr_page->getValue(); // Must be of type InputField!
	}
	
	public function setOrderBy($order_by) {
		$this->order_by = $order_by;
	}

	public function getTableAttributes() {
		return $this->attributes;
	}

	public function columnTitle($field) {
		return $field;
	}

	public function columnAttributes($field) {
		return null;
	}

	public function cellValue($field, $row) {
		return $row[$field];
	}

	private function doQuery() {
		if (!is_null($this->query))
			return;

		$db = db_connect();

		$sql = $this->sql;

		if (!is_empty($this->order_by))
			$sql .= ' ORDER BY '.$this->order_by;

		if ($this->per_page > 0) {
			$curr_page = 1;
			if ($this->curr_page != null)
				$curr_page = $this->curr_page->getValue();
			if ($curr_page < 1)
				$curr_page = 1;
			$offset = $this->per_page * ($curr_page-1);
			$sql .= ' LIMIT '.$this->per_page.' OFFSET '.$offset;
		}

		$this->query = $db->query($sql, $this->sqlargs);
	}

	public function html() {
		$this->doQuery();

		$fields = $this->query->getFieldNames();
		
		$out = table($this->getTableAttributes());
		$out->add(thead());
		$out->add(tr());
		$row_count = 0;
		foreach ($fields as $field) {
			$title = $this->columnTitle($field);
			if (!($title instanceof Nix)) {
				$row_count++;
				$attr = $this->columnAttributes($field);
				if ($attr == null)
					$attr = [ 'style'=>'text-align: left;' ];
				$out->add(th($attr, $title));
			}
		}
		$out->add(_tr());
		$out->add(_thead());

		$out->add(tbody());
		if ($row = $this->query->getUnbufferedRow('array')) {
			do {
				$out->add(tr());
				foreach ($fields as $field) {
					$value = $this->cellValue($field, $row);
					if (!($value instanceof Nix)) {
						$attr = $this->columnAttributes($field);
						if ($attr == null)
							$attr = [ 'style'=>'text-align: left;' ];
						$out->add(td($attr, $value));
					}
				}
				$out->add(_tr());
			}
			while ($row = $this->query->getUnbufferedRow('array'));
		}
		else {
			$out->add(tr());
			$out->add(td(array('colspan'=>$row_count), 'Keine Daten gefunden'));
			$out->add(_tr());
		}
		$out->add(_tbody());
		$out->add(_table());
		return $out;
	}

	public function paginationHtml() {
		if (is_empty($this->per_page))
			return;

		if (empty($this->page_sql)) {
			retry:
			$this->doQuery();

			$max_rows = (integer) db_1_value('SELECT FOUND_ROWS()');
			$max_page = (integer) (($max_rows + $this->per_page - 1) / $this->per_page);

			if ($this->curr_page == null)
				$curr_page = 1;
			else
				$curr_page = $this->curr_page->getValue();
			if ($curr_page < 1) {
				$curr_page = 1;
				$this->curr_page->setValue($curr_page);
			}
			else if ($curr_page > max(1, $max_page)) {
				$curr_page = 1;
				$this->curr_page->setValue($curr_page);
				$this->query = null;
				goto retry;
			}

			$out = div(array('class'=>'pagination-div'));
			$out->add(href(url($this->page_url.'1'), '⇤'));
			$out->add(nbsp());
			$out->add(href(url($this->page_url.max(1, $curr_page-1)), '<'));
			for ($i = 1; $i <= $max_page; $i++) {
				$out->add(nbsp());
				if ($curr_page == $i)
					$out->add(href(url($this->page_url.$i), $i, array('selected')));
				else
					$out->add(href(url($this->page_url.$i), $i));
			}
			$out->add(nbsp());
			$out->add(href(url($this->page_url.min($max_page, $curr_page+1)), '>'));
			$out->add(nbsp());
			$out->add(href(url($this->page_url.$max_page), '⇥'));
			$out->add(_div());
		}
		else {
			$db = db_connect();

			$sql = $this->page_sql;

			if (!is_empty($this->order_by))
				$sql .= ' ORDER BY '.$this->order_by;

			$query = $db->query($sql, $this->sqlargs);
			$fields = $query->getFieldNames();

			$pages = [];			
			$max_rows = 0;
			$page = 1;
			$i = 0;
			$from = '';
			while ($row = $query->getUnbufferedRow('array')) {
				$max_rows++;
				$i++;
				$to = substr($row[$fields[0]], 0, 3);
				if ($i == 1)
					$from = $to;
				else if ($i == $this->per_page) {
					$pages[$page] = $from.' - '.$to;
					$from = '';
					$page++;
					$i = 0;
				}
			}
			if (!empty($from))
				$pages[$page] = $from.' - '.$to;

			$max_page = (integer) (($max_rows + $this->per_page - 1) / $this->per_page);

			if ($this->curr_page == null)
				$curr_page = 1;
			else
				$curr_page = $this->curr_page->getValue();
			if ($curr_page < 1 || $curr_page > max(1, $max_page)) {
				$curr_page = 1;
				$this->curr_page->setValue($curr_page);
			}

			$out = div(array('class'=>'pagination-div'));
			foreach ($pages as $page=>$label) {
				if ($page > 1)
					$out->add(nbsp());
				$out->add(href(url($this->page_url.$page), $label, $curr_page == $page ? [ 'selected' ] : [ ]));
			}
			$out->add(_div());
		}

		$this->row_count = $max_rows;
		return $out;
	}
	
	public function getRowCount() {
		return $this->row_count;
	}
}

class AsyncLoader {
	private $id;
	private $page;
	private $params;

	public function __construct($id, $page, $params = array()) {
		$this->id = $id;
		$this->page = $page;
		$this->params = $params;
	}

	public function html() {
		//the div the table will be loaded into
		$out = div(array('id'=>$this->id), table(array('style'=>'width: 100%;'), tr(td('Loading...'))));
		$out->add($this->loadPageHtml());
		return $out;
	}

	function loadPageHtml() {
		$out = script();
		if (arr_is_assoc($this->params)) {
			$out->add(out('function [](', $this->id));
			$i=0;
			foreach ($this->params as $param=>$def_value) {
				if ($i>0)
					$out->add(out(','));
				$out->add(out('[]='.$def_value, $param));
				$i++;
			}
			$out->add(out(') { var params = {};'));
			foreach ($this->params as $param=>$def_value) {
				$out->add(out('params["[]"] = [];', $param, $param));
			}
			$out->add(out('$("#[]").load("[]", params);}', $this->id, $this->page));
			$out->add(out('[](); ', $this->id)); // Call the function for the first time!
		}
		else {
			$out->add(out('function [](ev = null) {', $this->id));
			$out->add(out('if (ev != null && ev.key.length != 1) return;'));
			$out->add(out('loadPage("[]", "[]"', $this->id, $this->page));
			foreach ($this->params as $param) {
				$out->add(out(', "[]"', $param));
			}
			$out->add(');} ');
			$out->add(out('[](); ', $this->id)); // Call the function for the first time!

			// Create triggers for parameters:
			foreach ($this->params as $param) {
				$out->add(out('$("#[]").on("keyup", function(event) { [](event); });', $param, $this->id));
			}
		}

		$out->add(_script());
		return $out;
	}
}


