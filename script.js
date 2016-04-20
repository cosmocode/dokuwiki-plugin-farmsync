jQuery(function(){
    'use strict';

    var $farmsync = jQuery('#plugin__farmsync');

    $farmsync.find('div.progress').slideUp();

    $farmsync.find('div.result h2').click(function (event) {
        jQuery(this).next('div').slideToggle()
    });

    $farmsync.find('a.show_noconflicts').click(function (event) {
        jQuery(this).next('ul').slideToggle();
    });

    $farmsync.find('form button[name=diff]').click(function (event) {
        // FIXME implement via AJAX
        event.stopPropagation();
        event.preventDefault();

        if ( jQuery(this).closest('div.li').find('table.diff').length ) {
            jQuery(this).closest('div.li').find('table.diff').toggle();
            return;
        }

        var $this = jQuery(this);
        var sectok = $this.parent('form').find('input[name="sectok"]').val();
        var animal = $this.parent('form').data('animal');
        var page = $this.parent('form').data('page');

        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_farmsync',
                'farmsync-animal': animal,
                'farmsync-page': page,
                'farmsync-action': 'diff',
                'farmsync-getdiff': true,
                'sectok': sectok
            }
        ).done(function (data, textStatus, jqXHR) {
            $this.closest('div.li').append(data);
            $this.closest('div.li').find('table.diff').show()
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $this.closest('div.li').append('<span>Failure! ' + textStatus + ' ' + errorThrown + '</span>');
            console.dir(jqXHR);
        });
    });

    $farmsync.find('form button[name=theirs]').click(function(event){
        var animal = jQuery(this).parent('form').data('animal');
        var page = jQuery(this).parent('form').data('page');
        jQuery(this).replaceWith('<span>Done!</span>');
        jQuery('form[data-animal="' + animal + '"][data-page="' + page + '"] button').hide();

    });
    $farmsync.find('form button[name=override]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $this = jQuery(this);
        var animal = $this.parent('form').data('animal');
        var page = $this.parent('form').data('page');
        var ismedia = $this.parent('form').data('ismedia');
        var sectok = $this.parent('form').find('input[name="sectok"]').val();
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_farmsync',
                'farmsync-animal': animal,
                'farmsync-page': page,
                'farmsync-action': 'overwrite',
                'farmsync-ismedia': ismedia,
                'sectok': sectok
            }
        ).done(function (data, textStatus, jqXHR) {
            $this.replaceWith('<span>Done!</span>');
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $this.replaceWith('<span>Failure! ' + textStatus + ' ' + errorThrown + '</span>');
            console.dir(jqXHR);
        });
        jQuery('form[data-animal="' + animal + '"][data-page="' + page + '"] button').hide();
    });

    $farmsync.find('form button[name=edit]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $form = jQuery(this).parent('form');
        $form.find('button[name=theirs],button[name=override],button[name=edit]').hide();
        $form.find('textarea[name=editarea]').show();
        $form.find('button[name=save],button[name=cancel]').show();
    });

    $farmsync.find('form button[name=cancel]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $form = jQuery(this).parent('form');
        $form.find('button[name=theirs],button[name=override],button[name=edit]').show();
        $form.find('textarea[name=editarea]').hide().val($form.find('textarea[name=backup]').val());
        $form.find('button[name=save],button[name=cancel]').hide();
    });

    $farmsync.find('form button[name=save]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $form = jQuery(this).parent('form');
        var animal = $form.data('animal');
        var page = $form.data('page');
        var sectok = $form.find('input[name="sectok"]').val();
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_farmsync',
                'farmsync-animal': animal,
                'farmsync-page': page,
                'farmsync-action': 'overwrite',
                'farmsync-content': $form.find('textarea[name=editarea]').val(),
                'sectok': sectok
            }
        ).done(function () {
            $form.replaceWith('<span>Done!</span>');
        }).fail(function () {
            $form.replaceWith('<span>Failure!</span>');
        });
        $form.find('textarea[name=editarea]').hide();
        $form.find('button[name=save],button[name=cancel]').hide();
    });
});
