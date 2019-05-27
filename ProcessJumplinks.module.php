<?php

/**
 * Jumplinks for ProcessWire
 * Manage permanent and temporary redirects. Uses named wildcards and mapping collections.
 *
 * Process module for ProcessWire 2.6.1+
 *
 * @author Mike Rockett
 * @copyright (c) 2015-18, Mike Rockett. All Rights Reserved.
 * @license ISC
 *
 * @see Documentation:     https://jumplinks.rockett.pw
 * @see Modules Directory: https://mods.pw/92
 * @see Forum Thred:       https://processwire.com/talk/topic/8697-jumplinks/
 * @see Donate:            https://rockett.pw/donate
 */

class ProcessJumplinks extends Process
{
  /** Schema version for current release */
  const SCHEMA_VERSION = 4;

  /** NULL Date **/
  const NULL_DATE = '0000-00-00 00:00:00';

  /**
   * Determine if the text/plain header is set
   * @var boolean
   */
  protected $headerSet = false;

  /**
   * Object (Array) that holds SQL statements
   * @var stClass
   */
  protected $sql;

  /**
   * Hold module information
   * @var array
   */
  protected $moduleInfo;

  /**
   * The base table name
   * @rfc Should we make this constant?
   * @var string
   */
  protected $tableName = 'process_jumplinks';

  /**
   * The base table name for ProcessRedirects
   * @var string
   */
  protected $redirectsTableName = 'ProcessRedirects'; // This is the default, and will be checked for case

  /**
   * Paths to forms
   * @var string
   */
  protected $entityFormPath = 'entity/';

  /**
   * @var string
   */
  protected $mappingCollectionFormPath = 'mappingcollection/';

  /**
   * @var string
   */
  protected $importPath = 'import/';

  /**
   * @var string
   */
  protected $clearNotFoundLogPath = 'clearnotfoundlog/';

  /**
   * Lowest date - for use when working with timestamps
   * @var string
   */
  protected $lowestDate = '1974-10-10';

  /**
   * Set the wildcard types.
   * A wildcard type is the second fragment of a wildcard/
   * Ex: {name:type}
   * @var array
   */
  protected $wildcards = [
    'all' => '.*',
    'alpha' => '[a-z]+',
    'alphanum' => '\w+',
    'any' => '[\w.-_%\=\s]+',
    'ext' => 'aspx|asp|cfm|cgi|fcgi|dll|html|htm|shtml|shtm|jhtml|phtml|xhtm|xhtml|rbml|jspx|jsp|phps|php4|php',
    'num' => '\d+',
    'segment' => '[\w_-]+',
    'segments' => '[\w/_-]+',
  ];

  /**
   * Set smart wildcards.
   * These are like shortcuts for declaring wildcards.
   * See the docs for more info.
   * @var array
   */
  protected $smartWildcards = [
    'all' => 'all',
    'ext' => 'ext',
    'name|title|page|post|user|model|entry|segment' => 'segment',
    'path|segments' => 'segments',
    'year|month|day|id|num' => 'num',
  ];

  /**
   * Inject assets (used as assets are automatically inserted when
   * using the same name as the module, but the get thrown in before
   * JS dependencies. WireTabs also gets thrown in.)
   * @return void
   */
  protected function injectAssets()
  {
    // Inject script and style
    $moduleAssetPath = "{$this->config->urls->ProcessJumplinks}Assets";
    $this->config->scripts->add("{$moduleAssetPath}/ProcessJumplinks.min.js");
    $this->config->styles->add("{$moduleAssetPath}/ProcessJumplinks.css");

    // Include WireTabs
    $this->modules->get('JqueryWireTabs');
  }

  /**
   * Class constructor
   * Init moduleInfo, sql
   * @return void
   */
  public function __construct()
  {
    // Set the lowest allowable date
    $this->lowestDate = strtotime($this->lowestDate);

    // Get the module info
    $this->moduleInfo = wire('modules')->getModuleInfo($this, ['verbose' => true]);

    // Get the correct table name for ProcessRedirects
    $redirectsTableNameQuery = $this->database->prepare('SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db_name AND TABLE_NAME LIKE :table_name');
    $redirectsTableNameQuery->execute([
      'db_name' => $this->config->dbName,
      'table_name' => $this->redirectsTableName,
    ]);
    if ($redirectsTableNameQuery->rowCount() > 0) {
      $redirectsTableNameResult = $redirectsTableNameQuery->fetch(PDO::FETCH_OBJ)->TABLE_NAME;
      if ($this->redirectsTableName == $redirectsTableNameResult ||
        strtolower($this->redirectsTableName) == $redirectsTableNameResult) {
        $this->redirectsTableName = $redirectsTableNameResult;
      }
    }

    // Set the SQL statements for use elsewhere.
    // These are kept in one place for ease of reference.
    $this->sql = (object) [
      'entity' => (object) [
        'selectAll' => "SELECT * FROM {$this->tableName} ORDER BY `source`",
        'selectOne' => "SELECT * FROM {$this->tableName} WHERE id = :id",
        'dropOne' => "DELETE FROM {$this->tableName} WHERE id = :id",
        'insert' => "INSERT INTO {$this->tableName} SET `source` = :source, destination = :destination, hits = :hits, date_start = :date_start, date_end = :date_end, user_created = :user_created, user_updated = :user_updated, created_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP ON DUPLICATE KEY UPDATE id = id",
        'update' => "UPDATE {$this->tableName} SET `source` = :source, destination = :destination, date_start = :date_start, date_end = :date_end, user_updated = :user_updated, updated_at = CURRENT_TIMESTAMP WHERE id = :id",
        'updateHits' => "UPDATE {$this->tableName} SET hits = :hits WHERE id = :id",
        'updateLastHitDate' => "UPDATE {$this->tableName} SET last_hit = :last_hit WHERE id = :id",
      ],
      'collection' => (object) [
        'selectAll' => "SELECT * FROM {$this->tableName}_mc ORDER BY collection_name",
        'selectOne' => "SELECT * FROM {$this->tableName}_mc WHERE id = :id",
        'selectOneByName' => "SELECT * FROM {$this->tableName}_mc WHERE collection_name = :collection",
        'dropOne' => "DELETE FROM {$this->tableName}_mc WHERE id = :id",
        'insert' => "INSERT INTO {$this->tableName}_mc SET collection_name = :collection, collection_mappings = :mappings, user_created = :user_created, user_updated = :user_updated, created_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP ON DUPLICATE KEY UPDATE id = id",
        'update' => "UPDATE {$this->tableName}_mc SET collection_name = :collection, collection_mappings = :mappings, user_updated = :user_updated WHERE id = :id",
      ],
      'notFoundMonitor' => (object) [
        'selectAll' => "SELECT * FROM {$this->tableName}_nf ORDER BY created_at DESC LIMIT 100",
        'insert' => "INSERT INTO {$this->tableName}_nf SET request_uri = :request_uri, referrer = :referrer, user_agent = :user_agent, created_at = CURRENT_TIMESTAMP ON DUPLICATE KEY UPDATE id = id",
        'deleteAll' => "TRUNCATE TABLE {$this->tableName}_nf",
      ],
    ];
  }

  /**
   * Module initialisation
   * @hook ProcessPageView::pageNotFound to scanAndRedirect
   * @return void
   */
  public function init()
  {
    parent::init();

    // Make sure schemas are up to date.
    // This process will be changed to ___upgrade() when the minimum PW version is 2.7.1.
    if ($this->schemaVersion < self::SCHEMA_VERSION) {
      $this->updateDatabaseSchema();
    }

    // Get the current request
    $this->request = ltrim(@$_SERVER['REQUEST_URI'], '/');

    // Trim out index.php from the beginning of the URI if it is suffixed with a path.
    // ProcessWire doesn't support these anyway.
    $indexRequest = "~^index\.php(/.*)$~i";
    if (preg_match($indexRequest, $this->request)) {
      $this->session->redirect(preg_replace($indexRequest, '\\1', $this->request));
    }

    // Hook prior to the pageNotFound event ...
    if ($this->moduleDisable == false) {
      $this->addHookBefore('ProcessPageView::pageNotFound', $this, 'scanAndRedirect', ['priority' => 10]);
    }
  }

  /**
   * Update database schema
   * This method applies incremental updates until latest schema version is
   * reached, while also keeping schemaVersion config setting up to date.
   * @return void
   */
  private function updateDatabaseSchema()
  {
    // Loop through each version, applying the applicable Blueprint for each one.
    while ($this->_schemaVersion < self::SCHEMA_VERSION) {
      ++$this->_schemaVersion;
      $memoryVersion = $this->_schemaVersion;
      switch (true) {
        case ($memoryVersion <= 4):
          $statement = $this->blueprint("schema-update-v{$memoryVersion}");
          break;
        default:
          throw new WireException("[Jumplinks] Unrecognized database schema version: {$memoryVersion}");
      }
      if ($statement && $this->database->exec($statement) !== false) {
        // Now set the version name for later comparison.
        $configData = $this->modules->getModuleConfigData($this);
        $configData['_schemaVersion'] = $memoryVersion;
        $this->modules->saveModuleConfigData($this, $configData);
        $this->message($this->_('[Jumplinks] Schema updates applied.'));
      } else {
        throw new WireException("[Jumplinks] Couldn't update database schema to version {$memoryVersion}");
      }
    }
  }

