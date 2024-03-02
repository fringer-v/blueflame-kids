<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Database extends BF_Controller {
	public function initController(
		RequestInterface $request,
		ResponseInterface $response,
		LoggerInterface $logger)
	{
		parent::initController($request, $response, $logger, false);
		//$this->start_session();
	}

	public function index()
	{
		//$this->load->library('session');

		$form = new Form('update_database', 'database', 1);
		$update = $form->addSubmit('submit', 'Update Database', array('class'=>'button-black'));

		if ($update->submitted() && !$this->db_model->up_to_date()) {
			if (is_empty($this->error)) {
				$this->db_model->update_database();
				$this->set_success("Database updated");
			}
		}

		$this->header('Database update');
		table(array('style'=>'border-collapse: collapse;'));
		tr();
		td(array('class'=>'left-panel', 'align'=>'left', 'valign'=>'top'));
		if ($this->db_model->up_to_date()) {
			out("The database is up-to-date");
		}
		else {
			out("The database schema must be updated");
			$form->show();
		}
		_td();
		_tr();
		_table();
		$this->footer();
		return '';
	}
}
