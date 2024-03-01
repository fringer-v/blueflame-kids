<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

function base64url_encode($data) {
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

class Test extends BF_Controller {
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger);
		$this->db_model = model('db_model');
	}

	public function index()
	{
		out('<!DOCTYPE html>');
		tag('html');
		tag('head');
		tag('meta', array('http-equiv'=>'Content-Type', 'content'=>'text/html; charset=utf-8'));
		tag('link', array('href'=>base_url('/css/blue-flame.css'), 'rel'=>'stylesheet', 'type'=>'text/css'));
		tag('title', "BlueFlame Kids: Test");
		//script('https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js');
		script(base_url('/js/jquery.js'));
		script(base_url('/js/blue-flame.js'));
		_tag('head');
		tag('body'/*, array('onload'=>'setHeaderSizesOfScrollableTables();')*/);

		br();
		br();
		table(('style'=>'border-collapse: collapse;']);
		  tr();
			td('Normal:');
			td(('width' => '120'], '<input type="checkbox" />');
			td(('width' => '120'], '<input type="checkbox" checked />');
			td(('width' => '120'], '<input type="checkbox" disabled />');
			td(('width' => '120'], '<input type="checkbox" disabled checked />');
		  _tr();
		/*
		  tr();
			td('Custom:');
			td(('width' => '120', 'height'=>'40'], '<input type="checkbox" class="check-box" />');
			td(('width' => '120', 'height'=>'40'], '<input type="checkbox" checked class="check-box" />');
			td(('width' => '120', 'height'=>'40'], '<input type="checkbox" disabled class="check-box" />');
			td(('width' => '120', 'height'=>'40'], '<input type="checkbox" disabled checked class="check-box" />');
		  _tr();
		  tr();
			td('Custom:');
			td(('width' => '120', 'height'=>'40'], '<input type="checkbox" class="check-box g-0" />');
			td(('width' => '120', 'height'=>'40'], '<input type="checkbox" checked class="check-box g-0" />');
			td(('width' => '120', 'height'=>'40'], '<input type="checkbox" disabled class="check-box g-0" />');
			td(('width' => '120', 'height'=>'40'], '<input type="checkbox" disabled checked class="check-box g-0" />');
		  _tr();
		  tr();
		  td(('width' => '120', 'height'=>'400']);
		  _tr();
		  tr();
			td('Custom:');
			td(('width' => '120', 'height'=>'40'], span(('class'=>'group g-1'], span(('class'=>'group-number'], 4), "asd"));
		  _tr();
		  */
		_table();

		$this->footer();
		return '';
	}

}