  /**
   * Generate help link (contextual)
   * @param  string $uri
   * @return string
   */
  protected function helpLinks($uri = '', $justTheLink = false, $title = null)
  {
    // Prepend a slash to the URI.
    if (!empty($uri)) {
      $uri = "/{$uri}";
    }

    // If only the link is required, then return it.
    // Otherwise, format it along with all the other links.
    if ($justTheLink) {
      return $this->moduleInfo['href'] . $uri;
    } else {
      $supportDevelopment = $this->_('Support Jumplinks’ Development');
      $needHelp = $this->_('Forum Support Thread');
      $documentation = $this->_('Documentation');
      if ($title !== null) {
        $documentation = $this->_('Documentation on ') . $title;
      }
      return "<div class=\"pjHelpLink\"><a class=\"paypal\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"https://rockett.pw/donate\">{$supportDevelopment}</a><a target=\"_blank\" href=\"https://processwire.com/talk/topic/8697-jumplinks/\">{$needHelp}</a><a style=\"font-weight:700\" target=\"_blank\" href=\"{$this->moduleInfo['href']}{$uri}\">{$documentation}</a></div>";
    }
  }

  /**
   * Create a blueprint from file and give it some variables.
   * @caller multiple
   * @param  string $name
   * @param  array  $data
   * @return string
   */
  protected function blueprint($name, $data = [])
  {
    // Require the Blueprint parser
    require_once __DIR__ . '/Classes/Blueprint.php';

    $blueprint = new Blueprint($name);

    // Set the data
    $data = array_filter($data);
    if (empty($data)) {
      $data = ['table-name' => $this->tableName];
    }
    $blueprint->hydrate($data);

    return (string) $blueprint->build();
  }

  /**
   * Compile destination URL, keeping page refs, HTTPS, and subdirectories considered.
   * @caller multiple
   * @param  string $destination
   * @param  bool   $renderForOutput
   * @param  bool   $http
   * @return string
   */
  protected function compileDestinationUrl($destination, $renderForOutput = false, $httpUrl = true)
  {
    $pageIdentifier = 'page:';
    $usingPageIdentifier = substr($destination, 0, 5) === $pageIdentifier;

    // Check if we're not using a page identifier or selector
    if (!$usingPageIdentifier) {
      // Check to see if we're working with an absolute URL
      $isAbsolute = $this->destinationHasScheme($destination);

      // If URL is absolute, then skip the prefix, otherwise build it
      $prefix = ($isAbsolute) ? '' : $this->config->urls->root;

      // If we're rendering for backend output, truncate and return the destination.
      // Otherwise, return the full destination.
      return ($renderForOutput)
        ? $this->truncate($destination)
        : $prefix . $destination;
    } else {
      // If we're using a page identifier, fetch it
      $pageId = str_replace($pageIdentifier, '', $destination);
      $page = $this->pages->get((int) $pageId);

      // If it's a valid page, then get its URL
      if ($page->id) {
        $destination = ($httpUrl ? $page->httpUrl : ltrim($page->path, '/'));
        if (empty($destination)) {
          $destination = '/';
        }
        if ($renderForOutput) {
          $destination = "<abbr title=\"{$page->title} ({$page->httpUrl})\">{$destination}</abbr>";
        }
      }

      return $destination;
    }

  }

  /**
   * Fetch the URI to the module's config page
   * @caller multiple
   * @return string
   */
  protected function getModuleConfigUri()
  {
    return "{$this->config->urls->admin}module/edit?name={$this->moduleInfo['name']}";
    // ^ Better way to get this URI?
  }

  /**
   * Clean a passed wildcard value
   * @caller scanAndRedirect
   * @param  string $input
   * @param  bool   $noLower
   * @return string
   */
  public function cleanWildcard($input, $noLower = false)
  {
    if ($this->enhancedWildcardCleaning) {
      // Courtesy @sln on StackOverflow
      $input = preg_replace_callback("~([A-Z])([A-Z]+)(?=[A-Z]|\b)~", function ($captures) {
        return $captures[1] . strtolower($captures[2]);
      }, $input);
      $input = preg_replace('~(?<=\\w)(?=[A-Z])~', '-\\1\\2', $input);
    }

    $input = preg_replace("~%u([a-f\d]{3,4})~i", '&#x\\1;', urldecode($input));
    $input = preg_replace("~[^\\pL\d\/]+~u", '-', $input);
    $input = iconv('utf-8', 'us-ascii//TRANSLIT', $input);

    if ($this->enhancedWildcardCleaning) {
      $input = preg_replace("~(\d)([a-z])~i", '\\1-\\2', preg_replace("~([a-z])(\d)~i", '\\1-\\2', $input));
    }

    $input = trim($input, '-');
    $input = preg_replace('~[^\-\w\/]+~', '', $input);
    if (!$noLower) {
      $input = strtolower($input);
    }

    return (empty($input)) ? '' : $input;
  }

  /**
   * Truncate string, and append ellipses with tooltip
   * @caller multiple
   * @param  string $string
   * @param  int    $length
   * @return string
   */
  protected function truncate($string, $length = 55)
  {
    if (strlen($string) > $length) {
      return substr($string, 0, $length) . " <span class=\"ellipses\" title=\"{$string}\">...</span>";
    } else {
      return $string;
    }
  }

  /**
   * Given a fieldtype, create, populate, and return an Inputfield
   * @param  string $fieldNameId
   * @param  array  $meta
   * @return Inputfield
   */
  protected function buildInputField($fieldNameId, $meta)
  {
    $field = $this->modules->get($fieldNameId);

    foreach ($meta as $metaNames => $metaInfo) {
      $metaNames = explode('+', $metaNames);
      foreach ($metaNames as $metaName) {
        $field->$metaName = $metaInfo;
      }

    }

    return $field;
  }

  /**
   * Given a an Inputfield, add props and return
   * @param  string $field
   * @param  array  $meta
   * @return Inputfield
   */
  protected function populateInputField($field, $meta)
  {
    foreach ($meta as $metaNames => $metaInfo) {
      $metaNames = explode('+', $metaNames);
      foreach ($metaNames as $metaName) {
        $field->$metaName = $metaInfo;
      }

    }

    return $field;
  }

  /**
   * Get response code of remote request
   * @caller scanAndRedirect
   * @param  $request
   * @return string
   */
  protected function getResponseCode($request)
  {
    stream_context_set_default([
      'http' => [
        'method' => 'HEAD',
      ],
    ]);

    $response = get_headers($request);

    return substr($response[0], 9, 3);
  }

  /**
   * Determine if the current user has debug rights.
   * Must have relevant permission, and debug mode must be turned on.
   * @return bool
   */
  protected function userHasDebugRights()
  {
    return ($this->moduleDebug && $this->user->hasPermission('jumplinks-admin'));
  }

  /**
   * Log something. Will set plain text header if not already set.
   * @caller scanAndRedirect
   * @param  string $message
   * @param  bool   $indent
   * @param  bool   $break
   * @param  bool   $die
   */
  protected function log($message, $message2 = null, $extraLine = false)
  {
    if ($this->userHasDebugRights()) {
      if (!$this->headerSet) {
        header('Content-Type: text/plain');
        $this->headerSet = true;
      }

      if (null === $message2) {
        $output = $message;
      } else {
        $message = str_pad("- $message:", 30);
        $output = $message . $message2;
      }

      if ($extraLine) {
        $output .= "\n";
      }

      print "$output\n";
    }
  }

  /**
   * Log 404 to monitor
   * @caller scanAndRedirect
   * @param  string $request
   */
  protected function log404($request)
  {
    $canLog = true;

    // MarkupSitemap[XML] exception
    if (
      ($this->modules->isInstalled('MarkupSitemapXML') ||
        $this->modules->isInstalled('MarkupSitemap')) &&
      stripos($request, 'sitemap.xml') !== false
    ) {
      $canLog = false;
    }

    // Log the 404 if it matches specific criteria
    if ($canLog) {
      $this->database->prepare($this->sql->notFoundMonitor->insert)->execute([
        'request_uri' => $request,
        'referrer' => @$_SERVER['HTTP_REFERER'],
        'user_agent' => @$_SERVER['HTTP_USER_AGENT'],
      ]);
    }
  }

