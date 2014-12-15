<?php
namespace gazelle\core;

class Master {

    public $superglobals;
    public $legacy_handler_needed = false;

    public function __construct($application_dir, array $superglobals) {
        $this->application_dir = $application_dir;
        $this->superglobals = $superglobals;
        $this->server = $this->superglobals['server'];
        $this->settings = new Settings($this, $this->application_dir . '/settings.ini');
    }

    public function handle_request() {
        $base = basename(parse_url($this->server['SCRIPT_NAME'], PHP_URL_PATH), '.php');
        if (!preg_match('/^[a-z0-9]+$/i', $base)) {
            $this->active_section = null;
            return;
        }

        switch ($base) {
            case 'announce':
            case 'scrape':
                print("d14:failure reason40:Invalid .torrent, try downloading again.e\n");
                exit;

            case 'browse':
                header('Location: torrents.php');
                exit;

            case 'collage':
                $_SERVER['SCRIPT_FILENAME'] = 'collages.php'; // PHP CLI fix
                define('ERROR_EXCEPTION', true);
                $this->active_section = 'collages';
                $this->legacy_handler_needed = true;
                break;

            case 'details':
                $this->active_section = 'torrents';
                $this->legacy_handler_needed = true;
                break;

            case 'irc':
            case 'tools':
                $_SERVER['SCRIPT_FILENAME'] = $base.'.php'; // PHP CLI fix
                $this->active_section = $base;
                $this->legacy_handler_needed = true;
                break;

            case 'schedule':
            case 'peerupdate':
                define('MEMORY_EXCEPTION', true);
                define('TIME_EXCEPTION', true);
                define('ERROR_EXCEPTION', true);
                $_SERVER['SCRIPT_FILENAME'] = $base.'.php'; // CLI Fix
                $this->active_section = $base;
                $this->legacy_handler_needed = true;
                break;

            case 'signup':
                header('Location: register.php');
                exit;

            case 'whitelist':
                header('Location: articles.php?topic=clients');
                exit;

            case 'artist':
            case 'better':
            case 'bookmarks':
            case 'collages':
            case 'comments':
            case 'delays':
            case 'details':
            case 'forums':
            case 'friends':
            case 'groups':
            case 'staffblog':
            case 'tags':
            case 'torrents':
            case 'upload':
            case 'userhistory':
            case 'user':
            case 'wiki':
                define('ERROR_EXCEPTION', true); # Not sure why this is done only some of the time
                $this->active_section = $base;
                $this->legacy_handler_needed = true;
                break;
                
            case 'ajax':
            case 'articles':
            case 'blog':
            case 'bonus':
            case 'captcha':
            case 'chat':
            case 'cheaters':
            case 'donate':
            case 'error':
            case 'inbox':
            case 'index':
            case 'login':
            case 'logout':
            case 'log':
            case 'register':
            case 'reports':
            case 'reportsv2':
            case 'requests':
            case 'rules':
            case 'sandbox':
            case 'staff':
            case 'staffpm':
            case 'stats':
            case 'top10':
            case 'watchlist':
                $this->active_section = $base;
                $this->legacy_handler_needed = true;
                break;
            default:
                $this->active_section = null;
        }
    }

}
