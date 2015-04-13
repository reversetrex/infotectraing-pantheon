/*!
 * dbridge.min.js -- v1.0.0 -- 3/8/2015
 * Copyright (c) 2015 ECPI University; Licensed Proprietary
 */

/**
 * @package infotec module
 * @subpackage short-form handler (front page)
 * @author gbellucci
 * @version 1.0.0
 * @abstract
 *    This script handles the short from on the front page that shows the course categories and courses.
 *    The data is obtained via the BridgeClient
 */

if (typeof jQuery === 'undefined') {
    throw new Error('dbridge.min.js script requires jQuery')
}

var
// global variables
    dbError = 1, dbWarn = 2, dbInfo = 3,
    cObj, sObj, secObj = [], cform,
    activeSection = 'Courses',
    config = {
        pathUrl: location.protocol + '//' + location.hostname + '/',
        debug: true // true/false
    };

// setup the debug console
setUpConsole();

/**
 * Helper object
 * Selector storage object
 * @param name - the name of a selector
 * @constructor
 */
function Selector(name) {
    "use strict";

    var s = this, $ = jQuery;
    s.id = name;
    s.obj = $('#' + name);
    s.rState = false;

    s.getId = function () {
        return (s.id);
    };
    s.getObj = function () {
        return (s.obj);
    };
    s.getRstate = function () {
        return (s.rState);
    };
    s.setRstate = function (state) {
        s.rState = state;
    };
    s.enable = function () {
        s.getObj().prop('disabled', false);
        debug(dbInfo, '==> ' + s.getId() + ' is enabled');
    };
    s.disable = function () {
        s.getObj().prop('disabled', true);
        s.getObj().val('');
        debug(dbInfo, '==> ' + s.getId() + ' is disabled');
    };
    s.setAttr = function(attr, state) {
        s.getObj().prop(attr, state);
    }
}

/**
 * Infotec drop down control handler
 *
 * This object is designed to control the visibility of drop down selections based on the user's
 * choice of infotec training delivery categories. Each category contains a set of groups and each
 * group contains a set of courses. All delivery methods, groups and courses are loaded into three
 * select tags. The delivery method (category) option value determines which group is visible in the
 * the group dropdown; the group dropdown options determine which courses are visible. This object
 * controls the addition and removal of visible options based on the selections. It does this by
 * creating DOM copies of each option for each level and inserting them into the select tags or
 * removing them from the select tags. This method works with all modern browsers.
 *
 * @uses Selector objects
 */
