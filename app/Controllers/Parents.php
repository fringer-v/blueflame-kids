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

	public function columnAttributes($field) {
		switch ($field) {
			case 'button_column':
				return [ 'style'=>'text-align: center; width: 32px;' ];
		}
		return null;
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

class ParentKidsTable extends Table {
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
		$par_filter->persistent('staff');
		$par_page = in('par_page', 1);
		$par_page->persistent('staff');
		$clear_filter = $display_parent->addSubmit('clear_filter', 'X',
			[ 'class'=>'button-black', 'onclick'=>'$("#par_filter").val(""); parent_list(); return false;' ]);

		$update_parent = new Form('update_parent', 'parents', 1, ['class'=>'input-table', 'style'=>'width: 100%;']);
		if ($read_only)
			$update_parent->disable();
		$par_id = $update_parent->addHidden('par_id');
		$par_id->persistent('staff');

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
		$par_email->setRule('is_email|is_unique[bf_parents.par_email.par_id]');
		$par_cellphone = $update_parent->addTextInput('par_cellphone', 'Handy-Nr', $parent_row['par_cellphone']);
		$par_cellphone->setRule('is_phone|is_unique[bf_parents.par_cellphone.par_id]');

		// Buttons:
		if (is_empty($par_id_v)) {
			$save_parent = $update_parent->addSubmit('save_parent', 'Begleitperson Hinzufügen', ['class'=>'button-black']);
			$clear_parent = $update_parent->addSubmit('clear_parent', 'Clear', ['class'=>'button-black']);
		}
		else {
			$save_parent = $update_parent->addSubmit('save_parent', 'Änderung Sichern', array('class'=>'button-black'));
			$clear_parent = $update_parent->addSubmit('clear_parent', 'Weiteres Aufnehmen...', array('class'=>'button-black'));
		}
		
		$display_kid = new Form('display_kid', 'kids', 1, [ 'class'=>'input-table' ]);
		$set_kid_id = $display_kid->addHidden('set_kid_id');

		$parent_kids_table = $this->kids_table(empty($parent_row['par_code']) ? '#' : $parent_row['par_code']);
		//$parent_kids_table->hasButton = false;

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
					'par_email' => $par_email->getValue(true),
					'par_cellphone' => $par_cellphone->getValue(true)
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
			table([ 'style'=>'border-collapse: collapse; margin-right: 5px;' ]);
			tbody();
			tr(); td([ 'style'=>'border: 1px solid black; padding: 5px 5px;' ]);
			$update_parent->show();
			_td(); _tr();
			tr(); td();
			$this->print_result();
			_td(); _tr();
			tr(th([ 'style'=>'font-size: 18px; padding: 5px 5px;' ], 'Registrierte Kinder'));
			tr(); td();
			$display_kid->open();
			$parent_kids_table->html();
			$display_kid->close();
			_td(); _tr();
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
		$par_page->persistent('staff');
		$par_filter = in('par_filter', '');
		$par_filter->persistent('staff');
		$par_filter_v = trim($par_filter->getValue());

		$sql = 'SELECT SQL_CALC_FOUND_ROWS par_code, par_fullname, par_email, "button_column", par_id, par_cellphone, par_password ';
		$sql .= 'FROM bf_parents WHERE ';

		$qtype = MATCH_ALL;
		$args = [ ];
		if (empty($par_filter_v)) {
			$par_filter_v = '%';
		}
		else if (preg_match('/^[[:alpha:]]+(\s+[[:alpha:]]+)+$/', $par_filter_v)) {
			$qtype = MATCH_FULL_NAME;
			$parts = explode(' ', $par_filter_v);
			$parts = array_filter($parts, 'strlen'); // removes the empty entries in the array
			$args = [ implode('% ', $parts).'%' ];
		}
		else if (preg_match('/^[ACDEFHJKLMNPQSTUVWXYZ][0-9]([A-Z][0-9]?)?$/', $par_filter_v)) {
			$qtype = MATCH_KID_ID;
		}
		else if (str_contains($par_filter_v, '@')) {
			$qtype = MATCH_EMAIL;
		}
		else
			$par_filter_v = '%'.$par_filter_v.'%';

		if ($qtype == MATCH_FULL_NAME) {
			// First_Last
			$sql .= 'par_fullname LIKE ?';
		}
		else if ($qtype == MATCH_KID_ID) {
			$sql .= 'par_code LIKE ?';
			$args = [ $par_filter_v.'%' ];
		}
		else if ($qtype == MATCH_EMAIL) {
			// Registered with, search parents:
			$sql .= 'par_email LIKE ?';
			$args = [ '%'.$par_filter_v.'%' ];
		}
		else {
			$sql .= 'CONCAT(par_fullname, "|", par_email) LIKE ?';
			$args = [ '%'.$par_filter_v.'%' ];
		}

		$parent_list = new ParentTable($sql, $args, [ 'class'=>'details-table no-wrap-table', 'style'=>'width: 600px;' ]);
		$parent_list->setPagination('parents?par_page=', 21, $par_page);
		$parent_list->setOrderBy('par_fullname');

		table(array('style'=>'border-collapse: collapse;'));
		tr(td($parent_list->paginationHtml()));
		tr(td($parent_list->html()));
		_table();
		return '';
	}
}
