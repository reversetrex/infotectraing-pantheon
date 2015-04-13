/**
 * @package infotec module
 * @subpackage short-form handler (front page)
 * @author gbellucci
 * @version 1.0.0
 * @abstract
 *    This script handles the short from on the front page that shows the course categories and courses.
 *    The data is obtained via the BridgeClient
 */

if (typeof jQuery === 'undefined') { throw new Error('short-form.js script requires jQuery') }

var
    dbError = 1, dbWarn = 2, dbInfo = 3,

    config = {
        path : '/',
        debug: 1 // 1|0
    };

// setup the debug console
setUpConsole();

// Bridge interface
;(function($, window, undefined) {
    "use strict";

    var sf = {

        bridge: {
            initialized: false,
            url: 'index.php',

            // initialize server negotiation
            init: function () {
                if (!this.initialized) {
                    debug(dbInfo, "starting server key exchange");
                    this.url = window.config.path + this.url;
                }
            },

            // send local bridge request
            send: function (action, params, callback) {
                debug(dbInfo, "server action: " + action + ", fkey:" + fkey + ", fargs: " + JSON.stringify(params) + ", url: " + this.url);
                $.ajax({
                    type: "POST", dataType: "json", async: false, url: this.url,
                    data: {bridge: action, args: params},
                    success: function (data) {
                        if (typeof data != 'undefined') {
                            if (data.rc == 0) {
                                debug(dbInfo, "server response: " + JSON.stringify(data));
                                callback(data);
                            }
                            else if (data.rc == 1) {
                                debug(dbInfo, "server error: " + JSON.stringify(data));
                                throw new Error('server response error from request.');
                            }
                        }
                    }
                    , error: function (XMLHttpRequest, textStatus, error) {
                        debug(D_ERROR, "server response error: " + textStatus);
                        throw new Error('ajax error from request.');
                    }
                });
            }
        }
    };

    // form control
    $(document).ready(function($) {

    });

}(jQuery, window, 'undefined'));


/**
 * Setup the window console
 * @returns nothing
 */
function setUpConsole() {
    if(!window.console){ window.console = {log: function(){} }; }
    console.log = console.log     || function(msg){};
    console.warn = console.warn   || function(msg){};
    console.error = console.error || function(msg){};
    console.info = console.info   || function(msg){};
}

/**
 * Private debug
 * @param type - error,warn or info constant
 * @param msg - string
 */
function debug(type, msg) {
    if(config.debug) {
        if(type == dbError) {
            console.error(msg);
        }
        else if(type == dbWarn) {
            console.warn(msg);
        }
        else if(type == dbInfo) {
            console.info(msg);
        }
        else {
            debug(dbInfo, msg);
        }
    }
}