<?php
/**
 * Fieldset to Display a Section for each Repository
 */
class setting_fieldset2 extends setting_fieldset {
	function prompt(&$plugin) {
		return $this->_text;
	}
}

/**
 * gitlivesync_newrepo setting class for adding a new Repository to Configuration
 */
class setting_gitlivesync_newrepo extends setting_string {
	function html(&$plugin, $echo=false) {
		$res = parent::html($plugin,$echo);
		$res[1] .= '<br><button type="submit" name="submit">Add Repository</button>';
		return $res;
	}
	public function _out_key($pretty=false,$url=false) {
		if ($pretty) {
			return "";//str_replace(CM_KEYMARKER,"»",$this->_key);
		} else {
			$key = $this->_local;
			if (substr($this->_local,0,4)!="git:") {
				$this->_local = "git:".$this->_local;
			} else {
				$key= substr($key,4); 
			}
			return str_replace("_nEwRePo",''.$key,str_replace(CM_KEYMARKER,"']['",$this->_key.CM_KEYMARKER."gitMap"));
		}
	}

}

/**
 * A text-only view setting class to view/display repository status
 */
class setting_gitlivesync_textonly extends setting_gitlivesync {

	function html(&$plugin, $echo=false) {
		$disable = '';

	$key = htmlspecialchars($this->_key);
	//$value = htmlspecialchars($this->_input);
	$value = $this->_input;


	$label = '<label>'.$this->prompt($plugin).'</label>';
	$input = $value;
	return array($label,$input);
	}
}

/**
 * gitlivesync string setting class which impelents Git-Repository Functions and handels Repository delete
 */
class setting_gitlivesync extends setting_string {
	var $_reponame = '';

	/**
	 * Returns the configured Git-Directory for the plugin
	 */
	function getGitDir() {
                global $conf;

                $gitdir = $conf['plugin']['gitlivesync']['gitdir'];
                if (!isset($gitdir) || $gitdir === false || $gitdir === "") {
                        $gitdir = $conf['savedir']."/git/";
                }

                return DOKU_INC.$gitdir;

	}

	/**
	 * Returns an initialized Git-Object for the repository specified by the _reponame var of this setting class
	 */
	function initializeGitObject() {
	 
		$repodir = $this->getGitDir().$this->_reponame;

                if (!is_dir($repodir)) {
			mkdir($repodir,0770,true);
		}
		if (is_dir($repodir)) {
				$repo = new GitRepo($repodir, true, false);
                        	if (!($repo->is_repo_present())) {
                                	$repo->run('init');
                        	}
				return $repo;
		}

		return false;

	}

	/**
	 * Handels to set the defaultConfiguration to new Repositories
	 */
	public function initialize($default, $local, $protected) {
		parent::initialize($default,$local,$protected);

		if (!isset($local) && isset($this->_mydefault)) {
			$this->_local = $this->_mydefault;
		}
	}

	/**
	 * Handles deleteRepository by deleting the Setting from the configuration file save output (return "")
	 */
	function out($var, $fmt='php') {
		if(isset($_REQUEST['gitlivesync_deleteRepo'])) {
                        if ($_REQUEST['gitlivesync_deleteRepo']==$this->_reponame) {
				return '';
			}
		}
		return parent::out($var,$fmt);
	}
}

/**
 * Setting class onoff with own default value and repository delete handle.
 */
class setting_gitlivesync_onoff extends setting_onoff {
	var $_reponame = '';
        public function initialize($default, $local, $protected) {
                parent::initialize($default,$local,$protected);
                
                if (!isset($local) && isset($this->_mydefault)) {
                        $this->_local = $this->_mydefault;
                }
        }
        function out($var, $fmt='php') {
                if(isset($_REQUEST['gitlivesync_deleteRepo'])) {
                        if ($_REQUEST['gitlivesync_deleteRepo']==$this->_reponame) {
                                return '';
                        }
                }
                return parent::out($var,$fmt);
        }

}

/**
 * Setting class numeric with own default value and repository delete handle.
 */
class setting_gitlivesync_numeric extends setting_numeric {
        var $_reponame = '';
        public function initialize($default, $local, $protected) {
                parent::initialize($default,$local,$protected);
                
                if (!isset($local) && isset($this->_mydefault)) {
                        $this->_local = $this->_mydefault;
                }
        }
        function out($var, $fmt='php') {
                if(isset($_REQUEST['gitlivesync_deleteRepo'])) {
                        if ($_REQUEST['gitlivesync_deleteRepo']==$this->_reponame) {
                                return '';
                        }
                }
                return parent::out($var,$fmt);
        }

}

/**
 * Setting class to delete an gitlivesync-repository
 */
class setting_gitlivesync_delrepo extends setting_gitlivesync {

	//See: http://php.net/manual/de/function.rmdir.php#110489
	private function _delTree($dir, $del=false) { 
		$files = array_diff(scandir($dir), array('.','..')); 
		foreach ($files as $file) { 
			(is_dir("$dir/$file")) ? $this->_delTree("$dir/$file",true) : @unlink("$dir/$file"); 
		} 

		if ($del) {
			return @rmdir($dir);
		} else {
			return;
		} 
	}