function DDHandler(catSelector, groupSelector, courseSelector) {
    "use strict";

    var iter, ds = this, $ = jQuery;
    ds.collection_2 = [];  // internal array of level 2 detached options
    ds.collection_3 = [];  // internal array of level 3 detached options

    // save selector definitions
    ds.jQLevel_1 = new Selector(catSelector);     // training delivery methods
    ds.jQLevel_2 = new Selector(groupSelector);   // groups within categories
    ds.jQLevel_3 = new Selector(courseSelector);  // courses within groups

    // initialize reset states
    ds.jQLevel_2.setRstate(true);
    ds.jQLevel_3.setRstate(true);

    // recreate the original list
    ds._rebuildList = function (ar, sel) {
        for (ds.iter = 0; ds.iter < ar.length; ds.iter++) {
            sel.append(ar[ds.iter]);
        }
        debug(dbInfo, 'added ' + (ar.length + 1) + ' options');
    };

    // remove unwanted items from the list
    ds._filterList = function (val, sel) {
        var count = 0;
        sel.children('option').each(function () {
            if (!$(this).hasClass(val) && $(this).val() != '') {
                $(this).detach();
                count++;
            }
        });
        debug(dbInfo, "removed " + (count + 1) + ' options');
    };

    // reconstruct all level 2 options
    ds.rebuildLevel_2 = function () {
        // reconstruct the options but only if they were previously filtered
        if (true === ds.jQLevel_2.getRstate()) {
            ds._rebuildList(ds.collection_2, ds.jQLevel_2.getObj());
            ds.jQLevel_2.setRstate(false);
            ds.jQLevel_2.disable();
            debug(dbInfo, ">> level-2 rebuilt");
        }
    };

    // reconstruct all level 3 options
    ds.rebuildLevel_3 = function () {
        // reconstruct the options but only if they were previously filtered
        if (true === ds.jQLevel_3.getRstate()) {
            ds._rebuildList(ds.collection_3, ds.jQLevel_3.getObj());
            ds.jQLevel_3.setRstate(false);
            ds.jQLevel_3.disable();
            debug(dbInfo, ">> level-3 rebuilt");
        }
    };

    // reconstruct level 2 and 3 options
    ds.rebuildAll = function () {
        ds.rebuildLevel_2();
        ds.rebuildLevel_3();
    };

    // filters the level 2 options using a level 1 class name
    ds.filterLevel_2 = function (val) {
        debug(dbInfo, "* level-2 filtering for " + val);
        ds._filterList(val, ds.jQLevel_2.getObj());
        ds.jQLevel_2.setRstate(true);
        ds.jQLevel_2.enable();
    };

    // filters the level 3 options using a level 2 class name
    ds.filterLevel_3 = function (val) {
        debug(dbInfo, "* level-3 filtering for " + val);
        ds._filterList(val, ds.jQLevel_3.getObj());
        ds.jQLevel_3.setRstate(true);
        ds.jQLevel_3.enable();
    };

    // Initialize a the dropdowns and setup change event
    // handlers for each dropdown - selections from level 1
    // will cause updates to occur in level 2. Selections
    // from level 2 will cause updates to level 3.
    ds.init = function (categories, groups, courses) {
        var $ = jQuery;

        // load the options into the select tags
        $(categories).appendTo(ds.jQLevel_1.getObj());
        $(groups).appendTo(ds.jQLevel_2.getObj());
        $(courses).appendTo(ds.jQLevel_3.getObj());
        var ar = [];

        // detach/save all of the level 2 options
        ar = ds.jQLevel_2.getObj().children('option');
        for (ds.iter = 0; ds.iter < ar.length; ds.iter++) {
            ds.collection_2.push($(ar[ds.iter]).detach());
        }

        // detach/save all of the level 3 options
        ar = ds.jQLevel_3.getObj().children('option');
        for (ds.iter = 0; ds.iter < ar.length; ds.iter++) {
            ds.collection_3.push($(ar[ds.iter]).detach());
        }

        // rebuild the select tags
        ds.rebuildAll();

        // ------------------
        // Event Handlers
        // ------------------

            // Level 2 change event
        $( '#' + ds.jQLevel_2.getId() ).on('change', function (e) {
            var hasFocus = $(this).is(':focus');
            if (hasFocus) {
                e.stopImmediatePropagation();
                var value = $(this).val();
                debug(dbInfo, "--> Level-2 change to: " + ((value != '') ? value : "no selection"));
                debug(dbInfo, ' ---> rebuilding level 3');
                ds.rebuildLevel_3();
                if ('' != value) {
                    ds.filterLevel_3(value);
                }
                return false;
            }
        });

        // Level 1 change event
        $( '#' + ds.jQLevel_1.getId() ).on('change', function (e) {
            var hasFocus = $(this).is(':focus');
            if(hasFocus) {
                e.stopImmediatePropagation();
                var value = $(this).val();
                debug(dbInfo, "--> Level-1 change to: " + ((value != '') ? value : "no selection"));
                debug(dbInfo, ' ---> rebuilding all levels');
                ds.rebuildAll();
                if ('' != value) {
                    ds.filterLevel_2(value);
                }
                return false;
            }
        });

        $( '#' + ds.jQLevel_1.getId() ).on('activate', function(e) {
            $(this).val('');
            ds.jQLevel_1.enable();
            debug(dbInfo, " ---> " + ds.jQLevel_1.getId() + ' activated');
            return false;
        });

        $( '#' + ds.jQLevel_1.getId() ).on('deactivate', function(e) {
            debug(dbInfo, "active section: " + window.activeSection);
            if(window.activeSection != 'Courses') {
                $(this).val('');
                ds.jQLevel_1.disable();
                ds.jQLevel_2.disable();
                ds.jQLevel_3.disable();
                debug(dbInfo, " ---> " + ds.jQLevel_1.getId() + ' deactivated');
                return false;
            }
            else {
                debug(dbInfo, "[ ignoring deactivate event ] ");
            }
        });
    }
}// end DDHandler object