  /**
   * The fun part.
   * @caller Hook: ProcessPageView::pageNotFound
   * @return void
   */
  protected function scanAndRedirect()
  {
    // Get the current request
    $request = $this->request;

    // Fetch all jumplinks
    $jumplinks = $this->database->query($this->sql->entity->selectAll);

    // If there aren't any, then log the hit and break out
    if ($jumplinks->rowCount() === 0) {
      $this->log404($request);
      return false;
    }

    // Otherwise, start logging...
    $this->log('404 Page Not Found');

    // Get the home page URL - this is the same as the site root.
    $siteRoot = rtrim($this->pages->get(1)->httpUrl, '/');

    // Do some intro logging
    $this->log(sprintf('Checked %s', date('r')));
    $this->log("Request: {$siteRoot}/{$request}");
    $this->log("ProcessWire Version: {$this->config->version}", null, true);
    $this->log('Scanning for jumplinks...', null, true);

    $rootUrl = $this->config->urls->root;
    if ($rootUrl !== '/') {
      $request = substr($request, strlen($rootUrl) - 1);
    }

    // Get the available wildcards, prepare for pattern match
    $availableWildcards = '';
    foreach ($this->wildcards as $wildcard => $expression) {
      $availableWildcards .= "{$wildcard}|";
    }
    $availableWildcards = rtrim($availableWildcards, '|');

    // Assign the wildcard pattern check
    $pattern = '~\{!?([a-z]+):(' . $availableWildcards . ')\}~';

    // Begin the loop
    while ($jumplink = $jumplinks->fetchObject()) {
      // If the jumplink source starts with a double-exclamation mark, then it is disabled
      if (substr($jumplink->source, 0, 2) === '!!') {
        continue;
      }

      $starts = (strtotime($jumplink->date_start) > $this->lowestDate) ? strtotime($jumplink->date_start) : false;
      $ends = (strtotime($jumplink->date_end) > $this->lowestDate) ? strtotime($jumplink->date_end) : false;

      $this->log("[Checking jumplink #{$jumplink->id}]");

      // Timed Activation:
      // If it ends, but doesn't start, then make it start now
      if ($ends && !$starts) {
        $starts = time();
      }

      // If it starts (which it will always do), but doesn't end,
      // then set a dummy timestamp that is always in the future.
      $dummyEnd = false;
      $message = '';
      if ($starts && !$ends) {
        $ends = time() + (60 * 60);
        $dummyEnd = true;
        $message = '(has no ending, using dummy timestamp)';
      }

      // Log the activation periods for debugging
      if ($starts || $ends) {
        $this->log('Timed Activation (Starts)', date('r', $starts));
        if (!$dummyEnd) {
          $this->log('Timed Activation (Ends)', date('r', $ends));
        }
      }

      $this->log('Original Source Path', $jumplink->source);

      // Prepare the Source Path for matching:
      // First, escape ? (and reverse /\?) & :
      // Then, convert '[character]' to 'character?' for matching.
      $source = preg_replace('~\[([a-z0-9\/])\]~i', '\\1?', str_replace(
        ['?', '/\?', '&', ':'],
        ['\?', '/?', '\&', '\:'],
        $jumplink->source
      ));
      // As a workaround for query strings attached to trailing slashes
      // ensure that slashes are not made optional in the middle of the source.
      // Refer: https://processwire.com/talk/topic/8697-jumplinks/?do=findComment&comment=126551
      $source = preg_replace('~(.+)/\?(.+)~', '\\1/\?\\2', $source);

      // Reverse ':' escaping for wildcards
      $source = preg_replace("~\{([a-z]+)\\\:([a-z]+)\}~i", '{\\1:\\2}', $source);

      if ($source !== $jumplink->source) {
        $this->log('Escaped Source Path', $source);
      }

      // Compile the destination URL
      $destination = $this->compileDestinationUrl($jumplink->destination);

      // Setup capture prevention
      $nonCaptureMatcher = '~<(.*?)>~';
      if (preg_match($nonCaptureMatcher, $source)) {
        $source = preg_replace($nonCaptureMatcher, '(?:\\1)', $source);
      }

      // Prepare Smart Wildcards - replace them with their equivalent standard ones.
      $hasSmartWildcards = false;
      foreach ($this->smartWildcards as $wildcard => $wildcardType) {
        $smartWildcardMatcher = "~\{($wildcard)\}~i";
        if (preg_match($smartWildcardMatcher, $source)) {
          $source = preg_replace($smartWildcardMatcher, "{\\1:{$wildcardType}}", $source);
          $hasSmartWildcards = true;
        }
      }

      $computedReplacements = [];

      // Convert wildcards into expressions for replacement
      $computedWildcards = preg_replace_callback($pattern, function ($captures) use (&$computedReplacements) {
        $computedReplacements[] = $captures[1];
        return "({$this->wildcards[$captures[2]]})";
      }, $source);

      // Some more logging
      if ($hasSmartWildcards) {
        $this->log('After Smart Wildcards', $source);
      }

      $this->log('Compiled Source Path', $computedWildcards);

      // If the request matches the source currently being checked:
      if (preg_match("~^$computedWildcards$~i", $request)) {
        // For the purposes of mapping, fetch all the collections and compile them
        $collections = $this->database->query($this->sql->collection->selectAll);
        $compiledCollections = new StdClass();
        while ($collection = $collections->fetchObject()) {
          $collectionData = explode("\n", $collection->collection_mappings);
          $compiledCollectionData = [];
          foreach ($collectionData as $mapping) {
            $mapping = explode('=', $mapping);
            $compiledCollectionData[$mapping[0]] = $mapping[1];
          }
          $compiledCollections->{$collection->collection_name} = $compiledCollectionData;
        }

        // Iterate through each source wildcard:
        if ($computedWildcards == '(.*)') {
          $computedWildcards = '(.+)';
        };
        $convertedWildcards = preg_replace_callback("~$computedWildcards~i", function ($captures) use ($destination, $computedReplacements) {
          $result = $destination;

          for ($c = 1, $n = count($captures); $c < $n; ++$c) {
            $value = array_shift($computedReplacements);

            // Check for destination wildcards that don't need to be cleaned
            $paramSkipCleanCheck = "~\{!$value\}~i";
            $uncleanedCapture = $captures[$c];
            if (empty($uncleanedCapture)) {
              continue;
            }
            if (!preg_match($paramSkipCleanCheck, $result)) {
              $wildcardCleaning = $this->wildcardCleaning;
              if ($wildcardCleaning === 'fullClean' || $wildcardCleaning === 'semiClean') {
                $captures[$c] = $this->cleanWildcard($captures[$c], ($wildcardCleaning === 'fullClean') ? false : true);
              }
            }
            $openingTag = (preg_match($paramSkipCleanCheck, $result)) ? '{!' : '{';
            $result = str_replace($openingTag . $value . '}', $captures[$c], $result);

            // In preparation for wildcard mapping,
            // Swap out any mapping wildcards with their uncleaned values
            $value = preg_quote($value);
            $result = preg_replace("~\{{$value}\|([a-z]+)\}~i", "($uncleanedCapture|\\1)", $result);
            $this->log('- Wildcard Check', "{$c}> {$value} = {$uncleanedCapture} -> {$captures[$c]}");
          }

          // Trim the result of trailing slashes, and
          // add one again if the Destination Path asked for it.
          $result = rtrim($result, '/');
          if (substr($destination, -1) === '/') {
            $result .= '/';
          }

          return $result;
        }, $request);

        // Perform any mappings
        $convertedWildcards = preg_replace_callback("~\(([\w\-_\/]+)\|([a-z]+)\)~i", function ($mapCaptures) use ($compiledCollections) {
          // If we have a match, bring it in
          // Otherwise, fill the mapping wildcard with the original data
          if (isset($compiledCollections->{$mapCaptures[2]}[$mapCaptures[1]])) {
            return $compiledCollections->{$mapCaptures[2]}[$mapCaptures[1]];
          } else {
            return $mapCaptures[1];
          }
        }, $convertedWildcards);

        // Check for any selectors and get the respective page
        $selectorUsed = false;
        $selectorMatched = false;

        $convertedWildcards = preg_replace_callback("~\[\[([\w\-_\/\s=\",.'|@]+)\]\]~i", function ($selectorCaptures) use (&$selectorUsed, &$selectorMatched) {
          $selectorUsed = true;
          $page = $this->pages->get($selectorCaptures[1]);
          if ($page->id > 0) {
            $selectorMatched = true;
            return ltrim($page->url, '/');
          }
        }, $convertedWildcards);

        $this->log('Original Destination Path', $jumplink->destination);

        // If a match was found, but the selector didn't return a page, then continue the loop
        if ($selectorUsed && !$selectorMatched) {
          $this->log("\nWhilst a match was found, the selector you specified didn't return a page. As a result, this jumplink will be skipped.");
          continue;
        }

        $this->log('Compiled Destination Path', $convertedWildcards, true);

        // Check for Timed Activation and determine if we're in the period specified
        $time = time();
        $activated = ($starts || $ends)
          ? ($time >= $starts && $time <= $ends)
          : true;

        // If we're not debugging, and we're Time-activated, then do the redirect
        if (!$this->userHasDebugRights() && $activated) {
          $hitsPlusOne = $jumplink->hits + 1;
          $this->database->prepare($this->sql->entity->updateHits)->execute([
            'hits' => $hitsPlusOne,
            'id' => $jumplink->id,
          ]);
          $this->database->prepare($this->sql->entity->updateLastHitDate)->execute([
            'last_hit' => date('Y-m-d H:i:s'),
            'id' => $jumplink->id,
          ]);
          $this->session->redirect($convertedWildcards, !($starts || $ends));
        }

        // Otherwise, continue logging
        $type = ($starts) ? '302, temporary' : '301, permanent';
        $this->log("Match found! We'll do the following redirect ({$type}) when Debug Mode has been turned off:", null, true);
        $this->log('From URL', "{$siteRoot}/{$request}");
        $this->log('To URL', "{$convertedWildcards}");

        if ($starts || $ends) {
          // If it ends before it starts, then show the time it starts.
          // Otherwise, show the period.
          if ($dummyEnd) {
            $this->log('Timed', sprintf('From %s onwards', date('r', $starts)));
          } else {
            $this->log('Timed', sprintf('From %s to %s', date('r', $starts), date('r', $ends)));
          }
        }

        // We can exit at this point.
        if ($this->userHasDebugRights()) {
          die();
        }
      }

      // If there were no available redirect definitions,
      // then inform the debugger.
      $this->log("\nNo match there...", null, true);
    }

    // Considering we don't have one available, let's check to see if the Source Path
    // exists on the Legacy Domain, if defined.
    $legacyDomain = trim($this->legacyDomain);
    if (!empty($legacyDomain)) {
      // Fetch the accepted codes
      $okCodes = trim(!empty($this->statusCodes))
        ? array_map('trim', explode(' ', $this->statusCodes))
        : explode(',', $this->statusCodes);

      // Prepare and do the request
      $domainRequest = $this->legacyDomain . $request;
      $status = $this->getResponseCode($domainRequest);

      // If the response has an accepted code, then 302 redirect (or log)
      if (in_array($status, $okCodes)) {
        if (!$this->userHasDebugRights()) {
          $this->session->redirect($domainRequest, false);
        }

        $this->log("Found Source Path on Legacy Domain (with status code {$status}); redirect allowed to:");
        $this->log("> {$domainRequest}");
      }
    }

    // If set in config, log 404 hits to the database
    if ($this->enable404Monitor == true) {
      $this->log404($request);
    }

    // If all fails, say so.
    $this->log("No matches, sorry. We'll let your 404 error page take over when Debug Mode is turned off.");
    if ($this->userHasDebugRights()) {
      die();
    }
  }

