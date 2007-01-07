<?php
	/**
	*	Copyright (c) 2006, Matthias Kestenholz <mk@spinlock.ch>
	*	Distributed under the GNU General Public License.
	*	Read the entire license text here: http://www.gnu.org/licenses/gpl.html
	*/

	class MultiDomainDispatcher extends ControllerDispatcherModule {
		public function collect_informations()
		{
			$matches = array();
			$match = preg_match('/(http(s?):)\/\/([^\/]*)(:[0-9]+)?(.*)/',
				$this->input(), $matches);
			$this->set_output($matches[5]);

			Swisdk::set_config_value('runtime.request.protocol', $matches[1]);
			Swisdk::set_config_value('runtime.request.host', $matches[3]);
			Swisdk::set_config_value('runtime.request.uri', $matches[5]);

			$host = $matches[3];
			$out = null;

			$domains = Swisdk::config_value('runtime.parser.domain');

			if(!in_array($host, $domains)) {
				foreach($domains as &$d) {
					if($aliases = Swisdk::config_value('domain.'.$d.'.alias')) {
						$aliases = split('[ ,]+', $aliases);
						if(in_array($host, $aliases)) {
							$out = $d;
							break;
						}
					}
				}
			} else
				$out = $host;

			if($out) {
				Swisdk::set_config_value('runtime.domain', $out);
				Swisdk::set_config_value('runtime.website', Swisdk::config_value(
					'domain.'.$out.'.website'));
				Swisdk::add_loader_base(CONTENT_ROOT.$out.'/');
			} else {
				SwisdkError::handle(new FatalError(dgettext('swisdk',
					'No matching host found')));
			}
		}
	}
?>