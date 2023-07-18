function toggleInstructions(key) {
    if (key == 1) {
        $('#divManually').hide();
        $('#divUpgrade').hide();
        $('#divUpgradev4').hide();
        $('#divWizard').fadeIn();
    } else if (key == 2) {
        $('#divWizard').hide();
        $('#divUpgrade').hide();
        $('#divUpgradev4').hide();
        $('#divManually').fadeIn();
    } else if (key == 3) {
        $('#divWizard').hide();
        $('#divManually').hide();
        $('#divUpgradev4').hide();
        $('#divUpgrade').fadeIn();
    } else if (key == 4) {
        $('#divWizard').hide();
        $('#divManually').hide();
        $('#divUpgradev4').fadeIn();
        $('#divUpgrade').hide();
    } else {
        $('#divWizard').hide();
        $('#divManually').hide();
        $('#divUpgrade').hide();
        $('#divUpgradev4').fadeIn();
    }
}

function textboxOnFocus(key) {
    $("#notes_message").attr("style", "display:none;");
    $('#' + key).show();
}

function textboxOnBlur(key) {
    $("#" + key).attr("style", "display:none;");
}

function setFocus(key) {
    $("#" + key).focus();
}

function testDatabaseConnection() {

    $('.loading_img').show();
    $('#notes_message').attr("style", "display:none;");
    buttonDisable();

    var database_host = $("#database_host").val();
    var database_name = $("#database_name").val();
    var database_username = $("#database_username").val();
    var database_password = $("#database_password").val();

    $.ajax({
        url: "ajax/handler.ajax.php",
        global: false,
        type: "POST",
        data: ({
            db_host: database_host,
            db_name: database_name,
            db_username: database_username,
            db_password: database_password,
            check_key: "yes"
        }),
        dataType: "html",
        async: false,
        error: function (html) {
            $('.loading_img').hide();
            $("#notes_message").html(EasyInstaller._MSG["ajax_connection_error"]);
        },
        success: function (html) {
            var obj = jQuery.parseJSON(html);
            if (obj.status == "1") {
                if (obj.db_connection_status == "1") {
                    $("#notes_message").html("<h4 class='success'>" + EasyInstaller._MSG["success"] + "</h4><p>" + EasyInstaller._MSG["connection_was_established"] + "</p>");
                } else {
                    $("#notes_message").html("<h4>" + EasyInstaller._MSG["error"] + "</h4><p>" + obj.db_error + "</p>");
                }
            } else {
                $("#notes_message").html("<span class='msg_error'>" + EasyInstaller._MSG["connection_error"] + "</span>");
            }
        }
    });
    $('.loading_img').hide();
    $('#notes_message').fadeIn();
    buttonEnable();
}

function validateLicenseKey() {

    $('.loading_img').show();
    $('#notes_message').attr("style", "display:none;");

    var license_key = $("#license_key").val();

    $.ajax({
        url: "ajax/license_key_handler.ajax.php",
        global: false,
        type: "POST",
        data: ({license_key: license_key}),
        dataType: "json",
        async: false,
        error: function (json) {
            $('.loading_img').hide();
            $("#notes_message").html("<h4 class='success'>Error</h4><p>There was a general error validating the license key, please try again later.</p>");
        },
        success: function (json) {
            console.log(json);
            $("#notes_message").html("<h4 class='" + json['messageType'] + "'>" + json['title'] + "</h4><p>" + json['content'] + "</p>");
        }
    });
    $('.loading_img').hide();
    $('#notes_message').fadeIn();
}

function buttonDisable() {
    $("#button_test").attr("src", EI_LOCAL_PATH + "buttons/button_test_disabled.gif");
    $("#button_test").attr("style", "cursor:default;");
}

function buttonEnable() {
    $("#button_test").attr("src", EI_LOCAL_PATH + "buttons/button_test.gif");
    $("#button_test").attr("style", "cursor:pointer;");
}

function buttonOver(key, path) {
    $("#" + key).attr("src", EI_LOCAL_PATH + "buttons/" + key + "_over.gif");
}

function buttonOut(key) {
    $("#" + key).attr("src", EI_LOCAL_PATH + "buttons/" + key + ".gif");
}

function installTypeOnClick(val) {
    if (val == "un-install") {
        $("#line_admin_info").hide("fast");
        $("#line_admin_login").hide("fast");
        $("#line_admin_password").hide("fast");
        $("#line_password_encryption").hide("fast");
    } else {
        $("#line_admin_info").show("fast");
        $("#line_admin_login").show("fast");
        $("#line_admin_password").show("fast");
        $("#line_password_encryption").show("fast");
    }
}

/**
 *   Change location
 */
function goTo(page) {
    document.location.href = page;
}