  /**
   * Admin Page: Module Root
   * @return string
   */
  public function ___execute()
  {
    // Assets
    $this->injectAssets();

    // Get Jumplinks
    $jumplinks = $this->database->query($this->sql->entity->selectAll);

    // Set Page title
    $this->setFuel('processHeadline', $this->_('Manage Jumplinks'));

    // Assign the main container (wrapper)
    $tabContainer = new InputfieldWrapper();

    // Add the Jumplinks tab
    $jumplinksTab = new InputfieldWrapper();
    $jumplinksTab->attr('title', 'Jumplinks');

    // Setup the datatable
    $jumplinksTable = $this->modules->get('MarkupAdminDataTable');
    $jumplinksTable->setEncodeEntities(false);
    $jumplinksTable->setClass('jumplinks redirects');
    $jumplinksTable->headerRow([$this->_('Source'), $this->_('Destination'), $this->_('Start'), $this->_('End'), $this->_('Hits')]);

    // Setup and add the tab description markup
    $pronoun = $this->_n('it', 'one', $jumplinks->rowCount());
    if ($jumplinks->rowCount() == 0) {
      $description = $this->_("You don't have any jumplinks yet.");
    } else {
      $description = $this->_n('You have one jumplink registered.', 'Your jumplinks are listed below.', $jumplinks->rowCount()) . ' ' . sprintf($this->_('To edit/delete %s, simply click on its Source.'), $pronoun);
    }
    $jumplinksDescriptionMarkup = $this->modules->get('InputfieldMarkup');
    $jumplinksDescriptionMarkup->value = $description;
    $jumplinksTab->append($jumplinksDescriptionMarkup);

    // Work through each jumplink, formatting data as we go along.
    $hits = 0;
    while ($jumplink = $jumplinks->fetchObject()) {
      // Source and Destination
      $jumplink->source = htmlentities($jumplink->source);
      $jumplink->destination = $this->compileDestinationUrl($jumplink->destination, true, false);

      // Timed Activation columns
      if (strtotime($jumplink->date_start) < $this->lowestDate) {
        $jumplink->date_start = null;
      }
      if (strtotime($jumplink->date_end) < $this->lowestDate) {
        $jumplink->date_end = null;
      }
      $relativeStartTime = str_replace('Never', '', wireRelativeTimeStr($jumplink->date_start, true));
      $relativeEndTime = str_replace('Never', '', wireRelativeTimeStr($jumplink->date_end, true));
      $relativeStartTime = ($relativeStartTime === '-')
        ? $relativeStartTime
        : "<abbr title=\"{$jumplink->date_start}\">{$relativeStartTime}</abbr>";
      $relativeEndTime = ($relativeEndTime === '-')
        ? $relativeEndTime
        : "<abbr title=\"{$jumplink->date_end}\">{$relativeEndTime}</abbr>";
      $relativeLastHit = wireRelativeTimeStr($jumplink->last_hit, true);

      // Format the Hits column to show the last hit date in a tooltip.
      $jumplinkHits = (strtotime($jumplink->last_hit) < $this->lowestDate)
        ? $jumplink->hits
        : "<abbr title=\"Last hit: {$relativeLastHit} ($jumplink->last_hit)\">{$jumplink->hits}</abbr>";

      // If the last hit was more than 30 days ago,
      // let the user know so that it may be deleted.
      if (strtotime($jumplink->last_hit) > $this->lowestDate &&
        strtotime($jumplink->last_hit) < strtotime('-30 days')) {
        $jumplinkHits .= '<span id="staleJumplink"></span>';
      }
      $hits = $hits + $jumplink->hits;

      // Add the row, now that the data has been formatted.
      $jumplinksTable->row([
        $this->truncate($jumplink->source, 80) => "{$this->entityFormPath}?id={$jumplink->id}",
        $jumplink->destination,
        $relativeStartTime,
        $relativeEndTime,
        $jumplinkHits,
      ]);
    }

    // Register button setup
    switch ($jumplinks->rowCount()) {
      case 0:
        $registerJumplinkButtonLabel = $this->_('Register First Jumplink');
        break;
      case 1:
        $registerJumplinkButtonLabel = $this->_('Register Another Jumplink');
        break;
      default:
        $registerJumplinkButtonLabel = $this->_('Register New Jumplink');
        break;
    }

    // Build Register button
    $registerJumplinkButton = $this->populateInputField($this->modules->get('InputfieldButton'), [
      'id' => 'registerJumplink',
      'href' => $this->entityFormPath,
      'value' => $registerJumplinkButtonLabel,
      'icon' => 'plus-circle',
    ])->addClass('head_button_clone');

    // Build config button
    $moduleConfigLinkButton = $this->populateInputField($this->modules->get('InputfieldButton'), [
      'id' => 'moduleConfigLink',
      'href' => $this->getModuleConfigUri(),
      'value' => $this->_('Configuration'),
      'icon' => 'cog',
    ])->addClass('ui-priority-secondary ui-button-float-right');

    // Add buttons
    $buttons = $registerJumplinkButton->render() . $moduleConfigLinkButton->render();

    // Render and append the table container
    $jumplinksTableContainer = $this->modules->get('InputfieldMarkup');
    $jumplinksTableContainer->value = $jumplinksTable->render() . $buttons;
    $jumplinksTab->append($jumplinksTableContainer);

    // Add the Mapping Collections tab
    $mappingCollectionsTab = new InputfieldWrapper();
    $mappingCollectionsTab->attr('title', $this->_('Mapping Collections'));
    $mappingCollectionsTab->id = 'mappingCollections';

    // Get Mapping Collections
    $mappingCollections = $this->database->query($this->sql->collection->selectAll);

    // Setup the data table
    $mappingCollectionsTable = $this->modules->get('MarkupAdminDataTable');
    $mappingCollectionsTable->setEncodeEntities(false);
    $mappingCollectionsTable->setClass('jumplinks mapping-collections');
    $mappingCollectionsTable->setSortable(false);
    $mappingCollectionsTable->headerRow([$this->_('Collection Name'), $this->_('Mappings'), $this->_('Created'), $this->_('Last Modified')]);

    // Setup the description markup
    if ($mappingCollections->rowCount() === 0) {
      $pronoun = 'one';
      $head = $this->_("You don't have any collections installed.");
    } else {
      $head = $this->_n('You have one collection installed.', 'Your collections are listed below.', $mappingCollections->rowCount());
      $pronoun = $this->_n('it', 'one', $mappingCollections->rowCount());
    }
    $description = ($mappingCollections->rowCount() === 0) ? '' : sprintf($this->_('To edit/uninstall %s, simply click on its Name.'), $pronoun);

    $mappingCollectionsDescriptionMarkup = $this->modules->get('InputfieldMarkup');
    $mappingCollectionsDescriptionMarkup->value = "{$head} {$description}";

    // Add the description markup.
    $mappingCollectionsTab->append($mappingCollectionsDescriptionMarkup);

    // Work through each collection.
    while ($mappingCollection = $mappingCollections->fetchObject()) {
      // Timestamps
      $userCreated = $this->users->get($mappingCollection->user_created)->name;
      $userUpdated = $this->users->get($mappingCollection->user_updated)->name;
      $created = wireRelativeTimeStr($mappingCollection->created_at) . sprintf($this->_(' by %s'), $userCreated);
      $updated = wireRelativeTimeStr($mappingCollection->updated_at) . sprintf($this->_(' by %s'), $userUpdated);
      if ($mappingCollection->created_at === $mappingCollection->updated_at) {
        $updated = '';
      }

      // Add the collection
      $mappingCollectionsTable->row([
        $mappingCollection->collection_name => "{$this->mappingCollectionFormPath}?id={$mappingCollection->id}",
        count(explode("\n", trim($mappingCollection->collection_mappings))),
        $created,
        $updated,
      ]);
    }

    // Install button label setup
    $installMappingCollectionButtonLabel = ($mappingCollections->rowCount() === 1) ? $this->_('Install Another Mapping Collection') : $this->_('Install New Mapping Collection');

    // Install button setup
    $installMappingCollectionButton = $this->populateInputField($this->modules->get('InputfieldButton'), [
      'id' => 'installMappingCollection',
      'href' => $this->mappingCollectionFormPath,
      'value' => $installMappingCollectionButtonLabel,
      'icon' => 'plus-circle',
    ]);

    // Add the button
    $buttons = $installMappingCollectionButton->render();

    // Add the description and table.
    $mappingCollectionsTableContainer = $this->modules->get('InputfieldMarkup');
    $mappingCollectionsTableContainer->value = $mappingCollectionsTable->render() . $buttons;
    $mappingCollectionsTab->append($mappingCollectionsTableContainer);

    // Add Import tab
    $importTab = new InputfieldWrapper();
    $importTab->attr('title', $this->_('Import'));
    $importTab->id = 'import';

    // Setup description markup.
    $infoContainer = $this->modules->get('InputfieldMarkup');
    if ($this->modules->isInstalled('ProcessRedirects')) {
      $infoContainer->value = $this->_('To import your jumplinks, select an option below:');
    } else {
      $infoContainer->value = $this->_('To import your jumplinks, click the button below:');
    }

    // Add description markup.
    $importTab->append($infoContainer);

    // Setup main container.
    $importContainer = $this->modules->get('InputfieldMarkup');

    // Setup CSV button
    $importFromCSVButton = $this->populateInputField($this->modules->get('InputfieldButton'), [
      'id' => 'importFromCSV',
      'href' => "{$this->importPath}",
      'value' => $this->_('Import from CSV'),
    ]);

    // Add button.
    $importContainer->value = $importFromCSVButton->render();

    // If ProcessRedirects is installed, add the applicable Import button.
    if ($this->modules->isInstalled('ProcessRedirects')) {
      $importFromRedirectsButton = $this->populateInputField($this->modules->get('InputfieldButton'), [
        'id' => 'importFromRedirects',
        'href' => "{$this->importPath}?type=redirects",
        'value' => $this->_('Import from Redirects Module'),
      ])->addClass('ui-priority-secondary');
      $importContainer->value .= $importFromRedirectsButton->render();
    }

    // Append the container.
    $importTab->append($importContainer);

    // If the 404 monitor is enabled, add the tab and container.
    if ($this->enable404Monitor) {
      // Add 404 Monitor tab.
      $notFoundMonitorTab = new InputfieldWrapper();
      $notFoundMonitorTab->attr('title', $this->_('404 Monitor'));
      $notFoundMonitorTab->id = 'notFoundMonitor';

      // Get 404 hits.
      $notFoundEntities = $this->database->query($this->sql->notFoundMonitor->selectAll);

      // Setup the container.
      $infoContainer = $this->modules->get('InputfieldMarkup');

      // Setup the description.
      if ($notFoundEntities->rowCount() === 0) {
        $infoContainer->value = $this->_("There have been no '404 Not Found' hits on your site.");
      } else if ($notFoundEntities->rowCount() === 1) {
        $infoContainer->value = $this->_("Below is the last '404 Not Found' hit. To create a jumplink for it, simply click on its Request URI.");
      } else {
        $infoContainer->value = $this->_("Below are the last {$notFoundEntities->rowCount()} '404 Not Found' hits. To create a jumplink for one, simply click on its Request URI.");
      }

      // Add description to tab container.
      $notFoundMonitorTab->append($infoContainer);

      // Setup the datatable.
      $notFoundMonitorTable = $this->modules->get('MarkupAdminDataTable');
      $notFoundMonitorTable->setEncodeEntities(false);
      $notFoundMonitorTable->setClass('jumplinks notFounds');
      $notFoundMonitorTable->setSortable(false);
      $notFoundMonitorTable->headerRow([$this->_('Request URI'), $this->_('Referrer'), $this->_('User Agent'), $this->_('Date/Time')]);

      // Get the UA parser
      require_once __DIR__ . '/Classes/ParseUserAgent.php';

      // Loop through each 404, formatting as we go along.
      while ($notFoundEntity = $notFoundEntities->fetchObject()) {
        $userAgentParsed = ParseUserAgent::get($notFoundEntity->user_agent);
        $source = urlencode($notFoundEntity->request_uri);

        // Add the 404 row.
        $notFoundMonitorTable->row([
          $notFoundEntity->request_uri => "{$this->entityFormPath}?id=0&source={$source}",
          (!null === $notFoundEntity->referrer) ? $notFoundEntity->referrer : '',
          "<abbr title=\"{$notFoundEntity->user_agent}\">{$userAgentParsed['browser']} {$userAgentParsed['version']}</abbr>",
          $notFoundEntity->created_at,
        ]);
      }

      // Setup Clear Button
      $button = '';
      if ($notFoundEntities->rowCount() > 0) {
        $clearNotFoundLogButton = $this->populateInputField($this->modules->get('InputfieldButton'), [
          'id' => 'clearNotFoundLog',
          'href' => $this->clearNotFoundLogPath,
          'value' => $this->_('Clear All'),
          'icon' => 'times-circle',
        ]);
        $button = $clearNotFoundLogButton->render();
      }

      // Add the data table and button.
      $notFoundMonitorTableContainer = $this->modules->get('InputfieldMarkup');
      $notFoundMonitorTableContainer->value = $notFoundMonitorTable->render() . $button;

      // Add the 404 container.
      $notFoundMonitorTab->append($notFoundMonitorTableContainer);
    }

    // Add all tabs
    $tabContainer
      ->append($jumplinksTab)
      ->append($mappingCollectionsTab)
      ->append($importTab);
    if ($this->enable404Monitor) {
      $tabContainer->append($notFoundMonitorTab);
    }

    // Let backend know that we're adminstering jumplinks.
    $this->config->js('pjAdmin', true);

    // We have to wrap it in a form to prevent spacing underneath
    // the tabs. This goes hand in hand with a rule in the stylesheet.
    return "<form id=\"pjTabs\">{$tabContainer->render()}{$this->helpLinks()}</form>";
  }

