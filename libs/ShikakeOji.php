<?php
/***************
 * NAGINATA.fi *
 ***************
 * Juga Paazmaya <olavic@gmail.com>
 * http://creativecommons.org/licenses/by-sa/3.0/
 * 
 * A class for handling application flow.
 * Let's see how many times the buzzword HTML5 can be repeated.
 *
 * http://blog.thenounproject.com/post/7310229014/how-to-properly-attribute-cc-by-a-guest-blog-post-by
 *
 * Usage:
 *  $shio = new ShikakeOji(realpath('../naginata-data.json'));
 *  $shio->loadConfig(realpath('../naginata-config.json'));
 *  $shio->output->useMinification = true;
 *  $shio->checkRequestedPage();
 *  echo $shio->renderPage();
 */
require 'ShikakeOjiPage.php';

class ShikakeOji
{
    /**
     * What is the version of this class?
     */
    public static $VERSION = '0.8';

    /**
     * Current language, defaults to Finnish.
     */
    public $language = 'fi';

    /**
     * As in fi_FI or en_GB, the latter part of that is the territory.
     */
    public $territory = 'FI';

    /**
     * Should be set to the realpath of the JSON file where app data is stored.
     * This location is used for storing the moderated versions of the content.
     */
    public $dataPath = '../naginata-data.json';

    /**
     * Configuration for Emails sending, 3rd party API keys, etc.
     * See "naginata-config.json.dist" for possible keys.
     */
    public $config;

    /**
     * Application data, decoded from JSON string which is loaded
     * from $this->dataPath.
     */
    public $addData;

    /**
     * Last modification date of the data.
     * Does not separate per language, just the last edit of anything.
     */
    public $dataModified;

    /**
     * An instance of the ShikakeOjiPage class.
     */
    public $output;

    /**
     * Current page, as seen in the request url.
     * Needs to match the ones available in navigation.
     * Defaults to / as in front page.
     * All URLs are starting with forward slash.
     */
    public $currentPage = '/';

    /**
     * The format used with "date()" while writing a log entry.
     */
    public $logDateFormat = 'Y-m-d H:i:s';

    /**
     * Log for minification. Entry added every time minification is needed.
     */
    public $redirectLog = '../naginata-redirect.log';

    /**
     * Log for the use of OpenID.
     */
    public $openidLog = '../naginata-openid.log';

    /**
     * Is the user logged in?
     * If true, then the userEmail should be one found in the "users" section of App data.
     */
    public $isLoggedIn = false;

    /**
     * Email address of the current user, if any.
     * Be careful, this is used with "isLoggedIn" to validate the user.
     */
    public $userEmail = '';

    /**
     * Library path, which is used to find the other libraries included.
     */
    public $libPath = __DIR__;

    /**
     * Client supports compression?
     * This is checked and set in constructor.
     */
    private $isCompressionSupported = false;

    /**
     * Is the page internal, thus only ouputting JSON?
     * These require user to login via OAuth.
     */
    private $isInternalPage = false;

    /**
     * PDO connected database connection, mainly for storing Modernizr stats
     * http://php.net/pdo
     */
    private $database;

    /**
     * URLs used by the application, not for showing content.
     * These should be human readable English with dashes.
     * Key is the URL, value is the name of the function to be called.
     */
    private $appUrls = array(
        '/update-article' => 'pageUpdateArticle',
        '/receive-modernizr-statistics' => 'pageReceiveModernizrStats',
        '/authenticate-user' => 'pageAuthenticateUser',
        '/keep-session-alive' => 'keepSessionAlive'
    );

    /**
     * Constructor will load the JSON data and decode it as well as
     * check for compression support of the client.
	 * If loading fails, the process is not continued.
     */
    function __construct($jsonpath)
    {
        $this->checkSession();

        $this->dataPath = $jsonpath;
        $this->loadData();

        if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
        {
            $this->isCompressionSupported = true;
            //$this->minifiedName .= 'gz.'; // It might conflict with what Apache is delivering already compressed
        }
		
		$this->output = new ShikakeOjiPage($this);
    }

