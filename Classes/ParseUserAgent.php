<?php

/**
 * Jumplinks for ProcessWire
 * Manage permanent and temporary redirects. Uses named wildcards and mapping collections.
 *
 * Process module for ProcessWire 2.6.1+
 *
 * @author Mike Rockett
 * @copyright (c) 2015, Mike Rockett. All Rights Reserved.
 * @license ISC
 *
 * @see Documentation:     https://jumplinks.rockett.pw
 * @see Modules Directory: https://mods.pw/92
 * @see Forum Thred:       https://processwire.com/talk/topic/8697-jumplinks/
 * @see Donate:            https://rockett.pw/donate
 */

/**
 * (this file)
 *
 * Parses a user agent string into its important parts
 *
 * Changelog
 * [mikerockett] Converted to a class and formatted.
 *
 * @author Jesse G. Donat <donatj@gmail.com>
 * @link https://github.com/donatj/PhpUserAgent
 * @link http://donatstudios.com/PHP-Parser-HTTP_USER_AGENT
 * @param string|null $u_agent User agent string to parse or null. Uses $_SERVER['HTTP_USER_AGENT'] on NULL
 * @throws InvalidArgumentException on not having a proper user agent to parse.
 * @return string[] an array with browser, version and platform keys
 */

class ParseUserAgent
{
  /**
   * @param $u_agent
   * @return mixed
   */
  public static function get($u_agent = null)
  {
    if (null === $u_agent) {
      if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $u_agent = $_SERVER['HTTP_USER_AGENT'];
      } else {
        return '';
      }
    }

    $platform = null;
    $browser = null;
    $version = null;

    $empty = ['platform' => $platform, 'browser' => $browser, 'version' => $version];

    if (!$u_agent) {
      return $empty;
    }

    if (preg_match('/\((.*?)\)/im', $u_agent, $parent_matches)) {
      preg_match_all('/(?P<platform>BB\d+;|Android|CrOS|Tizen|iPhone|iPad|Linux|Macintosh|Windows(\ Phone)?|Silk|linux-gnu|BlackBerry|PlayBook|(New\ )?Nintendo\ (WiiU?|3DS)|Xbox(\ One)?)
					(?:\ [^;]*)?
					(?:;|$)/imx', $parent_matches[1], $result, PREG_PATTERN_ORDER);
      $priority = ['Xbox One', 'Xbox', 'Windows Phone', 'Tizen', 'Android'];
      $result['platform'] = array_unique($result['platform']);
      if (count($result['platform']) > 1) {
        if ($keys = array_intersect($priority, $result['platform'])) {
          $platform = reset($keys);
        } else {
          $platform = $result['platform'][0];
        }
      } else if (isset($result['platform'][0])) {
        $platform = $result['platform'][0];
      }

    }

    if ($platform == 'linux-gnu') {
      $platform = 'Linux';
    } else if ($platform == 'CrOS') {
      $platform = 'Chrome OS';
    }

    preg_match_all('%(?P<browser>Camino|Kindle(\ Fire\ Build)?|Firefox|Iceweasel|Safari|MSIE|Trident|AppleWebKit|TizenBrowser|Chrome|
				Vivaldi|IEMobile|Opera|OPR|Silk|Midori|Edge|CriOS|
				Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
				NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
				(?:\)?;?)
				(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
      $u_agent, $result, PREG_PATTERN_ORDER);

    // If nothing matched, return null (to avoid undefined index errors)
    if (!isset($result['browser'][0]) || !isset($result['version'][0])) {
      if (!$platform && preg_match('%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?([;| ]\ ?.*)?$%ix', $u_agent, $result)) {
        return ['platform' => null, 'browser' => $result['browser'], 'version' => isset($result['version']) ? $result['version'] ?: null: null];
      }

      return $empty;
    }

    if (preg_match('/rv:(?P<version>[0-9A-Z.]+)/si', $u_agent, $rv_result)) {
      $rv_result = $rv_result['version'];
    }

    $browser = $result['browser'][0];
    $version = $result['version'][0];

    $find = function ($search, &$key) use ($result) {
      $xkey = array_search(strtolower($search), array_map('strtolower', $result['browser']));
      if ($xkey !== false) {
        $key = $xkey;

        return true;
      }

      return false;
    };

    $key = 0;
    $ekey = 0;
    if ($browser == 'Iceweasel') {
      $browser = 'Firefox';
    } elseif ($find('Playstation Vita', $key)) {
      $platform = 'PlayStation Vita';
      $browser = 'Browser';
    } elseif ($find('Kindle Fire Build', $key) || $find('Silk', $key)) {
      $browser = $result['browser'][$key] == 'Silk' ? 'Silk' : 'Kindle';
      $platform = 'Kindle Fire';
      if (!($version = $result['version'][$key]) || !is_numeric($version[0])) {
        $version = $result['version'][array_search('Version', $result['browser'])];
      }
    } elseif ($find('NintendoBrowser', $key) || $platform == 'Nintendo 3DS') {
      $browser = 'NintendoBrowser';
      $version = $result['version'][$key];
    } elseif ($find('Kindle', $key)) {
      $browser = $result['browser'][$key];
      $platform = 'Kindle';
      $version = $result['version'][$key];
    } elseif ($find('OPR', $key)) {
      $browser = 'Opera Next';
      $version = $result['version'][$key];
    } elseif ($find('Opera', $key)) {
      $browser = 'Opera';
      $find('Version', $key);
      $version = $result['version'][$key];
    } elseif ($find('Midori', $key)) {
      $browser = 'Midori';
      $version = $result['version'][$key];
    } elseif ($browser == 'MSIE' || ($rv_result && $find('Trident', $key)) || $find('Edge', $ekey)) {
      $browser = 'MSIE';
      if ($find('IEMobile', $key)) {
        $browser = 'IEMobile';
        $version = $result['version'][$key];
      } elseif ($ekey) {
        $version = $result['version'][$ekey];
      } else {
        $version = $rv_result ?: $result['version'][$key];
      }
    } elseif ($find('Vivaldi', $key)) {
      $browser = 'Vivaldi';
      $version = $result['version'][$key];
    } elseif ($find('Chrome', $key) || $find('CriOS', $key)) {
      $browser = 'Chrome';
      $version = $result['version'][$key];
    } elseif ($browser == 'AppleWebKit') {
      if (($platform == 'Android' && !($key = 0))) {
        $browser = 'Android Browser';
      } elseif (strpos($platform, 'BB') === 0) {
        $browser = 'BlackBerry Browser';
        $platform = 'BlackBerry';
      } elseif ($platform == 'BlackBerry' || $platform == 'PlayBook') {
        $browser = 'BlackBerry Browser';
      } elseif ($find('Safari', $key)) {
        $browser = 'Safari';
      } elseif ($find('TizenBrowser', $key)) {
        $browser = 'TizenBrowser';
      }

      $find('Version', $key);

      $version = $result['version'][$key];
    } elseif ($key = preg_grep('/playstation \d/i', array_map('strtolower', $result['browser']))) {
      $key = reset($key);

      $platform = 'PlayStation ' . preg_replace('/[^\d]/i', '', $key);
      $browser = 'NetFront';
    }

    return ['platform' => $platform ?: null, 'browser' => $browser ?: null, 'version' => $version ?: null];
  }

}
