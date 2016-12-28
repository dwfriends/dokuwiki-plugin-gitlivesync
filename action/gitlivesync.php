<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';
require_once dirname(__FILE__).'/../lib/Git.php';

class action_plugin_gitlivesync_gitlivesync extends DokuWiki_Action_Plugin {

	private $state = 0;
	private $date = 0;
	private $mapping = false;
	private $repoconfig = false;

	public function register(Doku_Event_Handler &$controller) {
		$controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'handle_pull_before_edit');
		$controller->register_hook('CHANGEREACT', 'AFTER', $this, 'handle_changereact');
		$controller->register_hook('DOKUWIKI_DONE', 'AFTER', $this, 'handle_periodic_sync');
		$controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'handle_io_wikipage_write');  //use for change the date of the current revision.
	}

	/*
	 * Get Git-Mapping from Config
	 */
	private function getMapping() {
		if ($this->mapping === false) {
			$mapconfig = $this->getConf('repositories');

			//No Configuration available?
			if ($mapconfig === false || $mapconfig === "") {
				$this->mapping = array();
			} else {
				$this->mapping = array();
				foreach ($mapconfig as $rn => $val) {
					if (isset($val['wikiMap']) && isset($val['gitMap'])) {
						$this->mapping[$val['wikiMap']] = $val['gitMap'];
					}
				}
				dbglog("GetMapConfig: ".print_r($this->mapping,true)." \n");
			}
		}

		return $this->mapping;

	}

	/**
	 * get Plugin COnfig entry
	 */
	public function getRepoConf($key,$reponame) {
		//ToDo:
		if ($this->repoconfig === false) {
			$this->repoconfig = $this->getConf('repositories');
		}

		if(isset($this->repoconfig[$reponame][$key])) {
			return $this->repoconfig[$reponame][$key];
		} else {
			return ""; //Fallback if not exists
		}
	}

	/*
	 * Change file permissions of a hole tree
	 */
	private function changePerm($file, $chmod) {
		if (@chown($file, posix_getuid())) {
			@chgrp($file, posix_getgid());
			@chmod($file, $chmod);
		}
	}

	/*
	 * get the gitdir
	 */
	private function getGitDir() {
		global $conf;

		$gitdir = $this->getConf('gitdir');
		if ($gitdir === false || $gitdir === "") {
			$gitdir = $conf['savedir']."/git/";
		}

		return DOKU_INC.$gitdir;

	}

	/* Get info from DokuWiki: Author */
	private function getAuthor() {
		return $GLOBALS['USERINFO']['name'];
	}

	/* Get info from DokuWiki: AuthorMail */
	private function getAuthorMail() {
		return $GLOBALS['USERINFO']['mail'];
	}

	/*
	 * Use helper mount_mapper to map between git and wiki path
	 */
	private function git_get_map($pageid) {
		require_once dirname(__FILE__).'/../helper/mount_mapper.php';

		$map = map_from_wiki($this->getMapping(), ':'.$pageid);
		list($repotype, $reponame, $filename) = preg_split("/:|\//",
			$map, 3);

		dbglog("[git_get_map] for Page '".$pageid."' results '".$map."' and splitted (type,name,file) '".$repotype."' '".$reponame."' '".$filename."'\n");

		if ($repotype != "git") {
			return false;
		}

		//dbglog("wikigit_getgitforns: ".$pageid." - ".$ispage." \n");

		return array("filename"=>$filename, "repository"=>$reponame);
	}

	/*
	 * GIT: Wrapper to initialize GitRepo object
	 */
	private function git($reponame) {
		$repodir = $this->getGitDir().$reponame;
		$repo = new GitRepo($repodir, false, false);

		//Load SSH key script
		$keyfile = $this->getGitDir()."/key/".$reponame.".rsa";
		$this->changePerm($keyfile,0600);

		$myssh = dirname(__FILE__)."/../git_ssh_custom.sh";
		if (file_exists($repodir.".sh")) {
			$myssh = $repodir.".sh";
		} else if (file_exists($this->getGitDir."master.sh")) {
			$myssh =  $this->getGitDir."master.sh";
		}
		@chmod($myssh, 0755);
		$repo->env = array(
			"LANG"=>"C",
			"GIT_SSH_KEY_FILE" => $keyfile,
			"GIT_SSH" => $myssh,
			//ToDo: Set Date: add parameter od choose other position to set
			//"GIT_AUTHOR_DATE" => $date." ".date("O"),
			//"GIT_COMMITTER_DATE" => $date." ".date("O"),
		);

		return $repo;
	}

	/*
	 * GIT: add changes, commit them to the git repo and perform push
	 */
	private function git_commit($reponame, $filePath, $message, $date=0) {

		dbglog("Commit git repository '".$reponame."' with file '".$filePath."' and Summary '".$message."'\n");

		try {
			$repo = $this->git($reponame);

			// set the author
			if ($this->getRepoConf('gitSetWikiAuthor',$reponame)) {
				$repo->git_commitadd =
					escapeshellarg(
						"--author=".
						$this->getAuthor().
						$this->getRepoConf('addStringToAuthor',$reponame).
						" <" .
						$this->getAuthorMail() .
						">"
					);
			}
			/*
			if ($this->getConf('addParams') != "") {
				$repo->git_commitadd .= " ".
					$this->getConf('addParams');
			}
			 */
			// see: https://git-scm.com/book/uz/v2/Git-Internals-Environment-Variables#Committing

			if ($date>0) {
				$repo->env["GIT_AUTHOR_DATE"] = $date." ".date("O");
				$repo->env["GIT_COMMITTER_DATE"] = $date." ".date("O");
			}

			//add the changed file and set the commit message
			$repo->add($filePath);
			$repo->commit($message);

			//if the push after Commit option is set we push the active branch to origin
			if ($this->getRepoConf('pushAfterCommit',$reponame)) {
				$branch = $repo->active_branch();
				if (trim($branch)=="") { $branch="master"; }
				$repo->push('origin',$branch);
			}
		} catch (Exception $e) {
			dbglog("Error while commit: '".$e->getMessage()."'\n");
		}
	}

	/*
	 * check if repo as updates
	 */
	private function git_perform_pull($reponame, $import=false) {
		try {
			$repo = $this->git($reponame);

			$rem = $repo->run("remote show");

			$oldrev = "";
			if (!$import) {
				try {
					$oldrev = trim($repo->run("rev-list -1 HEAD"));
				} catch (Exception $e) {
				}
			}


			if (trim($rem)!=="") {
				$branch = $repo->active_branch();
				if (trim($branch)=="") { $branch="master"; }

				$repo->pull('--rebase origin',$branch);
				$repo->run("checkout $branch");
			}

			$newrev = trim($repo->run("rev-list -1 HEAD"));
			dbglog("PerformPull: $reponame , $import, $oldrev, $newrev");

			if ($oldrev!=$newrev) {
				dbglog("Run import Changed sor repo $reponame from revision $oldrev to revision $newrev");
				$this->git_import_changes($repo, $reponame,
						$oldrev, $newrev);
			}

		} catch (Exception $e) {
			dbglog("Error while pull: '".$e->getMessage()."'\n");
		}
	}

	private function git_pull($pageid) {

		$fileinfo = $this->git_get_map($pageid);

		if (($fileinfo!==false) && (isset($fileinfo['repository']))) {

			$this->git_perform_pull($fileinfo['repository']);

		}

	}

	/*
	 * Check if the file to be edited need an update
	 */
	public function handle_pull_before_edit(Doku_Event &$event, $param) {
		global $ID;

		if ($event->data === "edit") {
			dbglog("Received edit request for:".$ID."\n");
			//Perform git pull for edit-file repo
			$this->git_pull($ID);
		}
	}

	/*
	 * Bring content to wiki
	 */
	private function changes_to_wiki($type, $file, $content, $summary,
			$author, $date, $minor = false) {
		global $conf, $lang, $INPUT;

		require_once dirname(__FILE__).'/../helper/mount_mapper.php';

		$map = map_from_git($this->getMapping(), $file);

		// delete leading ":"
		$id = substr($map,1);

		// if wikipage!
		if ($type == "page") {
			$id = preg_replace("/\.txt$/", "", $id);

			// workaround: set author:
			$tmp = $INPUT->server->str('REMOTE_USER');
			$INPUT->server->set('REMOTE_USER', $author);

			// workaround: set file modification date:
			$this->state=1;
			$this->date = $date;

			// save content
			saveWikiText(
				/*$date,*/
				$id,
				$content,
				$summary,
				$minor
			);

			// unset author
			$INPUT->server->set('REMOTE_USER',$tmp);

		} else {
			dbglog("handle git_import_changes for $id");
			// modify or add media file:
			$fn = mediaFN($id);
			$old=0;

			// BEGIN COPYED FROM DOKUWIKI SOURCE (simpler version):
			// see: media_upload_finish
			if (file_exists($fn)) {
				$old=@filemtime($fn);
			}

			if (($old>0) && (!file_exists(mediaFN($id, $old)))) {
				media_saveOldRevision($id);
			}

			//insertion of delete code
			//see: media_delete
			if ($content == "") {
				if (@unlink($fn)) {
					addMediaLogEntry($date, $id, DOKU_CHANGE_TYPE_DELETE, $lang['deleted']);
					io_sweepNS($id,'mediadir');
				}
			} else {
			//end insertion.

			io_createNamespace($id, 'media');

			file_put_contents($fn,$content);
			@touch($fn,$date);
			@clearstatcache(true,$fn);

			chmod($fn, $conf['fmode']);

			if ($old>0) {
				addMediaLogEntry($date, $id, DOKU_CHANGE_TYPE_EDIT);
			} else {
				addMediaLogEntry($date, $id, DOKU_CHANGE_TYPE_CREATE, $lang['created']);
			}
			// END COPYED.
			}
		}
	}

	/*
	 * get information from commit
	 */
	private function recognise_gitcommitstring($line,$repo="") {
		$minor = false;

		// parse output of command git log ...
		list($date, $rev, $author, $summary) = preg_split("/\t/",
				$line, 4);

		// remove mail address:
		$author = preg_replace("/\s*<.*$/", "", $author);
		// remove " (by DokuWiki)"
		$author = preg_replace("/\s*".preg_quote(trim($this->getRepoConf('addStringToAuthor',$repo)), "\s*/")."/i", "", $author);

		// is text minor in the commit message?
		if (preg_match("/\s+\(minor\)$/i", $summary)) {
			$minor = true;
			$summary = preg_replace("/ \(minor\)$/i", "", $summary);
		}
		// is an author name in the commit message?
		if (preg_match("/^\[(.*?)\]\s+/i", $summary, $matches)) {
			$author = $matches[1];
			$summary = preg_replace("/^\[(.*?)\]\s+/i","",$summary);
		}

		dbglog("[git_import_changes] Parsing commit message: ".
				print_r(array($date, $rev, $author, $summary),
					true));

		return array($date, $rev, $author, $summary, $minor);
	}

	/*
	 * (git) get files from a commit rev
	 */
	private function git_get_files_from_rev($repo, $rev) {
		$rev = trim($rev);
		if ($rev == "")
			return array();

		// git command, see: http://stackoverflow.com/questions/424071/list-all-the-files-for-a-commit-in-git
		//$files = trim($repo->run('diff-tree --no-commit-id --name-only -r '.$rev));
		$files = trim($repo->run('show --pretty="format:" --name-status '.$rev));

		$files_list = preg_split("/\n/", $files);
		dbglog("Import_git rev $rev filelist = ".print_r($files_list,true));
		return $files_list;
	}

	/*
	 * (git) get file content (of changes from a commit)
	 */
	private function git_get_file_content($repo, $rev, $file) {
		try {
			// git command, see: http://stackoverflow.com/questions/610208/how-to-retrieve-a-single-file-from-specific-revision-in-git
			$content = $repo->run('show '.$rev.':'.$file);
		} catch (Exception $e) {
			// catch: File not found.
			if (!preg_match("/does not exist in/i", $e->getMessage())) {
				throw($e);
			}
			$content = "";
		}
		return $content;
	}

	/*
	 * Get a changelog from git and modify wiki pages
	 */
	private function git_import_changes($repo, $reponame,
			$oldrev, $newrev) {

		if ($oldrev != "") { $oldrev .=".."; }

		// get changes (multiple commits):
		// git command, see: https://git-scm.com/docs/git-log
		// Format: %ct %H %an %s
		$log = $repo->run('log --reverse --format="%ct%x09%H%x09%an%x09%s" '.$oldrev.$newrev);
		$log_lines = preg_split("/\n/", $log);
		dbglog("GIt reverse-log: ".print_r($log_lines,true));
		foreach ($log_lines as $line) {
			list($date, $rev, $author, $summary, $minor) =
				$this->recognise_gitcommitstring($line,$reponame);

			// get content from single commit:
			$files_list = $this->git_get_files_from_rev($repo,$rev);
			dbglog("files in cmommit:".print_r($files_list,true));
			foreach ($files_list as $filestatus) {

				list($status,$file) = preg_split("/\s+/",$filestatus,2);
				dbglog("File: '$file' Status: '$status'");

				// get file content:
				$content = "";
				if ($status!="D") { //File not Deleted
					$content = $this->git_get_file_content($repo, $rev, $file);
				}

				// determine file type:
				if (preg_match("/\.txt$/i", $file)) {
					$type = "page";
				} else {
					$type = "media";
				}

				// create git path (to be translated)
				$gitfile = "git:".
					$reponame."/".
					$file;

				$this->changes_to_wiki($type,
					$gitfile,
					$content,
					$summary,
					$author,
					$date,
					$minor
				);
			}
		}
	}

	/*
	 * Handle: IO_WikiPage_write
	 * On update to an existing page this event is called twice,
	 * once for the transfer of the old version to the attic (rev will
	 * have a value) and once to write the new version of the page into
	 * the wiki (rev is false)
	 */
	public function handle_io_wikipage_write(Doku_Event &$event, $param) {
		if ($this->state==1) {  //Write new wikipage from git

			$rev = $event->data[3];

			if (!$rev) {
				if (touch($event->data[0][0],$this->date)){
					clearstatcache();
					dbglog("Try to changes time for '".$event->data[0][0]."' to: ".$this->date." and reads ".filemtime($event->data[0][0])." --");
				} else {
					dbglog("Cannot change time for '".$event->data[0][0]."'");
				}
				$this->date=0;
				$this->state=0;
			}
		}
	}

	/*
	 * Format a commit message for git
	 */
	public function commitmessage($type, $pageid, $action, $summary,
			$minor, $date, $repo="") {
		if ($summary == "") {
			$summary = $action." ".$type;
		}

		$message = $summary;
		if ($minor) {
			$message .= " (minor)";
		}

		if ($this->getRepoConf('addAuthorToCommitMessage',$repo)) {
			$message = "[".$this->getAuthor()."] ".$message;
		}

		return $message;
	}

	/*
	 * Bring changes (from wiki) into the git repo
	 */
	public function changes_to_git($type, $pageid, $content, $summary,
			$minor, $date) {
		$fileinfo = $this->git_get_map($pageid);
		if ($fileinfo === false)
			return;
		$repodir = $this->getGitDir().$fileinfo['repository'];

		if ($type=="page") {
			// Append txt
			$fileinfo['filename'] .= ".txt";

			if($content=="") {
				//delete page
				$action = "delete";
				@unlink($repodir."/".$fileinfo['filename']);
			} else {
				//edit page
				$action = "edit";
				file_put_contents($repodir."/".$fileinfo['filename'],$content);
			}
		} elseif ($type == "media") {
			if($content == "upload") {
				//upload media
				$action = "upload";
				$wikifile = mediaFN($pageid);
				dbglog("Copy media file from '".$wikifile."' to '".$fileinfo['repository']."/".$fileinfo['filename']."'\n");
				copy($wikifile,$repodir."/".$fileinfo['filename']);
			} elseif($content == "delete") {
				//delete media
				$action = "delete";
				@unlink($repodir."/".$fileinfo['filename']);
			} else {
				//unsupported
				return;
			}
		} else {
			//unsupported
			return;
		}
		$cmsg = $this->commitmessage($type, $pageid, $action,
				$summary, $minor, $date, $fileinfo['repository']);
		$this->git_commit($fileinfo['repository'],
				$fileinfo['filename'], $cmsg, $date);
	}
	/*
	 * Handle trigger from changereact plugin: Bring changes to git
	 */
	public function handle_changereact(Doku_Event &$event, $param) {
		if ($this->state==0) {
			dbglog("event CHANGEREACT catched:  ".print_r($event->data,true)." \n");
			list($type, $pageid, $content, $summary, $minor, $date) = $event->data;
			$this->changes_to_git($type, $pageid, $content,
				$summary, $minor, $date);
		}
	}

        /*
         * Part copied from action: periodic pull
         */
        public function getLastPull($repo="") {
                        //CHeck time
			if ($repo=="" || $repo=="global") {
                        $lastpull_file = $this->getGitDir()."lastpull";
			} else {
				$lastpull_file = $this->getGitDir().$repo.".lastpull";
			}

                        $lastpull_time = 0;
                        if (file_exists($lastpull_file)) {
                                $lastpull_time =  file_get_contents($lastpull_file);
                        }


                return $lastpull_time;

        }

        /*
         * Part copied from action: periodic pull
         */
        public function setLastPull($pulltime, $repo="") {
                        //CHeck time
                        if ($repo=="" || $repo=="global") {
                        $lastpull_file = $this->getGitDir()."lastpull";
                        } else {
                                $lastpull_file = $this->getGitDir().$repo.".lastpull";
                        }

                        file_put_contents($lastpull_file,$pulltime);
        }


	public function setRepositoryForcePull($repo,$force_import=false) {
		$file = $this->getGitDir().$repo.".force";
		touch($this->getGitDir()."forcepull");
		touch($file."pull");
		if ($force_import) {
			touch($file."import");
		}
	}

	public function getRepositoryForcePull($repo="",$force_import=false) {
		if ($repo=="") {
			if (file_exists($this->getGitDir()."forcepull")) {
				@unlink($this->getGitDir()."forcepull");
				return true;
			} else {
				return false;
			}
		} else {
			$file = $this->getGitDir().$repo.".force";
			if ($force_import) {
				if (file_exists($file."import")) {
					@unlink($file."import");
					@unlink($file."pull");
					return true;
				} else {
					return false;
				}
			} else {
				if (file_exists($file."pull")) {
					@unlink($file."pull");
					return true;
				} else {
					return false;
				}
			}
		}
	}

	/*
	 * Perform a periodic pull on all git repos
	 */
	public function handle_periodic_sync(Doku_Event &$event, $param) {


			foreach ($this->getMapping() as $d=>$map) {
				list($repotype, $reponame, $filename) = preg_split("/:|\//", $map, 3);

				if ($repotype === "git") {
					$lastpull_time=$this->getLastPull($reponame);
					$forcepull=$this->getRepositoryForcePull($reponame);
					if ($forcepull || ($this->getRepoConf('periodicPull', $reponame) && ($lastpull_time < (time()-$this->getRepoConf('periodicMinutes',$reponame)*60) ))) {

						dbglog("Perform Periodic pull for $reponame -- $d -> $g");
						$forceimport=$this->getRepositoryForcePull($reponame,true);
						$this->git_perform_pull($reponame,$forceimport);

						$this->setLastPull(time(),$reponame);
					}
				}
			}
		}

}

