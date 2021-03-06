<?php
	/*
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	abstract class FormRenderer {

		protected $html_start = '';
		protected $html_end = '';
		protected $javascript = '';
		protected $validation_javascript = '';
		protected $file_upload = false;
		protected $js_validation = true;
		protected $form_submitted = null;

		/**
		 * Render the HTML of an input field
		 */
		abstract protected function _render($obj, $field_html, $row_class = null);

		/**
		 * Render the HTML of a bar which (normally) spans the whole width of the
		 * form, f.e. a form title or a section title
		 */
		abstract protected function _render_bar($obj, $html, $row_class = null);

		/**
		 * Add a HTML fragment
		 *
		 * <form> (added by call to add_html_start() in visit_Form_end)
		 *
		 * add_html_start()
		 *
		 * 	head
		 *
		 * add_html()
		 *
		 * 	body (input elements)
		 *
		 * 	foot
		 *
		 * add_html_end()
		 *
		 * </form> (added by call to add_html_end() in visit_Form_end)
		 *
		 */
		public function add_html($html)
		{
			$this->html_start .= $html;
		}

		public function add_html_start($html)
		{
			$this->html_start = $html.$this->html_start;
		}

		public function add_html_end($html)
		{
			$this->html_end .= $html;
		}

		/**
		 * Array of javascript validation functions
		 */
		protected $functions = array();

		/**
		 * Enable/disable validation javascript output (has no effect on
		 * the server side of validation)
		 */
		public function set_javascript_validation($enabled = true)
		{
			$this->js_validation = $enabled;
		}

		public function javascript_validation()
		{
			return $this->js_validation;
		}

		/**
		 * handle the passed object
		 *
		 * it first tries to find a method named visit_ObjectClass , and if
		 * not successful, walks the inheritance ancestry to find a matching
		 * visit method.
		 *
		 * That way, you can derive your own FormItems without necessarily
		 * needing to extend the FormRenderer
		 */
		public function visit($obj, $stage = FORMRENDERER_VISIT_DEFAULT)
		{
			$suffix = '';
			if($stage==FORMRENDERER_VISIT_START)
				$suffix = '_start';
			else if($stage==FORMRENDERER_VISIT_END)
				$suffix = '_end';

			$class = get_class($obj);
			$method = 'visit_'.$class.$suffix;
			if(method_exists($this, $method)) {
				return call_user_func(array($this, $method), $obj);
			}

			$parents = class_parents($class);
			foreach($parents as $p) {
				$method = 'visit_'.$p.$suffix;
				if(method_exists($this, $method)) {
					return call_user_func(array($this, $method),
						$obj);
				}
			}

			if(method_exists($obj, 'html')) {
				$this->_collect_javascript($obj);
				$this->_render($obj, $obj->html());
				return;
			}

			SwisdkError::handle(new FatalError(sprintf(
				_T('FormRenderer: Cannot visit %s'), $class)));
		}

		protected function visit_Form_start($obj)
		{
			$this->form_submitted = $obj->submitted();
			if($title = $obj->title())
				$this->_render_bar($obj,
					'<big><strong>'.$title.'</strong></big>',
					'sf-form-title');
		}

		protected function visit_Form_end($obj)
		{
			$this->_collect_javascript($obj);

			$upload = '';
			$valid = '';
			if($this->file_upload)
				$upload = 'enctype="multipart/form-data">'."\n"
					.'<input type="hidden" name="MAX_FILE_SIZE" '
					.'value="'
					.str_replace(array('k', 'm'), array('000', '000000'),
						strtolower(ini_get('upload_max_filesize')))
					.'"';
			list($html, $js) = $this->_validation_html($obj);
			$this->add_html_start(
				'<form method="'.$obj->method()
				.'" action="'.htmlspecialchars($obj->action())
				.'" id="'.$obj->id()."\" $html class=\"sf-form\" "
				."accept-charset=\"utf-8\" $upload>\n<div>\n".$js);
			$this->add_html_end($this->_message_html($obj));
			$this->add_html_end($this->_info_html($obj));
			$this->add_html_end("</div></form>\n");
		}

		protected function _validation_html($obj)
		{
			$js = '<script type="text/javascript">
//<![CDATA[
'.$this->javascript;
			$end = '
//]]>
</script>';
			if(!$this->js_validation || !count($this->functions))
				return array(null, $js.$end);

			$id = $obj->id();
			$js .= $this->validation_javascript.'
function swisdk_form_do_validate(valid, field, message)
{
	if(!valid) {
		document.getElementById(field+\'_msg\').innerHTML += message;
		return false;
	}
	return true;
}

function validate_'.$id.'()
{
	var valid = true;
	var form = document.getElementById(\''.$id.'\');
	for(var i=0; i<form.elements.length; i++) {
		spanElem = document.getElementById(form.elements[i].id+\'_msg\');
		if(spanElem)
			spanElem.innerHTML = \' \';
	}
	document.getElementById(\''.$id.'_msg\').innerHTML = \' \';
	if(!'.implode(") valid = false;\n\tif(!", $this->functions).') valid = false;
	return valid;
}';
			return array('onsubmit="return validate_'.$id.'()"', $js.$end);
		}

		protected function _collect_javascript($obj)
		{
			$this->javascript .= $obj->javascript();

			if(!$this->js_validation)
				return;

			// add validation rule javascript
			$rules = $obj->rules();
			foreach($rules as &$rule) {
				list($funccall, $js) = $rule->validation_javascript($obj);
				$this->validation_javascript .= $js;
				if($funccall)
					$this->functions[] = $funccall;
			}
		}

		protected function visit_FormBox_start($obj)
		{
			// FIXME placement of message div should not always be at the
			// end of form (end of FormBox!)
			$this->add_html_end($this->_message_html($obj));
			if($title = $obj->title())
				$this->_render_bar($obj, '<strong>'.$title.'</strong>',
					'sf-box-title');
		}

		protected function visit_FormBox_end($obj)
		{
		}

		protected function visit_HiddenInput($obj)
		{
			$this->_collect_javascript($obj);
			$this->add_html($this->_simpleinput_html($obj)."\n");
		}

		protected function visit_HiddenArrayInput($obj)
		{
			$name = $obj->id();
			return sprintf(
				'<input type="hidden" name="%s" id="%s" value="%s" %s />',
				$name, $name, htmlspecialchars(implode(',', $obj->value())),
				$this->_attribute_html($obj));
		}

		protected function visit_SimpleInput($obj)
		{
			$this->_collect_javascript($obj);
			$this->_render($obj, $this->_simpleinput_html($obj));
		}

		protected function visit_TextInput($obj)
		{
			$this->_collect_javascript($obj);
			$value = $obj->value();
			if($format = $obj->format())
				$value = sprintf($format, $value);

			$this->_render($obj, $this->_simpleinput_html($obj, $value));
		}

		protected function visit_SpinButton($obj)
		{
			$this->_collect_javascript($obj);
			$id = $obj->id();
			$range = $obj->range();
			$cfg = array();

			if(($min = s_get($range, 0))!==null)
				$cfg[] = 'min:'.$min;
			if(($max = s_get($range, 1))!==null)
				$cfg[] = 'max:'.$max;
			if(($step = s_get($range, 2))!==null)
				$cfg[] = 'step:'.$step;

			$cfg = '{'.implode(',', $cfg).'}';

			$js = <<<EOD
<script type="text/javascript">
//<![CDATA[
$(function(){
	$("#$id").SpinButton($cfg);
});
//]]>
</script>

EOD;

			$this->_render($obj, $js.$this->_simpleinput_html($obj));
		}

		protected function visit_CaptchaInput($obj)
		{
			$this->_collect_javascript($obj);
			$this->_render($obj, $obj->captcha_html()
				.$this->_simpleinput_html($obj));
		}

		protected function visit_TagInput($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$this->_render($obj, sprintf(
				'<input type="%s" name="%s" id="%s" value="%s" %s />',
				$obj->type(), $name, $name, htmlspecialchars($obj->tag_string()),
				$this->_attribute_html($obj)));
		}

		protected function visit_FileUpload($obj)
		{
			$this->_collect_javascript($obj);
			$this->file_upload = true;
			$this->visit_SimpleInput($obj);
		}

		protected function visit_DBFileUpload($obj)
		{
			$this->_collect_javascript($obj);
			$this->file_upload = true;
			$name = $obj->id();
			$current = htmlspecialchars($obj->current_value());
			$this->_render($obj, sprintf(
				'%s<input type="%s" name="%s" id="%s" value="%s" %s />',
				($current
					?sprintf('Current: <a href="%s/%s">%s</a>, '
						.'remove? <input type="checkbox" '
							.'name="%s__delete" id="%s__delete" /><br />',
						$obj->download_controller(),
						$current, $current, $name, $name)
					:''),
				$obj->type(), $name, $name, htmlspecialchars($obj->value()),
				$this->_attribute_html($obj)));
		}

		protected function visit_CheckboxInput($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$this->_render($obj, sprintf(
				'<input type="checkbox" name="%s" id="%s" %s value="1" />'
				.'<input type="hidden" name="'.$name
				.'__check" value="1" />',
				$name, $name,
				($obj->value()?'checked="checked" ':' ')
				.$this->_attribute_html($obj)));
		}

		protected function visit_TristateInput($obj)
		{
			$this->_collect_javascript($obj);
			static $js = '#';
			if($js=='#')
				$js = <<<EOD
<script type="text/javascript">
//<![CDATA[
function formitem_tristate(elem)
{
	var value = document.getElementById(elem.id.replace(/__cont$/, ''));
	var cb = document.getElementById(value.id+'__cb');

	switch(value.value) {
		case 'checked':
			cb.checked = false;
			value.value = 'unchecked';
			break;
		case 'unchecked':
			cb.checked = true;
			cb.disabled = true;
			value.value = 'mixed';
			break;
		case 'mixed':
		default:
			cb.checked = true;
			cb.disabled = false;
			value.value = 'checked';
			break;
	}

	return false;
}
//]]>
</script>

EOD;
			$name = $obj->id();
			$value = $obj->value();
			$cb_html = '';
			if($value=='mixed')
				$cb_html = 'checked="checked" disabled="disabled"';
			else if($value=='checked')
				$cb_html = 'checked="checked"';
			$this->_render($obj, $js.sprintf(
'<span style="position:relative;">
	<div style="position:absolute;top:0;left:0;width:20px;height:20px;"
		id="%s__cont" onclick="formitem_tristate(this)"></div>
	<input type="checkbox" name="%s__cb" id="%s__cb" %s />
	<input type="hidden" name="%s" id="%s" value="%s" />
</span>'."\n", $name, $name, $name, $cb_html, $name, $name, htmlspecialchars($value)));

			// only send the javascript once
			$js = '';
		}

		protected function _auto_xss_helper($obj)
		{
			if($obj->auto_xss_protection())
				return;

			$id = $obj->id().'__xss';
			$checked = 'checked="checked"';
			if($obj->xss_protection()==false)
				$checked = '';

			return sprintf(<<<EOD
<div class="sf-element-ext">
	<input type="checkbox" id="%s" name="%s" $checked />
	<label for="%s">%s</label>
</div>
EOD
,
				$id, $id, $id, _T('Automatic XSS protection'));
		}

		protected function visit_Textarea($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$this->_render($obj, sprintf(
				'<textarea name="%s" id="%s" %s>%s</textarea>%s',
				$name, $name,
				$this->_attribute_html($obj),
				$obj->value(),
				$this->_auto_xss_helper($obj)));
		}

		protected function visit_WymEditor($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$this->_render($obj, sprintf(
				'<textarea name="%s" id="%s" %s>%s</textarea>%s',
				$name, $name,
				$this->_attribute_html($obj),
				$obj->value(),
				$this->_auto_xss_helper($obj)));
		}

		protected function visit_RichTextarea($obj)
		{
			$prefix = Swisdk::config_value('runtime.webroot.js', '/js');
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$value = htmlspecialchars($obj->value());
			$attributes = $this->_attribute_html($obj);
			$type = $obj->type();
			$auto_xss_protection = $this->_auto_xss_helper($obj);
			$html = <<<EOD
<textarea name="$name" id="$name" $attributes>$value</textarea>
<script type="text/javascript">
//<![CDATA[
$(function(){
	var oFCKeditor = new FCKeditor('$name');
	oFCKeditor.BasePath = '$prefix/fckeditor/';
	oFCKeditor.Height = 450;
	oFCKeditor.Width = 550;
	oFCKeditor.ToolbarSet = '$type';
	oFCKeditor.ReplaceTextarea();
});
//]]>
</script>
$auto_xss_protection

EOD;
			$this->_render($obj, $html);
		}

		protected function visit_DropdownInput($obj)
		{
			$this->dropdown_helper($obj, $obj->value());
		}

		protected function visit_TimeInput($obj)
		{
			$this->dropdown_helper($obj, $obj->value());
		}

		protected function dropdown_helper($obj, $value)
		{
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$html = '<select name="'.$name.'" id="'.$name.'"'
				.$this->_attribute_html($obj).">\n";
			$items = $obj->items();
			foreach($items as $id => $title) {
				$html .= '<option ';
				if((is_numeric($id) && $id===intval($value))
						|| (!is_numeric($id) && $id==="$value"))
					$html .= ' selected="selected" ';
				$html .= 'value="'.htmlspecialchars($id).'">'
					.htmlspecialchars($title)."</option>\n";
			}
			$html .= "</select>\n";
			$this->_render($obj, $html);
		}

		protected function visit_RadioButtons($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$attributes = $this->_attribute_html($obj);
			$html = '<span '
				.$this->_attribute_html($obj)
				.'>';
			$value = $obj->value();
			$items = $obj->items();
			foreach($items as $id => $title) {
				$html .= '<span><input style="width:auto" type="radio" id="'
					.htmlspecialchars($name.$id).'" name="'
					.htmlspecialchars($name).'"';
				if((is_numeric($id) && $id===intval($value))
						|| (!is_numeric($id) && $id==="$value"))
					$html .= ' checked="checked" ';
				$html .= 'value="'.htmlspecialchars($id).'"><label for="'
					.htmlspecialchars($name.$id).'">'
					.htmlspecialchars($title)."</label></span>\n";
			}
			$html .= '</span>';
			$this->_render($obj, $html);
		}

		protected function visit_Combobox($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$html = '<select name="'.$name.'" id="'.$name.'"'
				.$this->_attribute_html($obj).">\n";
			$value = $obj->value();
			$items = $obj->items();
			foreach($items as $id => $title) {
				$html .= '<option ';
				if((is_numeric($id) && $id===intval($value))
						|| (!is_numeric($id) && $id==="$value"))
					$html .= ' selected="selected" ';
				$html .= 'value="'.htmlspecialchars($id).'">'
					.htmlspecialchars($title)."</option>\n";
			}
			$html .= "</select>\n";
			$html .= '<input type="button" name="'.$name.'_button"'
				.'id="'.$name.'_button" value=" + " />';
			$js = '';
			static $js_sent = false;
			if(!$js_sent) {
				$js_sent = true;
				$js = file_get_contents(SWISDK_ROOT
					.'lib/contrib/combobox.js')."\n";
			}
			$html .= <<<EOD
<script type="text/javascript">
//<![CDATA[
$js	toCombo("$name","{$name}_button");
//]]>
</script>
EOD;
			$this->_render($obj, $html);
		}

		protected function visit_Multiselect($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$html = '<select name="'.$name.'[]" id="'.$name
				.'" multiple="multiple"'
				.$this->_attribute_html($obj).">\n";
			$value = $obj->value();
			if(!$value)
				$value = array();
			$items = $obj->items();
			foreach($items as $k => $v) {
				$html .= '<option ';
				if(in_array($k,$value))
					$html .= ' selected="selected" ';
				$html .= 'value="'.htmlspecialchars($k).'">'
					.$v."</option>\n";
			}
			$html .= "</select>\n";
			$html .= '<input type="hidden" name="'.$name.'__check" value="1" />'."\n";
			$this->_render($obj, $html);
		}

		protected function visit_ThreewayInput($obj)
		{
			$name = $obj->id();

			require_once SWISDK_ROOT.'lib/contrib/json.php';
			$json = new HTML_AJAX_JSON();
			$v = $json->encode($obj->value());
			$s = $json->encode($obj->second());
			$c = $json->encode($obj->choices());
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			$class = $obj->_class();

			$html = <<<EOD
<input type="hidden" name="{$name}__check" value="1" />
<span id="{$name}">
</span>
<img src="$prefix/icons/add.png" onclick="javascript:open_$name()" style="cursor:pointer" />

<script type="text/javascript">
//<![CDATA[
var {$name}_choices = eval('($c)');
var {$name}_second = eval('($s)');

function threeway_populate_select_$name(elem)
{
	while(elem.options.length)
		elem.options[0] = null;
	for(val in {$name}_choices)
		elem.options[elem.options.length] = new Option({$name}_choices[val], val);
}

function threeway_create_input_$name(second, third)
{
	var myself = document.getElementById('$name');

	var span = document.createElement('span');
	var input = document.createElement('select');
	threeway_populate_select_$name(input);
	input.name = '{$name}['+second+'][]';
	input.value = third;
	myself.appendChild(span);
	span.appendChild(document.createTextNode({$name}_second[second]+' '));
	span.appendChild(input);

	var img = document.createElement('img');
	img.src = '$prefix/icons/delete.png';
	img.style.cursor = 'pointer';
	$(img).click(function(){
		var n = this.parentNode;
		n.parentNode.removeChild(n);
	});
	span.appendChild(img);

	span.appendChild(document.createElement('br'));
}

function open_$name()
{
	var inputs = document.getElementById('$name').getElementsByTagName('select');
	var str = '';
	for(i=0; i<inputs.length; i++)
		str += inputs[i].id.replace(/^.*\[([0-9]+)\]$/, '$1')+',';

	str = '';

	window.open('/__swisdk__/picker?element=$name&class=$class&params[:exclude_ids]='+str, '$name',
		'width=400,height=600,toolbar=no,location=no,resizable=yes,scrollbars=yes');
	return false;
}

function select_$name(val, str)
{
	threeway_create_input_$name(val);
}


$(function(){
	var values = eval('({$v})');

	for(second in values) {
		for(third in values[second]) {
			threeway_create_input_$name(second, values[second][third]);
		}
	}
});
//]]>
</script>

EOD;
			$this->_render($obj, $html);
			return;
		}

		protected function visit_InlineEditor($obj)
		{
			$this->_collect_javascript($obj);

			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			$class = $obj->_class();
			$fields = $obj->fields();
			$name = $obj->id();

			$empty_field = "<div id=\"{$name}___ID__\">";
			foreach($fields as $f)
				$empty_field .= "$f: <input style=\"float:none\" type=\"text\" "
					."id=\"{$name}[__ID__][$f]\" name=\"{$name}[__ID__][$f]\" value=\"\" /> ";

			$empty_field .= "<img src=\"$prefix/icons/delete.png\" "
				."onclick=\"remove_$name(\'__ID__\')\" style=\"cursor:pointer\" /><div>";

			$html = <<<EOD
<script type="text/javascript">
//<![CDATA[
function remove_$name(id)
{
	elem = document.getElementById('{$name}_'+id);
	elem.parentNode.removeChild(elem);
}

function add_$name(val, str)
{
	elem = document.getElementById('$name');

	// save the values ...
	var inputs = elem.getElementsByTagName('input');
	var values = new Array();
	for(i=0; i<inputs.length; i++)
		values[inputs[i].id] = inputs[i].value;

	var i=1;
	while(document.getElementById('{$name}_new'+i))
		i++;

	html = '$empty_field'.replace(/__ID__/g, 'new'+i);

	elem.innerHTML += html;

	// ... and restore them (they get clobbered when appending to innerHTML)
	for(i=0; i<inputs.length; i++) {
		if(values[inputs[i].id])
			inputs[i].value = values[inputs[i].id];
	}
}
//]]>
</script>

EOD;
			$html .= "<div id=\"$name\">";

			$current = DBOContainer::find_by_id($class, $obj->value());

			foreach($current as $dbo) {
				$id = $dbo->id();
				$html .= <<<EOD
<div id="{$name}_$id">

EOD;
				foreach($fields as $f) {
					$value = htmlspecialchars($dbo->$f);
					$field_id = $name."[$id][$f]";
					$html .= <<<EOD
$f: <input style="float:none" type="text" id="$field_id" name="$field_id" value="$value" />

EOD;
				}

				$html .= <<<EOD
<img src="$prefix/icons/delete.png" onclick="remove_$name($id)" style="cursor:pointer" />
</div>

EOD;
			}

			$html .= str_replace(
				array('__ID__', '\\\''),
				array('new1', '\''),
				$empty_field).<<<EOD
</div>
</div>
</div>

<img src="$prefix/icons/add.png" onclick="javascript:add_$name()" style="cursor:pointer" />

EOD;
			$this->_render($obj, $html);
		}

		protected function visit_DateInput($obj)
		{
			$prefix = Swisdk::config_value('runtime.webroot.js', '/js');
			$this->_collect_javascript($obj);
			$html = '';

			$format = '%d.%m.%Y';

			$name = $obj->id();
			$trigger_name = $name.'_trigger';
			$value = intval($obj->value());
			if(!$value)
				$value = time();

			$display_value = strftime($format, $value);

			$close_actions = '';
			$select_actions = '';

			$actions = $obj->actions();
			if(isset($actions['close']))
				$close_actions = $actions['close'];
			if(isset($actions['select']))
				$select_actions = $actions['select'];

			$properties = $obj->properties();
			$p = '';
			foreach($properties as $k => $v) {
				$p .= ",\n$k: $v";
			}

			$html.=<<<EOD
<input class="date-picker" type="text" name="$name" id="$name" value="$display_value" />
<script type="text/javascript">
//<![CDATA[
Date.firstDayOfWeek = 1;
Date.format = 'dd.mm.yyyy';
$(function(){
	$('#$name').datePicker({
		startDate: '01/01/1970'$p
	});
});
//]]>
</script>

EOD;

			if($obj->time()) {
				$date = getdate($value);
				$hour = $date['hours'];
				$minute = $date['minutes'];

				$html .= '<select id="'.$name.'__hour" name="'.$name.'__hour"
						style="width:50px;float:none">';
				for($h=0; $h<24; $h++) {
					$s = '';
					if($hour==$h)
						$s = ' selected="selected"';
					$html .= '<option value="'.$h.'"'.$s.'>'
						.str_pad($h, 2, '0', STR_PAD_LEFT).'</option>';
				}
				$html .= '</select>';
				$html .= '<select id="'.$name.'__minute" name="'.$name.'__minute"
						style="width:50px;float:none">';
				for($m=0; $m<60; $m+=5) {
					$s = '';
					if($minute>=$m && $minute<$m+5)
						$s = ' selected="selected"';
					$html .= '<option value="'.$m.'"'.$s.'>'
						.str_pad($m, 2, '0', STR_PAD_LEFT).'</option>';
				}
				$html .= '</select>';

			}

			$html .= '<br style="clear:left;line-height:1px;visibility:hidden;" />';

			$this->_render($obj, $html);
		}

		protected function visit_PickerBase($obj)
		{
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			$this->_collect_javascript($obj);
			$name = $obj->id();
			$value = htmlspecialchars($obj->value());
			$display = $obj->display_string();
			$url = $obj->popup_url();
			$behavior_functions = $obj->behavior_functions();
			$html = <<<EOD
<input type="text" id="$name" name="$name" value="$value"
	style="visibility:hidden;height:0px;width:0px;border:none;" />
<span id="{$name}_span">$display</span>
<a href="javascript:open_$name()"><img src="$prefix/icons/database_edit.png" /></a>
<a href="javascript:select_$name(0, '')"><img src="$prefix/icons/cross.png" /></a>
<script type="text/javascript">
//<![CDATA[
function open_$name()
{
	window.open('$url', '$name', 'width=400,height=600,toolbar=no,location=no,resizable=yes,scrollbars=yes');
}

function select_$name(val, str)
{
	var elem = document.getElementById('$name');
	elem.value = val;
	if(!str)
		str = '&hellip;';
	document.getElementById('{$name}_span').innerHTML = str;
	$behavior_functions
}
//]]>
</script>

EOD;
			$this->_render($obj, $html);
		}

		protected function visit_ListSelector($obj)
		{
			$name = $obj->id();
			$class = $obj->_class();
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');

			$html = <<<EOD
<script type="text/javascript">
//<![CDATA[
function remove_$name(id)
{
	elem = document.getElementById('{$name}_'+id);
	elem.parentNode.removeChild(elem);
}

function open_$name()
{
	var inputs = document.getElementById('$name').getElementsByTagName('input');
	var str = '';
	for(i=0; i<inputs.length; i++)
		str += inputs[i].value+',';

	window.open('/__swisdk__/picker?element=$name&class=$class&params[:exclude_ids]='+str, '$name',
		'width=400,height=600,toolbar=no,location=no,resizable=yes,scrollbars=yes');
	return false;
}

function select_$name(val, str)
{
	elem = document.getElementById('$name');

	html = '<div id="{$name}_'+val+'">';
	html += '<input type="hidden" name="{$name}[]" value="'+val+'" />';
	html += '<img src="$prefix/icons/delete.png" onclick="remove_$name(';
	html += val+')" style="cursor:pointer" /> '+str+'</div>';

	elem.innerHTML += html;
}
//]]>
</script>

<div id="$name">

EOD;

			$items = $obj->items();
			$value = $obj->value();
			foreach($value as $v) {
				$html .= <<<EOD
<div id="{$name}_$v">
	<input type="hidden" name="{$name}[]" value="$v" />
	<img src="$prefix/icons/delete.png" onclick="remove_$name($v)" style="cursor:pointer" />
	{$items[$v]}
</div>

EOD;
			}

			$html .= <<<EOD
</div>
<img src="$prefix/icons/add.png" onclick="javascript:open_$name()" style="cursor:pointer" />

EOD;

			$this->_render($obj, $html);
		}

		protected function visit_GroupItem($obj)
		{
			$this->_render($obj, $this->_groupitem_helper($obj));
		}

		protected function visit_GroupItemBar($obj)
		{
			$this->_render_bar($obj, $this->_groupitem_helper($obj));
		}

		protected function _groupitem_helper($obj)
		{
			require_once MODULE_ROOT.'inc.form.arrayrenderer.php';
			$renderer = new ArrayFormRenderer();

			$obj->box()->accept($renderer);
			$name = $obj->name();
			$elems = $renderer->fetch_array();

			$html = array();
			$msg = array();

			$this->functions = array_merge($this->functions, $renderer->functions);
			$this->html_start .= $renderer->html_start;
			$this->html_end = $renderer->html_end.$this->html_end;
			$this->javascript .= $renderer->javascript;
			$this->validation_javascript .= $renderer->validation_javascript;
			if($renderer->file_upload)
				$this->file_upload = $renderer->file_upload;

			foreach($elems[$name] as $elem) {
				$h = '';
				if($elem['id'] && $elem['title'])
					$h = <<<EOD
<label for="{$elem['id']}">{$elem['title']}</label>

EOD;
				$html[] = $h.$elem['html']."\n";
				$msg[] = $elem['info'].$elem['message'];
			}

			$s = $obj->separator();

			return implode($s, $html).implode($s, $msg);
		}

		protected function visit_FormButton($obj)
		{
			$this->_collect_javascript($obj);
			$name = $obj->name();
			$caption = htmlspecialchars($obj->caption());
			if(!$caption)
				$caption = 'Submit';
			$this->_render_bar($obj,
				'<input type="submit" '.$this->_attribute_html($obj)
				.($name?' name="'.$name.'"':'')
				.' value="'.$caption.'" />',
				'sf-button');
		}

		protected function _title_html($obj)
		{
			if(($i = $obj->id()) && ($t = $obj->title()))
				return '<label class="sf-label" for="'.$i.'">'.$t.'</label>';
			return '';
		}

		protected function _message_html($obj)
		{
			$msg = ($this->form_submitted===true)?$obj->message():null;
			$name = $obj->id();
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			return '<div id="'.$name.'_msg" style="clear:both;color:red">'
				.($msg?
				'<img style="position:relative;top:4px" src="'.$prefix.'/icons/error.png" alt="error" />'
				.$msg:' ')."</div>\n";
		}

		protected function _info_html($obj)
		{
			$msg = $obj->info();
			$prefix = Swisdk::config_value('runtime.webroot.img', '/img');
			return $msg?'<div style="float:left;clear:both;font-size:80%">'
				.'<img style="position:relative;top:4px;" src="'.$prefix.'/icons/information.png" alt="info" /> '
				.$msg."</div>\n":'';
		}

		protected function _simpleinput_html($obj, $value=null)
		{
			if($value===null)
				$value = $obj->value();
			$name = $obj->id();
			return sprintf(
				'<input type="%s" name="%s" id="%s" value="%s" %s />',
				$obj->type(), $name, $name, htmlspecialchars($value),
				$this->_attribute_html($obj));
		}

		/**
		 * helper function which composes a html-compatible attribute
		 * string
		 */
		protected function _attribute_html($obj)
		{
			$attributes = $obj->attributes();
			$css_class = $obj->css_class();
			$attributes['class'] = s_get($attributes, 'class').$css_class;
			$html = ' ';
			foreach($attributes as $k => $v)
				if($v)
					$html .= $k.'="'.htmlspecialchars($v).'" ';
			return $html;
		}
	}

?>
