<?php

/** config meta for the gitlivesync plugin
 */

//Global PluginConfig
$meta['gitDataDirectory'] = array('string','_caution' => 'danger');
$meta['repositories'.CM_KEYMARKER.'_nEwRePo'] = array('gitlivesync_newrepo','_caution' => 'danger');

//Settings and Status for each repository
$meta_repostatus['gitMap'] = array('gitlivesync','_caution' => 'danger');
$meta_repostatus['wikiMap'] = array('gitlivesync','_caution' => 'danger');
$meta_repostatus['_remote'] = array('gitlivesync_repoinfo_remote','_reponame' => 'new_test');
$meta_repostatus['_lastPull'] = array('gitlivesync_lastpull','_reponame' => 'new_test');
$meta_repostatus['_key'] = array('gitlivesync_sshkey','_reponame'=>'new_test');
$meta_repostatus['_revision'] = array('gitlivesync_repoinfo_revision','_reponame' => 'new_test');
$meta_repostatus['_branch'] = array('gitlivesync_repoinfo_branch','_reponame' => 'new_test');

$meta_repostatus['pushAfterCommit'] = array('gitlivesync_onoff');
$meta_repostatus['periodicPull'] = array('gitlivesync_onoff');
$meta_repostatus['periodicMinutes'] = array('gitlivesync_numeric');
$meta_repostatus['addStringToAuthor'] = array('gitlivesync');
$meta_repostatus['addAuthorToCommitMessage'] = array('gitlivesync_onoff');
$meta_repostatus['gitSetWikiAuthor'] = array('gitlivesync_onoff');
$meta_repostatus['_delete'] = array('gitlivesync_delrepo','_reponame' => 'new_test');

//Default Settings for new Repository
$defaultconf['pushAfterCommit'] = 1;
$defaultconf['periodicPull'] = 1;
$defaultconf['periodicMinutes'] = 6;
$defaultconf['addStringToAuthor'] = ' (by DokuWiki)';
$defaultconf['addAuthorToCommitMessage'] = 0;
$defaultconf['gitSetWikiAuthor'] = 1;
$defaultconf['gitDataDirectory'] = '';
$defaultconf['gitToWikiMap'] = '';

//Generate $meta entries for each Reository from Config.
global $conf;
if (isset($conf['plugin']['gitlivesync']['repositories'])) {
	foreach ($conf['plugin']['gitlivesync']['repositories'] as $repo => $repoconfig) {
		$meta['_h_'.$repo] = array('fieldset2', "_text"=>"GitLiveSync: " . $repo);
		foreach ($meta_repostatus as $key=>$value) {
			$meta["repositories".CM_KEYMARKER.$repo.CM_KEYMARKER.$key] = $value;
			$meta["repositories".CM_KEYMARKER.$repo.CM_KEYMARKER.$key]['_reponame'] = $repo;
			if (isset($defaultconf[$key])) {
				$meta["repositories".CM_KEYMARKER.$repo.CM_KEYMARKER.$key]['_mydefault'] = $defaultconf[$key];
			}
		}
	}
}