  /**
   * Admin Page: Add/Edit Entity (Redirect)
   * @return string
   */
  public function ___executeEntity()
  {
    $this->injectAssets();

    // Get the ID if we're editing
    $editingId = $this->input->get->id;
    $editingId = (isset($editingId) and $editingId > 0 and is_numeric($editingId)) ? $editingId : 0;

    $this->setFuel('processHeadline', ($editingId > 0) ? $this->_('Editing Jumplink') : $this->_('Register New Jumplink'));

    if ($editingId > 0) {
      // Fetch the details and list vars
      $query = $this->database->prepare($this->sql->entity->selectOne);
      $query->execute([
        'id' => $editingId,
      ]);
      list($id, $sourcePath, $destinationUriUrl, $hits,
        $userCreated, $userUpdated, $dateStart, $dateEnd,
        $createdAt, $updatedAt, $lastHit) = $query->fetch();

      // Format dates (times)
      $dateStart = (strtotime($dateStart) > $this->lowestDate) ? date('Y-m-d h:m A', strtotime($dateStart)) : null;
      $dateEnd = (strtotime($dateEnd) > $this->lowestDate) ? date('Y-m-d h:m A', strtotime($dateEnd)) : null;
    }

    // Prep the form
    $form = $this->modules->get('InputfieldForm');
    $form->id = 'pjInputForm';
    $form->method = 'POST';
    $form->action = '../commit/';

    // ID field
    $field = $this->modules->get('InputfieldHidden');
    $form->add($this->populateInputField($field, [
      'name' => 'id',
      'value' => $editingId,
    ]));

    if ($editingId > 0 &&
      strtotime($lastHit) > $this->lowestDate &&
      strtotime($lastHit) < strtotime('-30 days')) {
      $field = $this->modules->get('InputfieldMarkup');
      $form->add($this->populateInputField($field, [
        'id' => 'staleJumplink',
        'value' => sprintf($this->_("This jumplink hasn't been hit in over 30 days (last hit on %s), and so it is safe to delete."), $lastHit),
      ]));
    }

    if ($editingId == 0 && $this->input->get->source != '') {
      $sourcePath = urldecode($this->input->get->source);
    }

    // Source Path field
    $field = $this->modules->get('InputfieldText');
    $form->add($this->populateInputField($field, [
      'name+id' => 'sourcePath',
      'label' => $this->_('Source'),
      'description' => sprintf($this->_('Enter a URI relative to the root of your site. **[(see examples)](%1$s/more/tldr-examples.html)**'), $this->moduleInfo['href']),
      'required' => 1,
      'collapsed' => Inputfield::collapsedNever,
      'value' => isset($sourcePath) ? $sourcePath : '',
    ]));

    // Destination fields
    $destinationFieldset = $this->buildInputField('InputfieldFieldset', [
      'label' => __('Destination'),
    ]);
    $destinationSelectorsFieldset = $this->buildInputField('InputfieldFieldset', [
      'label' => __('or select one using...'),
      'notes' => $this->_('If you choose to not use either of the Page selectors below, be sure to enter a valid path above.'),
      'collapsed' => Inputfield::collapsedYes,
    ]);
    $destinationPageField = $this->modules->get('InputfieldPageListSelect');
    $destinationPageAutoField = $this->modules->get('InputfieldPageAutocomplete');
    $destinationPathField = $this->modules->get('InputfieldText');

    // Check if the current destination is a page
    if (isset($destinationUriUrl) && $page = $this->pages->get((int) str_replace('page:', '', $destinationUriUrl))) {
      $isPage = (bool) $page->id;
      if ($isPage) {
        $destinationPageField->value = $page->id;
        $destinationPageAutoField->value = $page->id;
        $destinationPathField->collapsed = Inputfield::collapsedYes;
        $destinationSelectorsFieldset->collapsed = Inputfield::collapsedNo;
      } else {
        $destinationSelectorsFieldset->collapsed = Inputfield::collapsedYes;
        $destinationPathField->collapsed = Inputfield::collapsedBlank;
      }
    } else {
      $destinationPageAutoField->collapsed = Inputfield::collapsedYes;
    }

    // Destination Path field
    $destinationFieldset->add($this->populateInputField($destinationPathField, [
      'name+id' => 'destinationUriUrl',
      'label' => $this->_('Specify a destination'),
      'description' => sprintf($this->_('Enter either a URI relative to the root of your site, an absolute URL, or a Page ID. **[(see examples)](%1$s/more/tldr-examples.html)**'), $this->moduleInfo['href']),
      'notes' => $this->_('If you select a page from either of the Page selectors below, its identifier will be placed here.'),
      'required' => 1,
      'value' => isset($destinationUriUrl) ? $destinationUriUrl : '',
    ]));

    // Select from tree
    $destinationSelectorsFieldset->add($this->populateInputField($destinationPageField, [
      'name+id' => 'destinationPage',
      'label' => $this->_('Page Tree'),
      'parent_id' => 0,
      'startLabel' => $this->_('Choose a Page'),
    ]));

    // Select via auto-complete
    $destinationSelectorsFieldset->add($this->populateInputField($destinationPageAutoField, [
      'name+id' => 'destinationPageAuto',
      'label' => $this->_('Auto Complete'),
      'parent_id' => 0,
      'maxSelectedItems' => 1,
    ]));

    $destinationFieldset->add($destinationSelectorsFieldset);
    $form->add($destinationFieldset);

    // Timed Activation fieldset
    $fieldSet = $this->modules->get('InputfieldFieldset');
    $fieldSet->label = $this->_('Timed Activation');
    $fieldSet->collapsed = Inputfield::collapsedYes;
    $fieldSet->description = $this->_("If you'd like this jumplink to only function during a specific time-range, then select the start and end dates and times below.");
    $fieldSet->notes = $this->_("You don't have to specify both. If you only specify a start time, you're simply delaying activation. If you only specify an end time, then you're simply telling it when to stop.\nIf an End Date/Time is specified, a temporary redirect will be made (302 status code, as opposed to 301).");

    $datetimeFieldDefaults = [
      'datepicker' => 1,
      'timeInputFormat' => 'h:m A',
      'yearRange' => '-0:+100',
      'collapsed' => Inputfield::collapsedNever,
      'columnWidth' => 50,
    ];

    // Start field
    $field = $this->modules->get('InputfieldDatetime');
    $fieldSet->add($this->populateInputField($field, array_merge([
      'name' => 'dateStart',
      'label' => $this->_('Start Date/Time'),
      'value' => (isset($dateStart)) ? $dateStart : '',
    ], $datetimeFieldDefaults)));

    // End field
    $field = $this->modules->get('InputfieldDatetime');
    $fieldSet->add($this->populateInputField($field, array_merge([
      'name' => 'dateEnd',
      'label' => $this->_('End Date/Time'),
      'value' => (isset($dateEnd)) ? $dateEnd : '',
    ], $datetimeFieldDefaults)));

    $form->add($fieldSet);

    // If we're editing:
    if ($editingId > 0) {
      // Get and ddd info markup
      $field = $this->modules->get('InputfieldMarkup');
      $userCreated = $this->users->get($userCreated);
      $userUpdated = $this->users->get($userUpdated);
      $userUrl = wire('config')->urls->admin . 'access/users/edit/?id=';
      $relativeTimes = [
        'created' => wireRelativeTimeStr($createdAt),
        'updated' => wireRelativeTimeStr($updatedAt),
      ];
      $lastHitFormatted = $this->_("This jumplink hasn't been hit yet.");
      if (strtotime($lastHit) > $this->lowestDate) {
        $lastHitFormatted = sprintf($this->_('Last hit on: %s (%s)'), $lastHit, wireRelativeTimeStr($lastHit));
      }

      $form->add($this->populateInputField($field, [
        'id' => 'info',
        'label' => $this->_('Info'),
        'value' => $this->blueprint('entity-info', [
          'user-created-name' => $userCreated->name,
          'user-updated-name' => $userUpdated->name,
          'user-created-url' => $userUrl . $userCreated->id,
          'user-updated-url' => $userUrl . $userUpdated->id,
          'created-at' => $createdAt,
          'created-at-relative' => $relativeTimes['created'],
          'updated-at' => $updatedAt,
          'updated-at-relative' => $relativeTimes['updated'],
          'last-hit' => $lastHitFormatted,
        ]),
      ]));

      // Add Clear Hit Counter checkbox if there are hits
      if ($hits > 0) {
        $field = $this->modules->get('InputfieldCheckbox');
        $form->add($this->populateInputField($field, [
          'name' => 'clearhits',
          'label' => $this->_('Clear Hit Counter'),
          'icon' => 'times',
          'description' => $this->_("If you'd like to clear the hit counter for this jumplink, check the box below."),
          'label2' => $this->_('Clear Hit Counter'),
          'collapsed' => Inputfield::collapsedYes,
        ]));
      }

      // Add Delete checkbox
      $field = $this->modules->get('InputfieldCheckbox');
      $form->add($this->populateInputField($field, [
        'name' => 'delete',
        'label' => $this->_('Delete'),
        'icon' => 'times-circle',
        'description' => $this->_("If you'd like to delete this jumplink, check the box below."),
        'label2' => $this->_('Delete this jumplink'),
        'collapsed' => Inputfield::collapsedYes,
      ]));
    }

    // Save/Update button
    $field = $this->modules->get('InputfieldButton');
    $form->add($this->populateInputField($field, [
      'name+id' => 'saveJumplink',
      'value' => ($editingId) ? $this->_('Update Jumplink') : $this->_('Save Jumplink'),
      'icon' => 'save',
      'type' => 'submit',
    ])->addClass('head_button_clone'));

    $this->config->js('pjEntity', true);

    // Return the rendered page
    return $form->render() . $this->helpLinks('basics/getting-started.html', false, 'Getting Started with Jumplinks');
  }

