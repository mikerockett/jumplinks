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
 *  Crunched: Yip
 */

$(function() {
    'use strict';

    // Declare vars
    var classInputfieldTask,
        defaults,
        buttonTag,
        submitButtonParentSelector,
        buttonPlacement,
        buttonClass,
        spanTag,
        spanClass,
        $buttonManageRedirects,
        $spanManageRedirects,
        $t = $("form#parTabs");

    // Function: Get URL arg/param
    var urlParam = function(param) {
        var i,
            paramName,
            url = window.location.search.substring(1),
            vars = url.split("&");
        for (i = 0; i < vars.length; i++)
            if (paramName = vars[i].split("="), paramName[0] == param)
                return paramName[1];
    };

    // Detect if we're on the module's admin page
    // Do stuff if we are
    typeof config.parRowAssociations != 'undefined' && config.parRowAssociations[0] !== '' && function(rows) {

        // Setup WireTabs on the module's admin page
        $t.find("script").remove();
        $t.WireTabs({
            items: $("#parTabs > .Inputfields > .InputfieldWrapper"),
            id: "ProcessAdvancedRedirectsTabs",
            rememberTabs: true,
            skipRememberTabIDs: ['history', 'variables', 'replacements'],
        });

        // Get the ID param
        // Used to highlight last affected item
        var idParam = urlParam("id");
        idParam && function() {
            for (var key in rows)
                rows.hasOwnProperty(key) &&
                idParam == rows[key] &&
                (key++, $("table.advanced-redirects.redirects tr:nth-child(k)".replace("k", key)).addClass("affected"));
        }();
    }(config.parRowAssociations);

    // Detect if we're on the module's config page
    // Do stuff if we are
    $('form#ModuleEditForm[action$=ProcessAdvancedRedirects]').length > 0 && function() {

        // Set initial vars for module's config page
        classInputfieldTask = "InputfieldTask";
        defaults = {
            statusCodes: "200 301 302",
            extensions: "aspx asp cfm cgi fcgi dll html htm shtml shtm jhtml phtml xhtm rbml jspx jsp phps php4 php",
            extensionRegex: "aspx?|cfm|f?cgi|dll|s?html?|[jp]html|xhtm|rbml|jspx?|php[s4]?"
        }

        // Set action for 'HTTP Status Codes for Legacy Domain' Restore Defaults link-button
        $("a[href=#resetLegacyStatusCodes]")
            .removeAttr("target")
            .addClass(classInputfieldTask)
            .on("click", function(event) {
                $("input#statusCodes").val(defaults.statusCodes), event.preventDefault();
            });

        // Set action for 'Default Extensions' Restore Defaults link-button
        $("a[href=#resetDefaultExtensions]")
            .removeAttr("target")
            .addClass(classInputfieldTask)
            .on("click", function(event) {
                $("input#defaultExtensions").val(defaults.extensions), event.preventDefault();
            });

        // Set action for 'Default Extensions' Use Default Regex link-button
        $("a[href=#regexDefaultExtensions]")
            .removeAttr("target")
            .addClass(classInputfieldTask)
            .on("click", function(event) {
                $("input#defaultExtensions").val(defaults.extensionRegex), event.preventDefault();
            })

        // Set button vars for module's config page
        buttonTag = "<button/>";
        buttonPlacement = ".Inputfield_submit_save_module .InputfieldContent";
        buttonClass = "ui-button ui-widget ui-state-default ui-priority-secondary";
        spanTag = "<span/>";
        spanClass = "ui-button-text";

        // Add 'Manage Redirects' button
        $buttonManageRedirects = $(buttonTag).attr("id", "ButtonManageRedirects")
            .addClass(buttonClass)
            .on("click", function(event) {
                event.preventDefault();
                window.location = config.parAdminPageUrl;
            })
            .appendTo(buttonPlacement),

            // Add span to 'Manage Redirects' button
            $spanManageRedirects = $(spanTag)
            .addClass(spanClass)
            .text("Manage Redirects")
            .appendTo($buttonManageRedirects);
    }();

});
