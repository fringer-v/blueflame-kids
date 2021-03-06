<?php
defined('BASEPATH') OR exit('No direct script access allowed');

date_default_timezone_set('CET');
setlocale(LC_ALL, 'de_DE.UTF8', 'de_DE', 'de', 'ge');

class Form {
	private $id;
	private $action;
	private $columns;
	private $attributes; // Assoc. array of attributes for the table
	private $disabled = false;
	private $hiddens = array();	
	private $fields = array();	
	private $buttons = array();	
	private $groups = array();	
	private $openned = false;

	public function __construct($id, $action = '', $columns = 1, $attributes = array()) {
		$this->id = $id;
		$this->action = $action;
		$this->columns = $columns;
		$this->attributes = $attributes;
	}

	public function getFormAttributes() {
		$attr = array('id'=>$this->id);
		if ($this->disabled)
			$attr['disabled'] = null;
		return $attr;
	}

	function addHidden($name, $default_value = '') {
		$field = new Hidden($name, $default_value);
		$field->setForm($this);
		$this->hiddens[$name] = $field;
		return $field;
	}

	function addField($label, $value = '') {
		$field = new OutputField();
		$field->setForm($this);
		$this->fields['$'.count($this->fields)] = array($label, $field);
		$field->setValue($value);
		return $field;
	}

	function addSpace() {
		$field = new OutputField();
		$field->setForm($this);
		$this->fields['$'.count($this->fields)] = array('', $field);
		return $field;
	}

	function addRow($value = '') {
		$field = new OutputField();
		$field->setForm($this);
		$this->fields['$'.count($this->fields)] = array('', $field);
		$field->setValue($value);
		$field->setFormat([ 'nolabel'=>true, 'colspan'=>'*' ]);
		return $field;
	}

	function addText($text) {
		$field = $this->addSpace();
		$field->setValue($text);
		return $field;
	}

	function addTextInput($name, $label, $default_value = '', $attributes = array()) {
		$field = new TextInput($name, $default_value, $attributes);
		$field->setForm($this);
		$this->fields[$name] = array($label, $field);
		return $field;
	}

	function addPassword($name, $label, $default_value = '', $attributes = array()) {
		$field = new Password($name, $default_value, $attributes);
		$field->setForm($this);
		$this->fields[$name] = array($label, $field);
		return $field;
	}

	function addTextArea($name, $label, $default_value = '', $attributes = array()) {
		$field = new TextArea($name, $default_value, $attributes);
		$field->setForm($this);
		$this->fields[$name] = array($label, $field);
		return $field;
	}
	
	function addSelect($name, $label, $values, $default_value = '', $attributes = array()) {
		$field = new Select($name, $values, $default_value, $attributes);
		$field->setForm($this);
		$this->fields[$name] = array($label, $field);
		return $field;
	}

	function addCheckbox($name, $label, $default_value = '', $attributes = array()) {
		$field = new Checkbox($name, $default_value, $attributes);
		$field->setForm($this);
		$this->fields[$name] = array($label, $field);
		return $field;
	}

	function addSubmit($name, $label, $attributes = array()) {
		$button = new Submit($name, $label, $attributes);
		$button->setForm($this);
		$this->buttons[$name] = $button;
		return $button;
	}

	function addButton($name, $label, $attributes = array()) {
		$button = new Button($name, $label, $attributes);
		$button->setForm($this);
		$this->buttons[$name] = $button;
		return $button;
	}

	public function disable() {
		$this->disabled = true;
	}

	private function getFields($group = '') {
		if (is_empty($group))
			 return $this->fields;
		return $this->groups[$group]['fields'];
	}
	
	private function getButtons($group = '') {
		if (is_empty($group))
			return $this->buttons;
		return $this->groups[$group]['buttons'];
	}

	// Turn the exiting fields and buttons into a group:
	public function createGroup($group) {
		foreach ($this->fields as $name => $field_info) {
			$field = $field_info[1];
			$field->setGroup($group);
		}
		$this->groups[$group] = array('fields'=>$this->fields, 'buttons'=>$this->buttons);
		$this->fields = array();
		$this->buttons = array();
	}