    /**
     * Load the given JSON configuration file.
     * If it contains database connection values, connection will be open.
     */
    public function loadConfig($configPath)
    {
        if (!file_exists($configPath))
        {
            return false;
        }
        $this->config = json_decode(file_get_contents($configPath), true);

        $error = $this->getJsonError();
        if ($error != '')
        {
            header('Content-type: text/plain');
            header('X-Failure-type: config');
            echo $error;
            exit();
        }
        
        // PDO database connection if the settings are set.
        if (isset($this->config['database']) && isset($this->config['database']['type']) &&
            in_array($this->config['database']['type'], PDO::getAvailableDrivers()))
        {
            $attr = array();
            $dsn = $this->config['database']['type'] . ':';
            if ($this->config['database']['type'] != 'sqlite')
            {
                $dsn .= 'dbname=' . $this->config['database']['database'] . ';host=' . $this->config['database']['address'];
                $attr[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8';
            }
            else
            {
                $dir = dirname($configPath);
                $dsn .= realpath($dir . '/' . $this->config['database']['address']);
            }


            $this->database = new PDO($dsn, $this->config['database']['username'],
                $this->config['database']['password'], $attr);

            $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
            $this->database->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);
            $this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
    }

    /**
     * Check for all session variables and restart session if needed.
     * Session should not be started elsewhere.
     */
    public function checkSession()
    {
        session_name('SOFI');
        session_start();

        $id = sha1($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);

        if (!isset($_SESSION['id']) || $_SESSION['id'] != $id)
        {
            session_regenerate_id(); // updates the cookie if there is one
			
            // Must match the sha1 of REMOTE_ADDR and HTTP_USER_AGENT
            $_SESSION['id'] = $id;
            // Must found from the "users" list if not empty. If empty, not logged in.
            $_SESSION['email'] = '';
            // When was the current session initiated? This is mainly intesting to see the true lifetime.
            $_SESSION['init'] = time();
        }

        if ($_SESSION['email'] != '' && 
            ($this->isEmailAdministrator($_SESSION['email']) || $this->isEmailContributor($_SESSION['email']))
        )
        {
            $this->isLoggedIn = true;
            $this->userEmail = $_SESSION['email'];
        }
		
		$log = date('Y-m-d H:i:s') . ' REMOTE_ADDR: ' . $_SERVER['REMOTE_ADDR'] . "\n\t" .
				'REQUEST_URI : ' . $_SERVER['REQUEST_URI'] . "\n\t" .
				'HTTP_USER_AGENT: ' . $_SERVER['HTTP_USER_AGENT'] . "\n\t" .
				'$id: ' . $id . "\n\t" .
				'$_SESSION[email]: ' . $_SESSION['email'] . "\n\t" .
				'$_SESSION[id]: ' . $_SESSION['id'] . "\n";
				
		file_put_contents('../session-checker.log', $log, FILE_APPEND);
    }

    /**
     * Check the $_SERVER['HTTP_ACCEPT_LANGUAGE'] and set
     * the $this->language variable for one that is found from data.
     * If nothing is found, default to Finnish.
     */
    public function checkRequestedLanguage()
    {
        $this->language = 'fi';
    }

    /**
     * Remove "www" prefix from the URL and redirect.
     * .htaccess should take care of this
     */
    public function removeWwwRedirect()
    {
        if (substr($_SERVER['HTTP_HOST'], 0, 3) == 'www')
        {
            $go = 'http://' . substr($_SERVER['HTTP_HOST'], 4) . $_SERVER['REQUEST_URI'];
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $go);
            exit();
        }
    }

