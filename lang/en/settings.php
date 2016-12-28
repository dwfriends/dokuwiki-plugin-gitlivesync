<?php

$lang['pushAfterCommit'] = "Push the git repository to remote after changing DokuWiki content";
$lang['periodicPull'] = "Perform a periodic pull to receive updates from remote";
$lang['periodicMinutes'] = "Time between periodic pulls in minutes";
$lang['gitDataDirectory'] = "Specify the directory to save the git repositories inside. Leave blank to use default <code>data/gitdata/</code>";
$lang['addStringToAuthor'] = "Appends an additional String to the Author used for git commit messages.";
$lang['addAuthorToCommitMessage'] = "Adds the Author to the CommitMessage.";
$lang['gitSetWikiAuthor'] = "Forces git to use the wiki author name as git author/comitter.";

$lang_repostatus['gitMap'] = "(Sub-) directory prefix in the git repository to sync";
$lang_repostatus['wikiMap'] = "Wiki namespace prefix to sync";
$lang_repostatus['_delete'] = "Delete this repository?";
$lang_repostatus['_lastPull'] = "Date of the last pull";
$lang_repostatus['_key'] = "SSH key";
$lang_repostatus['_remote'] = "Remote";
$lang_repostatus['_branch'] = "Branch";
$lang_repostatus['_revision'] = "Revision";

$lang2 = $lang;

global $conf;
if (isset($conf['plugin']['gitlivesync']['repositories'])) {
foreach ($conf['plugin']['gitlivesync']['repositories'] as $repo => $repoconfig) {
        foreach ($lang_repostatus as $key=>$value) {
                $lang["repositories".CM_KEYMARKER.$repo.CM_KEYMARKER.$key] = $value;
        }
        foreach ($lang2 as $key=>$value) {
                $lang["repositories".CM_KEYMARKER.$repo.CM_KEYMARKER.$key] = $value;
        }
}
}

$lang['repositories'.CM_KEYMARKER.'_nEwRePo'] = "Name for new repository:<br><small>Repository will be created after saving.</small>";