	public function getLabel($name, $quote = false, $group = '') {
		$fields = $this->getFields($group);
		$label = $fields[$name][0];
		if ($quote)
			return '"'.$label.'"';
		return $label;
	}

	public function getField($name, $group = '') {
		$fields = $this->getFields($group);
		if (array_key_exists($name, $fields))
			return $fields[$name][1];
		if (array_key_exists($name, $this->hiddens))
			return $this->hiddens[$name];
		return null;
	}

	public function setValues($values) {
		foreach ($this->hiddens as $name => $field) {
			if (isset($values[$name]))
				$field->setValue($values[$name]);
		}
		$this->setFields($this->fields, $values);
		foreach ($this->groups as $group)
			$this->setFields($group['fields'], $values);
	}

	private function setFields($fields, $values) {
		foreach ($fields as $name => $field_info) {
			if (isset($values[$name])) {
				$field = $field_info[1];
				$field->setValue($values[$name]);
			}
		}
	}

	// Return an array of error messages, if no error
	// occurs this returns an empty array.
	public function validate($group = '') {
		$errors = array();
		$fields = $this->getFields($group);
		foreach ($fields as $name => $field_info) {
			$label = $field_info[0];
			$field = $field_info[1];
			$error = $field->validate($this);
			if (!is_empty($error))
				$errors[] = $error;
		}
		return $errors;
	}

	public function open() {
		$attr = $this->getFormAttributes();
		$attr['action'] = $this->action;
		$attr['method'] = 'POST';
		form($attr); 
		
		$this->openned = true;
	}

	public function close() {
		foreach ($this->hiddens as $hidden) {
			if (!$hidden->isHidden())
				$hidden->show();
		}

		_form();
	}

	public function show($group = '') {
		$openned = $this->openned;

		if (!$openned)
			$this->open();

		$fields = $this->getFields($group);
		$buttons = $this->getButtons($group);
		if (!is_empty($fields) || !is_empty($buttons)) {
			table($this->attributes);
			
			if (!is_empty($fields)) {
				tr();
				$cols = 0;
				$fields_shown = 0;
				$start_row = false;
				foreach ($fields as $name => $field_info) {
					$label = $field_info[0];
					$field = $field_info[1];

					if ($field->isHidden())
						continue;

					if ($this->disabled)
						$field->disable();

					if ($start_row) {
						tr();
						$start_row = false;
					}

					$colspan = 1;
					$haslabel = true;
					$style = '';
					$postfix = out('');
					if (!empty($field->format)) {
						foreach ($field->format as $format=>$value) {
							if ($format == 'nolabel')
								$haslabel = false;
							else if ($format == 'colspan') {
								if ($value == '*')
									$colspan = $this->columns;
								else
									$colspan = (integer) $value;
							}
							else if ($format == 'style')
								$style = $value;
							else if ($format == 'postfix') {
								$postfix = $value;
							}
						}
					}

					if (empty($style))
						$attr = [];
					else
						$attr['style'] = $style;
					if ($field instanceof Checkbox) {
						$attr['colspan'] = $colspan*2;
						td($attr);
						$field->show();
						label(array('for'=>$name), ' '.$label);
						$postfix->show();
						_td();
					}
					else if (!$haslabel) {
						$attr['colspan'] = $colspan*2;
						td($attr);
						$field->show();
						$postfix->show();
						_td();
					}
					else {
						th(label(array('for'=>$name), is_empty($label) ? '' : $label.':'));
						if ($colspan*2-1 != 1)
							$attr['colspan'] = $colspan*2-1;
						td($attr);
						$field->show();
						$postfix->show();
						_td();
					}
			
					$cols += $colspan;
					$fields_shown++;
			
					//if we have reached the end of this 'row' of the form
					if ($cols >= $this->columns) {
						_tr();
						$cols = 0;
						//open a new row if there are more fields to come
						if ($fields_shown < count($fields))
							$start_row = true;
					}
				}
				// Add blank table cells until we complete this row of the table
				if ($cols < $this->columns && $cols != 0) {
					while ($cols < $this->columns) {
						th(nbsp());
						td(nbsp());
						$cols++;
					}
					_tr();
				}
			}

			if (!is_empty($buttons)) {
				$attr = array('colspan'=>$this->columns*2, 'class'=>'button-row');
				$i = 0;
				$start_row = true;
				foreach ($buttons as $button) {
					if ($button->isHidden())
						continue;
					if ($start_row) {
						tr();
						td($attr);
						$start_row = false;
					}
					if ($i != 0)
						nbsp();
					$i++;
					if ($this->disabled)
						$button->disable();
					$button->show();
				}
				if (!$start_row) {
					_td();
					_tr();
				}
			}

			_table();
		}
		
		if (!$openned)
			$this->close();
	}
}