    /**
     * Check the $_SERVER['REQUEST_URI'] and set
     * the $this->currentPage variable for one that is found from navigation.
     * If nothing is matched, redirect to front page.
     * Matching is done first against application URLs and then content URLs via "nav".
     */
    public function checkRequestedPage()
    {
        if (!isset($_SERVER['REQUEST_URI']))
        {
            // How come, super variable not set?
            return false;
        }
        if (!isset($this->appData['navigation']))
        {
            return false;
        }

        $url = parse_url($_SERVER['REQUEST_URI']); // use as such

        // URLs should only be lowercase, thus check and redirect later
        $lowercase = strtolower($url['path']);

        if (array_key_exists($lowercase, $this->appUrls))
        {
            // Application internal URLs. Allow query in the URL.
            $this->currentPage = $lowercase;
            $this->isInternalPage = true;
        }
        else
        {
            // Content
            $found = false;
            $data = $this->appData['navigation'];
            foreach($data as $lang => $nav)
            {
                foreach($nav as $item)
                {
                    if ($lowercase == $item['url'])
                    {
                        $this->currentPage = $lowercase;
                        $found = true;

                        // How about language? just stick to default for now.
                        if ($item['url'] != '/')
                        {
                            //$this->language = $lang;
                        }
                    }
                }
            }

            if (!$found)
            {
                $this->redirectTo('/');
            }
            else
            {
                // Was the address found in lowercase?
                if (!$this->isInternalPage && $this->currentPage != $url['path'] && $url['query'] != '')
                {
                    $this->redirectTo($this->currentPage);
                }
            }
        }
    }

    /**
     * Build a regular page with the content as per request.
     */
    public function renderPage()
    {
        if ($this->isInternalPage)
        {
            // These functions return a string/true or false on failure.
            $out = call_user_func(array($this, $this->appUrls[$this->currentPage]));

            // Front end needs to handle whatever the output is.
            header('Content-type: application/json');
            return json_encode(array('answer' => $out));
        }
        else
        {
            header('Content-type: text/html; charset=utf-8');
            header('Content-Language: ' . $this->language);
            header('Last-modified: ' . date('r', $this->dataModified));
            return $this->output->renderHtml($this->appData);
        }
    }

    /**
     * Load the JSON data if available
     */
    private function loadData()
    {
        if ($this->dataPath != '' && file_exists($this->dataPath))
        {
            $json = file_get_contents($this->dataPath);
            $this->appData = json_decode($json, true);
            $this->dataModified = filemtime($this->dataPath);

            $error = $this->getJsonError();
            if ($error != '')
            {
				header('Content-type: text/plain');
                header('X-Failure-type: data');
                echo $error;
				exit();
            }
        }
    }

    /**
     * Save application data to the JSON file storage and update modificatiion date.
     * @return     boolean    True if saving succeeded, else false
     */
    private function saveData()
    {
        if ($this->dataPath != '' && $this->isLoggedIn)
        {
            $jsonstring = ShikakeOjiPage::jsonPrettyPrint(json_encode($this->appData)); // PHP 5.4 onwards JSON_PRETTY_PRINT
            $this->dataModified = time();
            return (file_put_contents($this->dataPath, $jsonstring) !== false);
        }
        return false;
    }

    /**
     * Save JSON data for moderation in the same location as the original
     * @return     boolean    True if saving succeeded, else false
     */
    private function saveDataModeration()
    {
        if ($this->isLoggedIn)
        {
            $time = date('Y-m-d_H-i-s');
            $path = substr($this->dataPath, 0, strrpos($this->dataPath, '.')) . '.' . $time . '.' . $this->userEmail . '.json';
            $jsonstring = ShikakeOjiPage::jsonPrettyPrint(json_encode($this->appData)); // PHP 5.4 onwards JSON_PRETTY_PRINT
            return (file_put_contents($path, $jsonstring) !== false);
        }
        return false;
    }

