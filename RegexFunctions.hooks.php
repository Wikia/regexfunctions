<?php
/**
 * RegexFunctions extension -- Regular Expression parser functions
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Schmidt
 * @version 1.4.3
 * @license http://en.wikipedia.org/wiki/Public_domain Public domain
 * @link http://www.mediawiki.org/wiki/Extension:RegexFunctions Documentation
 */

class RegexFunctionsHooks {
	private static $num = 0;
	private static $modifiers = array('i', 'm', 's', 'x', 'A', 'D', 'S', 'U', 'X', 'J', 'u');
	private static $options = array('i', 'm', 's', 'x', 'U', 'X', 'J');
	
	public static function onParserFirstCallInit($parser) {
		$parser->setFunctionHook('rmatch', array(__CLASS__, 'rmatch'));
		$parser->setFunctionHook('rsplit', array(__CLASS__,	'rsplit'));
		$parser->setFunctionHook('rreplace', array(__CLASS__, 'rreplace'));
		return true;
	}
	
	public static function onParserClearState($parser) {
		self::$num = 0;
		return true;
	}
	
	public static function rmatch(&$parser, $string = '', $pattern = '', $return = '', $notfound = '', $offset = 0) {
		global $wgRegexFunctionsPerPage, $wgRegexFunctionsAllowModifiers, $wgRegexFunctionsDisable;
		if (in_array('rmatch', $wgRegexFunctionsDisable)) {
			return;
		}
		self::$num++;
		if (self::$num > $wgRegexFunctionsPerPage) {
			return;
		}
		$pattern = self::sanitize($pattern, $wgRegexFunctionsAllowModifiers, false);
		$num     = preg_match($pattern, $string, $matches, PREG_OFFSET_CAPTURE, (int) $offset);
		if ($num === false) {
			return;
		}
		if ($num === 0) {
			return $notfound;
		}
		// change all backslashes to $
		$return = str_replace('\\', '%$', $return);
		$return = preg_replace('/%?\$%?\$([0-9]+)/e', 'array_key_exists($1, $matches) ? $matches[$1][1] : \'\'', $return);
		$return = preg_replace('/%?\$%?\$\{([0-9]+)\}/e', 'array_key_exists($1, $matches) ? $matches[$1][1] : \'\'', $return);
		$return = preg_replace('/%?\$([0-9]+)/e', 'array_key_exists($1, $matches) ? $matches[$1][0] : \'\'', $return);
		$return = preg_replace('/%?\$\{([0-9]+)\}/e', 'array_key_exists($1, $matches) ? $matches[$1][0] : \'\'', $return);
		$return = str_replace('%$', '\\', $return);
		return $return;
	}
	
	public static function rsplit(&$parser, $string = '', $pattern = '', $piece = 0) {
		global $wgRegexFunctionsPerPage, $wgRegexFunctionsAllowModifiers, $wgRegexFunctionsLimit, $wgRegexFunctionsDisable;
		if (in_array('rsplit', $wgRegexFunctionsDisable)) {
			return;
		}
		self::$num++;
		if (self::$num > $wgRegexFunctionsPerPage) {
			return;
		}
		$pattern = self::sanitize($pattern, $wgRegexFunctionsAllowModifiers, false);
		$res     = preg_split($pattern, $string, $wgRegexFunctionsLimit);
		$p       = (int) $piece;
		// allow negative pieces to work from the end of the array
		if ($p < 0) {
			$p = $p + count($res);
		}
		// sanitation for pieces that don't exist
		if ($p < 0) {
			$p = 0;
		}
		if ($p >= count($res)) {
			$p = count($res) - 1;
		}
		return $res[$p];
	}
	
	public static function rreplace(&$parser, $string = '', $pattern = '', $replace = '') {
		global $wgRegexFunctionsPerPage, $wgRegexFunctionsAllowModifiers, $wgRegexFunctionsAllowE, $wgRegexFunctionsLimit, $wgRegexFunctionsDisable;
		if (in_array('rreplace', $wgRegexFunctionsDisable)) {
			return;
		}
		self::$num++;
		if (self::$num > $wgRegexFunctionsPerPage) {
			return;
		}
		$pattern = self::sanitize(str_replace(chr(0), '', $pattern), $wgRegexFunctionsAllowModifiers, $wgRegexFunctionsAllowE);
		$res     = preg_replace($pattern, $replace, $string, $wgRegexFunctionsLimit);
		return $res;
	}
	
	// santizes a regex pattern
	private static function sanitize($pattern, $m = false, $e = false) {
		if (preg_match('/^\/(.*)([^\\\\])\/(.*?)$/', $pattern, $matches)) {
			$pat = preg_replace('/([^\\\\])?\(\?(.*\:)?(.*)\)/Ue', '\'$1(?\' . self::cleanupInternal(\'$2\') . \'$3)\'', $matches[1] . $matches[2]);
			$ret = '/' . $pat . '/';
			if ($m) {
				$mod = '';
				foreach (self::$modifiers as $val) {
					if (strpos($matches[3], $val) !== false) {
						$mod .= $val;
					}
				}
				if (!$e) {
					$mod = str_replace('e', '', $mod);
				}
				$ret .= $mod;
			}
		} else {
			$pat = preg_replace('/([^\\\\])?\(\?(.*\:)?(.*)\)/Ue', '\'$1(?\' . self::cleanupInternal(\'$2\') . \'$3)\'', $pattern);
			$pat = preg_replace('!([^\\\\])/!', '$1\\/', $pat);
			$ret = '/' . $pat . '/';
		}
		return $ret;
	}
	
	// cleans up internal options, making sure they are valid
	private static function cleanupInternal($str) {
		global $wgRegexFunctionsAllowOptions;
		$ret = '';
		if (!$wgRegexFunctionsAllowOptions) {
			return '';
		}
		foreach (self::$options as $opt) {
			if (strpos($str, $opt) !== false) {
				$ret .= $opt;
			}
		}
		return $ret;
	}
}