class InputField extends BaseOutput {
	public $name; // Name and ID of the field
	public $label;
	public $default_value;
	protected $attributes; // Assoc. array of attributes
	protected $disabled = false;
	protected $form = null;
	protected $rules = '';
	public $format = '';
	public $group = '';
	
	public function __construct($name = '', $default_value = '', $attributes = array()) {
		$this->name = $name;
		$this->default_value = ($default_value instanceof Output) ? $default_value->html() : $default_value;
		$this->attributes = $attributes;

		if (!is_array($attributes))
			fatal_error('InputField attributes, must be an array');

		// Input fields do not "auto echo" by default. Use the
		// short cut create functions below to get auto echo functionality
		$this->autoEchoOff();
	}

	public function __destruct() {
		// If this instance has not been shown then show it now.
		// This is so you can print with out('text');
		// rather than typing out('text')->show();
		//if ($this->autoEcho() && !$this->isHidden())
		//	warningout("> WARNING: AUTO-ECHO $this->name | auto_echo=".$this->autoEcho()." | hidden=".$this->isHidden());
		parent::__destruct();
	}

	public function setLabel($label) {
		$this->label = $label;
	}

	public function setForm($form) {
		$this->form = $form;
	}

	public function setGroup($group) {
		$this->group = $group;
	}

	public function getLabel($quote = false) {
		if (is_null($this->form))
			return $this->label;
		return $this->form->getLabel($this->name, $quote, $this->group);
	}

	public function addAttribute($name, $value = null) {
		$this->attributes[$name] = $value;
	}
	
	public function getAttributes($type, $include_value = true, $class = '') {
		if (is_empty($this->name))
			$attr = [ ];
		else
			$attr = [ 'name'=>$this->name, 'id'=>$this->name ];
		if (!is_empty($type))
			$attr['type'] = $type;
		if ($include_value)
			$attr['value'] = $this->getValue();
		if ($this->disabled)
			$attr['disabled'] = null;
		if (!empty($class) && !isset($this->attributes['class']))
			$attr['class'] = $class;
		$attr = array_merge($attr, $this->attributes);
		return $attr;
	}

	public function submitted() {
		if (is_empty($this->name))
			return false;
		if (isset($_POST[$this->name]))
			return true;
		if (isset($_GET[$this->name]))
			return true;
		return false;
	}

	public function setValue($value = '') {
		if (!is_empty($this->name)) {
			if (isset($_POST[$this->name]))
				unset($_POST[$this->name]);
			if (isset($_GET[$this->name]))
				unset($_GET[$this->name]);
			if (isset($_SESSION[$this->name]))
				$_SESSION[$this->name] = $value;
		}
		$this->default_value = $value;
	}

	public function getValue() {
		if (is_empty($this->name))
			return $this->default_value;

		if (isset($_POST[$this->name])) {
			$value = $_POST[$this->name];
			return $value;
		}

		if (isset($_GET[$this->name])) {
			$value = $_GET[$this->name];
			return $value;
		}

		if (isset($_SESSION[$this->name]))
			return $_SESSION[$this->name];

		return $this->default_value;
	}

	public function getDate($fmt = '') {
		$val = $this->getValue();
		$ts = str_to_date($val);
		if (!is_empty($fmt))
			return $ts->format($fmt);
		return $ts;
	}

	public function disable() {
		$this->disabled = true;
	}

	public function setRule($rules, $label = null) {
		$this->rules = $rules;
		if ($label != null)
			$this->label = $label;
	}

	public function setFormat($format) {
		$this->format = $format;
	}

	public function persistent() {
		if (!is_empty($this->name)) {
			$value = $this->getValue();
			$_SESSION[$this->name] = $value;
		}
	}

