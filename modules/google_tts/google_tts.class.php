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
class google_tts extends module {

    /**
     * google_tts
     *
     * Module class constructor
     *
     * @access private
     */
    function google_tts() {
        $this->name = "google_tts";
        $this->title = "Google TTS";
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
    function saveParams($data = 1) {
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
    function getParams() {
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
    function run() {
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
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out) {
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
    function usual(&$out) {
        $this->admin($out);
    }

    function processSubscription($event, &$details) {
        $this->getConfig();
        if ($event == 'SAY' && !$this->config['DISABLED'] && !$details['ignoreVoice']) {
            //DebMes($details);
            $level = $details['level'];
            $message = $details['message'];

            // $accessKey = $this->config['ACCESS_KEY'];
            // $speaker = $this->config['SPEAKER'];

            if ($level >= (int) getGlobal('minMsgLevel')) {
                $filename = md5($message) . '_google.mp3';
                $cachedVoiceDir = ROOT . 'cms/cached/voice';
                $cachedFileName = $cachedVoiceDir . '/' . $filename;

                $base_url = 'https://translate.google.com/translate_tts?';

                if (!file_exists($cachedFileName)) {

                    $lang = SETTINGS_SITE_LANGUAGE;
                    if ($lang == 'ua') {
                        $lang = 'uk';
                    }

                    $qs = http_build_query([
                        'ie' => 'UTF-8',
                        'client' => 'tw-ob',
                        'q' => $message,
                        'tl' => $lang,
                        //'ttsspeed' => 1 // 0-4
                        //'speaker' => $speaker,
                        //'key' => $accessKey,
                    ]);
                    //DebMes($base_url . $qs);

                    try {
                        $contents = file_get_contents($base_url . $qs);
                    } catch (Exception $e) {
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
    function install($data = '') {
        subscribeToEvent($this->name, 'SAY', '', 777);
        parent::install();
    }

    /**
     * Uninstall
     */
    function uninstall() {
        unsubscribeFromEvent($this->name, 'SAY');
        parent::uninstall();
    }

}
