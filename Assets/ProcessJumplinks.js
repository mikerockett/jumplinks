/**--
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

$(function() {
    'use strict';

    var $t = $("form#pjTabs");

    // Function: Get URL arg/param
    var urlParam = function(param) {
        var paramName,
            url = window.location.search.substring(1),
            vars = url.split("&");
        for (i = 0; i < vars.length; i++)
            if (paramName = vars[i].split("="), paramName[0] == param)
                return paramName[1];
    };

    // Function: Show error dialog
    var errorDialog = function(errors) {
        var errorString = '';
        errors.map(function(message) {
            errorString += '<li style="padding: 9px 24px; border-bottom: 1px solid #ececec">' + message + '</li>';
        });
        $('body').css('overflow', 'hidden');
        $('<div id="validationMessage"></div>')
            .html('<ol>' + errorString + '</ol>')
            .appendTo('body')
            .dialog({
                draggable: false,
                modal: true,
                position: ['middle', 125],
                resizable: false,
                title: 'Errors',
                width: 550,
                buttons: {
                    'Close': function() {
                        $(this).dialog('close');
                    }
                },
                close: function() {
                    $(this).dialog('destroy').remove();
                    $('body').css('overflow', 'auto');
                }
            });
    }

    // Check if we're on the module's admin page
    config.pjAdmin && function() {

        // Setup WireTabs on the module's admin page
        $t.find("script").remove();
        $t.WireTabs({
            items: $("#pjTabs > .Inputfields > .InputfieldWrapper"),
            id: "ProcessJumplinksTabs",
            rememberTabs: true,
            skipRememberTabIDs: ['log', 'import'],
        });

        // Highlight jumplinks that haven't been hit in over 30 days
        var $deleteSpan = $('.AdminDataTable.jumplinks td > span#staleJumplink', $t);
        var $deleteSpanRowParent = $deleteSpan.parent().parent()
        $deleteSpanRowParent.find('td').css('background-color', '#fff3f3');
        $deleteSpanRowParent.find('td:first > a').css('color', '#db4747');

    }();

    // Check if we're working with a jumplink
    config.pjEntity && function() {

        $('#destinationPage').on('pageSelected', function(a, b) {
            if (b.id > 0) {
                $('input#destinationUriUrl').val('page:' + b.id)
                $('#destinationPageAuto').val(b.id);
                $('#destinationPageAuto_input').attr('data-selectedlabel', b.title).val(b.title);
            }
        });
        $('#destinationPageAuto').on('change', function(b) {
            if (b.currentTarget.value) {
                $('input#destinationUriUrl').val('page:' + b.currentTarget.value);
                $('#destinationPage').val(b.currentTarget.value).parent().find('.label_title, .PageListSelectName').text($('#destinationPageAuto_input').attr('data-selectedlabel'));
            }
        });

        $('button#saveJumplink').on('click', function() {

            var $values = {
                    sourcePath: $('input#sourcePath').val(),
                    destinationUriUrl: $('input#destinationUriUrl').val(),
                    dateStart: $('#dateStart').val(),
                    dateEnd: $('#dateEnd').val(),
                },
                errors = [],
                errorString = '';

            if ($values.sourcePath.trim().length === 0)
                errors.push("Source can't be empty...");

            else {
                var isAbsolute = new RegExp('^(?:[a-z]+:)?//', 'i');
                if (isAbsolute.test($values.sourcePath))
                    errors.push('Source cannot be an absolute URL. It needs to be relative to the root of your installation, without the leading forward-slash.');
            }

            if ($values.destinationUriUrl.trim().length === 0)
                errors.push("You need to select a Destination...");

            if ($values.dateStart && $values.dateEnd)
                if (new Date($values.dateStart).getTime() >= new Date($values.dateEnd).getTime())
                    errors.push("End Date/Time cannot occur on or before Start Date/Time.");

            if (errors.length) {
                errorDialog(errors);
                return false;
            }
        })

    }();

    // Check if we're working with a collection
    config.pjCollection && function() {

        $('button#installMappingCollection').on('click', function() {

            var $values = {
                    name: $('input#collectionName').val(),
                    data: $('textarea#collectionData').val(),
                },
                errors = [],
                errorString = '';

            if (!$values.name)
                errors.push("Collection Name can't be empty...");

            else if ($values.name.trim().length < 3)
                errors.push("Collection Name should be at least 3 characters");

            if (!$values.data)
                errors.push("Collection Data can't be empty...");

            else if ($values.data.trim().length < 6)
                errors.push("Collection Data should be at least 6 characters");

            if (errors.length) {
                errorDialog(errors);
                return false;
            }
        })

    }();

    // Check if we're importing from CSV
    config.pjImport && function() {

        $('button#doImport').on('click', function() {

            var $values = {
                    data: $('textarea#csvData').val(),
                },
                errors = [],
                errorString = '';

            if (config.pjImportCSVData)
                if (!$values.data)
                    errors.push("CSV data can't be empty...");

            if (errors.length) {
                errorDialog(errors);
                return false;
            }

        });

    }();

    // Detect if we're on the module's config page
    config.pjModuleAdmin && function() {

        // Set initial vars for module's config page
        var classInputfieldTask = "InputfieldTask";
        var defaults = {
            statusCodes: "200 301 302",
        }

        // Set action for 'HTTP Status Codes for Legacy Domain' Restore Defaults link-button
        $("a[href=#resetLegacyStatusCodes]")
            .removeAttr("target")
            .addClass(classInputfieldTask)
            .on("click", function(event) {
                $("input#statusCodes").val(defaults.statusCodes), event.preventDefault();
            });

        // Set button vars for module's config page

        var addButton = function(id, text, relHref) {
            relHref = typeof relHref !== 'undefined' ? relHref : '';
            var $button = $("<button/>").attr('id', id).addClass("ui-button ui-widget ui-state-default ui-priority-secondary")
                .on('click', function(event) {
                    event.preventDefault();
                    window.location = config.pjAdminPageUrl + relHref;
                }).appendTo(".Inputfield_submit_save_module .InputfieldContent");
            $("<span/>").addClass("ui-button-text").text(text).appendTo($button);
        }

        // Add 'Manage Jumplinks' button
        addButton('ButtonManageRJumplinks', 'Manage Jumplinks');

        // Add 'Import from CSV' button
        addButton('ButtonImportCSV', 'Import from CSV', 'import/?type=csv');

        // If ProcessRedirects is installed, show 'Import from Redirects module' button
        if (config.pjOldRedirectsInstalled)
            addButton('ButtonImportRedirectsModule', 'Import from Redirects module', 'import/?type=redirects');
    }();

});