	public function validate($form = null) {
		$rules = explode('|', $this->rules);
		$value = $this->getValue();
		$error = '';
		foreach ($rules as $rule) {
			if (str_startswith($rule, 'required')) {
				if (is_empty($value))
					$error = $this->getLabel(true).' muss vorhanden sein';
			}
			else if (str_startswith($rule, 'is_number')) {
				if (!is_numeric($value) || ((integer) $value) <= 0)
					$error = $this->getLabel(true).' muss eine Zahl sein';
			}
			else if (str_startswith($rule, 'is_valid_date')) {
				if (!is_empty($value)) {
					if (date_create_from_format('d.m.Y', $value) === false)
						$error = $this->getLabel(true).' ist kein gültiges Datum';
				}
			}
			else if (str_startswith($rule, 'maxlength')) {
				$arg = str_left(str_right($rule, '['), ']');
				if (strlen($value) > $arg)
					$error = $this->getLabel(true)." darf nicht länger als $arg Zeichen sein";
			}
			else if (empty($form)) {
				$error = 'Rule: "'.$rule.'" requires this field be attached to a form';
			}
			else {
				if (str_startswith($rule, 'is_unique')) {
					$cii =& get_instance();
					$cii->load->database();

					$arg = str_left(str_right($rule, '['), ']');
					$dots = explode('.', $arg);
					$table = $dots[0];
					$column = $dots[1];
					$id = $dots[2];
					$sql = 'SELECT COUNT(*) AS count FROM '.$table.' WHERE '.$column.' = ?';
					if (!is_empty($id))
						$sql .= ' AND '.$id.' != ?';
					$query = $cii->db->query($sql, array($value, $form->getField($id)->getValue()));
					$row = $query->row_array()['count'];
					if ($row != 0)
						$error = $this->getLabel(true).' muss eindeutig sein';
				}
				else if (str_startswith($rule, 'matches')) {
					$arg = str_left(str_right($rule, '['), ']');
					if ($value != $form->getField($arg)->getValue())
						$error = $this->getLabel(true).' ist nicht gleich '.$form->getLabel($arg, true);
				}
			}

			if (!is_empty($error))
				break;		
		}
		return $error;
	}
}

class In extends InputField {
	public function __construct($name = '', $default_value = '', $attributes = array()) {
		$this->hide();
		parent::__construct($name, $default_value, $attributes);
	}

	public function output() {
		return '';
	}
}

class Submit extends InputField {
	public function output() {
		return tag('input', $this->getAttributes('submit'))->html();
	}
}

class Button extends InputField {
	private $title;
	
	public function __construct($name = '', $default_value = '', $attributes = array()) {
		$this->title = $default_value;
		parent::__construct($name, $default_value, $attributes);
	}

	public function output() {
		$v = tag('button', $this->getAttributes('', false), $this->title)->html();
		return $v;
	}
}

class ImageButton extends InputField {
	public function __construct($name = '', $src = '', $attributes = array()) {
		$attributes['src'] = $src;
		parent::__construct($name, '', $attributes);
	}

	public function output() {
		return tag('input', $this->getAttributes('image'))->html();
	}
}

class Hidden extends InputField {
	public function output() {
		return tag('input', $this->getAttributes('hidden'))->html();
	}
}

class OutputField extends InputField {
	public function __construct($default_value = '') {
		parent::__construct('', $default_value);
	}

	public function output() {
		return out('[]', $this->default_value)->html();
	}
}

class TextField extends InputField {
	public function clearBox() {
	}

