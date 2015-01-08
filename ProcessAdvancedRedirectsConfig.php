<?php

/**
 * ProcessAdvancedRedirects - a ProcessWire Module by Mike Anthony
 * Manage permanent and temporay redirects. Supports wildcards.
 *
 * Intended for: ProcessWire 2.6-dev
 * Developed in: ProcessWire 2.5.13
 *
 * Copyright (c) 2015, Mike Anthony. All Rights Reserved.
 * Licence: MIT License - http://mikeanthony.mit-license.org/
 *
 * http://pw.foundrybusiness.co.za/advanced-redirects
 *
 */

class ProcessAdvancedRedirectsConfig extends ModuleConfig {

	const HREF = "http://pw.foundrybusiness.co.za/advanced-redirects";

	const DEFAULT_EXTENSIONS = "defaultExtensions";
	const CLEAN_PATH = "cleanPath";
	const LEGACY_DOMAIN = "legacyDomain";
	const STATUS_CODES = "statusCodes";
	const EXPERIMENT_EPC = "experimentEnhancedPathCleaning";
	const MODULE_DEBUG = "moduleDebug";

	/**
	 * Get the default configuration details
	 * @return array
	 */
	public function getDefaults() {
		return array(
			'moduleDebug' => false,
			'defaultExtensions' => 'aspx asp cfm cgi fcgi dll html htm shtml shtm jhtml phtml xhtm xhtml rbml jspx jsp phps php4 php',
			'cleanPath' => 'fullClean',
			'statusCodes' => '200 301 302',
			'experimentEnhancedPathCleaning' => false,
		);
	}

	/**
	 * Given a fieldtype, create, fill, and return an Inputfield
	 * @param  string $fieldNameId
	 * @param  array  $meta
	 * @return Inputfield
	 */
	protected function buildField($fieldNameId, $meta) {
		$field = wire('modules')->get($fieldNameId);
		foreach ($meta as $metaNames => $metaInfo) {
			$metaNames = explode('+', $metaNames);
			foreach ($metaNames as $metaName) {
				$field->$metaName = $metaInfo;
			}
		}

		return $field;
	}