    /**
     * There seems to be a request for updating an article.
     * Checks required data from $_POST and creates a diff.
     * User must be logged in to perform this.
     *
     * @return    boolean    If writing to a JSON file and Email sending succeeded true, else false
     */
    private function pageUpdateArticle()
    {
        $required = array(
            'lang', // language: fi
            'page', // page url: /koryu
            'content', // modified article
            'modified' // unix time stamp which should be the same as $this->dataModified
        );
        $received = $this->checkRequiredPost($required);
        if ($received === false || !$this->isLoggedIn)
        {
            return false;
        }

        // In app data, under "article", object named by "lang", has object with "page" is an array.
        // $this->appData['article'][$this->language][$this->currentPage];
        if (isset($this->appData['article']) &&
            isset($this->appData['article'][$received['lang']]) &&
            isset($this->appData['article'][$received['lang']][$received['page']]) &&
            is_array($this->appData['article'][$received['lang']][$received['page']]) &&
            count($this->appData['article'][$received['lang']][$received['page']]) > 0)
        {
            // For now just using the first item in the array
            $current = $this->appData['article'][$received['lang']][$received['page']]['0'];

            // Save the data in disk for later moderation
            $this->appData['article'][$received['lang']][$received['page']]['0'] = $received['content'];
            $isSaved = $this->saveDataModeration();

            // Create diff for sending it via email
            $a = explode("\n", ShikakeOjiPage::decodeHtml($current));
            $b = explode("\n", ShikakeOjiPage::decodeHtml($received['content']));

            require $this->libPath . '/php-diff/Diff.php';
            require $this->libPath . '/php-diff/Diff/Renderer/Text/Unified.php';

            $diff = new Diff($a, $b);
            $renderer = new Diff_Renderer_Text_Unified;
            $out = $diff->render($renderer);

            // Now do the emailing
            $isEmailed = $this->sendEmail(
                $this->config['email']['address'],
                $this->config['email']['name'],
                $_SERVER['HTTP_HOST'] . $received['page'] . ' - Muokattu, tekijänä ' . $this->userEmail,
                'Terve, ' . "\n" . 'Sivustolla ' . $_SERVER['HTTP_HOST'] .
                    ' tapahtui sisällön muokkaus tapahtuma, jonka tekijänä ' . $this->userEmail . '.' . "\n" .
                    'Alkuperäisen sisällön päiväys: ' . date($this->logDateFormat, $received['modified']) . "\n" .
                    'Sivun ' . $received['page'] . ' muutokset esitetty alla.' . "\n\n" . $out
            );
            return $isSaved && $isEmailed;
        }
        return false;
    }

    /**
     * Save Modernizr statistics
     */
    private function pageReceiveModernizrStats()
    {
        $required = array(
            'modernizr',
            'useragent',
            'flash',
            'version'
        );
        $received = $this->checkRequiredPost($required);
        if ($received === false)
        {
            return false;
        }

        $counter = 0;
        /*
        'mdrnzr_client' id, useragent, flash, created, address, modernizr
        'mdrnzr_key' id, title
        'mdrnzr_value' id, key_id, client_id, hasthis
        */
        if (isset($this->database))
        {
            $sql = 'INSERT INTO mdrnzr_client (useragent, flash, created, address, modernizr) ' .
                'VALUES (\'' . $received['useragent'] . '\', \'' . $received['flash'] .
                '\', \'' . time() . '\', \'' . $_SERVER['REMOTE_ADDR'] . '\', \'' . $received['version'] . '\')';
            $this->database->query($sql);
            $client_id = $this->database->lastInsertId();

            // Check that there is no key that would not be saved in the mdrnzr_key table.
            $keys = array();
            $values = array();
            foreach ($received['modernizr'] as $k => $v)
            {
                // There should be no arrays, checked client side...
                if (is_string($v))
                {
                    $values[] = array($k, $v, $client_id); // optimize by moving $client_id to prepare...
                    $keys[] = '(\'' . $k . '\')';
                }
            }
            
            // TODO: how about other database types?
            // http://dev.mysql.com/doc/refman/5.1/en/insert.html
            $sql = 'INSERT IGNORE INTO mdrnzr_key (title) VALUES ' . implode(', ', $keys); // title must be unique indexed
            $this->database->query($sql);

            // Insert the values of this client to mdrnzr_value table.
            $prep = $this->database->prepare('INSERT INTO mdrnzr_value (hasthis, key_id, client_id) VALUES(' .
                '(SELECT id FROM mdrnzr_key WHERE title = ? LIMIT 1), ?, ? )');
            foreach ($values as $row)
            {
                $prep->execute($row);
            }
            
            $stat = $this->database->query('SELECT COUNT(id) AS total FROM mdrnzr_client WHERE address = \'' .
                $_SERVER['REMOTE_ADDR'] . '\' GROUP BY address');
            $res = $stat->fetch(PDO::FETCH_ASSOC);

            $counter = $res['total'];
        }
        // How many times this IP address sent its Modernizr data
        return $counter;
    }

