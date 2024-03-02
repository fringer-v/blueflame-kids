<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class ParentTable extends Table {
	public function columnTitle($field) {
		switch ($field) {
			case 'par_code':
				return 'Kids-ID';
			case 'par_fullname':
				return 'Name';
			case 'par_email':
				return 'E-Mail';
			case 'button_column':
				return '&nbsp;';
		}
		return nix();
	}

	public function cellValue($field, $row) {
		switch ($field) {
			case 'par_code':
			case 'par_fullname':
			case 'par_email':
				if (empty($row[$field]))
					return nbsp();
				return $row[$field];
			case 'button_column':
				return a([ 'class'=>'button-black',
					'style'=>'display: block; color: white; height: 24px; width: 32px; text-align: center; line-height: 26px; border-radius: 6px;',
					'onclick'=>'$("#set_par_id").val('.$row['par_id'].'); $("#display_parent").submit();' ], out('&rarr;'))->html();
		}
		return nix();
	}
}

class Parents extends BF_Controller {
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
		if (!$this->authorize_staff())
			return '';

		$read_only = !is_empty($this->session->par_login_tech);

		$current_period = $this->db_model->get_setting('current-period');

		$display_parent = new Form('display_parent', 'parents', 1, array('class'=>'input-table'));
		$set_par_id = $display_parent->addHidden('set_par_id');
		$par_filter = $display_parent->addTextInput('par_filter', '', '', [ 'placeholder'=>'Suchfilter', 'style'=>'width: 280px;' ]);
		$par_filter->persistent();
		$par_page = in('par_page', 1);
		$par_page->persistent();
		$clear_filter = $display_parent->addSubmit('clear_filter', 'X',
			[ 'class'=>'button-black', 'onclick'=>'$("#par_filter").val(""); parent_list(); return false;' ]);

		$update_parent = new Form('update_parent', 'parents', 1, array('class'=>'input-table'));
		if ($read_only)
			$update_parent->disable();
		$par_id = $update_parent->addHidden('par_id');
		$par_id->persistent();

		if ($set_par_id->submitted()) {
			$par_id->setValue($set_par_id->getValue());
			return redirect("parents");
		}

		$par_id_v = $par_id->getValue();
		$parent_row = $this->get_parent_row($par_id_v);

		// Fields
		$par_code = $update_parent->addField('Kids-ID');
		$par_code->setValue($parent_row['par_code']);

		$par_fullname = $update_parent->addTextInput('par_fullname', 'Name',
			$parent_row['par_fullname'], [ 'style'=>'width: 420px' ]);
		$par_fullname->setRule('required|is_unique[bf_parents.par_fullname.par_id]');

		$par_email = $update_parent->addTextInput('par_email', 'E-Mail', $parent_row['par_email']);
		$par_email->setRule('is_unique[bf_parents.par_email.par_id]');
		$par_cellphone = $update_parent->addTextInput('par_cellphone', 'Handy-Nr', $parent_row['par_cellphone']);
		$par_cellphone->setRule('is_unique[bf_parents.par_cellphone.par_id]');

		// Buttons:
		if (is_empty($par_id_v)) {
			$save_parent = $update_parent->addSubmit('save_parent', 'Begleitperson Hinzufügen', ['class'=>'button-black']);
			$clear_parent = $update_parent->addSubmit('clear_parent', 'Clear', ['class'=>'button-black']);
		}
		else {
			$save_parent = $update_parent->addSubmit('save_parent', 'Änderung Sichern', array('class'=>'button-black'));
			$clear_parent = $update_parent->addSubmit('clear_parent', 'Weiteres Aufnehmen...', array('class'=>'button-black'));
		}

		if ($clear_parent->submitted()) {
			$par_id->setValue(0);
			return redirect("parents");
		}

		if ($save_parent->submitted()) {
			$pwd = isset($par_password) ? $par_password->getValue() : '';

			$this->error = $update_parent->validate();

			if (is_empty($this->error)) {
				if (!is_empty($pwd))
					$pwd = password_hash(strtolower(md5($pwd.'129-3026-19-2089')), PASSWORD_DEFAULT);
				$data = array(
					'par_fullname' => $par_fullname->getValue(),
					'par_email' => $par_email->getValue(),
					'par_cellphone' => $par_cellphone->getValue()
				);
				if (is_empty($par_id_v)) {
					$data['par_code'] = $this->get_parent_code();
					$data['par_password'] = $pwd;
					$builder = $this->db->table('bf_parents');
					$builder->insert($data);
					$par_id_v = $this->db->insertID();
					$par_id->setValue($par_id_v);
					$this->set_success($par_fullname->getValue().' hinzugefügt');
				}
				else {
					if (!is_empty($pwd))
						$data['par_password'] = $pwd;

					$builder = $this->db->table('bf_parents');
					$builder->where('par_id', $par_id_v);
					$builder->update($data);

					$this->set_success($par_fullname->getValue().' geändert');
				}

				return redirect("parents");
			}
		}

		$parent_list_loader = new AsyncLoader('parent_list', 'parents/getparent',
			[ 'par_filter' ]);

		// Generate page ------------------------------------------
		$this->header('Begleitpersonen');

		table(array('style'=>'border-collapse: collapse;'));
		tr();
		td(array('class'=>'left-panel', 'align'=>'left', 'valign'=>'top'));
			$display_parent->open();
			table([ 'class'=>'input-table' ]);
			tr(td(table(tr(td($par_filter), td(nbsp()), td($clear_filter)))));
			tr();
			td();
			$parent_list_loader->html();
			//$this->getparent();
			_td();
			_tr();
			_table();
			$display_parent->close();
		_td();
		td(array('align'=>'left', 'valign'=>'top'));
			table(array('style'=>'border-collapse: collapse; margin-right: 5px;'));
			tbody();
			tr();
			td(array('style'=>'border: 1px solid black; padding: 5px 5px;'));
			$update_parent->show();
			_td();
			_tr();
			tr();
			td();
			$this->print_result();
			_td();
			_tr();
			_tbody();
			_table();
		_td();
		_tr();
		_table();

		$this->footer();
		return '';
	}
	
	public function getparent() {
		if (!$this->authorize_staff())
			return '';

		$par_page = in('par_page', 1);
		$par_page->persistent();
		$par_filter = in('par_filter', '');
		$par_filter->persistent();
		$par_filter_v = trim($par_filter->getValue());

		$sql = 'SELECT SQL_CALC_FOUND_ROWS par_code, par_fullname, par_email, "button_column", par_id, par_cellphone, par_password ';
		$sql .= 'FROM bf_parents';

		if (!empty($par_filter_v)) {
			$sql .= ' WHERE CONCAT(par_code, "|", par_fullname, "|", par_email) LIKE "%'.db_escape($par_filter_v).'%" ';
		}

		$parent_list = new ParentTable($sql, [], [ 'class'=>'details-table no-wrap-table', 'style'=>'width: 600px;' ]);
		$parent_list->setPagination('parents?par_page=', 21, $par_page);
		$parent_list->setOrderBy('par_fullname');

		table(array('style'=>'border-collapse: collapse;'));
		tr(td($parent_list->paginationHtml()));
		tr(td($parent_list->html()));
		_table();
		return '';
	}
}