/**
 * Inquiry form section control.
 *
 * An object for controlling the visibility of an optional section of the form. This object will
 * open/close a form section and trigger events to close other sections. All sections are mutually exclusive.
 * Form sections are optional form controls that are only active and visible when an "area of interest" is
 * selected. Form section ids must match the options value set in the area of interest dropdown and the
 * section must be assigned a 'form-section' class. Any controls within the form section are disabled
 * when not displayed. This is so form submissions to not contain unnecessary form fields. (disabled
 * controls are not submitted)
 *
 * @param selector - the jQuery object for this section.
 * @param oSections - an array of jQuery objects for the other sections in the form
 *        (excluding the current section)
 * @param state - the initial state of this section (1 = open, 0 = closed)
 * @constructor
 */

function Section(selector, oSections, state) {
    "use strict";

    var sec = this, $ = jQuery;
    sec.others = oSections;                 // an array of other sections jQuery objects
    sec.jQo = selector;                     // the jQuery object for this section
    sec.id = $(selector).attr('id');        // selector id
    sec.isOpen = state;                     // 0 = closed, 1 = open

    // find all input controls in this section
    sec.controls = $('#' + sec.id + '> *').find('select, input, textarea');

    // open this section
    sec.open = function () {
        sec.enableControls();
        $(sec.jQo).fadeIn(1200, function () {
            sec.isOpen = true;
            window.activeSection = sec.id;
            debug(dbInfo, 'Section ' + sec.id + ' open');
        });
        sec.closeOpenSection();
    };

    // close this section
    sec.close = function () {
        $(sec.jQo).fadeOut(1200, function () {
            sec.isOpen = false;
            sec.disableControls();
            debug(dbInfo, 'Section ' + sec.id + ' closed');
        });
    };

    // disable all decendant input controls
    sec.disableControls = function() {
        if(sec.controls.length > 0) {
            $(sec.controls).each(function () {
                $(this).trigger('deactivate');
                debug(dbInfo, "<< sent deactivate event: " + $(this).attr('name'));
            });
        }
    };

    // enable all decendant input controls
    sec.enableControls = function() {
        if(sec.controls.length > 0) {
            $(sec.controls).each(function () {
                $(this).trigger('activate');
                debug(dbInfo, "<< sent activate event: " + $(this).attr('name'));
            });
        }
    };

    // triggers an event to close other sections
    sec.closeOpenSection = function () {
        if(sec.others.length > 0) {
            $.each(sec.others, function () {
                $(this).trigger('closeSection');
                debug(dbInfo, "<< sent closeSection event");
            });
        }
    };

    // open section event handler
    $(sec.jQo).on('openSection', function (e) {
        if (!sec.isOpen) {
            sec.open();
        }
    });

    // close section event handler
    $(sec.jQo).on('closeSection', function (e) {
        if (sec.isOpen) {
            sec.close();
        }
    });

    // object initialization
    // section is initially hidden
    if (!sec.isOpen) {
        $(sec.jQo).hide();
        sec.disableControls();
    }
    else {
        // section is initially open
        sec.open();
        sec.enableControls();
    }

}// end Section

