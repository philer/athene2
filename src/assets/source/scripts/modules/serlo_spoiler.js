/*global define*/
define(['jquery'], function ($) {
    "use strict";
    var Spoiler;

    Spoiler = function () {
        return $(this).each(function () {
            $('> .spoiler-teaser', this)
                .unbind('click')
                .first()
                .click(function (e) {
                    var glyphicon = $(this).find('.fa');
                    e.preventDefault();
                    $(this).next('.spoiler-content').slideToggle();
                    glyphicon.toggleClass('fa-plus');
                    glyphicon.toggleClass('fa-minus');
                    return;
                });
        });
    };

    $.fn.Spoiler = Spoiler;
});