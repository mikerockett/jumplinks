<?php

/**
 * ProcessAdvancedRedirects - a ProcessWire Module by Mike Anthony
 * Manage permanent and temporary redirects. Supports wildcards.
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

	/** Schema version for this release */
	const SCHEMA_VERSION = 1;

	const HREF = "http://pw.foundrybusiness.co.za/advanced-redirects";

	const WILDCARD_CLEANING = "wildcardCleaning";
	const LEGACY_DOMAIN = "legacyDomain";
	const STATUS_CODES = "statusCodes";
	const ENHANCED_WILDCARD_CLEANING = "enhancedWildcardCleaning";
	const MODULE_DEBUG = "moduleDebug";

	/**
	 * Get the default configuration details
	 * @return array
	 */
	public function getDefaults() {
		return array(
			'schemaVersion' => 1,
			self::MODULE_DEBUG => false,
			self::WILDCARD_CLEANING => 'fullClean',
			self::STATUS_CODES => '200 301 302',
			self::ENHANCED_WILDCARD_CLEANING => false,
		);
	}

	/**
	 * Given a fieldtype, create, fill, and return an Inputfield
	 * @param  string $fieldNameId
	 * @param  array  $meta
	 * @return Inputfield
	 */
	protected function buildInputField($fieldNameId, $meta) {
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
		$this->config->js('parModuleAdmin', true);
		$inputfields = parent::getInputfields();

		// Wildcard Cleaning Fieldset
		$fieldset = $this->buildInputField('InputfieldFieldset', array(
			'label' => $this->_('Wildcard Cleaning'),
			'collapsed' => Inputfield::collapsedNever,
		));

		// Wildcard Cleaning
		$fieldset->add($this->buildInputField('InputfieldRadios', array(
			'name+id' => self::WILDCARD_CLEANING,
			'description' => $this->_("When set to 'Full Clean', each wildcard in a Destination Path will be automatically cleaned, or 'slugged', so that it is lower-case, and uses hyphens as word separators."),
			'notes' => sprintf($this->_("**Note:** It's highly recommended to keep this set to 'Full Clean', unless you have a module installed that uses different path formats (such as TitleCase with underscores or hyphens). [Learn more about Path Cleaning](%s/config#path-cleaning)"), self::HREF),
			'options' => array(
				'fullClean' => $this->_('Full Clean (default, recommended)'),
				'semiClean' => $this->_("Clean, but don't change case"),
				'noClean' => $this->_("Don't clean at all (not recommended)"),
			),
			'columnWidth' => 50,
			'collapsed' => Inputfield::collapsedNever,
			'skipLabel' => Inputfield::skipLabelHeader,
		)));

		// Enhanced Wildcard Cleaning
		$fieldset->add($this->buildInputField('InputfieldCheckbox', array(
			'name+id' => self::ENHANCED_WILDCARD_CLEANING,
			'label' => $this->_('Enhanced Widcard Cleaning'),
			'description' => $this->_("The following experiment enhances wildcard cleaning by means of breaking and hyphenating TitleCase wildcards, as well as those that contain abbreviations (ex: NASALaunch). Examples below."),
			'label2' => $this->_('Use Enhanced Wildcard Cleaning'),
			'notes' => $this->_("**Examples:** 'EnvironmentStudy' would become 'environment-study' and 'NASALaunch' would become 'nasa-launch'\n**Note:** This is only an experiment, and may not work with some paths. It may be removed from the final 1.0.0 release."),
			'columnWidth' => 50,
			'collapsed' => Inputfield::collapsedNever,
			'autocheck' => true,
		)));

		$inputfields->add($fieldset);

		// Legacy Domain Fieldset
		$fieldset = $this->buildInputField('InputfieldFieldset', array(
			'label' => $this->_('Legacy Domain'),
			'collapsed' => Inputfield::collapsedYes,
		));

		// Legacy Domain Name
		$fieldset->add($this->buildInputField('InputfieldText', array(
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
		$fieldset->add($this->buildInputField('InputfieldText', array(
			'name+id' => self::STATUS_CODES,
			'columnWidth' => 50,
			'description' => $this->_('Only redirect if a request to it yields one of these HTTP status codes:'),
			'notes' => $this->_("Separate each code with a space. **[Use Default](#resetLegacyStatusCodes)**"),
			'collapsed' => Inputfield::collapsedNever,
			'skipLabel' => Inputfield::skipLabelHeader,
			'spellcheck' => 'false',
		)));

		$inputfields->add($fieldset);

		// Debug Mode
		$inputfields->add($this->buildInputField('InputfieldCheckbox', array(
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