/**
 * Contact Form Handler
 * Used for handling forms and form submissions.
 *
 * HTML tags for input form controls assumes the following:
 *
 *    > The control id and name is the same
 *    > Controls are identified by the attribute data-type="text | select | email | tel | mselect"
 *    > Labels associated with email fields are defined using the attribute data-label="<field label>"
 *    > required input uses the attribute required="required"
 *
 * @param selector - selector name of the form
 *        i.e: #contact-form
 *
 * @constructor
 */
function FormHandler(selector) {
    "use strict";

    var fh = this, $ = jQuery;
    fh.id = selector;
    fh.fields = null;
    fh.errorClass = 'cf-error';
    fh.errors = 0;

    // clear form error classes
    fh.clearErrors = function () {
        $('.' + fh.errorClass).each(function () {
            $(this).removeClass(fh.errorClass);
        });
        debug(dbInfo, "all errors cleared");
        fh.errors = 0;
    };

    // adds an error class to a field
    fh.markError = function (id) {
        $('#' + id).addClass(fh.errorClass);
        debug(dbInfo, "invalid field: " + id);
        fh.errors += 1;
    };

    // removes an error class from a field
    fh.unMarkError = function (id) {
        $('#' + id).removeClass(fh.errorClass);
        fh.errors = (fh.errors > 0) ? fh.errors - 1 : 0;
        debug(dbInfo, "--> error cleared for " + id);
    };

    // validates field entries
    // Field validation occurs both here and on the server. Fields are validated here and allow the form data
    // to be submitted to the server. The server also performs validation in the event someone attempts to
    // bypass this form.
    fh.validate = function (fields) {
        var
        // multi option select container
            msel = {
                field: {
                    name: null,
                    value: [],
                    label: null,
                    order: null,
                    type: null,
                    req: 0
                }
            },
            fields_ar = [],
            email = /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/,
            alpha = /^[a-zA-Z]+$/,
            alpha_hyphen = /^[a-zA-Z-]+$/,
            nonblank = /^\s*[0-9a-zA-Z][0-9a-zA-Z'-@]*$/,

        // US Phone Number: This regular expression for US phone numbers conforms to NANP A-digit and D-digit requirements (ANN-DNN-NNNN).
        // Area Codes 001-199 are not permitted; Central Office Codes 001-199 are not permitted. Format validation accepts 10-digits without
        // delimiters, optional parens on area code, and optional spaces or dashes between area code, central office code and station code.
        // Acceptable formats include 2225551212, 222 555 1212, 222-555-1212, (222) 555 1212, (222) 555-1212, etc.
            usphone = /^(?:\([2-9]\d{2}\)\ ?|[2-9]\d{2}(?:\-?|\ ?))[2-9]\d{2}[- ]?\d{4}/,
            zipcode = /^\d{5}(-\d{4})?$/,

        // test a regEx expression
            testExpr = function (str, expr) {
                return ( expr.test(str) );
            },

        // checks for blank entries
            isBlank = function (str) {
                return (!str || /^\s*$/.test(str));
            },

        // name validation
            isName = function (str) {
                return (testExpr(str, alpha_hyphen));
            },

        // email validation
            isEmail = function (str) {
                return (testExpr(str, email) );
            },

        // phone validation
            isUSPhone = function (str) {
                return (testExpr(str, usphone) );
            },

        // zipcode validation
            isZipCode = function (str) {
                return (testExpr(str, zipcode) );
            };

        // validate each field

        $.each(fields, function (i, field) {
            var
                sel = '#' + field.name,
                isEmpty = isBlank(field.value),
                isRequired = ($(sel).prop('required') == true),
                type = $(sel).data('type'),
                label = $(sel).data('label'),
                order = $(sel).data('order'),
                skip = 0;

            if (isRequired && !isEmpty) {
                switch (type) {
                    case "mselect" :
                        // multi-select field container
                        if (!msel.field.name) {
                            msel.field.name = field.name;
                            msel.field.value.push(field.value);
                            msel.field.label = label;
                            msel.field.order = order;
                            msel.field.type = type;
                            msel.field.req = isRequired;
                            skip = 1;
                        }
                        else if (msel.field.name == field.name) {
                            msel.field.value.push(field.value);
                            skip = 1;
                        }
                        break;

                    case "name":
                        if (!isName(field.value)) {
                            fh.markError(field.name);
                        }
                        break;

                    case "email":
                        if (!isEmail(field.value)) {
                            fh.markError(field.name)
                        }
                        break;

                    case "tel":
                        if (!isUSPhone(field.value)) {
                            fh.markError(field.name);
                        }
                        break;

                    default:
                        break;
                }// end switch
            }
            else {
                if (isRequired) {
                    fh.markError(field.name);
                }
            }

            // don't copy this field?
            if(!skip) {
                fields_ar.push({
                    name:  field.name,
                    value: field.value,
                    label: label,
                    order: order,
                    type:  type,
                    req:   isRequired
                });
            }
        });

        // if we have a multi-select field, then add it to the array
        if (msel.field.name != null) {
            fields_ar.push({
                name: msel.field.name,
                value: msel.field.value,
                label: msel.field.label,
                order: msel.field.order,
                req:   msel.req
            });
        }

        // overwrite the fields array
        fh.fields = fields_ar;
    };


    // submits a form for emailing
    fh.submit = function () {
        fh.clearErrors();
        fh.fields = $(fh.id).serializeArray();
        fh.validate(fh.fields);
        fh.fields = ((!fh.errors) ? fh.fields : []);
        debug(dbInfo, "submission error count: " + fh.errors);
        return (fh.errors);
    };

    // get the validated field array
    fh.getFields = function () {
        return (fh.fields);
    };
}

// ----------------------
// Bridge interface
// ----------------------
;
(function ($, window, undefined) {
    "use strict";

    var
        sf = { // data bridge server interface
            bridge: {
                initialized: false,
                url: '',
                token: 0,
                data: null,

                // initialize server negotiation - this handshake
                // is required before the form can obtain data from
                // the server.
                handshake: function (fid) {
                    if (!this.initialized) {
                        // add active abd inactive styles to the head tag
                        debug(dbInfo, "getting bridge token");
                        var param = {'fid': fid};
                        sf.bridge.url = window.config.pathUrl;
                        sf.bridge.send('hello', param, sf.bridge.complete);
                    }
                }
                // handshake callback
                , complete: function (data, param) {
                    if (typeof data != 'undefined') {
                        // sf.bridge.initialized = true;
                        sf.bridge.token = data.token;

                        // request dropdown information
                        // sql request:   courseList
                        // dman request:  optionsFormat

                        var dmanRequest = 'sform' == param.fid ? 'sfOptionsFormat' : 'cfOptionsFormat',
                            args = {'dman': dmanRequest, 'sql': 'courseList'};
                        sf.bridge.send('bridgeSql', args, ('sform' == param.fid ? sf.bridge.sfInitForm : sf.bridge.cfInitForm));
                    }
                }

                // callback function to initialize the short form
                // drop down controls. Dropdown select controls are
                // managed by DDHandler objects
                , sfInitForm: function (data, param) {
                    if (typeof data != 'undefined') {
                        // create a drop down handler for the short-form
                        window.sObj = new DDHandler('category', 'group', 'course').
                            init(data.list.groups, data.list.subgroups, data.list.courses);

                        // course selection
                        // this will redirect the user to the course page;
                        $('body').on('click', '#course-select', function () {
                            if ('' != $('#category').val() && '' != $('#group').val() && '' != $('#' + 'course').val()) {
                                // redirect to course page
                                window.location.href = $('#course').val();
                            }
                            return (false);
                        });
                    }
                }

                // callback function to initialize the contact form
                , cfInitForm: function (data, param) {
                    if (typeof data != 'undefined') {
                        // create a drop down handler for the inquiry form
                        window.cObj = new DDHandler('cf-category', 'cf-group', 'cf-course').
                            init(data.list.groups, data.list.subgroups, data.list.courses);

                        // assign Section handlers for controlling sections
                        // Locate the form sections and assign the section controller
                        var sections = [], iter, tmp = $('.form-section');
                        if (tmp.length > 0) {
                            $(tmp).each(function () {
                                sections.push($(this));
                            });

                            var sel, othr, s;
                            for (iter = 0; iter < sections.length; iter++) {
                                sel = sections[iter];
                                othr = sections.slice(0); // make a copy of the sections array
                                othr.splice(iter, 1); // remove only this section
                                window.secObj.push(new Section(sel, othr, 0));
                            }
                        }
                        // assign the form handler
                        window.cform = new FormHandler('#contact-form');

                        // add the progress bar popup
                        $('<div id="pb-wrap"><div id="pb"></div></div>'+
                          '<div id="msgBox"></div>')
                            .insertBefore('#contact-form');

                        // area of interest selection
                        $('body').on('change', '#cf-interest', function () {
                            var area = $(this).val();
                            area = ('' == area) ? 'default' : area;
                            $('#' + area).trigger('openSection');
                            $.when($('#' + area).trigger('deactivate')).done(function() {
                                window.cform.clearErrors();
                            });

                        }).on('click', '#submit-contact-form', function (e) {
                            e.stopPropagation();
                            if (window.cform.submit()) {
                                return (false);
                            }
                            else {
                                $('#submit-contact-form').prop("disabled", true);
                                var fields = window.cform.getFields();
                                if(fields.length > 0) {
                                    sf.showProgressBar();
                                    sf.bridge.send('export', fields, sf.bridge.formSent);
                                }
                                else {
                                    throw Error('empty form fields');
                                }
                            }
                        });

                        // set the focus on the first, visible input control
                        $(':input:enabled:visible:not([readonly]):first').focus();
                    }
                }

                // callback function
                , formSent: function (data) {

                    // delay closing the progress bar
                    setTimeout(function() {
                        $('#pb-wrap').dialog("close");

                        // If there were errors - open an alert box
                        if(typeof data.err != "undefined") {
                            // the server sent back validation errors.
                            sf.openMsgBox(data.err);
                        }

                        // redirect the user
                        else if(typeof data.url != "undefined") {
                            window.location.href = data.url;
                        }

                    }, 10000);
                }

                // send local bridge request
                , send: function (action, param, callback) {
                    debug(dbInfo, "url: " + sf.bridge.url + "--> action: " + action + ", token:" + sf.bridge.token + ", param: " + JSON.stringify(param));
                    $.ajax({
                        type: "POST", dataType: "json", async: true, url: sf.bridge.url,
                        data: {bridge: action, token: sf.bridge.token, q: param},
                        success: function (data) {
                            if (typeof data != 'undefined') {
                                if (data.rc == 0) {
                                    debug(dbInfo, "response: " + JSON.stringify(data));
                                    callback(data, param);
                                }
                                else if (data.rc == 1) {
                                    debug(dbInfo, "error: " + JSON.stringify(data));
                                    throw new Error('response error from request.');
                                }
                            }
                        }
                        , error: function (XMLHttpRequest, textStatus, error) {
                            debug(dbError, "server response error: " + textStatus);
                            throw new Error('ajax error from request.');
                        }
                    });
                }
            }// end bridge:

            // open the message box
            , openMsgBox: function(errors) {

                // load the message box with the error messages
                var i, txt = '<ul>';
                for(i = 0; i < errors.length; i++) {
                    txt += '<li>' + errors[i] + '</li>';
                }
                txt += '</ul>';

                // open the box
                $('#msgBox').html(txt).dialog({
                    buttons: [
                        {
                            text: "OK",
                            click: function() {
                                $( this ).dialog( "close" );
                            }
                        }
                    ],
                    title: 'Form Errors',
                    autoOpen: true,
                    resizable: false,
                    modal: true,
                    hide: "blind",
                    show: "blind",
                    width: 500
                });

                // enable the submit button
                $('#submit-contact-form').prop("disabled", false);
            }

            // -------------------------------
            // course lookup popup dialogbox
            // -------------------------------
            , clookUp: {

                init: function () {

                    // get the short form and append it to the body tag
                    $.get(window.config.pathUrl + "sites/all/modules/custom/infotec/js/courseLookUp.html", function (data) {
                        // add the form to the page
                        $(data).appendTo('body');

                        // say hello to the bridge server
                        sf.bridge.handshake($('#short-form #fid').val());

                        // turn this into a popup form
                        $("#dialog-form").dialog({
                            autoOpen: false,
                            resizable: false,
                            modal: true,
                            hide: "blind",
                            show: "blind",
                            width: 375
                        });
                    });

                    $('body').on('click', 'a.course-lookup', function () {
                        $("#dialog-form").dialog('open');
                        return (false);
                    });
                }
            }
            // opens the progress bar while processing the form
            ,showProgressBar: function() {

                // scroll the window up
                $('html, body').animate({scrollTop: '10px'}, 300, 'swing', function () {

                    $("#pb-wrap").dialog({
                        dialogClass: "no-close",
                        height: 100,
                        width: 300,
                        modal: true,
                        hide: "blind",
                        show: "blind",
                        resizeable: false,
                        autoOpen: false,
                        closeOnEscape: false,
                        title: "Please Wait...",
                        position: {my: "center", at: "center"}
                    });

                    $("#pb").progressbar({value: false})
                        .css({'background-color': '#ccc'});

                    $("#pb-wrap .ui-button .ui-dialog-titlebar-close")
                        .css({'display': 'none'});

                    $("#pb-wrap").dialog("open");
                });
            }
        };

    // form control
    $(document).ready(function ($) {
        // determine if the course lookup form is on the page
        // nothing to do if it's not there.
        var sfControl = $('#short-form'),
            cfControl = $('#contact-form'),
            courseLookUp = $('a.course-lookup'),
            isBackEnd = window.location.href.indexOf('/admin/') != -1 ? 1 : 0,
            isFrontPage = $('body').hasClass('front') ? 1: 0;

        if(cfControl.length > 0) {
            debug(dbInfo, "-- form: contact-form detected");
            sf.bridge.handshake($('#contact-form #fid').val());
        }

        if(sfControl.length > 0) {
            debug(dbInfo, "-- form: short-form detected");
            sf.bridge.handshake($('#short-form #fid').val());
        }

        if(courseLookUp.length > 0 && !isFrontPage) {
            debug(dbInfo, "-- course lookup detected");
            sf.clookUp.init();
        }

    });

}(jQuery, window, 'undefined'));

/**
 * Setup the window console
 * @returns nothing
 */
function setUpConsole() {
    if (!window.console) {
        window.console = {
            log: function () {
            }
        };
    }
    console.log = console.log || function (msg) {
        noop();
    };
    console.warn = console.warn || function (msg) {
        noop();
    };
    console.error = console.error || function (msg) {
        noop();
    };
    console.info = console.info || function (msg) {
        noop();
    };
}

// a function that does nothing
function noop() {

}

/**
 * Private debug
 * @param type - error,warn or info constant
 * @param msg - string
 */
function debug(type, msg) {
    if (config.debug) {
        if (type == dbError) {
            console.error(msg);
        }
        else if (type == dbWarn) {
            console.warn(msg);
        }
        else if (type == dbInfo) {
            console.info(msg);
        }
        else {
            debug(dbInfo, msg);
        }
    }
}