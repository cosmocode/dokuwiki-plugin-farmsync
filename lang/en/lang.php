<?php
/**
 * English language file for farmsync plugin
 *
 * @author Michael Große <grosse@cosmocode.de>
 */

// menu entry for admin plugins
$lang['menu'] = 'Farming-Updates';

$lang['merge_animal'] = 'The conflicting text in the animal.';
$lang['merge_source'] = 'The conflicting text in the source.';

$lang['heading:Update animals'] = 'Update pages and media in farm animals';
$lang['heading:Update done'] = 'Finished updating the animals:';
$lang['heading:animal noconflict'] = '%s: updated without conflicts';
$lang['heading:animal conflict'] = '%s: <span>%d</span> conflicts to solve';
$lang['heading:conflicts'] = 'Remaining Conflicts:';
$lang['heading:templates'] = 'Templates';
$lang['heading:pages'] = 'Pages';
$lang['heading:media'] = 'Media';
$lang['heading:struct'] = 'Struct';

$lang['legend:choose source'] = 'Source from which to update';
$lang['legend:choose animals'] = 'Animals to update';
$lang['legend:choose documents'] = 'Documents to update';

$lang['label:source'] = 'Source';
$lang['label:PageEntry'] = 'Pages/Namespaces to update';
$lang['label:MediaEntry'] = "Media/Media-Namespaces to update";
$lang['label:struct synchronisation'] = 'Synchronize struct data?';

$lang['progress:pages'] = 'Pages of <b>%s</b>(%s/%s) are done';
$lang['progress:templates'] = 'Templates of <b>%s</b>(%s/%s) are done';
$lang['progress:media'] = 'Media-files of <b>%s</b>(%s/%s) are done';
$lang['progress:struct'] = 'Struct-data of <b>%s</b>(%s/%s) is done';

$lang['mergeresult:new file'] = 'is new file';
$lang['mergeresult:file overwritten'] = 'was overwritten';
$lang['mergeresult:merged without conflicts'] = 'was merged automatically';
$lang['mergeresult:merged with conflicts'] = 'has conflicts';
$lang['mergeresult:unchanged'] = 'remained unchanged';

$lang['button:edit'] = "Edit";
$lang['button:cancel'] = "Cancel";
$lang['button:keep'] = "Keep theirs";
$lang['button:overwrite'] = "Overwrite theirs";
$lang['button:edit'] = "Edit";
$lang['button:diff'] = "Show diff";
$lang['button:submit'] = "Submit";

$lang['link:nocoflictitems'] = 'Toggle nonconflicting items';
$lang['link:srcversion'] = 'Source Version';
$lang['link:dstversion'] = 'Target Version';

$lang['diff:animal'] = "Page currently at Animal";
$lang['diff:source'] = "Page to be copied from Source";

$lang['notice:struct disabled'] = "To synchronize struct data, enable the plugin in the farmer.";

$lang['js']['done'] = 'Done!';

//Setup VIM: ex: et ts=4 :
