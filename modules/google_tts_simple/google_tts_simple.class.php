<?php

/**
 * Google TTS
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @author Yurii Radio <yurii.radio@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 20:07:53 [Jul 02, 2018])
 */
//
//
class google_tts_simple extends module
{

    /**
     * google_tts
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name            = "google_tts_simple";
        $this->title           = "Google TTS Sіmple";
        $this->module_category = "<#LANG_SECTION_APPLICATIONS#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE']      = $this->mode;
        $out['ACTION']    = $this->action;
        $this->data       = $out;
        $p                = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result     = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        $this->getConfig();
        $out['DISABLED'] = $this->config['DISABLED'];

        if ($this->view_mode == 'update_settings') {
            global $disabled;
            $this->config['DISABLED'] = $disabled;
            $this->saveConfig();

            subscribeToEvent($this->name, 'SAY');
            $this->redirect("?ok=1");
        }

        if ($_GET['ok']) {
            $out['OK'] = 1;
        }

        global $cache_clear;
        if ($cache_clear) {
            array_map("unlink", glob(ROOT . "cms/cached/voice/*_google.mp3"));
            $this->redirect("?ok=1");
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }

    function processSubscription($event, &$details)
    {
        $this->getConfig();
        if ($this->config['DISABLED'])
            return;

        if ($details['SOURCE']) {
            if (($event == 'SAY' OR $event == 'SAYTO' OR $event == 'SAYREPLY') AND !$this->config['DISABLED']) {
                DebMes("Processing $event: " . json_encode($details, JSON_UNESCAPED_UNICODE), 'terminals');
                $message                    = $details['MESSAGE'];
                $level                      = $details['IMPORTANCE'];
                $mmd5                       = md5($message);
                $cached_filename            = ROOT . 'cms/cached/voice/sapi_' . $mmd5 . '.mp3';
                $cachedVoiceDir             = ROOT . 'cms/cached/voice';
                $details['CACHED_FILENAME'] = $cached_filename;
                $details['tts_engine']      = 'google_tts';

                $base_url = 'https://translate.google.com/translate_tts?';

                if (!file_exists($cached_filename)) {

                    $query = array(
                        'ie' => 'UTF-8',
                        'client' => 'tw-ob',
                        'q' => $message,
                        'tl' => SETTINGS_SITE_LANGUAGE_CODE
                        //'ttsspeed' => 1 // 0-4
                        //'speaker' => $speaker,
                        //'key' => $accessKey,
                    );
                    $qs    = http_build_query($query);

                    try {
                        $contents = file_get_contents($base_url . $qs);
                    } catch (Exception $e) {
                        registerError('google_tts', get_class($e) . ', ' . $e->getMessage());
                    }

                    if (isset($contents)) {
                        CreateDir($cachedVoiceDir);
                        SaveFile($cached_filename, $contents);
                        processSubscriptions('SAY_CACHED_READY', $details);
                    }
                } else {
                    processSubscriptions('SAY_CACHED_READY', $details);
                }
                $details['BREAK'] = true;
            }
            return true;
        }
        if ($event == 'SAY' && !$details['ignoreVoice']) {
            //DebMes($details);
            $level   = $details['level'];
            $message = $details['message'];

            // $accessKey = $this->config['ACCESS_KEY'];
            // $speaker = $this->config['SPEAKER'];

            if ($level >= (int) getGlobal('minMsgLevel')) {
                $filename       = md5($message) . '_google.mp3';
                $cachedVoiceDir = ROOT . 'cms/cached/voice';
                $cachedFileName = $cachedVoiceDir . '/' . $filename;

                $base_url = 'https://translate.google.com/translate_tts?';

                if (!file_exists($cachedFileName) && filesize($cachedFileName)) {

                    $query = array(
                        'ie' => 'UTF-8',
                        'client' => 'tw-ob',
                        'q' => $message,
                        'tl' => SETTINGS_SITE_LANGUAGE_CODE
                        //'ttsspeed' => 1 // 0-4
                        //'speaker' => $speaker,
                        //'key' => $accessKey,
                    );

                    $qs = http_build_query($query);


                    try {
                        $contents = file_get_contents($base_url . $qs);
                    }
                    catch (Exception $e) {
                        registerError('google_tts', get_class($e) . ', ' . $e->getMessage());
                    }

                    if (isset($contents)) {
                        CreateDir($cachedVoiceDir);
                        SaveFile($cachedFileName, $contents);
                    }
                } else {
                    @touch($cachedFileName);
                }
                if (file_exists($cachedFileName)) {
                    playSound($cachedFileName, 1, $level);
                    $details['ignoreVoice'] = 1;
                }
            }
        }
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        subscribeToEvent($this->name, 'SAY', '', 100);
        subscribeToEvent($this->name, 'SAYTO', '', 100);
        subscribeToEvent($this->name, 'SAYREPLY', '', 100);
        parent::install();
    }

    /**
     * Uninstall
     */
    function uninstall()
    {
        unsubscribeFromEvent($this->name, 'SAY');
        unsubscribeFromEvent($this->name, 'SAYTO');
        unsubscribeFromEvent($this->name, 'SAYREPLY');
        unsubscribeFromEvent($this->name, 'ASK');

        parent::uninstall();
    }

}
