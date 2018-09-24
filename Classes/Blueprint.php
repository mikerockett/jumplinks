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
 * Blueprint
 * Handle blueprint templates with simple var replacements.
 *
 * @author Mike Rockett <mike@rockett.pw>
 * @copyright (c) 2015, Mike Rockett. All Rights Reserved.
 * @license MIT License - http://mit-license.org/
 */

class Blueprint
{
  /**
   * @var mixed
   */
  protected $file;

  /**
   * @var array
   */
  protected $vars = [];

  /**
   * @param string $file
   */
  public function __construct($file)
  {
    $this->file = $this->findBlueprint("/../Blueprints/$file");
  }

  /**
   * @param string $file
   */
  protected function findBlueprint($file)
  {
    $extensions = ['sql', 'html'];

    if (file_exists(__DIR__ . $file)) {
      return file_get_contents(__DIR__ . $file);
    }

    foreach ($extensions as $extension) {
      $withExt = "{$file}.{$extension}";
      if (file_exists(__DIR__ . $withExt)) {
        return file_get_contents(__DIR__ . $withExt);
      }
    }

    throw new Exception('Blueprint not found: ' . __DIR__ . $file);
  }

  /**
   * @param string $key
   * @param string $value
   */
  public function set($key, $value)
  {
    $this->vars[$key] = $value;
  }

  /**
   * @param array $data
   */
  public function hydrate($data = [])
  {
    $this->vars = $data;
  }

  /**
   * @return mixed
   */
  public function build()
  {
    foreach ($this->vars as $key => $value) {
      $tagToReplace = "<$key>";
      $this->file = str_replace($tagToReplace, $value, $this->file);
    }
    return $this->file;
  }
}
