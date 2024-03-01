<?php
namespace App\Controllers;

function str_startswith($haystack, $needle){
	return !strncmp($haystack, $needle, strlen($needle));
}

function str_endswith($haystack, $needle) {
	$length = strlen($needle);
	if ($length == 0) {
		return true;
	}
	
	return (substr($haystack, -$length) === $needle);
}

function str_right($str, $value, $search_backwards = false) {
	if ($search_backwards)
		$pos = strrpos($str, $value);
	else
		$pos = strpos($str, $value);
	if ($pos === false) {
		if ($search_backwards)
			return $str;
		return '';
	}
	return substr($str, $pos + strlen($value));
}

function str_left($str, $value, $search_backwards = false) {
	if ($search_backwards)
		$pos = strrpos($str, $value);
	else
		$pos = strpos($str, $value);
	if ($pos === false) {
		if ($search_backwards)
			return '';
		return $str;
	}
	$retValue = substr($str, 0, $pos);
	return $retValue;
}

if (!function_exists('str_contains')) {
	function str_contains($haystack, $needle) {
		return stripos($haystack, $needle) !== false;
	}
}

function str_listappend($list, $value, $sep) {
	if (empty($list))
		return $value;
	return $list.$sep.$value;
}

function str_to_date($val)
{
	if (is_empty($val))
		return new \DateTime();
	if (str_contains($val, '.'))
		$ts = \DateTime::createFromFormat('d.m.Y', $val);
	else
		$ts = \DateTime::createFromFormat('Y-m-d', $val);
	if ($ts === false)
		return null;
	$year = (integer) $ts->format('Y');
	$month = (integer) $ts->format('m');
	$day = (integer) $ts->format('d');
	if ($year < 100)
		$ts->setDate($year+2000, $month, $day);
	return $ts;
}

function str_from_date($ts, $fmt)
{
	return $ts->format($fmt);
}
	
// This function fixes a bug that empty() has with the results of function calls
function is_empty($val) {
	if (!is_array($val))
		$val = trim($val);
	return empty($val);
}

function is_not_empty($val) {
	return !is_empty($val);
}

function is_int_val($data) {
	if (is_int($data))
		return true;
	if (is_string($data) && is_numeric($data))
		return strpos($data, '.') === false;
	return false;
}

function if_empty($val, $def) {
	if (empty($val))
		return $def;
	return $val;
}

function arr_remove_empty($array) {
	return array_filter($array, "is_not_empty");
}

function arr_nvl($array, $index, $default = null) {
	if (isset($array[$index]))
		return $array[$index];
	return $default;
}

function arr_is_assoc($array) {
	return array_keys($array) !== range(0, count($array) - 1);
}

function get_age($dob) {
	if (empty($dob))
		return null;

	if ($dob instanceof \DateTime)
		return $dob->diff(new \DateTime())->format('%y');

    //calculate years of age (input string: YYYY-MM-DD)
    list($year, $month, $day) = explode("-", $dob);

    $year_diff  = date("Y") - $year;
    $month_diff = date("m") - $month;
    $day_diff   = date("d") - $day;

    if ($month_diff < 0 || ($month_diff == 0 && $day_diff < 0))
        $year_diff--;

    return $year_diff;
}

function str_get_age($dob) {
	$age = get_age($dob);
	if (empty($age))
		return '';
	return $age.' Jahre';
}

function format_seconds($totalseconds) {
	$hours = 0;
	$minutes = 0;
	$seconds = 0;
	
	if ($totalseconds > 60) {
		$seconds = $totalseconds % 60;
		$totalMinutes = ($totalseconds - ($totalseconds % 60) ) / 60;
		
		if ($totalMinutes > 60) {
			$minutes = $totalMinutes % 60;
			$hours = ($totalMinutes - ($totalMinutes % 60) ) / 60;
		}
		else
			$minutes = $totalMinutes;
	}
	else
		$seconds = $totalseconds;


	if ($hours == 0 && $minutes == 0)
		return $seconds.'s';

	$result = ':'.str_pad($seconds, 2, '0', STR_PAD_LEFT);

	if ($hours == 0)
		return $minutes.$result.'m';

	return $hours.':'.str_pad($minutes, 2, '0', STR_PAD_LEFT).$result;
}

function datetime_to_unixtime($dt_str)
{
	if (is_empty($dt_str))
		return 0;
	date_default_timezone_set('Europe/Berlin');
	$dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dt_str, new \DateTimeZone(date('T')));
	return $dt->getTimestamp();
}

function how_long_ago($then) {
	$start_time = datetime_to_unixtime($then);
	return format_seconds(time() - $start_time);
}

function str_next_ch(&$i, $file_data) {
	if ($i >= strlen($file_data))
		return null;
	$ch = substr($file_data, $i, 1);
	$i++;
	if ($ch == "\r" && $i < strlen($file_data)) {
		$lf_ch = substr($file_data, $i, 1);
		if ($lf_ch == "\n") {
			$ch = "\n";
			$i++;
		}
	}
	else if ($ch == "\\" && $i < strlen($file_data)) {	
		$esc_ch = substr($file_data, $i, 1);
		switch ($esc_ch) {
			case 'n': $ch = "\n"; $i++; break;
			case 'r': $ch = "\r"; $i++; break;
			case 't': $ch = "\t"; $i++; break;
			case '"': $ch = '"'; $i++; break;
			default:
				break;
		}
	}
	return $ch;
}

function csv_to_array($filename='', $delimiter=';') {
	if (!file_exists($filename) || !is_readable($filename))
		return FALSE;

	$header = null;
	$data = [];
	$file_data = file_get_contents($filename);
	$i = 0;
	$ch = str_next_ch($i, $file_data);
	while ($ch != null) {
		$row = [];
		while ($ch != null && $ch != "\n") {
			$value = '';
			if ($ch == '"') {
				$delim = '"';
				$ch = str_next_ch($i, $file_data);
			}
			else
				$delim = '';
			while ($ch != null) {
				if (empty($delim)) {
					if ($ch == ';' || $ch == "\n")
						break;
				}
				else {
					if ($ch == $delim) {
						$ch = str_next_ch($i, $file_data);
						// Exell exports 2 delim as one:
						if ($ch != $delim)
							break;
					}
				}
				$value .= $ch;
				$ch = str_next_ch($i, $file_data);
			}
			while ($ch != null && $ch != ';' && $ch != "\n")
				$ch = str_next_ch($i, $file_data);
			if ($ch == ';')
				$ch = str_next_ch($i, $file_data);
			$row[] = $value;
		}
		if ($ch == "\n")
			$ch = str_next_ch($i, $file_data);

		if (!$header)
			$header = $row;
		else
			$data[] = array_combine($header, $row);
	}
	
	/*
	if (($handle = fopen($filename, 'r')) !== false) {
		while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
			if (!$header)
				$header = $row;
			else
				$data[] = array_combine($header, $row);
		}
		fclose($handle);
	}
	*/
	return $data;
}

function bit_set($bits, $bit_nr) {
	return ($bits & (1 << $bit_nr)) != 0;
}

function set_bit($bits, $bit_nr) {
	return $bits | (1 << $bit_nr);
}


