jQuery(function(){
    'use strict';

    var $farmsync = jQuery('#plugin__farmsync');

    $farmsync.find('div.progress').slideUp();
    $farmsync.find('span.progress').click(function() {
        $farmsync.find('div.progress').slideToggle();
    });

    $farmsync.find('select.make_chosen').chosen().change(function() {
        var $this = jQuery(this);
        $this.trigger('chosen:updated');
        $this.val();
        $farmsync.find('input[type="checkbox"]').prop('disabled', false);
        $farmsync.find('input[type="checkbox"][name="farmsync-animals['+$this.val()+']"]').prop('disabled', true).prop('checked', false);
    }).change();

    $farmsync.find('div.result h2').click(function (event) {
        jQuery(this).next('div').slideToggle()
    });

    $farmsync.find('a.show_noconflicts').click(function (event) {
        jQuery(this).next('ul').slideToggle();
    });

    $farmsync.find('form button[name=diff]').click(function (event) {
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
        var source = jQuery('#results').data('source');

        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_farmsync',
                'farmsync-source': source,
                'farmsync-animal': animal,
                'farmsync-page': page,
                'farmsync-action': 'diff',
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
        var $this = jQuery(this);
        var animal = $this.parent('form').data('animal');
        var page = $this.parent('form').data('page');
        var $conflicts = $this.closest('div.result').find('h2 span');
        $conflicts.html(Number($conflicts.html()) - 1);
        if (Number($conflicts.html()) === 0) $this.closest('div.result').switchClass('withconflicts','noconflicts');
        $this.replaceWith('<span>'+ LANG.plugins.farmsync['done'] +'</span>');
        jQuery('form[data-animal="' + animal + '"][data-page="' + page + '"] button').hide();
    });
    $farmsync.find('form button[name=override]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $this = jQuery(this);
        var animal = $this.parent('form').data('animal');
        var page = $this.parent('form').data('page');
        var type = $this.parent('form').data('type') || 'page';
        var sectok = $this.parent('form').find('input[name="sectok"]').val();
        var source = jQuery('#results').data('source');
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_farmsync',
                'farmsync-source': source,
                'farmsync-animal': animal,
                'farmsync-page': page,
                'farmsync-action': 'overwrite',
                'farmsync-type': type,
                'sectok': sectok
            }
        ).done(function (data, textStatus, jqXHR) {
            var $conflicts = $this.closest('div.result').find('h2 span');
            $conflicts.html(Number($conflicts.html()) - 1);
            if (Number($conflicts.html()) === 0) $this.closest('div.result').switchClass('withconflicts','noconflicts');
            $this.replaceWith('<span>'+ LANG.plugins.farmsync['done'] +'</span>');
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $this.replaceWith('<span>Failure! ' + textStatus + ' ' + errorThrown + '</span><div>' + jqXHR.responseText + '</div>');
            console.dir(jqXHR);
        });
        jQuery('form[data-animal="' + animal + '"][data-page="' + page + '"] button').hide();
    });

    var scrollToConflict = function ($event) {
        var line = Number(jQuery(this).data('line'));
        var $form = jQuery(this).closest('form');
        $form.find('textarea[name=editarea]').scrollToLine(line);
        generateConflictLinks($form);
    };

    var scrollToFirstConflict = function (element) {
        var $elem = jQuery(element);
        var lines = $elem.closest('form').find('textarea[name=editarea]').val().split("\n");
        for (var index = 0; index < lines.length; index += 1) {
            if (lines[index].substring(0,'✎———————'.length) == '✎———————') {
                $elem.scrollToLine(index);
                break;
            }
        }
    };

    var generateConflictLinks = function ($form) {
        var lines = $form.find('textarea[name=editarea]').val().split("\n");
        var conflicts = [];
        $form.find('.conflictlist ol').html('');
        for (var index = 0; index < lines.length; index += 1) {
            if (lines[index].substring(0,'✎———————'.length) == '✎———————') {
                conflicts.push(index);
                var link = jQuery('<li class="conflict" data-line="'+index+'"></li>').text(lines[index+1].substring(0,40)+'...');
                $form.find('.conflictlist ol').append(link.click(scrollToConflict));
            }
        }
    };

    $farmsync.find('form button[name=edit]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $form = jQuery(this).parent('form');
        $form.find('button[name=theirs],button[name=override],button[name=edit]').hide();
        $form.find('div.editconflict').show();
        generateConflictLinks($form);
        scrollToFirstConflict($form.find('textarea[name=editarea]'));
        $form.find('button[name=save],button[name=cancel]').show();
    });

    $farmsync.find('form button[name=cancel]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $form = jQuery(this).parent('form');
        $form.find('button[name=theirs],button[name=override],button[name=edit]').show();
        $form.find('div.editconflict').hide();
        $form.find('textarea[name=editarea]').val($form.find('textarea[name=backup]').val());
        $form.find('button[name=save],button[name=cancel]').hide();
    });

    $farmsync.find('form button[name=save]').click(function(event) {
        event.stopPropagation();
        event.preventDefault();
        var $form = jQuery(this).parent('form');
        var $content = $form.find('textarea[name=editarea]').val();
        if ($content.indexOf("✎———————") !== -1 ) {
            scrollToFirstConflict($form.find('textarea[name=editarea]'));
            alert('There are still unresolved conflicts left');
            return;
        }
        var animal = $form.data('animal');
        var page = $form.data('page');
        var type = $this.parent('form').data('type') || 'page';
        var sectok = $form.find('input[name="sectok"]').val();
        var source = jQuery('#results').data('source');
        jQuery.post(
            DOKU_BASE + 'lib/exe/ajax.php',
            {
                call: 'plugin_farmsync',
                'farmsync-source': source,
                'farmsync-animal': animal,
                'farmsync-page': page,
                'farmsync-action': 'overwrite',
                'farmsync-content': $content,
                'farmsync-type': type,
                'sectok': sectok
            }
        ).done(function (data, textStatus, jqXHR) {
            var $conflicts = $form.closest('div.result').find('h2 span');
            $conflicts.html(Number($conflicts.html()) - 1);
            if (Number($conflicts.html()) === 0) $form.closest('div.result').switchClass('withconflicts','noconflicts');
            $form.replaceWith('<span>'+ LANG.plugins.farmsync['done'] +'</span>');
        }).fail(function (jqXHR, textStatus, errorThrown) {
            $form.replaceWith('<span>Failure! ' + textStatus + ' ' + errorThrown + '</span>');
            console.dir(jqXHR);
        });
        $form.find('textarea[name=editarea]').hide();
        $form.find('button[name=save],button[name=cancel]').hide();
    });
});
