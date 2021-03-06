<?php
namespace Friendica\Core;
/**
 * @file include/Core/PConfig.php
 * @brief contains the class with methods for the management
 * of the user configuration
 */

/**
 * @brief Management of user configuration storage
 * Note:
 * Please do not store booleans - convert to 0/1 integer values
 * The PConfig::get() functions return boolean false for keys that are unset,
 * and this could lead to subtle bugs.
 */
class PConfig {

	/**
	 * @brief Loads all configuration values of a user's config family into a cached storage.
	 *
	 * All configuration values of the given user are stored in global cache
	 * which is available under the global variable $a->config[$uid].
	 *
	 * @param string $uid
	 *  The user_id
	 * @param string $family
	 *  The category of the configuration value
	 * @return void
	 */
	public static function load($uid,$family) {
		global $a;
		$r = q("SELECT `v`,`k` FROM `pconfig` WHERE `cat` = '%s' AND `uid` = %d",
			dbesc($family),
			intval($uid)
		);
		if(count($r)) {
			foreach($r as $rr) {
				$k = $rr['k'];
				$a->config[$uid][$family][$k] = $rr['v'];
			}
		} else if ($family != 'config') {
			// Negative caching
			$a->config[$uid][$family] = "!<unset>!";
		}
	}

	/**
	 * @brief Get a particular user's config variable given the category name
	 * ($family) and a key.
	 *
	 * Get a particular user's config value from the given category ($family)
	 * and the $key from a cached storage in $a->config[$uid].
	 *
	 * @param string $uid
	 *  The user_id
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to query
	 * @param mixed $default_value optional
	 *  The value to return if key is not set (default: null)
	 * @param boolean $refresh optional
	 *  If true the config is loaded from the db and not from the cache (default: false)
	 * @return mixed Stored value or null if it does not exist
	 */
	public static function get($uid, $family, $key, $default_value = null, $refresh = false) {

		global $a;

		if(! $instore) {
			// Looking if the whole family isn't set
			if(isset($a->config[$uid][$family])) {
				if($a->config[$uid][$family] === '!<unset>!') {
					return $default_value;
				}
			}

			if(isset($a->config[$uid][$family][$key])) {
				if($a->config[$uid][$family][$key] === '!<unset>!') {
					return $default_value;
				}
				return $a->config[$uid][$family][$key];
			}
		}

		// If APC is enabled then fetch the data from there, else try XCache
		/*if (function_exists("apc_fetch") AND function_exists("apc_exists"))
			if (apc_exists($uid."|".$family."|".$key)) {
				$val = apc_fetch($uid."|".$family."|".$key);
				$a->config[$uid][$family][$key] = $val;

				if ($val === '!<unset>!')
					return false;
				else
					return $val;
			}
		elseif (function_exists("xcache_get") AND function_exists("xcache_isset"))
			if (xcache_isset($uid."|".$family."|".$key)) {
				$val = xcache_get($uid."|".$family."|".$key);
				$a->config[$uid][$family][$key] = $val;

				if ($val === '!<unset>!')
					return false;
				else
					return $val;
			}*/


		$ret = q("SELECT `v` FROM `pconfig` WHERE `uid` = %d AND `cat` = '%s' AND `k` = '%s' LIMIT 1",
			intval($uid),
			dbesc($family),
			dbesc($key)
		);

		if(count($ret)) {
			$val = (preg_match("|^a:[0-9]+:{.*}$|s", $ret[0]['v'])?unserialize( $ret[0]['v']):$ret[0]['v']);
			$a->config[$uid][$family][$key] = $val;

			// If APC is enabled then store the data there, else try XCache
			/*if (function_exists("apc_store"))
				apc_store($uid."|".$family."|".$key, $val, 600);
			elseif (function_exists("xcache_set"))
				xcache_set($uid."|".$family."|".$key, $val, 600);*/

			return $val;
		}
		else {
			$a->config[$uid][$family][$key] = '!<unset>!';

			// If APC is enabled then store the data there, else try XCache
			/*if (function_exists("apc_store"))
				apc_store($uid."|".$family."|".$key, '!<unset>!', 600);
			elseif (function_exists("xcache_set"))
				xcache_set($uid."|".$family."|".$key, '!<unset>!', 600);*/
		}
		return $default_value;
	}

	/**
	 * @brief Sets a configuration value for a user
	 *
	 * Stores a config value ($value) in the category ($family) under the key ($key)
	 * for the user_id $uid.
	 *
	 * @note Please do not store booleans - convert to 0/1 integer values!
	 *
	 * @param string $uid
	 *  The user_id
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to set
	 * @param string $value
	 *  The value to store
	 * @return mixed Stored $value or false
	 */
	public static function set($uid,$family,$key,$value) {

		global $a;

		// manage array value
		$dbvalue = (is_array($value)?serialize($value):$value);

		if(is_null(self::get($uid,$family,$key,null, true))) {
			$a->config[$uid][$family][$key] = $value;
			$ret = q("INSERT INTO `pconfig` ( `uid`, `cat`, `k`, `v` ) VALUES ( %d, '%s', '%s', '%s' ) ",
				intval($uid),
				dbesc($family),
				dbesc($key),
				dbesc($dbvalue)
			);
			if($ret) 
				return $value;
			return $ret;
		}
		$ret = q("UPDATE `pconfig` SET `v` = '%s' WHERE `uid` = %d AND `cat` = '%s' AND `k` = '%s'",
			dbesc($dbvalue),
			intval($uid),
			dbesc($family),
			dbesc($key)
		);

		$a->config[$uid][$family][$key] = $value;

		// If APC is enabled then store the data there, else try XCache
		/*if (function_exists("apc_store"))
			apc_store($uid."|".$family."|".$key, $value, 600);
		elseif (function_exists("xcache_set"))
			xcache_set($uid."|".$family."|".$key, $value, 600);*/


		if($ret)
			return $value;
		return $ret;
	}

	/**
	 * @brief Deletes the given key from the users's configuration.
	 *
	 * Removes the configured value from the stored cache in $a->config[$uid]
	 * and removes it from the database.
	 *
	 * @param string $uid The user_id
	 * @param string $family
	 *  The category of the configuration value
	 * @param string $key
	 *  The configuration key to delete
	 * @return mixed
	 */
	public static function delete($uid,$family,$key) {

		global $a;
		if(x($a->config[$uid][$family],$key))
			unset($a->config[$uid][$family][$key]);
		$ret = q("DELETE FROM `pconfig` WHERE `uid` = %d AND `cat` = '%s' AND `k` = '%s'",
			intval($uid),
			dbesc($family),
			dbesc($key)
		);
		return $ret;
	}
}
