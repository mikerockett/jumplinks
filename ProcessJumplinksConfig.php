<?php

/**
 * ProcessJumplinks - a ProcessWire Module by Mike Rockett
 * Manage permanent and temporary redirects. Uses named wildcards and mapping collections.
 *
 * Compatible with ProcessWire 2.6.1+
 *
 * Copyright (c) 2015, Mike Rockett. All Rights Reserved.
 * Licence: MIT License - http://mit-license.org/
 *
 * @see https://github.com/mikerockett/ProcessJumplinks/wiki [Documentation]
 * @see https://mods.pw/92 [Modules Directory Page]
 * @see https://processwire.com/talk/topic/8697-jumplinks/ [Support/Discussion Thread]
 * @see https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=L8F6FFYK6ENBQ [PayPal Donation]
 */

class ProcessJumplinksConfig extends ModuleConfig
{
	/**
	 * Documentation link
	 * @const string
	 */
	const HREF = 'https://github.com/mikerockett/ProcessJumplinks/wiki';

    /**
     * Given a fieldtype, create, populate, and return an Inputfield
     * @param  string $fieldNameId
     * @param  array  $meta
     * @return Inputfield
     */
    protected static function buildInputField($fieldNameId, $meta)
    {
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
     * Get default condifguration, automatically passed to input fields.
     * @return array
     */
    public function getDefaults()
    {
        return array(
            '_schemaVersion' => 1, // Initial schema
            'enhancedWildcardCleaning' => false,
            'legacyDomain' => '',
            'enable404Monitor' => false,
            'moduleDebug' => false,
            'redirectsImported' => false,
            'statusCodes' => '200 301 302',
            'wildcardCleaning' => 'fullClean',
        );
    }

    /**
     * Render input fields on config Page.
     * @return string
     */
    public function getInputFields()
    {
        // Inject assets
        wire('config')->scripts->add(wire('config')->urls->ProcessJumplinks . 'Assets/ProcessJumplinks.min.js');
        wire('config')->styles->add(wire('config')->urls->ProcessJumplinks . 'Assets/ProcessJumplinks.css');

        // Add JS config data
        wire('config')->js('pjModuleAdmin', true);
        wire('config')->js('pjOldRedirectsInstalled', wire('modules')->isInstalled('ProcessRedirects'));

        // Start inputfields
        $inputfields = parent::getInputfields();

        // Wildcard Cleaning Fieldset
        $fieldset = self::buildInputField('InputfieldFieldset', array(
            'label' => __('Wildcard Cleaning'),
            'collapsed' => Inputfield::collapsedYes,
        ));

        // Wildcard Cleaning
        $fieldset->add(self::buildInputField('InputfieldRadios', array(
            'name+id' => 'wildcardCleaning',
            'description' => __("When set to 'Full Clean', each wildcard in a destination path will be automatically cleaned, or 'slugged', so that it is lower-case, and uses hyphens as word separators."),
            'notes' => sprintf(__("**Note:** It's recommended that you keep this set to 'Full Clean', unless you have a module installed that uses different path formats (such as TitleCase with underscores or hyphens). **[Learn more about Wildcard Cleaning](%s/Configuration#wildcard-cleaning)**"), self::HREF),
            'options' => array(
                'fullClean' => __('Full Clean (default, recommended)'),
                'semiClean' => __("Clean, but don't change case"),
                'noClean' => __("Don't clean at all (not recommended)"),
            ),
            'columnWidth' => 50,
            'collapsed' => Inputfield::collapsedNever,
            'skipLabel' => Inputfield::skipLabelHeader,
        )));

        // Enhanced Wildcard Cleaning
        $fieldset->add(self::buildInputField('InputfieldCheckbox', array(
            'name+id' => 'enhancedWildcardCleaning',
            'label' => __('Enhanced Wildcard Cleaning'),
            'description' => __("When enabled, wildcard cleaning goes a step further by means of breaking and hyphenating TitleCase wildcards, as well as those that contain abbreviations (ex: NASALaunch). Examples below."),
            'label2' => __('Use Enhanced Wildcard Cleaning'),
            'notes' => __("**Examples:** 'EnvironmentStudy' would become 'environment-study' and 'NASALaunch' would become 'nasa-launch'.\n**Note:** This feature only works when Wildcard Cleaning is enabled."),
            'columnWidth' => 50,
            'collapsed' => Inputfield::collapsedNever,
            'autocheck' => true,
        )));

        $inputfields->add($fieldset);

        // Legacy Domain Fieldset
        $fieldset = self::buildInputField('InputfieldFieldset', array(
            'label' => __('Legacy Domain'),
            'description' => sprintf(__('Only use this if you are performing a slow migration to ProcessWire, and would still like your visitors to access old content moved to a new location, like a subdomain or folder, for example. [Learn more about how this feature works](%s/Configuration#legacy-domain).'), self::HREF),
            'collapsed' => Inputfield::collapsedYes,
        ));

        // Legacy Domain Name
        $fieldset->add(self::buildInputField('InputfieldText', array(
            'name+id' => 'legacyDomain',
            'columnWidth' => 50,
            'description' => __('Attempt any requested, unresolved Source paths on a legacy domain/URL.'),
            'notes' => __("Enter a *full*, valid domain/URL. **Source Path won't be cleaned upon redirect**."),
            'placeholder' => __('Examples: "http://legacy.domain.com/" or "http://domain.com/old/"'),
            'collapsed' => Inputfield::collapsedNever,
            'skipLabel' => Inputfield::skipLabelHeader,
            'spellcheck' => 'false',
        )));

        // Legacy Domain Status Codes
        $fieldset->add(self::buildInputField('InputfieldText', array(
            'name+id' => 'statusCodes',
            'columnWidth' => 50,
            'description' => __('Only redirect if a request to it yields one of these HTTP status codes:'),
            'notes' => __("Separate each code with a space. **[Use Default](#resetLegacyStatusCodes)**"),
            'collapsed' => Inputfield::collapsedNever,
            'skipLabel' => Inputfield::skipLabelHeader,
            'spellcheck' => 'false',
        )));

        $inputfields->add($fieldset);

        // Log Not Found Hits
        $inputfields->add(self::buildInputField('InputfieldCheckbox', array(
            'name+id' => 'enable404Monitor',
            'label' => __('Enable 404 Monitor'),
            'description' => __("If you'd like to monitor and log 404 hits so that you can later create jumplinks for them, check the box below."),
            'label2' => __('Log 404 hits to the database'),
            'notes' => __("This log will be displayed on the Jumplinks setup page in a separate tab (limited to the last 100).\n**Note:** Turning this off will not delete any existing records from the database."),
            'collapsed' => Inputfield::collapsedBlank,
            'autocheck' => true,
        )));

        // Debug Mode
        $inputfields->add(self::buildInputField('InputfieldCheckbox', array(
            'name+id' => 'moduleDebug',
            'label' => __('Debug Mode'),
            'description' => __("If you run into any problems with your jumplinks, you can turn on debug mode. Once turned on, you'll be shown a scan log when a 404 Page Not Found is hit. That will give you an indication of what may be going wrong. If it doesn't, and you can't figure it out, then paste your log into the support thread on the forums."),
            'label2' => __('Turn debug mode on'),
            'notes' => __("**Notes:** Hits won't be affected when debug mode is turned on. Also, only those that have permission to manage jumplinks will be shown the debug logs."),
            'collapsed' => Inputfield::collapsedBlank,
            'autocheck' => true,
        )));

        return $inputfields;
    }
}
