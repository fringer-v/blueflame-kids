<?php
namespace App\Controllers;

function error_log_string($val) {
	switch (gettype($val)) {
		case "object":
		case "array":
		case "resource":
			$val = var_export($val, true);
			break;
		case "NULL":
			return "NULL";
	}
	$val = str_replace("\n", " ", $val);
	$val = str_replace("\t", " ", $val);
	$val = str_replace("\r", " ", $val);
	$val = str_replace("  ", " ", $val);
	$val = str_replace("  ", " ", $val);
	$val = str_replace("  ", " ", $val);
	return (string) $val;
}

function error_log_message($log_items)
{
	if (!is_array($log_items))
		$log_items = array ($log_items);

	$outstr = "";
	$i = 0;
	foreach($log_items as $item) {
		if ($i > 0)
			$outstr .= ", ";
		$outstr .= error_log_string($item);
		$i++;
	}

	error_log((string) $outstr);
}

function error_log_stack()
{
	$bt = array_slice(debug_backtrace(false), 1);
	foreach($bt as $backtrace_item){
		$file_name = array_pop(explode("/", $backtrace_item["file"]));
		error_log_message("- ".$file_name.":".$backtrace_item["line"]. " - ".$backtrace_item["function"]);
	}
}

function fatal_error($message)
{
	print_error($message);
	error_log_message($message);
	error_log_stack();
	exit -1;
}

// This function may only be used temporarily in code:
function bugout()
{
	error_log_message(func_get_args());
}

function warningout()
{
	error_log_message(func_get_args());
}
