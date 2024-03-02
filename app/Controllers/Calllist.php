<?php
namespace App\Controllers;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class CallListTable extends Table {
	public function columnTitle($field) {
		switch ($field) {
			case 'kid_number':
				return 'Kid-Nr';
			case 'kid_call_change_time':
				return 'Lezte Änderung';
			case 'kid_call_status':
				return 'Status';
			case 'kid_call_escalation':
				return 'Eskalation';
			case 'kid_call_start_time':
				return 'Start Zeit';
			case 'button_column':
				return '&nbsp;';
		}
		return nix();
	}

	public function cellValue($field, $row) {
		switch ($field) {
			case 'kid_number':
				return $row[$field];
			case 'kid_call_change_time':
				return how_long_ago($row[$field]);
			case 'kid_call_status':
				if ($row[$field] == CALL_PENDING) {
					if ($row['kid_call_escalation'] > 0)
						return div(array('class'=>'red-box', 'style'=>'width: 140px; height: 22px;'), TEXT_ESCALATED);
					return div(array('class'=>'blue-box', 'style'=>'width: 140px; height: 22px;'), TEXT_PENDING);
				}
				if ($row[$field] == CALL_CANCELLED)
					return div(array('class'=>'red-box', 'style'=>'width: 140px; height: 22px;'), TEXT_CANCELLED);
				if ($row[$field] == CALL_COMPLETED)
					return div(array('class'=>'red-box', 'style'=>'width: 140px; height: 22px;'), TEXT_COMPLETED);
				return $row[$field];
			case 'kid_call_escalation':
				return $row[$field];
			case 'kid_call_start_time':
				return how_long_ago($row[$field]);
			case 'button_column':
				if ($row['kid_call_status'] == CALL_CANCELLED)
					return submit('ack_button', 'OK',
						array('class'=>'button-black', 'onclick'=>'$("#cancel_ok").val('.$row['kid_id'].');'))->html();
				if ($row['kid_call_status'] == CALL_PENDING)
					return submit('ack_button', 'Gerufen',
						array('class'=>'button-green', 'onclick'=>'$("#call_done").val('.$row['kid_id'].');'))->html();
				return '&nbsp;';
		}
		return nix();
	}
}

class CallList extends BF_Controller {
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

		$form = new Form('calllist_form', 'calllist', 1, array('class'=>'input-table'));
		$cancel_ok = $form->addHidden('cancel_ok');
		$call_done = $form->addHidden('call_done');

		if (!is_empty($cancel_ok->getValue())) {
			$kid_id = $cancel_ok->getValue();
			$kid_row = $this->get_kid_row($kid_id);
			if ($kid_row['kid_call_status'] == CALL_CANCELLED ||
				$kid_row['kid_call_status'] == CALL_COMPLETED) {
				$sql = 'UPDATE bf_kids SET ';
				$sql .= 'kid_call_status = '.CALL_NOCALL.', ';
				$sql .= 'kid_call_escalation = 0, ';
				$sql .= 'kid_call_change_time = NOW() ';
				$sql .= 'WHERE kid_id = ?';
				$this->db->query($sql, [ $kid_id ]);
			}
		}

		if (!is_empty($call_done->getValue())) {
			$kid_id = $call_done->getValue();
			$kid_row = $this->get_kid_row($kid_id);
			if ($kid_row['kid_call_status'] == CALL_PENDING) {
				$sql = 'UPDATE bf_kids SET ';
				$sql .= 'kid_call_status = '.CALL_CALLED.', ';
				$sql .= 'kid_call_change_time = NOW() ';
				$sql .= 'WHERE kid_id = ?';
				$this->db->query($sql, array($kid_id));

				$builder = $this->db->table('bf_history');
				$builder->insert([
					'hst_kid_id'=>$kid_id,
					'hst_stf_id'=>$this->session->stf_login_id,
					'hst_action'=>CALLED]);
				$this->set_success($kid_row['par_fullname'].' ('.$kid_row['par_code'].') gerufen');
			}
		}

		$async_loader = new AsyncLoader('call_list', 'calllist/getcalls');

		$this->header('Rufliste');
		$form->open();
		table(array('style'=>'border-collapse: collapse;'));
		tr();
		td(array('class'=>'left-panel', 'align'=>'left', 'valign'=>'top'));

		$async_loader->html();
	
		_td();
		_tr();
		_table();
		$form->close();
		script();
		out('window.setInterval(call_list, 5000);');
		_script();
		$this->footer();
		return '';
	}

	public function getcalls() {
		if (!$this->authorize_staff())
			return '';

		$builder = $this->db->table('bf_kids');
		$builder->where('kid_call_status IN ('.CALL_CANCELLED.', '.CALL_COMPLETED.') AND ADDTIME(kid_call_change_time, "'.CALL_ENDED_DISPLAY_TIME.'") <= NOW()');
		$builder->update(array('kid_call_status'=>CALL_NOCALL));

		$table = new CallListTable('SELECT kid_id, kid_number, kid_call_change_time, kid_call_status, kid_call_escalation, kid_call_start_time,
			"button_column" FROM bf_kids WHERE kid_call_status >= '.CALL_PENDING, array(), array('class'=>'details-table no-wrap-table'));
		$table->setOrderBy('kid_call_change_time DESC');
		$table->html();
		return '';
	}
}
