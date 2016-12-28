<?php

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_LF')) define('DOKU_LF', "\n");
if (!defined('DOKU_TAB')) define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once DOKU_PLUGIN.'action.php';

class action_plugin_gitlivesync_changereact extends DokuWiki_Action_Plugin {

    function __construct() {
    }

    public function register(Doku_Event_Handler &$controller) {
        $controller->register_hook('IO_WIKIPAGE_WRITE', 'AFTER', $this, 'handle_io_wikipage_write');
        $controller->register_hook('MEDIA_UPLOAD_FINISH', 'AFTER', $this, 'handle_media_upload');
        $controller->register_hook('MEDIA_DELETE_FILE', 'AFTER', $this, 'handle_media_deletion');
    }

  public function handle_io_wikipage_write(Doku_Event &$event, $param) {
    global $SUM,$INPUT;

    $rev = $event->data[3];

    /* On update to an existing page this event is called twice,
     * once for the transfer of the old version to the attic (rev will have a value)
     * and once to write the new version of the page into the wiki (rev is false)
     */
    if (!$rev) {


	$pagens = $event->data[1];
	$pageid = $event->data[2];
	$content = $event->data[0][1];
	$summary = $SUM;
        $minor = $INPUT->bool('minor');//false; 

	$date = @filemtime($event->data[0][0]);


	//$this->wikigit_pushpagetogit($pageid,$pagens,$content,$summary,$minor,$date);
	$data = array("page",$pagens.":".$pageid,$content,$summary,$minor,$date);
	//dbglog($msg,"trigger: ".print_r($data,true)." \n");

        trigger_event("CHANGEREACT", $data);
      
    }

  }
    

  public function handle_media_deletion(Doku_Event &$event, $param) {
	global $conf;

    $mediaid = $event->data['path'];
    $medians = $event->data['name'];

    $date = time();//filemtime($mediaid);

    $mediaid = str_replace('/',':',str_replace($conf['mediadir'].'/','',$mediaid));

    //$this->wikigit_deletemedaifromgit($mediaid,$date);
        $data = array("media",$mediaid,"delete","",false,$date);
        trigger_event("CHANGEREACT", $data);

  }


  public function handle_media_upload(Doku_Event &$event, $param) {

    $mediapath= $event->data[1];
    $mediaid = $event->data[2];

    $date = filemtime($mediapath);

    //$this->wikigit_pushmediatogit($mediaid,$date);
        $data = array("media",$mediaid,"upload","",false,$date);
        trigger_event("CHANGEREACT", $data);

  }


}

