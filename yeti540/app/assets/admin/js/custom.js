/**
 * Main JavaScript File
 * z/5/PAVgYNbtmzNxfBdnAuP3j1FfMlbw03nb3n/7KAk=
 **/
(function ($, sr) {
    // debouncing function from John Hann
    // http://unscriptable.com/index.php/2009/03/20/debouncing-javascript-methods/
    var debounce = function (func, threshold, execAsap) {
        var timeout;

        return function debounced() {
            var obj = this, args = arguments;
            function delayed() {
                if (!execAsap)
                    func.apply(obj, args);
                timeout = null;
            }

            if (timeout)
                clearTimeout(timeout);
            else if (execAsap)
                func.apply(obj, args);

            timeout = setTimeout(delayed, threshold || 100);
        };
    };

    // smartresize 
    jQuery.fn[sr] = function (fn) {
        return fn ? this.bind('resize', debounce(fn)) : this.trigger(sr);
    };

})(jQuery, 'smartresize');
/**
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

var CURRENT_URL = window.location.href.split('#')[0].split('?')[0],
        $BODY = $('body'),
        $MENU_TOGGLE = $('#menu_toggle'),
        $SIDEBAR_MENU = $('#sidebar-menu'),
        $SIDEBAR_FOOTER = $('.sidebar-footer'),
        $LEFT_COL = $('.left_col'),
        $NAV_MENU = $('.nav_menu'),
        $FOOTER = $('footer');

// TODO: This is some kind of easy fix, maybe we can improve this
var setContentHeight = function () {
    var bodyHeight = $BODY.outerHeight(),
            footerHeight = $BODY.hasClass('footer_fixed') ? -10 : $FOOTER.height(),
            leftColHeight = $LEFT_COL.eq(1).height() + $SIDEBAR_FOOTER.height(),
            contentHeight = bodyHeight < leftColHeight ? leftColHeight : bodyHeight;

    // normalize content
    contentHeight -= $NAV_MENU.height() + footerHeight;
};

function setSelectedMenuItemByUrl(url) {
    $SIDEBAR_MENU.find('a[href="'+url+'"]').parent('li').addClass('current-page');
    $SIDEBAR_MENU.find('a').filter(function () {
        return this.href == url;
    }).parent('li').addClass('current-page').parents('ul').slideDown(function () {
        setContentHeight();
    }).parent().addClass('active');
}

// Sidebar
$(document).ready(function () {
    $SIDEBAR_MENU.find('a').on('click', function (ev) {
        var $li = $(this).parent();

        if ($li.is('.active')) {
            $li.removeClass('active active-sm');
            $('ul:first', $li).slideUp(function () {
                setContentHeight();
            });
        } else {
            // prevent closing menu if we are on child menu
            if (!$li.parent().is('.child_menu')) {
                $SIDEBAR_MENU.find('li').removeClass('active active-sm');
                $SIDEBAR_MENU.find('li ul').slideUp();
            }

            $li.addClass('active');

            $('ul:first', $li).slideDown(function () {
                setContentHeight();
            });
        }
    });

    // toggle small or large menu
    $MENU_TOGGLE.on('click', function () {
        if ($BODY.hasClass('nav-md')) {
            $SIDEBAR_MENU.find('li.active ul').hide();
            $SIDEBAR_MENU.find('li.active').addClass('active-sm').removeClass('active');
        } else {
            $SIDEBAR_MENU.find('li.active-sm ul').show();
            $SIDEBAR_MENU.find('li.active-sm').addClass('active').removeClass('active-sm');
        }

        $BODY.toggleClass('nav-md nav-sm');

        setContentHeight();
    });

    // check active menu
    setSelectedMenuItemByUrl(CURRENT_URL);

    // recompute content when resizing
    $(window).smartresize(function () {
        setContentHeight();
    });

    setContentHeight();

    // fixed sidebar
    if ($.fn.mCustomScrollbar) {
        $('.menu_fixed').mCustomScrollbar({
            autoHideScrollbar: false,
            theme: 'minimal',
            mouseWheel: {preventDefault: true},
            mouseWheelPixels: 200
        });
    }

    // setup generic modal
    $("#genericModalContainer").modal({
        show: false
    });
});
// /Sidebar

// Panel toolbox
$(document).ready(function () {
    $('.collapse-link').on('click', function () {
        var $BOX_PANEL = $(this).closest('.x_panel'),
                $ICON = $(this).find('i'),
                $BOX_CONTENT = $BOX_PANEL.find('.x_content');

        // fix for some div with hardcoded fix class
        if ($BOX_PANEL.attr('style')) {
            $BOX_CONTENT.slideToggle(200, function () {
                $BOX_PANEL.removeAttr('style');
            });
        } else {
            $BOX_CONTENT.slideToggle(200);
            $BOX_PANEL.css('height', 'auto');
        }

        $ICON.toggleClass('fa-chevron-up fa-chevron-down');
    });

    $('.close-link').click(function () {
        var $BOX_PANEL = $(this).closest('.x_panel');

        $BOX_PANEL.remove();
    });
});
// /Panel toolbox

// Tooltip
$(document).ready(function () {
    setupTooltips();
});
function setupTooltips()
{
    $('[data-toggle="tooltip"]').tooltip({
        container: 'body'
    });
}
// /Tooltip

// Progressbar
if ($(".progress .progress-bar")[0]) {
    $('.progress .progress-bar').progressbar();
}
// /Progressbar

// Switchery
$(document).ready(function () {
    if ($(".js-switch")[0]) {
        var elems = Array.prototype.slice.call(document.querySelectorAll('.js-switch'));
        elems.forEach(function (html) {
            var switchery = new Switchery(html, {
                color: '#26B99A'
            });
        });
    }
});
// /Switchery

// iCheck
var checkboxIds = {};
$(document).ready(function () {
    setupFlatCheckboxes();
});

function setupFlatCheckboxes()
{
    // first reload any checked items
    reloadCheckedItems();

    $('input.flat').iCheck({
        checkboxClass: 'icheckbox_flat-green',
        radioClass: 'iradio_flat-green'
    });

    // make sure existing checked rows are highlighted
    reloadCheckedRows();

    // Table
    $('table input').on('ifChecked', function () {
        $(this).parent().parent().parent().addClass('selected');
        $(this).attr('CHECKED', 'CHECKED');
        toggleRowIds(this);
    });
    $('table input').on('ifUnchecked', function () {
        $(this).parent().parent().parent().removeClass('selected');
        $(this).attr('CHECKED', '');
        toggleRowIds(this);
    });

    $('.bulk_action input').on('ifChecked', function () {
        $(this).parent().parent().parent().addClass('selected');
        $(this).attr('CHECKED', 'CHECKED');
        toggleRowIds(this);
    });
    $('.bulk_action input').on('ifUnchecked', function () {
        $(this).parent().parent().parent().removeClass('selected');
        $(this).attr('CHECKED', '');
        toggleRowIds(this);
    });
    $('.bulk_action input#check-all').on('ifChecked', function () {
        checkAll();
        $(this).attr('CHECKED', 'CHECKED');
    });
    $('.bulk_action input#check-all').on('ifUnchecked', function () {
        uncheckAll();
        $(this).attr('CHECKED', '');
    });
}

function toggleRowIds(ele)
{
    if ($(ele).is(':checked'))
    {
        checkboxIds['cb' + $(ele).val()] = $(ele).val();
    } else
    {
        if (typeof (checkboxIds['cb' + $(ele).val()]) != 'undefined')
        {
            delete checkboxIds['cb' + $(ele).val()];
        }
    }

    if (typeof ("updateButtonText") != "undefined")
    {
        updateButtonText();
    }
}

function reloadCheckedItems()
{
    for (i in checkboxIds)
    {
        $elementId = 'cbElement' + checkboxIds[i];
        if (typeof ($('#' + $elementId)) != 'undefined')
        {
            // check checkbox
            $('#' + $elementId).attr('CHECKED', 'CHECKED');
        }
    }
}

function reloadCheckedRows()
{
    for (i in checkboxIds)
    {
        $elementId = 'cbElement' + checkboxIds[i];
        if (typeof ($('#' + $elementId)) != 'undefined')
        {
            // check checkbox
            $('#' + $elementId).parent().parent().parent().addClass('selected');
        }
    }
}

function checkAll()
{
    $(".bulk_action input[name='table_records']").iCheck('check');
}

function uncheckAll()
{
    $(".bulk_action input[name='table_records']").iCheck('uncheck');
}

function countChecked() {
    count = 0;
    for (i in checkboxIds)
    {
        count++;
    }

    return count;
}
// /iCheck

// Accordion
$(document).ready(function () {
    $(".expand").on("click", function () {
        $(this).next().slideToggle(200);
        $expand = $(this).find(">:first-child");

        if ($expand.text() == "+") {
            $expand.text("-");
        } else {
            $expand.text("+");
        }
    });
});

// NProgress
if (typeof NProgress != 'undefined') {
    $(document).ready(function () {
        NProgress.start();
    });

    $(window).load(function () {
        NProgress.done();
    });
}

function showError(msg)
{
    new PNotify({
        title: "Error",
        type: "error",
        text: msg,
        styling: 'bootstrap3'
    });
}

function showSuccess(msg)
{
    new PNotify({
        title: "Success",
        type: "success",
        text: msg,
        styling: 'bootstrap3'
    });
}

function showInfo(msg)
{
    new PNotify({
        title: "Info",
        type: "info",
        text: msg,
        styling: 'bootstrap3'
    });
}

function setupTagInterface()
{
    $('.tags').tagsInput({
        width: 'auto'
    });
}

function showModal(contentHtml, footerHtml)
{
    // allow for jquery object to be passed in
    if (typeof (contentHtml) == 'object')
    {
        contentHtml = $(contentHtml).html();
    }
    if (typeof (footerHtml) == 'object')
    {
        footerHtml = $(footerHtml).html();
    }

    // set the html on the generic modal and show it
    $("#genericModalContainer .modal-body").html(contentHtml);
    $("#genericModalContainer .modal-footer").html(footerHtml);
    $("#genericModalContainer").modal('show');
}

function hideModal()
{
    $("#genericModalContainer").modal('hide');
}

function showBasicModal(htmlContent, modalTitle, modalHtmlFooter)
{
    // create main content
    modalHtmlInner = '<div class="x_panel basic_modal">';
    modalHtmlInner += '    <div class="x_title">';
    modalHtmlInner += '        <h2>' + modalTitle + '</h2>';
    modalHtmlInner += '        <div class="clearfix"></div>';
    modalHtmlInner += '    </div>';
    modalHtmlInner += '    <div class="x_content">';
    modalHtmlInner += htmlContent;
    modalHtmlInner += '    </div>';
    modalHtmlInner += '</div>';

    // close button
    if (typeof (modalHtmlFooter) == "undefined")
    {
        modalHtmlFooter = '';
    }
    modalHtmlFooter = '<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>' + modalHtmlFooter;

    showModal(modalHtmlInner, modalHtmlFooter);
}

function setBasicModalContent(html)
{
    $('.basic_modal .x_content').html(html);
}

function setTableLoading()
{
    $('.dataTables_wrapper').addClass('dtLoading');
}

function setTableLoaded()
{
    $('.dtLoading').removeClass('dtLoading');
}

function sleep(milliseconds)
{
    var start = new Date().getTime();
    var start = new Date().getTime();
    for (var i = 0; i < 1e7; i++)
    {
        if ((new Date().getTime() - start) > milliseconds)
        {
            break;
        }
    }
}

function postDatatableRender()
{
    $('.dataTables_wrapper select, .dataTables_wrapper input[type=text]').addClass("form-control");
    $('.dataTables_wrapper select, .dataTables_wrapper input[type=text]').addClass("input-sm");
    reloadCheckedItems();
    setupFlatCheckboxes();
    setTableLoaded();
    setupTooltips();
}

function setElementLoading(elementSelect)
{
    $(elementSelect).html('<img src="' + CORE_ASSETS_ADMIN_WEB_ROOT + '/images/spinner_small.gif" width="16" height="16" alt="loading..." class="element_load_spinner"/>');
}

function toggleFullScreen()
{
    if (!document.fullscreenElement && // alternative standard method
            !document.mozFullScreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {  // current working methods
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        } else if (document.documentElement.msRequestFullscreen) {
            document.documentElement.msRequestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
            document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
            document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        }
    }
}