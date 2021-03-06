<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	/**
	 * Returns the Form as a nested array
	 */
	class ArrayFormRenderer extends FormRenderer {
		protected $array = array('form' => array());
		protected $stack = array();
		protected $ref;
		protected $form;

		protected $form_id;
		protected $submitted = false;

		public function __construct()
		{
			$this->ref = 0;
			$this->stack[0] =& $this->array;
		}

		protected function visit_Form_start($obj)
		{
			$this->form = $obj;
			parent::visit_Form_start($obj);

			$this->form_id = $obj->id();

			$this->submitted = $obj->submitted();
		}

		protected function visit_FormBox_start($obj)
		{
			$this->form = $obj;
			$name = $obj->name();
			$this->stack[$this->ref][$name] = array();
			$this->stack[$this->ref+1] =& $this->stack[$this->ref][$name];
			$this->ref++;
			parent::visit_FormBox_start($obj);
		}

		protected function visit_FormBox_end($obj)
		{
			parent::visit_FormBox_end($obj);
			$this->ref--;
		}

		protected function visit_CaptchaInput($obj)
		{
			$this->_collect_javascript($obj);
			$c = $obj->captcha_html();
			$s = $this->_simpleinput_html($obj);

			$this->_render($obj, $c.'<br />'.$s);
			$name = $obj->name();

			$this->stack[$this->ref][$name]['html_captcha'] = $c;
			$this->stack[$this->ref][$name]['html_input'] = $s;
		}

		protected function _render($obj, $field_html, $row_class = null)
		{
			$valid = true;
			if($this->submitted)
				$valid = $obj->is_valid();

			$name = $obj->name();
			if(!$name)
				$name = uniqid();

			$this->stack[$this->ref][$name] = array(
				'type' => get_class($obj),
				'name' => $obj->name(),
				'html' => $field_html,
				'id' => $obj->id(),
				'value' => $obj->value(),
				'title' => $this->_title_html($obj),
				'title_raw' => $obj->title(),
				'message' => $this->_message_html($obj),
				'info' => $this->_info_html($obj),
				'valid' => $valid,
				'message_raw' => $obj->message(),
				'info_raw' => $obj->info());
		}

		protected function _render_bar($obj, $html, $row_class = null)
		{
			if($obj instanceof Form)
				$this->array['form'] = array(
					'type' => get_class($obj),
					'html' => $html);
			else if($obj instanceof FormItem)
				$this->_render($obj, $html, $row_class);
			else
				$this->stack[$this->ref][] = array(
					'type' => get_class($obj),
					'html' => $html);
		}

		public function fetch_array()
		{
			$this->array['form'] = array_merge($this->array['form'], array(
				'start' => $this->html_start,
				'end' => $this->html_end));
			if($this->form_id)
				$this->array['id'] = $this->form_id;
			return $this->array;
		}
	}

?>
