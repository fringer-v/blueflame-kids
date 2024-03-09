<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Login extends BF_Controller {
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
		$login_form = new Form('user_login', url('admin-login'), 1, array('class'=>'input-table'));
		$stf_username = $login_form->addTextInput('stf_username', 'Kurzname');
		$stf_password = $login_form->addPassword('stf_password', 'Passwort');
		$stf_md5_pwd = $login_form->addHidden('stf_md5_pwd', 'Password');
		$login = $login_form->addSubmit('admin-login', 'Login', array('class'=>'button-black'/*, 'onclick'=>'doLogin();'*/));

		$stf_username->setRule('required');
		$stf_md5_pwd->setRule('required');

		$logout_action = in('action');
		if ($logout_action->getValue() == 'logout') {
			$this->set_staff_logged_out();
			return redirect("admin-login");
		}

		if ($login->submitted()) {
			$this->error = $login_form->validate();
			if (is_empty($this->error)) {
				$staff_row = $this->get_staff_row_by_username($stf_username->getValue());
				if (is_empty($staff_row))
					$this->error = "Unbekannter Benutzer: ".$stf_username->getValue();
				else if (is_empty($staff_row['stf_loginallowed']) &&
					strtolower($staff_row['stf_username']) != 'andrea' &&
					strtolower($staff_row['stf_username']) != 'jessica' &&
					strtolower($staff_row['stf_username']) != 'paul' &&
					strtolower($staff_row['stf_username']) != 'admin' &&
					strtolower($staff_row['stf_username']) != 'ipad') {
					$this->error = "Zugangsberechtigung verweigert: ".$stf_username->getValue();
				}
				else {
					$pwd = $stf_password->getValue();
					$pwd = md5($pwd.'129-3026-19-2089');
					if (password_verify($pwd, $staff_row['stf_password'])) {
						$this->set_staff_logged_in($staff_row);
						if (is_empty($staff_row['stf_technician']))
							return redirect("kids");
						return redirect("calllist");
					}
					$this->error = "Passwort falsch";
				}
			}
		}
		$stf_password->setValue('');
		$stf_md5_pwd->setValue('');

		$this->header('Login', false);

		div([ 'class'=>'topnav' ]);
		table();
		tr([ 'style'=>'border-bottom: 1px solid black;' ]);
		td([ 'style'=>'padding: 10px; font-size: 28px; color: white;' ], 'Administration');
		_tr();
		_table();
		_div();

		table(array('style'=>'border-collapse: collapse; width: 100%'));
		tr([ 'style'=>'height: 40px;' ]);
		td(nbsp());
		_tr();
		tr();
		td(array('align'=>'center'));
		img([ 'src'=>base_url('/img/bf-kids-logo.png'), 'style'=>'width: 200px; height: auto' ]);
		_td();
		tr(td(array('height'=>'5'), ''));
		_tr();
		tr();
		td(array('align'=>'center'));

		table();
		tr();
		td();		
		$login_form->show();
		_td();
		_tr();
		tr();
		td(array('align'=>'center'));
		$this->print_result();
		_td();
		_tr();
		_table();

		_td();
		_tr();
		_table();
		_div();

		$this->footer(base_url('/js/js-md5.js'));
		return '';
	}
}
