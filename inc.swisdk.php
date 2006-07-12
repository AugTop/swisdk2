<?php
	/*
	*	Project: SWISDK 2
	*	Author: Matthias Kestenholz < mk@irregular.ch >, Moritz Zumb�hl < mail@momoetomo.ch >
	*	Copyright (c) 2004, ProjectPflanzschulstrasse (http://pflanzschule.irregular.ch/)
	*	Distributed under the GNU Lesser General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
	*/

	class Swisdk {

		public static function runFromHttpRequest()
		{
			Swisdk::run(array('REQUEST_URI' =>
				((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']=='on')
					?'https://':'http://')
				.$_SERVER['SERVER_NAME']
				.(($_SERVER['SERVER_PORT']!=80)?':'.$_SERVER['SERVER_PORT']:'')
				.$_SERVER['REQUEST_URI']));
		}

		public static function runFromCommandLine()
		{
			/**
			 * the $_Server[Document_root] is not set when the call comes from the
			 * commandline.
			 * so we use the SCRIPT_NAME and assume that the file is in
			 * APP_ROOT/swisdk/commandline.php
			 */
			define('APP_ROOT', dirname(dirname(__FILE__)).'/');

			$requestUri = '';
			if( isset( $_SERVER['argv'][1]) ) {
				$requestUri = $_SERVER['argv'][1];
			}

			Swisdk::run( array( 'REQUEST_URI' => $requestUri  ) );
		}

		/**
		*	DO IT! ;)
		*	That means:
			1. Setup Error handling
			2. Read config
			3. Dispatch request
			4. Instance the controller and execute it
		*/
		public static function run($arguments)
		{
			if(!defined('APP_ROOT'))
				define('APP_ROOT', realpath($_SERVER['DOCUMENT_ROOT']
						.'/../../').'/');

			date_default_timezone_set('Europe/Zurich');

			define('HTDOCS_ROOT', APP_ROOT . 'webapp/htdocs/');
			define('SWISDK_ROOT', APP_ROOT . 'swisdk/');
			define('SMARTY_ROOT', SWISDK_ROOT . 'lib/smarty/');
			define('MODULE_ROOT', SWISDK_ROOT . 'modules/');
			define('WEBAPP_ROOT', APP_ROOT.'webapp/');
			define('LOG_ROOT', APP_ROOT.'log/');
			define('CONTENT_ROOT' , WEBAPP_ROOT . 'content/');
			define('CACHE_ROOT', WEBAPP_ROOT.'cache/');
			define('UPLOAD_ROOT', WEBAPP_ROOT.'upload/');

			require_once SWISDK_ROOT . 'core/inc.functions.php';
			require_once SWISDK_ROOT . 'core/inc.error.php';

			SwisdkError::setup();
			Swisdk::read_configfile();
			require_once SWISDK_ROOT . "dispatcher/inc.dispatcher.php";
			SwisdkControllerDispatcher::dispatch( $arguments['REQUEST_URI'] );
			require_once SWISDK_ROOT . 'site/inc.handlers.php';
			SwisdkSiteHandler::run();
		}

		protected static $config;

		public static function read_configfile()
		{
			if(file_exists(APP_ROOT.'webapp/config.ini')) {
				$cfg = parse_ini_file(APP_ROOT.'webapp/config.ini', true);
				foreach($cfg as $section => $array) {
					if(($pos=strpos($section, '.'))!==false) {
						$name = 'runtime.parser.'.substr($section, 0, $pos);
						if(!is_array(Swisdk::$config[$name]))
							Swisdk::$config[$name] = array();
						array_unshift(Swisdk::$config[$name],
							substr($section, $pos+1));
					}
					foreach($array as $key => $value)
						Swisdk::$config[$section.'.'.$key] = $value;
				}
			} else {
				SwisdkError::handle(new FatalError('No configuration file'));
			}
		}

		public static function dump()
		{
			if(!Swisdk::config_value('error.debug_mode'))
				return;
			echo '<pre>';
			echo '<strong>Swisdk config</strong><br />';
			print_r(Swisdk::$config);
			echo '</pre>';
		}

		public static function set_config_value($key, $value)
		{
			Swisdk::$config[$key] = $value;
		}

		public static function config_value($key)
		{
			if(isset(Swisdk::$config[$key]))
				return Swisdk::$config[$key];
			return null;
		}

		public static function language($key=null)
		{
			if($key) {
				$l = DBObject::db_get_array('SELECT * FROM tbl_language',
					array('language_key', 'language_id'));
				if(isset($l[$key]))
					return $l[$key];
			}

			if($val = Swisdk::config_value('runtime.language_id'))
				return $val;
			else if($val = Swisdk::config_value('runtime.language')) {
				$l = DBObject::db_get_array('SELECT * FROM tbl_language',
					array('language_key', 'language_id'));
				if(isset($l[$val]) && ($val = $l[$val])) {
					Swisdk::set_config_value('runtime.language_id', $val);
					return $val;
				}
			}
		}

		public static function register($class)
		{
			Swisdk::set_config_value('runtime.controller.class', $class);
		}

		/**
		*	Load a "module" (actually a module is just a php-file with a class inside).
		*	A module can exist in the swisdk-dir or the content dir. The parameter
		*	$dir is the ref path to the file.
		*/
		public static function load_module( $class , $dir , $instance = true )
		{
			if(class_exists($class)) {
				if($instance)
					return new $class;
				return true;
			}

			$filenotfound = false;

			// the file name is inc.classname_lowercase.php
			$file = "inc." . strtolower( $class ) . ".php";

			// now try to include the file in the dir unter swisdk
			$swisdkpath = SWISDK_ROOT . $dir . "/" . $file;

			if(file_exists($swisdkpath)) {
				require_once $swisdkpath;

				if( $instance )
					return Swisdk::module_instance( $class );
				else
					return true;
			}

			// now search under content
			$path = CONTENT_ROOT . $dir . "/" . $file;
			if(file_exists($path)) {
				require_once $path;

				if( $instance )
					return Swisdk::module_instance( $class );
				else
					return true;
			}

			// ok we just didnt find a file in the include path... return
			// error and say goodbye! :(

			return new FileNotFoundError("Could not load the module $class!"
				." Could not find the include-file and the class does"
				." not exist!", $dir);
		}

		public static function module_instance( $class )
		{
			if(class_exists($class))
				return new $class;
			else
				return CouldNotInstanceClassError(
					"Could no load the module $class!"
					." Class doesnt exist!", $class);
		}
	}

?>