	/**
	 * Compile Inputfields for module config (admin)
	 * @return InputfieldWrapper
	 */
	public function getInputfields() {
		$inputfields = parent::getInputfields();

		// Default File Extensions
		$inputfields->add($this->buildField('InputfieldText', array(
			'name+id' => self::DEFAULT_EXTENSIONS,
			'label' => $this->_('Default File Extensions'),
			'description' => $this->_("The file extensions below (each separated by a space) will be checked when an extension wilcard type is used in the Source Path of a redirect definition.\nWe've already provided a handy set of defaults (which will also be used of you empty this field), but feel free to tinker."),
			'notes' => sprintf($this->_("Regex permitted, e.g.: %s\n**Important Note:** If you use them, avoid using literal spaces. Use **\s** instead.\n**[Use Default](#resetDefaultExtensions)** • **[Use Default Regex](#regexDefaultExtensions)** • [Learn more about Default Extensions](%s/config#default-extensions)"), 'aspx?|cfm|f?cgi|dll|s?html?|[jp]html|xhtml?|rbml|jspx?|php[s4]?', self::HREF),
			'columnWidth' => 50,
			'collapsed' => Inputfield::collapsedNever,
			'spellcheck' => 'false',
		)));

		// Path Cleaning
		$inputfields->add($this->buildField('InputfieldRadios', array(
			'name+id' => self::CLEAN_PATH,
			'label' => $this->_('Path Cleaning'),
			'description' => $this->_("When set to 'Full Clean', each wildcard in a Destination Path will be automatically cleaned, or 'slugged', so that it is lower-case, and uses hyphens as word separators."),
			'notes' => sprintf($this->_("**Note:** It's highly recommended to keep this set to 'Full Clean', unless you have a module installed that uses different path formats (such as TitleCase with underscores or hyphens). [Learn more about Path Cleaning](%s/config#path-cleaning)"), self::HREF),
			'options' => array(
				//'completeClean' => $this->_('Complete Clean (cleans entire Destination Path) [don\'t use yet]'),
				'fullClean' => $this->_('Full Clean (default, recommended)'),
				'semiClean' => $this->_("Clean, but don't change case"),
				'noClean' => $this->_("Don't clean at all (not recommended)"),
			),
			'columnWidth' => 50,
			'collapsed' => Inputfield::collapsedNever,
		)));

		// Legacy Domain Fieldset
		$fieldset = $this->buildField('InputfieldFieldset', array(
			'label' => $this->_('Legacy Domain'),
			'icon' => 'globe',
			'collapsed' => Inputfield::collapsedYes,
		));

		// Legacy Domain Name
		$fieldset->add($this->buildField('InputfieldText', array(
			'name+id' => self::LEGACY_DOMAIN,
			'columnWidth' => 50,
			'description' => $this->_('Attempt any requested, unresolved Source Paths on a legacy domain/URL.'),
			'notes' => $this->_("Enter a full, valid domain/URL. **Source Path won't be cleaned upon redirect**."),
			'placeholder' => $this->_('Examples: "http://legacy.domain.com/" or "http://domain.com/old/"'),
			'collapsed' => Inputfield::collapsedNever,
			'skipLabel' => Inputfield::skipLabelHeader,
			'spellcheck' => 'false',
		)));

		// Legacy Domain Status Codes
		$fieldset->add($this->buildField('InputfieldText', array(
			'name+id' => self::STATUS_CODES,
			'columnWidth' => 50,
			'description' => $this->_('Only redirect if a request to it yields one of these HTTP status codes:'),
			'notes' => $this->_("Separate each code with a space. **[Use Default](#resetLegacyStatusCodes)**"),
			'collapsed' => Inputfield::collapsedNever,
			'skipLabel' => Inputfield::skipLabelHeader,
			'spellcheck' => 'false',
		)));

		$inputfields->add($fieldset);

		// Experiments Fieldset
		$fieldset = $this->buildField('InputfieldFieldset', array(
			'label' => $this->_('Experiments'),
			'icon' => 'flag',
			'description' => $this->_('Any experiments listed below are only experiments, and may not make it to the final v1.0 release. They may not be perfect, so your input on the forums is welcome.'),
			'collapsed' => Inputfield::collapsedYes,
		));

		// Enhanced Path Cleaning
		$fieldset->add($this->buildField('InputfieldCheckbox', array(
			'name+id' => self::EXPERIMENT_EPC,
			'label' => $this->_('Enhanced Path Cleaning'),
			'description' => $this->_("To make things a little easier, the following experiment enhances path cleaning by means of breaking and hyphenating TitleCase wildcards, as well as those that contain abbreviations (ex: NASALaunch). Examples below."),
			'label2' => $this->_('Use Enhanced Path Cleaning'),
			'notes' => $this->_("**Examples:** 'EnvironmentStudy' would become 'environment-study' and 'NASALaunch' would become 'nasa-launch'"),
			'collapsed' => Inputfield::collapsedNever,
			'autocheck' => true,
		)));

		$inputfields->add($fieldset);

		// Debug Mode
		$inputfields->add($this->buildField('InputfieldCheckbox', array(
			'name+id' => self::MODULE_DEBUG,
			'label' => $this->_('Debug Mode'),
			'icon' => 'bug',
			'description' => $this->_("If you run into any problems with your redirects, you can turn on debug mode. Once turned on, you'll be shown a scan log when a 404 Page Not Found is hit. That will give you an indication of what may be going wrong. If it doesn't, and you can't figure it out, then paste your log into the support thread on the forums."),
			'label2' => $this->_('Turn Debug Mode on'),
			'collapsed' => Inputfield::collapsedYes,
			'autocheck' => true,
		)));

		return $inputfields;
	}

}
