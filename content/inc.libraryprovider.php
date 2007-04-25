<?php
	/*
	*	Copyright (c) 2007, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class LibraryProvider {
		protected $js_prefix;
		protected $libraries = array();
		protected $sent = array();

		public function __construct()
		{
			$this->js_prefix = Swisdk::webroot('js');
		}

		public function libraries()
		{
			return $this->libraries();
		}

		public function set_libraries($libraries)
		{
			$this->libraries = $libraries;
		}

		public function html()
		{
			$html = '';
			foreach($this->libraries as $l)
				$html .= $this->provide($l);
			return $html;
		}

		public function provide($library)
		{
			if(s_test($this->sent, $library))
				return '';

			if(method_exists($this, $m = 'provide_'.$library)) {
				$this->sent[$library] = true;
				return $this->$m();
			}
		}

		public function provide_jquery()
		{
			return <<<EOD
<script type="text/javascript" src="{$this->js_prefix}/jquery/jquery.js"></script>

EOD;
		}

		public function provide_jquery_datepicker()
		{
			return $this->provide('jquery').<<<EOD
<link type="text/css" rel="stylesheet" href="{$this->js_prefix}/jquery/datepicker/styles.css" />
<script type="text/javascript" src="{$this->js_prefix}/jquery/datepicker/datePicker.js"></script>

EOD;
		}

		public function provide_jquery_spinbutton()
		{
			return $this->provide('jquery').<<<EOD
<link type="text/css" rel="stylesheet" href="{$this->js_prefix}/jquery/spinbutton/JQuerySpinBtn.css" />
<script type="text/javascript" src="{$this->js_prefix}/jquery/spinbutton/JQuerySpinBtn.js"></script>

EOD;
		}

		public function provide_jquery_interface()
		{
			return $this->provide('jquery').<<<EOD
<script type="text/javascript" src="{$this->js_prefix}/jquery/interface.js"></script>

EOD;
		}

		public function provide_jquery_cookie()
		{
			return $this->provide('jquery').<<<EOD
<script type="text/javascript" src="{$this->js_prefix}/jquery/jquery.cookie.js"></script>

EOD;
		}

		public function provide_jquery_deserialize()
		{
			return $this->provide('jquery').<<<EOD
<script type="text/javascript" src="{$this->js_prefix}/jquery/jquery.deserialize.js"></script>

EOD;
		}

		public function provide_jquery_form()
		{
			return $this->provide('jquery').<<<EOD
<script type="text/javascript" src="{$this->js_prefix}/jquery/jquery.form.js"></script>

EOD;
		}

		public function provide_fckeditor()
		{
			return <<<EOD
<script type="text/javascript" src="{$this->js_prefix}/fckeditor/fckeditor.js"></script>

EOD;
		}

		public function provide_calendar()
		{
			return <<<EOD
<link rel="stylesheet" type="text/css" media="all"
	href="{$this->js_prefix}/calendar/calendar-win2k-1.css" title="win2k-cold-1" />
<script type="text/javascript" src="{$this->js_prefix}/calendar/calendar.js"></script>
<script type="text/javascript" src="{$this->js_prefix}/calendar/calendar-en.js"></script>
<script type="text/javascript" src="{$this->js_prefix}/calendar/calendar-setup.js"></script>

EOD;
		}
	}

?>
