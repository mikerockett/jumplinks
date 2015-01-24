<?php

/**
 * ProcessJumplinks - a ProcessWire Module by Mike Anthony
 * Manage permanent and temporary redirects. Supports wildcards.
 *
 * Requires ProcessWire 2.5.3
 *
 * Copyright (c) 2015, Mike Anthony. All Rights Reserved.
 * Licence: MIT License - http://mikeanthony.mit-license.org/
 *
 * https://github.com/mike-anthony/ProcessJumplinks/wiki
 *
 */

class Blueprint {

	protected $file;
	protected $vars = array();

	/**
	 * @param string $file
	 */
	public function __construct($file) {
		$this->file = $this->findBlueprint("/../Blueprints/$file");
	}

	/**
	 * @param string $file
	 */
	protected function findBlueprint($file) {
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
	public function set($key, $value) {
		$this->vars[$key] = $value;
	}

	/**
	 * @param array $data
	 */
	public function hydrate($data = array()) {
		$this->vars = $data;
	}

	/**
	 * @return mixed
	 */
	public function build() {
		foreach ($this->vars as $key => $value) {
			$tagToReplace = "<$key>";
			$this->file = str_replace($tagToReplace, $value, $this->file);
		}
		return $this->file;
	}
}