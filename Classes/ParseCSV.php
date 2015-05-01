<?php

/**
 *  ParseCSV 0.4.3-beta
 *  https://github.com/parsecsv/parsecsv-for-php
 *
 *  [Mike Rockett] Removed un-necessary components for ProcessJumplinks
 *
 *  @license MIT
 *
 *  Copyright (c) 2014 Jim Myhrberg
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in
 *  all copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

class ParseCSV {

	public $auto_depth = 15;
	public $auto_non_chars = "a-zA-Z0-9\n\r";
	public $auto_preferred = ",;\t.:|";
	public $convert_encoding = false;
	public $data = array();
	public $delimiter = ',';
	public $enclose_all = false;
	public $enclosure = '"';
	public $error = 0;
	public $error_info = array();
	public $fields = array();
	public $file;
	public $file_data;
	public $heading = true;
	public $input_encoding = 'ISO-8859-1';
	public $keep_file_data = false;
	public $limit = null;
	public $linefeed = "\r";
	public $offset = null;
	public $sort_by = null;
	public $sort_reverse = false;
	public $sort_type = null;
	public $titles = array();

	public function __construct($input = null, $offset = null, $limit = null, $keep_file_data = null) {
		if (!is_null($offset)) {
			$this->offset = $offset;
		}
		if (!is_null($limit)) {
			$this->limit = $limit;
		}
		if (!is_null($keep_file_data)) {
			$this->keep_file_data = $keep_file_data;
		}
		if (!empty($input)) {
			$this->parse($input);
		}
	}

	public function parse($input = null, $offset = null, $limit = null) {
		if (is_null($input)) {
			$input = $this->file;
		}
		if (!empty($input)) {
			if (!is_null($offset)) {
				$this->offset = $offset;
			}
			if (!is_null($limit)) {
				$this->limit = $limit;
			}
			if (is_readable($input)) {
				$this->data = $this->parse_file($input);
			} else {
				$this->file_data = &$input;
				$this->data = $this->parse_string();
			}
			if ($this->data === false) {
				return false;
			}
		}
		return true;
	}

	public function encoding($input = null, $output = null) {
		$this->convert_encoding = true;
		if (!is_null($input)) {
			$this->input_encoding = $input;
		}
		if (!is_null($output)) {
			$this->output_encoding = $output;
		}
	}

	public function auto($file = null, $parse = true, $search_depth = null, $preferred = null, $enclosure = null) {
		if (is_null($file)) {
			$file = $this->file;
		}
		if (empty($search_depth)) {
			$search_depth = $this->auto_depth;
		}
		if (is_null($enclosure)) {
			$enclosure = $this->enclosure;
		}
		if (is_null($preferred)) {
			$preferred = $this->auto_preferred;
		}
		if (empty($this->file_data)) {
			if ($this->_check_data($file)) {
				$data = &$this->file_data;
			} else {
				return false;
			}
		} else {
			$data = &$this->file_data;
		}
		$chars = array();
		$strlen = strlen($data);
		$enclosed = false;
		$n = 1;
		$to_end = true;
		for ($i = 0; $i < $strlen; ++$i) {
			$ch = $data{$i};
			$nch = (isset($data{$i + 1})) ? $data{$i + 1} : false;
			$pch = (isset($data{$i - 1})) ? $data{$i - 1} : false;

			if ($ch == $enclosure) {
				if (!$enclosed || $nch != $enclosure) {
					$enclosed = ($enclosed) ? false : true;
				} elseif ($enclosed) {
					++$i;
				}
			} elseif (($ch == "\n" && $pch != "\r" || $ch == "\r") && !$enclosed) {
				if ($n >= $search_depth) {
					$strlen = 0;
					$to_end = false;
				} else {
					++$n;
				}
			} elseif (!$enclosed) {
				if (!preg_match('/[' . preg_quote($this->auto_non_chars, '/') . ']/i', $ch)) {
					if (!isset($chars[$ch][$n])) {
						$chars[$ch][$n] = 1;
					} else {
						++$chars[$ch][$n];
					}
				}
			}
		}
		$depth = ($to_end) ? $n - 1 : $n;
		$filtered = array();
		foreach ($chars as $char => $value) {
			if ($match = $this->_check_count($char, $value, $depth, $preferred)) {
				$filtered[$match] = $char;
			}
		}
		ksort($filtered);
		$this->delimiter = reset($filtered);
		if ($parse) {
			$this->data = $this->parse_string();
		}
		return $this->delimiter;
	}

	public function parse_file($file = null) {
		if (is_null($file)) {
			$file = $this->file;
		}
		if (empty($this->file_data)) {
			$this->load_data($file);
		}
		return (!empty($this->file_data)) ? $this->parse_string() : false;
	}

	public function parse_string($data = null) {
		if (empty($data)) {
			if ($this->_check_data()) {
				$data = &$this->file_data;
			} else {
				return false;
			}
		}
		$white_spaces = str_replace($this->delimiter, '', " \t\x0B\0");
		$rows = array();
		$row = array();
		$row_count = 0;
		$current = '';
		$head = (!empty($this->fields)) ? $this->fields : array();
		$col = 0;
		$enclosed = false;
		$was_enclosed = false;
		$strlen = strlen($data);
		$lch = $data{$strlen - 1};
		if ($lch != "\n" && $lch != "\r") {
			++$strlen;
		}
		for ($i = 0; $i < $strlen; ++$i) {
			$ch = (isset($data{$i})) ? $data{$i} : false;
			$nch = (isset($data{$i + 1})) ? $data{$i + 1} : false;
			$pch = (isset($data{$i - 1})) ? $data{$i - 1} : false;
			if ($ch == $this->enclosure) {
				if (!$enclosed) {
					if (ltrim($current, $white_spaces) == '') {
						$enclosed = true;
						$was_enclosed = true;
					} else {
						$this->error = 2;
						$error_row = count($rows) + 1;
						$error_col = $col + 1;
						if (!isset($this->error_info[$error_row . '-' . $error_col])) {
							$this->error_info[$error_row . '-' . $error_col] = array(
								'type' => 2,
								'info' => 'Syntax error found on row ' . $error_row . '. Non-enclosed fields can not contain double-quotes.',
								'row' => $error_row,
								'field' => $error_col,
								'field_name' => (!empty($head[$col])) ? $head[$col] : null,
							);
						}
						$current .= $ch;
					}
				} elseif ($nch == $this->enclosure) {
					$current .= $ch;
					++$i;
				} elseif ($nch != $this->delimiter && $nch != "\r" && $nch != "\n") {
					for ($x = ($i + 1);isset($data{$x}) && ltrim($data{$x}, $white_spaces) == ''; ++$x) {}
					if ($data{$x} == $this->delimiter) {
						$enclosed = false;
						$i = $x;
					} else {
						if ($this->error < 1) {
							$this->error = 1;
						}
						$error_row = count($rows) + 1;
						$error_col = $col + 1;
						if (!isset($this->error_info[$error_row . '-' . $error_col])) {
							$this->error_info[$error_row . '-' . $error_col] = array(
								'type' => 1,
								'info' =>
								'Syntax error found on row ' . (count($rows) + 1) . '. ' .
								'A single double-quote was found within an enclosed string. ' .
								'Enclosed double-quotes must be escaped with a second double-quote.',
								'row' => count($rows) + 1,
								'field' => $col + 1,
								'field_name' => (!empty($head[$col])) ? $head[$col] : null,
							);
						}
						$current .= $ch;
						$enclosed = false;
					}
				} else {
					$enclosed = false;
				}
			} elseif (($ch == $this->delimiter || $ch == "\n" || $ch == "\r" || $ch === false) && !$enclosed) {
				$key = (!empty($head[$col])) ? $head[$col] : $col;
				$row[$key] = ($was_enclosed) ? $current : trim($current);
				$current = '';
				$was_enclosed = false;
				++$col;
				if ($ch == "\n" || $ch == "\r" || $ch === false) {
					if ($this->_validate_offset($row_count)) {
						if ($this->heading && empty($head)) {
							$head = $row;
						} elseif (empty($this->fields) || (!empty($this->fields) && (($this->heading && $row_count > 0) || !$this->heading))) {
							if (!empty($this->sort_by) && !empty($row[$this->sort_by])) {
								if (isset($rows[$row[$this->sort_by]])) {
									$rows[$row[$this->sort_by] . '_0'] = &$rows[$row[$this->sort_by]];
									unset($rows[$row[$this->sort_by]]);
									for ($sn = 1;isset($rows[$row[$this->sort_by] . '_' . $sn]); ++$sn) {}
									$rows[$row[$this->sort_by] . '_' . $sn] = $row;
								} else {
									$rows[$row[$this->sort_by]] = $row;
								}
							} else {
								$rows[] = $row;
							}
						}
					}
					$row = array();
					$col = 0;
					++$row_count;
					if ($this->sort_by === null && $this->limit !== null && count($rows) == $this->limit) {
						$i = $strlen;
					}
					if ($ch == "\r" && $nch == "\n") {
						++$i;
					}
				}
			} else {
				$current .= $ch;
			}
		}
		$this->titles = $head;
		if (!empty($this->sort_by)) {
			$sort_type = SORT_REGULAR;
			if ($this->sort_type == 'numeric') {
				$sort_type = SORT_NUMERIC;
			} elseif ($this->sort_type == 'string') {
				$sort_type = SORT_STRING;
			}
			($this->sort_reverse) ? krsort($rows, $sort_type) : ksort($rows, $sort_type);

			if ($this->offset !== null || $this->limit !== null) {
				$rows = array_slice($rows, ($this->offset === null ? 0 : $this->offset), $this->limit, true);
			}
		}
		if (!$this->keep_file_data) {
			$this->file_data = null;
		}

		return $rows;
	}

	public function load_data($input = null) {
		$data = null;
		$file = null;
		if (is_null($input)) {
			$file = $this->file;
		} elseif (file_exists($input)) {
			$file = $input;
		} else {
			$data = $input;
		}
		if (!empty($data) || $data = $this->_rfile($file)) {
			if ($this->file != $file) {
				$this->file = $file;
			}
			if (preg_match('/\.php$/i', $file) && preg_match('/<\?.*?\?>(.*)/ims', $data, $strip)) {
				$data = ltrim($strip[1]);
			}
			if ($this->convert_encoding) {
				$data = iconv($this->input_encoding, $this->output_encoding, $data);
			}
			if (substr($data, -1) != "\n") {
				$data .= "\n";
			}
			$this->file_data = &$data;
			return true;
		}
		return false;
	}

	protected function _validate_offset($current_row) {
		if ($this->sort_by === null && $this->offset !== null && $current_row < $this->offset) {
			return false;
		}
		return true;
	}

	protected function _check_data($file = null) {
		if (empty($this->file_data)) {
			if (is_null($file)) {
				$file = $this->file;
			}
			return $this->load_data($file);
		}

		return true;
	}

	protected function _check_count($char, $array, $depth, $preferred) {
		if ($depth == count($array)) {
			$first = null;
			$equal = null;
			$almost = false;
			foreach ($array as $key => $value) {
				if ($first == null) {
					$first = $value;
				} elseif ($value == $first && $equal !== false) {
					$equal = true;
				} elseif ($value == $first + 1 && $equal !== false) {
					$equal = true;
					$almost = true;
				} else {
					$equal = false;
				}
			}
			if ($equal) {
				$match = ($almost) ? 2 : 1;
				$pref = strpos($preferred, $char);
				$pref = ($pref !== false) ? str_pad($pref, 3, '0', STR_PAD_LEFT) : '999';
				return $pref . $match . '.' . (99999 - str_pad($first, 5, '0', STR_PAD_LEFT));
			} else {
				return false;
			}
		}
	}

	protected function _rfile($file = null) {
		if (is_readable($file)) {
			if (!($fh = fopen($file, 'r'))) {
				return false;
			}
			$data = fread($fh, filesize($file));
			fclose($fh);
			return $data;
		}
		return false;
	}
}