/**
 * Scroll a textarea to a given line
 *
 *
 * Based upon:
 *   * http://stackoverflow.com/questions/5458655/jquery-scroll-textarea-to-given-position (question and answer)
 *   * http://wiki.sheep.art.pl/Textarea%20Scrolling
 *   * https://github.com/splitbrain/dokuwiki/blob/9ae98fa5862ce523ff8d8f97bd249bc67313dbbc/lib/scripts/edit.js#L385
 *     by @splitbrain
 *   * The jQuery plugin-template of @JayDeeDee
 */

(function($) {
    'use strict';

    $.fn.scrollToLine = function (linenumber) {

        return this.each(function() {

            var $this = $(this);
            try{
                // getting given textarea contents
                var text = $this.text().split("\n").slice(0,linenumber).join('<br>');

                // creating a DIV that is an exact copy of textarea
                var $copyDiv = $('<div></div>')
                    .append(text) // making newlines look the same
                    .css('width', $this.width() + 'px') // width with scrollbar, but close enough
                    .css('font-size', $this.css('font-size'))
                    .css('font-family', $this.css('font-family'))
                    .css('padding', $this.css('padding'))
                    .css('line-height', $this.css('line-height'));
                // inserting new div at the end of the document
                $copyDiv.appendTo('body');
                // The height of the new element is close to the position of the line in the textarea
                var pos = $copyDiv[0].scrollHeight;
                // now, we know where to scroll!
                $this.scrollTop(pos);
                // no need for DIV anymore
                $copyDiv.remove();

            }catch(err){
                console.log(err);
                console.dir(err);
            }
        });

    };

})(jQuery);