    /**
     * Try to authenticate the user via OAuth.
     * The email provider should tell if the user is who she/he/it claims to be.
     * http://code.google.com/apis/accounts/docs/OpenID.html
     * @return string/boolean
     */
    private function pageAuthenticateUser()
    {
        // https://gitorious.org/~paazmaya/lightopenid/paazmayas-lightopenid
        require $this->libPath . '/lightopenid/openid.php';

        $openid = new LightOpenID('naginata.fi');

        // http://svn.openid.net/repos/specifications/user_interface/1.0/trunk/openid-user-interface-extension-1_0.html
        $openid->ui = array(
            'openid.ns.ui'   => 'http://specs.openid.net/extensions/ui/1.0',
            //'openid.ui.mode' => 'popup', // Google can use x-has-session but not useful for this app
            'openid.ui.lang' => $this->language . '_' . $this->territory
        );

        if (isset($_GET['openid_mode']))
        {
            if ($openid->mode)
            {
                $attr = $openid->getAttributes();

                if ($openid->validate() &&
                    array_key_exists('contact/email', $attr) &&
                    ($this->isEmailAdministrator($attr['contact/email']) ||
                    $this->isEmailContributor($attr['contact/email']))
                )
                {
                    // TODO: add differentiation between admins and contributors
                    $this->isLoggedIn = true;
                    $this->userEmail = $attr['contact/email'];
                    $_SESSION['email'] = $attr['contact/email'];
                }

                // Show a message of the login state
                // Set a temporary session variable. Once it is found, it will be removed
                $_SESSION['msg-login-success'] = $this->isLoggedIn;
				
				// Build the mail message for site owner
                $mailBody = '';
                $mailBody .= 'Attributes:' . "\n";
                foreach($attr as $k => $v)
                {
                    $mailBody .= '  ' . $k . "\t" . $v . "\n";
                }
                $mailBody .= "\n" . 'Session values:' . "\n";
                foreach($_SESSION as $k => $v)
                {
                    $mailBody .= '  ' . $k . "\t" . $v . "\n";
                }
				$mailBody .= "\n" . 'Page: ' . (isset($_GET['page']) ? $_GET['page'] : 'not set...') . "\n";

                $this->sendEmail(
                    $this->config['email']['address'],
                    $this->config['email']['name'],
                    $_SERVER['HTTP_HOST'] . ' - Kirjautumis tapahtuma',
                    'Terve, ' . "\n" . 'Sivustolla ' . $_SERVER['HTTP_HOST'] . ' tapahtui OpenID kirjautumis tapahtuma.' . "\n\n" . $mailBody
                );

                // TODO: check that this is valid page url
                // page parameter was sent initially from our site, land back to that page.
                if (isset($_GET['page']) && $_GET['page'] != '')
                {
					// Since this is called, the execution stops. It does not seem to redirect to anywhere else except / ...
                    $this->redirectTo($_GET['page'], '');
                }
                return $openid->validate();
            }
        }
        else if (isset($_POST['identifier']) && $_POST['identifier'] != '' && isset($_POST['page']) && $_POST['page'] != '')
        {
            // Initial form was posted, thus asking the OpenID provider for auth.
            $id = filter_var($_POST['identifier'], FILTER_VALIDATE_EMAIL);
            if ($id === false)
            {
                return false;
            }
            
            // TODO: Check that this email address is in the list of users.
            
            if (strpos($id, '@gmail.com') !== false)
            {
                $id = 'https://www.google.com/accounts/o8/id';
            }
            $openid->returnUrl = 'http://' . $_SERVER['HTTP_HOST'] . $this->currentPage . '?page=' . $_POST['page'];
            $openid->required = array(
                'contact/email',
                'namePerson'
            );
            $openid->identity = $id;
            $authUrl = $openid->authUrl();

            $log = date($this->logDateFormat) . ' [' . $_SERVER['REMOTE_ADDR'] . '] ' . $id . ' ' . implode("\n\t\t" . '&', explode('&', $authUrl)) . "\n";
            file_put_contents($this->openidLog, $log, FILE_APPEND);

            //return $authUrl; 
            header('Location: ' . $authUrl); // no longer AJAX for this submission.
            exit();
        }
        else
        {
            return false;
        }
    }

