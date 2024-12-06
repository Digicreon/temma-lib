<?php

/**
 * Cache
 * @author	Amaury Bouchard <amaury@amaury.net>
 * @copyright	© 2011-2020, Amaury Bouchard
 */

namespace Temma\Plugins;

use \Temma\Base\Log as TµLog;

/**
 * Plugin used to keep in cache the HTML generated by views.
 *
 * @see	https://www.temma.net/fr/documentation/plugin-cache
 */
class Cache extends \Temma\Web\Plugin {
	/**
	 * Plugin method. Tell if a page could be pushed in the cache or not.
	 * @return	mixed	Always EXEC_FORWARD.
	 */
	public function preplugin() {
		TµLog::log('Temma/Web', 'INFO', "Cache plugin started.");
		// vérifications de base
		if (!empty($_SERVER['QUERY_STRING']) || $_SERVER['REQUEST_METHOD'] != 'GET') {
			TµLog::log('Temma/Web', 'DEBUG', "Unable to cache a request with parameters.");
			return (self::EXEC_FORWARD);
		}
		// load the cache configuration
		$cacheConf = $this->_loader->config->xtra('temma-cache');
		$dataSource = $cacheConf['source'] ?? null;
		if (!$dataSource && !isset($this->_loader->dataSources['cache'])) {
			TµLog::log('Temma/Web', 'DEBUG', "No cache configuration.");
			return (self::EXEC_FORWARD);
		}
		$cache = $this->_loader->dataSources[$dataSource ?? 'cache'];
		if (!$cache) {
			TµLog::log('Temma/Web', 'WARN', "The data source configured in cache configuration doesn't exist.");
			return (self::EXEC_FORWARD);
		}
		// check if the cache ids disabled by a session variable
		if (isset($cacheConf['sessionNoCache'])) {
			if (!is_array($cacheConf['sessionNoCache']))
				$cacheConf['sessionNoCache'] = [$cacheConf['sessionNoCache']];
			foreach ($cacheConf['sessionNoCache'] as $varName) {
				if (($sessionData = $this->_loader->session->get($varName))) {
					TµLog::log('Temma/Web', 'DEBUG', "Cache disabled by session variable '$varName'.");
					return (self::EXEC_FORWARD);
				}
			}
		}
		// URL check
		$isCacheable = false;
		if (!isset($cacheConf['url']) && !isset($cacheConf['prefix'])) {
			TµLog::log('Temma/Web', 'DEBUG', "No URL configuration. Page cacheable.");
			$isCacheable = true;
		} else if (isset($cacheConf['url']) && is_array($cacheConf['url']) &&
		    in_array($_SERVER['REQUEST_URI'], $cacheConf['url'])) {
			TµLog::log('Temma/Web', 'DEBUG', "Strict URL found.");
			$isCacheable = true;
		} else if (isset($cacheConf['prefix']) && is_array($cacheConf['prefix'])) {
			foreach ($cacheConf['prefix'] as $prefix) {
				if (mb_substr($_SERVER['REQUEST_URI'], 0, mb_strlen($prefix)) == $prefix) {
					TµLog::log('Temma/Web', 'DEBUG', "Prefixed URL found.");
					$isCacheable = true;
					break;
				}
			}
		}
		// is the current URL's page could be stored in cache
		if (!$isCacheable)
			return (self::EXEC_FORWARD);
		// use the cache
		$this['_temmaCacheable'] = true;
		// check if the content is already in cache
		$cacheVarName = $_SERVER['HTTP_HOST'] . ':' . $_SERVER['REQUEST_URI'];
		$data = $cache->setPrefix('temma-cache')->get($cacheVarName);
		$cache->setPrefix();
		if (!empty($data)) {
			// the page was found in cache: send it to the client and quit
			TµLog::log('Temma/Web', 'DEBUG', "Write from cache.");
			print($data);
			return (self::EXEC_QUIT);
		}
	}
}

