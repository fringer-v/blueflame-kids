<?php
namespace App\Controllers;

function tag($name) {
	$params = array_slice(func_get_args(), 1);

	while (is_array($params) && count($params) == 1 && is_array($params[0])) {
		reset($params[0]);
		if (key($params[0]) === 0)
			$params = $params[0];
		else
			break;
	}

	$tag = out('<[]', $name);
	if (count($params) > 0 && is_array($params[0])) {
		// Assume this are attributes of the tag:
		foreach ($params[0] as $attr => $avalue) {
			if (is_null($avalue))
				$tag->add(" $attr");
			else if (is_numeric($attr))
				$tag->add(" $avalue");
			else
				$tag->add(out(" $attr=\"[]\"", $avalue));
			
		}
		$params = array_slice($params, 1);
	}
	$tag->add(">");

	if (count($params) > 0) {
		foreach ($params as $subnode) {
			if (!is_null($subnode))
				$tag->add($subnode);
		}
		$tag->add(out('</[]>', $name));
	}

	return $tag;
}

function _tag($name) {
	return out("</[]>\n", $name);
}

function script($src = '', $text='') {
	$args = array('type'=>'text/javascript');
	if (!empty($src))
		$args['src'] = $src;
	$tag = tag('script', $args);
	if (!empty($text))
		$tag->add($text);
	if (!empty($src) || !empty($text))
		$tag->add(_script());
	return $tag;
}

function _script() {
	return _tag('script');
}

function nbsp() {
	return out("&nbsp;");
}

function p() {
	return tag('p', func_get_args());
}

function _p() {
	return _tag('p');
}

function a() {
	return tag('a', func_get_args());
}

function _a() {
	return _tag('a');
}

function img() {
	return tag('img', func_get_args());
}

function b() {
	return tag('b', func_get_args());
}

function _b() {
	return _tag('b');
}

function u() {
	return tag('u', func_get_args());
}

function em() {
	return tag('em', func_get_args());
}

function cr() {
	return out("\n");
}

function br() {
	return tag("br");
}

function h1() {
	return tag("h1", func_get_args());
}

function h2() {
	return tag("h2", func_get_args());
}

function div() {
	return tag('div', func_get_args());
}

function _div() {
	return _tag('div');
}

function span() {
	return tag('span', func_get_args());
}

function _span() {
	return _tag('span');
}

function form() {
	return tag('form', func_get_args());
}

function _form() {
	return _tag('form');
}

function input() {
	return tag('input', func_get_args());
}

function label() {
	return tag('label', func_get_args());
}

function _label() {
	return _tag('label');
}

function table() {
	return tag('table', func_get_args());
}

function _table() {
	return _tag('table');
}

function thead() {
	return tag('thead', func_get_args());
}

function _thead() {
	return _tag('thead');
}

function tbody() {
	return tag('tbody', func_get_args());
}

function _tbody() {
	return _tag('tbody');
}

function tr() {
	return tag('tr', func_get_args());
}

function _tr() {
	return _tag('tr');
}

function th() {
	return tag('th', func_get_args());
}

function _th() {
	return _tag('th');
}

function td() {
	return tag('td', func_get_args());
}

function _td() {
	return _tag('td');
}

function hr() {
	return tag('hr', func_get_args());
}

function url($page, $args = array()) {
	$url = out('[]', $page);
	if (!empty($args)) {
		$url->add('?');
		$i = 0;
		foreach ($args as $key => $arg) {
			if ($i != 0)
				$url->add('&');
			$i++;
			$url->add(out('[]=[]', $key, $arg));
		}
	}
	return $url->html();
}

function href($url, $item, $attr = array()) {
	$attr = array_merge(array('href'=>$url), $attr);
	return a($attr, $item);
}