	function initialize($default,$local,$protected) {
		global $conf;
		//ToDo;
                        if (isset($_REQUEST['gitlivesync_deleteRepo'])) {
                                if ($_REQUEST['gitlivesync_deleteRepo']==$this->_reponame) {
                                        if (checkSecurityToken()) {
						//ToDo: Handle Delete Repo
						$repodir = $this->getGitDir().$this->_reponame;
						$keyfile = $this->getGitDir()."key/".$this->_reponame.".rsa";
						$file = $this->getGitDir().$this->_reponame.".force";
						$lastpull_file = $this->getGitDir().$this->_reponame.".lastpull";
						$this->_delTree($repodir,true);
						@unlink($keyfile.".pub");
						@unlink($keyfile);
						@unlink($file."pull");
						@unlink($file."import");
						@unlink($lastpull_file);
						//ToDo: $this->_delTree(map); bzw ist eine löschung im zugehörigen config-objekt sinnvoll (wikiMap).
						$this->_delTree($conf['datadir'].str_replace(":","/",$conf['plugin']['gitlivesync']['repositories'][$this->_reponame]['wikiMap']));
						$this->_delTree($conf['mediadir'].str_replace(":","/",$conf['plugin']['gitlivesync']['repositories'][$this->_reponame]['wikiMap']));
					}
				}
			}
	}

	function html(&$plugin, $echo = false) {
		global $ID;
		//global $conf;

		$key = htmlspecialchars($this->_key);

		$label = '<label for="config___'.$key.'">'.$this->prompt($plugin).'</label>';
		$input = '<div class="input"><input id="config___'.$key.'" name="config['.$key.']" type="checkbox" class="checkbox" value="1"/>  ';
		$input .= '<button type="submit" name="gitlivesync_deleteRepo" value="'.$this->_reponame.'" onclick="if (document.getElementById(\'config___'.$key.'\').checked) { return confirm(\'delete Repo.\'); } else { return false; }">Delete Repository</button>';
		//$input .= "Wiki-Path:".$conf['mediadir'].str_replace(":","/",$conf['plugin']['gitlivesync']['repositories'][$this->_reponame]['wikiMap'])."bla:".$conf['datadir'];

		return array($label,$input);
	} 

}

/**
 * Setting class to display the creation time of an existing key for the repository and allows to generate a (new) one and offers the corresponding private key for donload
 */
class setting_gitlivesync_sshkey extends setting_gitlivesync_textonly {

	//see: http://stackoverflow.com/questions/6648337/generate-ssh-keypair-form-php
	private function _sshEncodePublicKey($privKey) {
		$keyInfo = openssl_pkey_get_details($privKey);
		$buffer  = pack("N", 7) . "ssh-rsa" .
		$this->_sshEncodeBuffer($keyInfo['rsa']['e']) .
		$this->_sshEncodeBuffer($keyInfo['rsa']['n']);
		return "ssh-rsa " . base64_encode($buffer);
	}

	private function _sshEncodeBuffer($buffer) {
		$len = strlen($buffer);
		if (ord($buffer[0]) & 0x80) {
			$len++;
			$buffer = "\x00" . $buffer;
		}
		return pack("Na*", $len, $buffer);
	}


	function initialize($default,$local,$protected) {
		global $ID;

		if ($this->_reponame != "") {
			if (!file_exists($this->getGitDir()."key/")) mkdir($this->getGitDir()."key/",0777,true);
			$keyfile = $this->getGitDir()."key/".$this->_reponame.".rsa";

			if (isset($_REQUEST['gitlivesync_generateKey'])) {
				if ($_REQUEST['gitlivesync_generateKey']==$this->_reponame) {
					if (checkSecurityToken()) {
						$rsaKey = openssl_pkey_new(array(
                                			'private_key_bits' => 1024,
                                			'private_key_type' => OPENSSL_KEYTYPE_RSA));

                       				$privKey = openssl_pkey_get_private($rsaKey);
                        			openssl_pkey_export($privKey, $pem); //Private Key
                        			$pubKey = $this->_sshEncodePublicKey($rsaKey); //Public Key

                        			$umask = umask(0066);
                        			@unlink($keyfile);
                        			file_put_contents($keyfile, $pem); //save private key into file
                        			@unlink($keyfile.'.rsa.pub');
                        			file_put_contents($keyfile.'.pub', $pubKey); //save public key into file

					}
				}
			}

			$keyfile .=".pub";
			if(file_exists($keyfile)) {
				$this->_input = "Key generated on: ".@date("d.m.Y H:m:s",@filemtime($keyfile));
				$this->_input .= "<br>[ <a href=\"".script()."?id=$ID&do=admin&page=config&gitlivesync_generateKey=$this->_reponame&sectok=".getSecurityToken()."\" onclick=\"return confirm('Are you sure to generate a new Key? The old one will be deleted.')\">Generate NEW Key</a> |";
				$this->_input .= " <a download=\"".$this->_reponame.".rsa.pub\" href=\"data:application/octet-stream;charset=utf-8;base64,".base64_encode(file_get_contents($keyfile))."\">Download Public Key</a> ]";
			} else {
				$this->_input = "No Key generated for this Repository";
				$this->_input .= "<br>[ <a href=\"".script()."?id=$ID&do=admin&page=config&gitlivesync_generateKey=$this->_reponame&sectok=".getSecurityToken()."\">Generate Key</a> ]";
			}
		}
	}

}

