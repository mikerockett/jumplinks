<?php

/**
 * ProcessAdvancedRedirects - a ProcessWire Module by Mike Anthony
 * Manage permanent and temporay redirects. Supports wildcards.
 *
 * Intended for: ProcessWire 2.5.2+
 * Developed in: ProcessWire 2.5.13
 *
 * Copyright (c) 2015, Mike Anthony. All Rights Reserved.
 * Licence: MIT License - http://mikeanthony.mit-license.org/
 *
 * http://pw.foundrybusiness.co.za/advanced-redirects
 *
 */

class ProcessAdvancedRedirectsConfig extends ModuleConfig {

	public function getDefaults()
	{
		return array(
			'moduleDebug' => false,
			'defaultExtensions' => 'aspx asp cfm cgi fcgi dll html htm shtml shtm jhtml phtml xhtm rbml jspx jsp phps php4 php',
			'cleanPath' => 'fullClean',
			'statusCodes' => '200 301 302',
			'experimentEnhancedPathCleaning' => false,
		);
	}

	public function getInputfields()
	{
		$inputFields = parent::getInputfields();

		// Default Extensions
		$f = wire('modules')->get("InputfieldText");
		$f->attr('name+id', 'defaultExtensions');
		$f->attr('spellcheck', 'false');
		$f->label = $this->_('Default File Extensions');
		$f->description = $this->_("The file extensions below (each separated by a space) will be checked when an extension wilcard type is used in the Source Path of a redirect definition.\nWe've already provided a handy set of defaults (which will also be used of you empty this field), but feel free to tinker.");
		$f->notes = sprintf($this->_("Regex permitted, e.g.: %s\n**Important Note:** If you use them, avoid using literal spaces. Use **\s** instead.\n**[Use Default](#resetDefaultExtensions)** • **[Use Default Regex](#regexDefaultExtensions)** • [Learn more about Default Extensions](%s/config#default-extensions)"), 'aspx?|cfm|f?cgi|dll|s?html?|[jp]html|xhtm|rbml|jspx?|php[s4]?', $this->moduleInfo['href']);
		$f->columnWidth = 50;
		$f->collapsed = Inputfield::collapsedNever;

		$inputFields->add($f);

		// Path Cleaning
		$f = wire('modules')->get("InputfieldRadios");
		$f->attr('name+id', 'cleanPath');
		$recommendedValueSet = ($this->cleanPath === 'fullClean') ? ' ('.$this->_('recommended value set').')' : '';
		$f->label = sprintf($this->_('Path Cleaning%s'), $recommendedValueSet);
		$f->description = $this->_("When set to 'Full Clean', each wildcard in a Destination Path will be automatically cleaned, or 'slugged', so that it is lower-case, and uses hyphens as word separators.");
		$f->notes = sprintf($this->_("**Note:** It's highly recommended to keep this set to 'Full Clean' (or even 'Complete Clean'), unless you have a module installed that uses different path formats (such as TitleCase with underscores or hyphens). [Learn more about Path Cleaning](%s/config#path-cleaning)"), $this->moduleInfo['href']);
		$f->options = array(
			'completeClean' => $this->_('Complete Clean (cleans entire Destination Path) [don\'t use yet]'),
			'fullClean' => $this->_('Full Clean (default, recommended)'),
			'semiClean' => $this->_("Clean, but don't change case"),
			'noClean' => $this->_("Don't clean at all (not recommended)"),
		);
		$f->columnWidth = 50;
		$f->collapsed = Inputfield::collapsedNever;

		$inputFields->add($f);

		$s = wire('modules')->get('InputfieldFieldset');

		// Legacy Domain
		$message = ($this->legacyDomain && (trim($this->legacyDomain) !== ''))
		? " (currently set to: {$this->legacyDomain})" : '';

		$s->label = $this->_("Legacy Domain{$message}");
		$s->icon = 'globe';
		$s->collapsed = Inputfield::collapsedYes;

		$f = wire('modules')->get('InputfieldText');
		$f->attr('name+id', 'legacyDomain');
		$f->attr('spellcheck', 'false');
		$f->skipLabel = Inputfield::skipLabelHeader;
		$f->description = $this->_('Attempt any requested, unresolved Source Paths on a legacy domain/URL.');
		$f->notes = $this->_("Enter a full, valid domain/URL. **Source Path won't be cleaned upon redirect**.");
		$f->placeholder = $this->_('Examples: "http://legacy.domain.com/" or "http://domain.com/old/"');

		$f->columnWidth = 50;
		$f->collapsed = Inputfield::collapsedNever;

		$s->add($f);

		// Legacy Domain Status Codes
		$f = wire('modules')->get('InputfieldText');
		$f->attr('name+id', 'statusCodes');
		$f->attr('spellcheck', 'false');
		$f->skipLabel = Inputfield::skipLabelHeader;
		$f->description = $this->_('Only redirect if a request to it yields one of these HTTP status codes:');
		$f->notes = $this->_('Separate each code with a space. **[Use Default](#resetLegacyStatusCodes)**');
		$f->columnWidth = 50;
		$f->collapsed = Inputfield::collapsedNever;

		$s->add($f);

		$inputFields->add($s);

		// Experiments Set
		$s = wire('modules')->get('InputfieldFieldset');
		$s->label = $this->_('Experiments');
		$s->description = $this->_('Any experiments listed below are only experiments, and may not make it to the final v1.0 release. They may not be perfect, so your input on the forums is welcome.');
		$s->icon = 'flag';
		$s->collapsed = Inputfield::collapsedYes;

		// Enhanced Path Cleaning
		$f = wire('modules')->get('InputfieldCheckbox');
		$f->attr('name+id', 'experimentEnhancedPathCleaning');
		$f->label = $this->_('Enhanced Path Cleaning');
		$f->skipLabel = Inputfield::skipLabelHeader;
		$f->description = $this->_("To make things a little easier, the following experiment enhances path cleaning by means of breaking and hyphenating TitleCase wildcards, as well as those that contain abbreviations (ex: NASALaunch). Examples below.");
		$f->label2 = $this->_('Use Enhanced Path Cleaning');
		$f->notes = $this->_("**Examples:** 'EnvironmentStudy' would become 'environment-study' and 'NASALaunch' would become 'nasa-launch'\n**ALPHA Note:** When you hit 'Submit', the state of this option will save, but the box will remain unchecked. Identified bug, tested in PW 2.5.13.");
		//$f->checked = $this->experimentEnhancedPathCleaning;
		$f->collapsed = Inputfield::collapsedNever;

		$s->add($f);

		$inputFields->add($s);

		// Debug Mode
		$f = wire('modules')->get('InputfieldCheckbox');
		$f->attr('name+id', 'moduleDebug');
		$f->label = $this->_('Debug Mode');
		$f->icon = 'bug';
		$f->description = $this->_("If you run into any problems with your redirects, you can turn on debug mode. Once turned on, you'll be shown a scan log when a 404 Page Not Found is hit. That will give you an indication of what may be going wrong. If it doesn't, and you can't figure it out, then paste your log into the support thread on the forums.");
		$f->label2 = $this->_('Turn Debug Mode on');
		$f->notes = $this->_("**ALPHA Note:** When you hit 'Submit', the state of this option will save, but the box will remain unchecked. Identified bug, tested in PW 2.5.13.");
		//$f->checked = $this->moduleDebug;
		$f->collapsed = Inputfield::collapsedYes;

		$inputFields->add($f);

		return $inputFields;
	}

}
