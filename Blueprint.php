<?php

class Blueprint {

	protected $file;
	protected $vars = array();

	public function __construct($file)
	{
		$this->file = $this->findBlueprint("/Blueprints/$file");
	}

	protected function findBlueprint($file)
	{
		$extensions = array('sql', 'html');
		if (file_exists(__DIR__ . $file))
			return file_get_contents(__DIR__ . $file);
		foreach ($extensions as $extension) {
			$withExt = "{$file}.{$extension}";
			if (file_exists(__DIR__ . $withExt))
				return file_get_contents(__DIR__ . $withExt);
		}
		throw new Exception('Blueprint not found:' . __DIR__ . $file);
	}

	public function set($key, $value)
	{
		$this->vars[$key] = $value;
	}

	public function hydrate($data = array())
	{
		$this->vars = $data;
	}

	public function build()
	{
		foreach ($this->vars as $key => $value) {
			$tagToReplace = "<$key>";
			$this->file = str_replace($tagToReplace, $value, $this->file);
		}
		return $this->file;
	}
}