/**
 * Setting class to display the gitlivesync-lastpull of an repositora and allows to force an pull/import
 */
class setting_gitlivesync_lastpull extends setting_gitlivesync_textonly {
        public function setRepositoryForcePull($force_import=false) {
                $file = $this->getGitDir().$this->_reponame.".force";
                touch($this->getGitDir()."forcepull");
                touch($file."pull");
                if ($force_import) {
                        touch($file."import");
                }
        }

	function initialize($default,$local,$protected) {
		global $conf;
		global $ID;

		if ($this->_reponame != "") {
                        $lastpull_file = $this->getGitDir().$this->_reponame.".lastpull";

                        $lastpull_time = 0;
                        if (file_exists($lastpull_file)) {
                                $lastpull_time =  file_get_contents($lastpull_file);
                        }

			if ($lastpull_time != 0) {
				$this->_input = date("d.m.Y H:m:s",$lastpull_time)."<br>[ ";
			} else {
				$this->_input = "No Pull for this Repository.<br>[ ";
			}
				$this->_input .= "<a href=\"".script()."?id=$ID&do=admin&page=config&gitlivesync_importpull=$this->_reponame&sectok=".getSecurityToken()."\">Pull and Import Changes</a> | ";
			//}
			$this->_input .= "<a href=\"".script()."?id=$ID&do=admin&page=config&gitlivesync_pull=$this->_reponame&sectok=".getSecurityToken()."\">Pull</a> ]";

			if (isset($_REQUEST['gitlivesync_pull'])) {
				if ($_REQUEST['gitlivesync_pull']==$this->_reponame) {
					if (checkSecurityToken()) {
						$this->setRepositoryForcePull();
						$this->_input .= "<br> Pull forced for next Page load.";
					}
				}
			}
                        if (isset($_REQUEST['gitlivesync_importpull'])) {
                                if ($_REQUEST['gitlivesync_importpull']==$this->_reponame) {
                                        if (checkSecurityToken()) {
                                                $this->setRepositoryForcePull(true);
                                                $this->_input .= "<br> Pull and Import forced for next Page load.";
                                        }
                                }
                        }

		} else {
			$this->_input="No Repository given.";
		}
	}
}

/**
 * Setting class to display the actual revision of an git repository
 */
class setting_gitlivesync_repoinfo_revision extends setting_gitlivesync_textonly {
        function initialize($default,$local,$protected) {
		$repo = $this->initializeGitObject();
		if ($repo!==false) {
			try {
                        	$this->_input = trim($repo->run("rev-list -1 HEAD"));
                        } catch (Exception $e) {
                        }
		} else {
			$this->_input="";
		}
	}
}

/**
 * Setting class to read/set the (origin) remote of an git repository
 */
class setting_gitlivesync_repoinfo_remote extends setting_gitlivesync {
        var $_repo = '';
	private function _getRemote() {
                if ($this->_repo!==false) {
                                try {
                                        $remote = trim($this->_repo->run("remote show"));
                                        if ($remote!=="") {
                                                $remote = trim($this->_repo->run("config --get remote.".$remote.".url"));
                                        }
                                        return $remote;
                                } catch (Exception $e) {
                                }

                } else {
                        return "";
                }

	}

        function initialize($default,$local,$protected) {
                $this->_repo = $this->initializeGitObject();
		$this->_local = $this->_getRemote();
        }

	public function out($var, $fmt='php') {
		if ($fmt=='php') {
			if ($this->_local!="") {
				$newremote = $this->_local;

				if ($this->_getRemote() == "") {
					//Add new remote
					//die("Set new remote $newremote");
					$this->_repo->run("remote add origin $newremote");
				} elseif($this->_getRemote()!=$newremote) {
					//Change Remote
					$remote = trim($this->_repo->run("remote show"));
					$this->_repo->run("config remote.".$remote.".url ".$newremote);
				}
			}
		}
		return '';
	}

}

/**
 * Setting class to display the active brach of an git repository
 */ 
class setting_gitlivesync_repoinfo_branch extends setting_gitlivesync_textonly {
        function initialize($default,$local,$protected) {
                $repo = $this->initializeGitObject();
                if ($repo!==false) {
			$this->_input = trim($repo->active_branch());
                } else {
                        $this->_input="";
                }

        }
}

