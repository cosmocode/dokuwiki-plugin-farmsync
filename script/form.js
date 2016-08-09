jQuery(function() {
    'use strict';

    jQuery('select[name="farmsync[source]"]').change(function (event) {
        var $this = jQuery(this);
        jQuery.get({
            'url': DOKU_BASE + 'lib/exe/ajax.php',
            'data': {
                'call': 'plugin_farmsync',
                'farmsync-source': $this.val(),
                'sectok': $this.closest('form').find('input[name=sectok]').val(),
                'farmsync-getstruct': true
            }
        }).done(function (data) {
            console.dir(data);
            jQuery('div.structsync').html(data);
            var checked = jQuery('input[name="farmsync[struct]"]').prop('checked');
            jQuery('div.structsync input[type=checkbox]').prop('checked', checked);
        }).fail(function (code, textstatus, jqxhr) {
            console.log(code);
            console.log(textstatus);
        });
    });

    jQuery('input[name="farmsync[struct]"]').change(function (event) {
        var $this = jQuery(this);
        if ($this.prop('checked')) {
            jQuery('div.structsync').show();
            jQuery('div.structsync input[type=checkbox]').prop('checked', 'checked');
        } else {
            jQuery('div.structsync').hide();
            jQuery('div.structsync input[type=checkbox]').prop('checked', '');
        }

    });
});