	public function getTextField($type) {
		$field = table([ 'id'=>$this->name.'_border', 'class'=>'input-field-border', 'style'=>'border-collapse: collapse;' ]);
		$field->add(tr());
		$field->add(td([ 'style'=>'border: 0px; padding: 0px;' ]));
		$field->add(tag('input', $this->getAttributes($type, true, 'input-field')));
		$field->add(_td());
		if (isset($this->format['clear-box'])) {
			$attr = [ 'id'=>$this->name.'_clear', 'class'=>'input-field-clear' ];
			$attr['onclick'] = '$("#'.$this->name.'").val("").focus().keyup();';
			$field->add(td($attr, '&#x2297;'));
		}
		$field->add(_tr());
		$field->add(_table());
		$field->add(script('', '
			$("#'.$this->name.'" ).focus(function() { $("#'.$this->name.'_border" ).addClass("has-focus"); });
			$("#'.$this->name.'" ).focusout(function() { $("#'.$this->name.'_border" ).removeClass("has-focus"); });
		'));
		return $field->html();
	}
}

class TextInput extends TextField {
	public function output() {
		return $this->getTextField('text');
		/*
		$field = tag('input', $this->getAttributes('text'));
		$formats = explode(',', $this->format);
		if (in_array('clear-box', $formats))
			$field->add($this->clearBox());
		return $field->html();
		*/
	}
}

class NumericField extends TextField {
	public function output() {
		return $this->getTextField('tel');
	/*
		$div_style = 'position: relative; border: 1px solid red;';
		$inp_attr = $this->getAttributes('text');
		if (isset($this->format['width'])) {
			$div_style .= ' width: '.$this->format['width'].'px;';
			$inp_attr['style'] = str_listappend(arr_nvl($inp_attr, 'style', ''), 'width: 100%;', ' ');
		}
		*/
		/*
		$field = tag('input', $this->getAttributes('tel'));
		$formats = explode(',', $this->format);
		if (in_array('clear-box', $formats))
			$field->add($this->clearBox());
		return $field->html();
		*/
	}
}

class Password extends TextField {
	public function output() {
		return $this->getTextField('password');
		//return tag('input', $this->getAttributes('password'))->html();
	}
}

class TextArea extends InputField {
	public function output() {
		$out = tag('textarea', $this->getAttributes('', false));
		$out->add(out('[]', $this->getValue()));
		$out->add(_tag('textarea'));
		return $out->html();
	}
}

class Select extends TextArea {
	private $values = array();

	public function __construct($name = '', $values = array(), $default_value = '', $attributes = array()) {
		$this->values = $values;
		parent::__construct($name, $default_value, $attributes);
	}

	public function output() {
		$current_value = $this->getValue();
		$out = tag('select', $this->getAttributes('', true));
		foreach ($this->values as $value => $text) {
			$attr = array('value'=>$value);
			if ($value == $current_value)
				$attr['selected'] = null;
			$out->add(tag('option', $attr, $text));
		}
		$out->add(_tag('select'));
		return $out->html();
	}
}

class Checkbox extends InputField {
	public function output() {
		$out = tag('input', array('type'=>'hidden', 'name'=>$this->name, 'value'=>'0'));
		$attr = $this->getAttributes('checkbox', false);
		$attr['value'] = '1';
		$value = $this->getValue();
		if (!is_empty($value))
			$attr['checked'] = null;
		$out->add(tag('input', $attr));
		return $out->html();
	}
}

function in($name, $default_value = '')
{
	return new In($name, $default_value);
}

function submit($name, $label, $attributes = [])
{
	$field = new Submit($name, $label, $attributes);
	$field->autoEchoOn();
	return $field;
}

function button($name, $label, $attributes = [])
{
	$field = new Button($name, $label, $attributes);
	$field->autoEchoOn();
	return $field;
}

function imagebutton($name, $src, $attributes = [])
{
	$field = new ImageButton($name, $src, $attributes);
	$field->autoEchoOn();
	return $field;
}

function hidden($name, $default_value = '')
{
	$field = new Hidden($name, $default_value);
	$field->autoEchoOn();
	return $field;
}

function textinput($name, $default_value = '', $attributes = [])
{
	$field = new TextInput($name, $default_value, $attributes);
	$field->autoEchoOn();
	return $field;
}

function password($name, $default_value = '', $attributes = [])
{
	$field = new Password($name, $default_value, $attributes);
	$field->autoEchoOn();
	return $field;
}

function textarea($name, $default_value = '', $attributes = [])
{
	$field = new TextArea($name, $default_value, $attributes);
	$field->autoEchoOn();
	return $field;
}

function select($name, $values, $default_value = '', $attributes = [])
{
	$field = new Select($name, $values, $default_value, $attributes);
	$field->autoEchoOn();
	return $field;
}

function checkbox($name, $default_value = '', $attributes = [])
{
	$field = new Checkbox($name, $default_value, $attributes);
	$field->autoEchoOn();
	return $field;
}
