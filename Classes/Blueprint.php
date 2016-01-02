<?php

/**
 * Jumplinks for ProcessWire
 * Manage permanent and temporary redirects. Uses named wildcards and mapping collections.
 *
 * Process module for ProcessWire 2.6.1+
 *
 * @author Mike Rockett
 * @copyright (c) 2015, Mike Rockett. All Rights Reserved.
 * @license MIT License - http://mit-license.org/
 *
 * @see Documentation:     http://rockett.pw/jumplinks
 * @see Modules Directory: https://mods.pw/92
 * @see Forum Thred:       https://processwire.com/talk/topic/8697-jumplinks/
 * @see PayPal Donation:   https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=L8F6FFYK6ENBQ
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

    protected $file;
    protected $vars = array();

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
        $extensions = array('sql', 'html');

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
    public function hydrate($data = array())
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
