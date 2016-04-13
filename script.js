jQuery(function(){
    'use strict';
    jQuery('form button[name=theirs]').click(function(event){
        var animal = jQuery(this).parent('form').data('animal');
        var page = jQuery(this).parent('form').data('page');
        jQuery(this).replaceWith('<span>Done!</span>');
        jQuery('form[data-animal="' + animal + '"][data-page="' + page + '"] button').hide();

    });
    jQuery('form button[name=override]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $this = jQuery(this);
        var animal = $this.parent('form').data('animal');
        var page = $this.parent('form').data('page');
        var sectok = $this.parent('form').find('input[name="sectok"]').val();
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_farmsync',
                'farmsync-animal': animal,
                'farmsync-page': page,
                'farmsync-action': 'overwrite',
                'sectok': sectok
            }
        ).done(function () {
            $this.replaceWith('<span>Done!</span>');
        }).fail(function () {
            $this.replaceWith('<span>Failure!</span>');
        });
        jQuery('form[data-animal="' + animal + '"][data-page="' + page + '"] button').hide();
    });
});
