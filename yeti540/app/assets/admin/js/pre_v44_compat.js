/* 
 * For compatibility with pre Yetishare v4.4 pages, such as third party plugins.
 */

$(document).ready(function () {
    $('.main_container > div.row .col_12 .widget').removeClass('widget');
    $('.main_container > div.row .col_12 .widget_inside .col_12').not('.ignore-compat').wrap('<div class=\'x_panel\'></div>');
    $('.main_container > div.row .col_12 form').addClass('form-horizontal form-label-left');
    $('.main_container > div.row .col_12 input[type=text]:not(.button), .main_container > div.row .col_12 input[type=password]:not(.button), .main_container > div.row .col_12 select, .main_container > div.row .col_12 textarea').addClass('form-control');
    $('.main_container > div.row .col_12 form .col_8 .form > div.clearfix').addClass('form-group');
    $('.main_container > div.row .col_12 form label').addClass('control-label col-md-3 col-sm-3 col-xs-12');
    $('.main_container > div.row .col_12 form .input').addClass('col-md-6 col-sm-6 col-xs-12');
    $('.main_container > div.row .col_12 form .col_4').not('.no_x_title').addClass('x_title');
    $('.main_container > div.row .col_12 form .col_8').not('.no_x_content').addClass('x_content');
    $('.main_container > div.row .col_12 form .x_title h3').each(function() {
        $(this).replaceWith('<h2>'+$(this).text()+'</h2><div class="clearfix"></div>');
    });
    $('.main_container > div.row .col_12 form .x_title p').each(function() {
        $(this).parent().next('.x_content').prepend($(this));
    });
    $('.main_container > div.row .col_12 form .col_8 .button').addClass('btn').removeClass('button');
    $('.main_container > div.row .col_12 form .col_8 .btn.blue').addClass('btn-primary').removeClass('blue');
    $('.main_container > div.row .col_12 form .col_8 .no-label').addClass('col-md-offset-3');
    $('.main_container > div.row .col_12 form .col_8 table, .main_container > div.row .widget_inside table').addClass('table table-striped table-only-border bulk_action');
    
    $('.main_container > div.row .col_12 .clearfix > h2').each(function() {
        $(this).replaceWith('<div class="page-title"><div class="title_left"><h3>'+$(this).text()+'</h3></div></div><div class="clearfix"></div>');
    });

});