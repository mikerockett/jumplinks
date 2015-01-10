/*! @preserve
 *
 *  ProcessAdvancedRedirects - Main Script
 *
 *  Author: Mike Anthony
 *  Copyright (c) 2015, Mike Anthony. All Rights Reserved.
 *  Licence: MIT License - http://mikeanthony.mit-license.org/
 *
 *  http://pw.foundrybusiness.co.za/advanced-redirects
 *
 */

$(function() {
    'use strict';

    var $t = $("form#parTabs");

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
    config.parAdmin && function() {

        // Setup WireTabs on the module's admin page
        $t.find("script").remove();
        $t.WireTabs({
            items: $("#parTabs > .Inputfields > .InputfieldWrapper"),
            id: "ProcessAdvancedRedirectsTabs",
            rememberTabs: true,
            skipRememberTabIDs: ['log'],
        });

    }();

    // Check if we're working with a redirect
    config.parEntity && function() {

        $('#destinationPage').bind('pageSelected', function(a, b) {
            b.id > 0 && $('input#destinationUriUrl').val('page:' + b.id)
        });

        $('button#saveRedirect').on('click', function() {

            var $values = {
                    sourcePath: $('input#sourcePath').val(),
                    destinationUriUrl: $('input#destinationUriUrl').val(),
                    dateStart: $('#dateStart').val(),
                    dateEnd: $('#dateEnd').val(),
                },
                errors = [],
                errorString = '';

            if ($values.sourcePath.trim().length === 0)
                errors.push("Source Path can't be empty...");

            else {
                var isAbsolute = new RegExp('^(?:[a-z]+:)?//', 'i');
                if (isAbsolute.test($values.sourcePath))
                    errors.push('Source Path cannot be an absolute URL. It needs to be relative to the root of your installation, without the leading forward-slash.');
            }

            if ($values.destinationUriUrl.trim().length === 0)
                errors.push("Destination Path can't be empty...");

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
    config.parCollection && function() {

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

    // Detect if we're on the module's config page
    config.parModuleAdmin && function() {

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
        var buttonTag = "<button/>";
        var buttonPlacement = ".Inputfield_submit_save_module .InputfieldContent";
        var buttonClass = "ui-button ui-widget ui-state-default ui-priority-secondary";
        var spanTag = "<span/>";
        var spanClass = "ui-button-text";

        // Add 'Manage Redirects' button
        var $buttonManageRedirects = $(buttonTag).attr("id", "ButtonManageRedirects")
            .addClass(buttonClass)
            .on("click", function(event) {
                event.preventDefault();
                window.location = config.parAdminPageUrl;
            })
            .appendTo(buttonPlacement),

            // Add span to 'Manage Redirects' button
            $spanManageRedirects = $(spanTag).addClass(spanClass).text("Manage Redirects").appendTo($buttonManageRedirects);
    }();

});