    /**
     * Keep session alive call via AJAX
     * @return    int    Session lifetime in seconds.
     */
    private function keepSessionAlive()
    {
        // Since checkSession runs before this call, the session variables should be there.
        $lifetime = time() - $_SESSION['init'];
        return $lifetime;
    }

    /**
     * Check $_POST against the given $required array.
     * @return array/boolean    False if any of the required is missing, else escaped data
     */
    private function checkRequiredPost($required)
    {
        $received = array();
        foreach($required as $r)
        {
            if (isset($_POST[$r]) && $_POST[$r] != '')
            {
                $received[$r] = ShikakeOjiPage::encodeHtml($_POST[$r]);
            }
        }

        if (count($required) !== count($received))
        {
            return false;
        }
        return $received;
    }

    /**
     * Redirect the client to the given URL with 301 HTTP code.
     * @param    string    $url    Redirect to this URL within this domain
     */
    private function redirectTo($url, $code = '301')
    {
        $log = date($this->logDateFormat) . ' [' . $_SERVER['REMOTE_ADDR'] . '] ' . $_SERVER['REQUEST_URI'] . ' --> ' . $url . "\n";
        file_put_contents($this->redirectLog, $log, FILE_APPEND);
        if ($code != '')
        {
            header('HTTP/1.1 ' . $code . ' Moved Permanently'); // TODO: different code has different text
        }
        header('Location: http://' . $_SERVER['HTTP_HOST']) . $url;
        exit();
    }
    
    /**
     * Is the given email found in the administrators list.
     * @param   string  $email
     * @return  boolean
     */
    private function isEmailAdministrator($email)
    {
        if (empty($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        {
            return false;
        }
		if (!isset($this->config['users']['administrators']) || !is_array($this->config['users']['administrators']))
		{
			return false;
		}
        return in_array($email, $this->config['users']['administrators']);
    }
    
    /**
     * Is the given email found in the contributors list.
     * @param   string  $email
     * @return  boolean
     */
    private function isEmailContributor($email)
    {
        if (empty($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        {
            return false;
        }
		if (!isset($this->config['users']['contributors']) || !is_array($this->config['users']['contributors']))
		{
			return false;
		}
        return in_array($email, $this->config['users']['contributors']);
    }

    /**
     * Send email to the given address with the given content.
     * Sends a blind copy to the sender address.
     * Uses PHPMailer - PHP email class, Version: 5.2.
     *
     * @param string $toMail    Email address of the recipient
     * @param string $toName    Name of the recipient
     * @param string $subject    Subject of the mail
     * @param string $message    Text format of the mail
     * @return boolean    True if the sending succeeded
     */
    function sendEmail($toMail, $toName, $subject, $message)
    {
        global $cf;
        require_once $this->libPath . '/phpmailer/class.phpmailer.php';

        mb_internal_encoding('UTF-8');

        $mail = new PHPMailer();
        $mail->SetLanguage($this->language);
        $mail->CharSet = 'utf-8';
        $mail->IsSMTP();
        $mail->Host = $this->config['email']['smtp'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->config['email']['username'];
        $mail->Password = $this->config['email']['password'];

        $sender = 'NAGINATA.fi';
        $sender = $sender; //mb_encode_mimeheader($sender, 'UTF-8', 'Q');

        $mail->SetFrom($this->config['email']['address'], $sender);

        $mail->XMailer = 'NAGINATA.fi ' . self::$VERSION;
        $mail->AddAddress($toMail, $toName);
        $mail->AddBCC($this->config['email']['address'], $mail->FromName);

        $mail->WordWrap = 120;
        $mail->IsHTML(false);

        $mail->Subject = $subject; //mb_encode_mimeheader($subject, 'UTF-8', 'Q');
        $mail->Body = $message;

        return $mail->Send();
        // $mail->ErrorInfo;
    }

    /**
     * http://www.php.net/manual/en/function.json-last-error.php
     * @return    string
     */
    private function getJsonError()
    {
        switch (json_last_error())
        {
            case JSON_ERROR_NONE:
                return '';
                break;
            case JSON_ERROR_DEPTH:
                return 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                return 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                return 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                return 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                return 'Unknown error';
                break;
        }
    }
}