  /**
   * Commit a new jumplink
   * @param  String $input
   * @param  int    $hits     = 0
   * @param  bool   $updating = false
   * @param  int    $id       = 0
   * @return void
   */
  protected function commitJumplink($input, $hits = 0, $updating = false, $id = 0)
  {
    $noWildcards = (
      false === strpos($input->destinationUriUrl, '{') &&
      false === strpos($input->destinationUriUrl, '}')
    );
    $isRelative = !$this->destinationHasScheme($input->destinationUriUrl);

    // If the Destination Path's URI matches that of a page, use a page ID instead
    if ($noWildcards && $isRelative) {
      if (($page = $this->pages->get('/' . trim($input->destinationUriUrl, '/'))) && $page->id) {
        $input->destinationUriUrl = "page:{$page->id}";
      }
    }

    // Escape Source and Destination (Sanitised) Paths
    $source = ltrim($input->sourcePath, '/');
    $destination = ltrim($input->destinationUriUrl, '/');

    // Prepare dates (times) for database entry
    $start = (!isset($input->dateStart) || empty($input->dateStart)) ? self::NULL_DATE : date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $input->dateStart)));
    $end = (!isset($input->dateEnd) || empty($input->dateEnd)) ? self::NULL_DATE : date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $input->dateEnd)));

    // Set the user creating/updating
    if (!$updating) {
      $userCreated = $this->user->id;
    }

    $userUpdated = $this->user->id;

    // Insert/Update

    $dataBind = [
      'source' => $source,
      'destination' => $destination,
      'date_start' => $start,
      'date_end' => $end,
      'user_updated' => $userUpdated,
    ];

    if ($updating) {
      $query = $this->database->prepare($this->sql->entity->update);
      $dataBind['id'] = $id;
    } else {
      $query = $this->database->prepare($this->sql->entity->insert);
      $dataBind['hits'] = $hits;
      $dataBind['user_created'] = $userCreated;
    }
    $query->execute($dataBind);
  }

  /**
   * Check if a $destination contains a scheme
   */
  protected function destinationHasScheme($destination)
  {
    return preg_match('~^(?:f|ht)tps?://~i', $destination);
  }

  /**
   * API method to add a new jumplink
   * @param String $source
   * @param String $destination
   * @param String $start
   * @param String $end
   * @return void
   */
  public function add($source, $destination, $start = '', $end = '')
  {
    $this->commitJumplink((object) [
      'sourcePath' => $source,
      'destinationUriUrl' => $destination,
      'dateStart' => $start,
      'dateEnd' => $end,
    ]);
  }

  /**
   * Admin Route: Commit new jumplink or update existing
   * @return void
   */
  public function ___executeCommit()
  {
    // Just to be on the safe side...
    if ($this->input->post->id == null) {
      $this->session->redirect('../');
    }

    $input = $this->input->post;

    // Set the ID and check if we're updating
    $id = (int) $input->id;
    $isUpdating = ($id !== 0);

    // If we're updating:
    if ($isUpdating) {
      // Check if the jumplink needs to be deleted.
      if ($input->delete) {
        $this->database->prepare($this->sql->entity->dropOne)->execute([
          'id' => $id,
        ]);
        $this->message($this->_('Jumplink deleted.'));
        $this->session->redirect('../');
      }

      // Check if we're clearing hits for this jumplink.
      if ($input->clearhits) {
        $this->database->prepare($this->sql->entity->updateHits)->execute([
          'hits' => 0,
          'id' => $id,
        ]);
        $this->message($this->_('Jumplink hits cleared.'));
      }
    }

    // Otherwise, continue to commit jumplink to DB
    $this->commitJumplink($input, 0, $isUpdating, $id);
    $this->message($this->_('Jumplink saved.'));
    $this->session->redirect('../');
  }

  /**
   * Admin Page: Install/Uninstall Mapping Collections
   * @return string
   */
  public function ___executeMappingCollection()
  {
    $this->injectAssets();

    $this->setFuel('processHeadline', $this->_('Install New Mapping Collection'));

    // Get the ID if we're editing
    $editingId = $this->input->get->id;
    $editingId = (isset($editingId) and $editingId > 0 and is_numeric($editingId)) ? $editingId : 0;

    if ($editingId) {
      // Fetch the details and list vars
      $query = $this->database->prepare($this->sql->collection->selectOne);
      $query->execute([
        'id' => $editingId,
      ]);
      list($id, $collectionName, $collectionData, $userCreated, $userUpdated, $updatedAt, $createdAt) = $query->fetch();

      $this->setFuel('processHeadline', $this->_("Editing Mapping Collection: {$collectionName}"));
    }

    // Prep the form
    $form = $this->modules->get('InputfieldForm');
    $form->id = 'pjInputForm';
    $form->method = 'POST';
    $form->action = '../commitmappingcollection/';

    // ID field
    $field = $this->modules->get('InputfieldHidden');
    $form->add($this->populateInputField($field, [
      'name' => 'id',
      'value' => $editingId,
    ]));

    // Mapping Name field
    $field = $this->modules->get('InputfieldText');
    $form->add($this->populateInputField($field, [
      'name+id' => 'collectionName',
      'label' => $this->_('Name'),
      'notes' => $this->_('Only use alpha characters (a-z). Name will be sanitised upon submission. This name is the identifier to be used in mapping wildcards.'),
      'required' => 1,
      'collapsed' => Inputfield::collapsedNever,
      'value' => isset($collectionName) ? $collectionName : '',
    ]));

    // Mapping Data field
    $field = $this->modules->get('InputfieldTextarea');
    $form->add($this->populateInputField($field, [
      'name+id' => 'collectionData',
      'label' => $this->_('Mapping Data'),
      'description' => sprintf($this->_('Enter each mapping for this collection, one per line, in the following format: key=value. You will more than likely make use of this feature if you are mapping IDs to URL-friendly names, but you can use named identifiers too. To learn more about how this feature works, please [read through the documentation](%s).'), $this->helpLinks('features/mapping-collections.html', true)),
      'notes' => sprintf($this->_("To make things easier, you'll probably want to export your data from your old platform/framework in this format.\n**Note:** All **values** will be cleaned according to the 'Wildcard Cleaning' setting in the [module's configuration](%s)."), $this->getModuleConfigUri()),
      'required' => 1,
      'rows' => 10,
      'collapsed' => Inputfield::collapsedNever,
      'value' => isset($collectionData) ? $collectionData : '',
    ]));

    // If we're editing:
    if ($editingId > 0) {
      // Get and ddd info markup
      $field = $this->modules->get('InputfieldMarkup');
      $userCreated = $this->users->get($userCreated);
      $userUpdated = $this->users->get($userUpdated);
      $userUrl = wire('config')->urls->admin . 'access/users/edit/?id=';
      $relativeTimes = [
        'created' => wireRelativeTimeStr($createdAt),
        'updated' => wireRelativeTimeStr($updatedAt),
      ];
      $form->add($this->populateInputField($field, [
        'id' => 'info',
        'label' => $this->_('Info'),
        'value' => $this->blueprint('collection-info', [
          'user-created-name' => $userCreated->name,
          'user-updated-name' => $userUpdated->name,
          'user-created-url' => $userUrl . $userCreated->id,
          'user-updated-url' => $userUrl . $userUpdated->id,
          'created-at' => $createdAt,
          'created-at-relative' => $relativeTimes['created'],
          'updated-at' => $updatedAt,
          'updated-at-relative' => $relativeTimes['updated'],
        ]),
      ]));

      // Add Uninstall button
      $field = $this->modules->get('InputfieldCheckbox');
      $form->add($this->populateInputField($field, [
        'name+id' => 'uninstallCollection',
        'label' => $this->_('Uninstall'),
        'icon' => 'times-circle',
        'description' => $this->_("If you'd like to uninstall this collection, check the box below."),
        'label2' => $this->_('Uninstall this collection'),
        'collapsed' => Inputfield::collapsedYes,
      ]));
    }

    // Install/Update & Return button
    $field = $this->modules->get('InputfieldButton');
    $form->add($this->populateInputField($field, [
      'name+id' => 'installMappingCollection',
      'value' => ($editingId) ? $this->_('Update & Return') : $this->_('Install & Return'),
      'icon' => 'save',
      'type' => 'submit',
    ]));

    $this->config->js('pjCollection', true);

    // Return the rendered page
    return $form->render() . $this->helpLinks('features/mapping-collections.html', false, 'Mapping Collections');
  }

  /**
   * Commit a new mapping collection
   * @param  String $collectionName
   * @param  Array  $data
   * @param  int    $id
   * @return void
   */
  protected function commitMappingCollection($collectionName, $collectionData, $id = 0)
  {
    // Clean up name (alphas only)
    $collectionName = preg_replace('~[^a-z]~', '', strtolower($collectionName));

    // Fetch, trim, and explode the data for cleaning
    $mappings = explode("\n", trim($collectionData));

    $compiledMappings = [];

    // Split up the key/value pairs and clean
    foreach ($mappings as $mapping) {
      $mapping = explode('=', $mapping);

      $wildcardCleaning = $this->wildcardCleaning;
      if ($wildcardCleaning === 'fullClean' || $wildcardCleaning === 'semiClean') {
        $mapping[1] = $this->cleanWildcard($mapping[1], ($wildcardCleaning === 'fullClean') ? false : true);
      }

      $compiledMappings[trim($mapping[0])] = $mapping[1];
    }

    $dbInput = '';

    foreach ($compiledMappings as $key => $value) {
      $dbInput .= "$key=$value\n";
    }

    $dbInput = trim($dbInput);

    $updating = ($id > 0);

    // Set the user creating/updating
    if (!$updating) {
      $userCreated = $this->user->id;
    }

    $userUpdated = $this->user->id;

    $dataBind = [
      'collection' => $collectionName,
      'mappings' => $dbInput,
      'user_updated' => $userUpdated,
    ];

    if ($updating) {
      $query = $this->database->prepare($this->sql->collection->update);
      $dataBind['id'] = $id;
    } else {
      $query = $this->database->prepare($this->sql->collection->insert);
      $dataBind['user_created'] = $userCreated;
    }
    $query->execute($dataBind);
  }

  /**
   * API call to create a new collection, or add to an existing one
   * @param String $name
   * @param Array $data
   * @return void
   */
  public function collection($name, $data)
  {
    $collectionData = '';
    $id = 0;

    // Check if the collection already exists
    // and grab its data if it does
    $collections = $this->database->prepare($this->sql->collection->selectOneByName);
    $collections->execute([
      'collection' => $name,
    ]);
    if (count($collections) !== 0) {
      while ($collection = $collections->fetch(PDO::FETCH_OBJ)) {
        $id = (int) $collection->id;
        $collectionData = $collection->collection_mappings . "\n";
      }
    }

    // Gather the data from the array
    foreach ($data as $key => $value) {
      $collectionData .= "{$key}={$value}\n";
    }

    // And send it off!
    $this->commitMappingCollection($name, trim($collectionData, "\n"), $id);
  }

  /**
   * Admin Route: Commit new mapping collection or update existing
   * @return void
   */
  public function ___executeCommitMappingCollection()
  {
    // Just to be on the safe side...
    if ($this->input->post->id == null) {
      $this->session->redirect('../');
    }

    $input = $this->input->post;

    // Set the ID and check if we're updating
    $id = (int) $input->id;
    $isUpdating = ($id > 0);

    // If we're updating, check if we should uninstall
    if ($isUpdating && $input->uninstallCollection) {
      $this->database->prepare($this->sql->collection->dropOne)->execute([
        'id' => $id,
      ]);
      $this->message($this->_('Collection uninstalled.'));
      $this->session->redirect('../');
    }

    $this->commitMappingCollection($input->collectionName, $input->collectionData, $id);
    $this->message(sprintf($this->_("Mapping Collection '%s' saved."), $input->collectionName));
    $this->session->redirect('../');
  }

  /**
   * Admin Page: Backup form
   * @return string
   */
  public function ___executeImport()
  {
    $this->injectAssets();

    // Prep the form
    $form = $this->modules->get('InputfieldForm');
    $form->id = 'pjInputForm';
    $form->method = 'POST';
    $form->action = '../doimport/';

    $importType = $this->input->get->type;
    if (null === $importType) {
      $importType = 'csv';
    }

    $redoing = $this->input->get('redo') == true;

    switch ($importType) {
      case 'redirects':
        $this->setFuel('processHeadline', $this->_('Import Jumplinks from ProcessRedirects'));
        $this->config->js('pjImportRedirectsModule', true);
        if (!$this->modules->isInstalled('ProcessRedirects')) {
          $this->session->redirect('../');
        }
        if (!$this->redirectsImported || $redoing) {
          // Information
          $field = $this->modules->get('InputfieldMarkup');
          if ($redoing) {
            $infoLabel = $this->_('If your last import failed, you can always try the import again. First, make sure that the jumplinks you imported have been deleted. Alternatively, just uncheck the ones that sucessfully imported the first time.');
          } else {
            $infoLabel = $this->_('You have the Redirects module installed. As such, you can migrate your existing redirects from the module (below) to Jumplinks. If there are any redirects you wish to exclude, simply uncheck the box in the first column');
          }
          $form->add($this->populateInputField($field, [
            'label' => $this->_('Import from the Redirects module'),
            'value' => $infoLabel,
          ]));
        }

        // Redirects
        $importInfoMarkup = $this->modules->get('InputfieldMarkup');

        if ($this->redirectsImported && !$redoing) {
          $importInfoMarkup->label = $this->_('Redirects already imported');
          $importInfoMarkup->value = $this->_('All your redirects from ProcessRedirects have already been imported. You can safely uninstall ProcessRedirects. However, if something went wrong during the import, you can always try again using the button below. Of course, this import facility will not appear once ProcessRedirects is uninstalled.');
          $form->add($importInfoMarkup);
          $oopsButton = $this->modules->get('InputfieldButton');
          $form->add($this->populateInputField($oopsButton, [
            'name+id' => 'oopsRedo',
            'value' => $this->_('Something went wrong, let me try again'),
            'icon' => 'repeat',
            'href' => '?type=redirects&redo=true',
          ]));
        } else {
          $redirectsTable = $this->modules->get('MarkupAdminDataTable');
          $redirectsTable->setClass('old-redirects');
          $redirectsTable->setEncodeEntities(false);
          $redirectsTable->setSortable(false);
          $redirectsTable->headerRow([$this->_('Import'), $this->_('Redirect From'), $this->_('Redirect To'), $this->_('Hits')]);

          $jumplinks = $this->database->query("SELECT * FROM {$this->redirectsTableName} ORDER BY redirect_from");

          while ($jumplink = $jumplinks->fetchObject()) {
            $redirectsTable->row([
              "<input type=\"checkbox\" name=\"importArray[]\" checked value=\"{$jumplink->id}\">",
              $jumplink->redirect_from,
              $jumplink->redirect_to,
              $jumplink->counter,
            ]);
          }

          $importInfoMarkup->label = $this->_('Available Redirects');
          $importInfoMarkup->value = $redirectsTable->render();
          $form->add($importInfoMarkup);
        }

        break;
      default: // type = csv
        $this->setFuel('processHeadline', $this->_('Import Jumplinks from CSV'));
        $this->config->js('pjImportCSVData', true);
        // Headings
        $field = $this->modules->get('InputfieldCheckbox');
        $form->add($this->populateInputField($field, [
          'name+id' => 'csvHeadings',
          'label2' => $this->_('My CSV data contains headings'),
          'label' => $this->_('Ignore Headings'),
          'notes' => $this->_('No need to worry about what your headings are called. The importer simply ignores them when you check this box.'),
          'columnWidth' => 30,
        ]));
        // Delims
        $field = $this->modules->get('InputfieldSelect');
        $form->add($this->populateInputField($field, [
          'name+id' => 'csvDelimiter',
          'options' => [
            ',' => 'Comma (,)',
            ';' => 'Semicolon (;)',
            '|' => 'Pipe (|)',
          ],
          'label' => $this->_('Select the delimiter your CSV data uses.'),
          'notes' => $this->_('Previously, this was auto detected. However, the CSV parser has been changed to something more robust with less bugs.'),
          'columnWidth' => 30,
          'defaultValue' => ',',
          'required' => true,
        ]));
        // Enclosure char
        $field = $this->modules->get('InputfieldSelect');
        $form->add($this->populateInputField($field, [
          'name+id' => 'csvEnclosure',
          'options' => [
            '"' => 'Double-quotes (")',
            "'" => "Single-quotes (')",
          ],
          'label' => $this->_('Select the enclosure character your CSV data uses.'),
          'notes' => $this->_('More often than not, cells are enclosed in double-quotes. If you are using single-quotes instead, set this option accordingly.'),
          'columnWidth' => 30,
          'defaultValue' => '"',
          'required' => true,
        ]));
        // Information
        $field = $this->modules->get('InputfieldTextarea');
        $form->add($this->populateInputField($field, [
          'name+id' => 'csvData',
          'label' => $this->_('CSV Data'),
          'description' => $this->_("Paste in your old redirects below, where each one is on its own line containing values separated by the chosen delimiter above. Any URI/URL that contains the chosen delimiter must be wrapped the enclosure character chosen above.\n\n**Column Order:** *Source*, *Destination*, *Time Start*, *Time End*. The last two columns are optional and do not need to be included where not required. If you need to specify the ending time, simply use an empty cell for the starting time (`source,destination,,end`)."),
          'notes' => $this->_("**Conversion Notes:**\n1. Any encoded ampersands (**&amp;amp;**) will be converted to **&amp;**.\n2. If the source or destination of a redirect contains leading slashes, they will be stripped.\n3. Empty lines will be discarded."),
          'rows' => 15,
        ]));

        break;
    }

    // Type of import
    $field = $this->modules->get('InputfieldHidden');
    $form->add($this->populateInputField($field, [
      'name' => 'importType',
      'value' => $importType,
    ]));

    // Import data/redirects button
    $field = $this->modules->get('InputfieldButton');
    if ($importType === 'redirects' && !$this->redirectsImported || $redoing) {
      $form->add($this->populateInputField($field, [
        'name+id' => 'doImport',
        'value' => $this->_('Import these Redirects'),
        'icon' => 'arrow-right',
        'type' => 'submit',
      ])->addClass('head_button_clone'));
    } else if ($importType !== 'redirects') {
      $form->add($this->populateInputField($field, [
        'name+id' => 'doImport',
        'value' => $this->_('Import Data'),
        'icon' => 'cloud-upload',
        'type' => 'submit',
      ])->addClass('head_button_clone'));
    }

    // Rename import type for docs
    if ($importType === 'redirects') {
      $importType = 'processredirects';
    }

    $this->config->js('pjImport', true);

    return $form->render() . $this->helpLinks('features/importing.html#comma-separated-values-csv', false, 'Importing from CSV');
  }

  /**
   * Admin Route: Do an import based on data sent.
   * @return void
   */
  public function ___executeDoImport()
  {
    // Just to be on the safe side...
    if ($this->input->post->importType == null) {
      $this->session->redirect('../');
    }

    // Get the type of import ...
    $importType = $this->input->post->importType;

    // ... and go!
    switch ($importType) {
      case 'csv':
        // Require the CSV reader
        require_once __DIR__ . '/Classes/LeagueCsv/autoload.php';
        $reader = League\Csv\Reader::createFromString($this->input->post->csvData);
        $reader->setDelimiter($this->input->post->csvDelimiter);
        $reader->setEnclosure($this->input->post->csvEnclosure);
        $columns = ['source', 'destination', 'starts', 'ends'];
        foreach ($reader->fetchAssoc($columns) as $row) {
          $nullCellCount = 0;
          foreach ($row as $key => $value) {
            if ($value === null) {
              $nullCellCount++;
            }
          }
          if ($nullCellCount === 4) {
            continue;
          }

          foreach ($columns as $column) {
            $row[$column] = ltrim($row[$column], '/');
          }
          $jumplink = (object) [
            'sourcePath' => str_replace('&amp;', '&', $this->database->escape_string($row['source'])),
            'destinationUriUrl' => str_replace('&amp;', '&', $this->sanitizer->url($this->database->escape_string($row['destination']))),
          ];
          if ($row['starts'] !== null && !empty($row['starts'])) {
            $jumplink->dateStart = $this->database->escape_string($row['starts']);
          }
          if ($row['ends'] !== null && !empty($row['ends'])) {
            $jumplink->dateEnd = $this->database->escape_string($row['ends']);
          }
          $this->commitJumplink($jumplink, 0);
        }
        $this->message('Redirects imported from CSV.');
        $this->session->redirect('../');
        break;
      case 'redirects':
        // Fetch the importArray - make sure all values are integers.
        $redirectsArray = implode(',', array_map('intval', $this->input->post->importArray));

        // Now fetch the redirects
        $query = $this->database->prepare("SELECT * FROM {$this->redirectsTableName} WHERE id IN ($redirectsArray)");
        $query->execute();

        // Gather and count the redirects
        $redirects = $query->fetchAll(PDO::FETCH_OBJ);
        $countRedirects = count($redirects);

        // And import them
        if ($countRedirects > 0) {
          foreach ($redirects as $redirect) {
            $jumplink = (object) [
              'sourcePath' => $redirect->redirect_from,
              'destinationUriUrl' => preg_replace("~\^(\d+)$~i", 'page:\\1', $redirect->redirect_to),
            ];
            $this->commitJumplink($jumplink, $redirect->counter);
          }
        }

        // Don't allow another import (we're importing for a reason - to migrate over to one module)
        $configData = $this->modules->getModuleConfigData($this);
        $configData['redirectsImported'] = true;
        $this->modules->saveModuleConfigData($this, $configData);

        $this->message($this->_('Redirects imported. You can now safely uninstall ProcessRedirects.'));
        $this->session->redirect('../');

        break;
      default:
        $this->session->redirect('../');
    }

  }

  /**
   * Admin Route: Clear the 404 log.
   * @return void
   */
  public function ___executeClearNotFoundLog()
  {
    $this->database->exec($this->sql->notFoundMonitor->deleteAll);
    $this->message($this->_('[Jumplinks] 404 Monitor cleared.'));
    $this->session->redirect('../');
  }

  /**
   * Install the module
   * @return void
   */
  public function ___install()
  {
    // Install tables (their schemas may not remain the same as updateDatabaseSchema() may change them)
    foreach (['main', 'mc'] as $schema) {
      $this->database->exec($this->blueprint("schema-create-{$schema}"));
    }

    parent::___install();
  }

  /**
   * Uninstall the module
   * @return void
   */
  public function ___uninstall()
  {
    // Uninstall tables
    $this->database->exec($this->blueprint('schema-drop'));
    parent::___uninstall();
  }

  /**
   * Dump and die
   * @param  Mixed $mixed Anything
   * @return void
   */
  protected function dd($mixed, $die = true)
  {
    header('Content-Type: text/plain');
    var_dump($mixed);
    $die && die;
  }

}
