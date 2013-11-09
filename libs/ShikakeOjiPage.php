<?php
/***************
 * NAGINATA.fi *
 ***************
 * Juga Paazmaya <olavic@gmail.com>
 * http://creativecommons.org/licenses/by-sa/3.0/
 *
 * A class for outputting HTML5 stuff.
 * Let's see how many times the buzzword HTML5 can be repeated.
 *
 * Usage:
 *  $shih = new ShikakeOjiPage();
 *  echo $shih->renderHtml();
 */
class ShikakeOjiPage
{
    /**
     * How will JS and CSS files will be called once minified in to one file per type?
     *
     * Compressed files are delivered via Apache.
     */
    public $minifiedName = 'naginata.min.';

    /**
     * Instance of a ShikakeOji class.
     */
    private $shikakeOji;

    /**
     * Markup for navigation
     */
    private $navigation;

    /**
     * Data used for the head section
     */
    private $head;

    /**
     * Title of the current page from page-data,
     * matching the current url.
     */
    private $pageTitle = '';

    /**
     * Constructor does not do much.
     */
    function __construct($shikakeOji)
    {
        if (!isset($shikakeOji) || !is_object($shikakeOji))
        {
            return false;
        }

        // Must be defined in order to access data and config.
        $this->shikakeOji = $shikakeOji;

        // Calculate interval time for 2 week of seconds.
        $this->cacheInterval = (60 * 60 * 24 * 7 * 2);

        // Create navigation for later use
        $navigation = '';
        foreach ($shikakeOji->appData['pages'] as $pages)
        {
            if (substr($pages['url'], 1, 2) == $this->shikakeOji->language)
            {
                $navigation .= '<li';
                if ($this->shikakeOji->currentPage == $pages['url'])
                {
                    $navigation .= ' class="current"';
                    $this->head = $pages; // head section data
                    $this->pageTitle = $pages['title'];
                }
                $navigation .= '><a href="' . $pages['url'] . '" title="' . $pages['header'] . '" rel="prefetch">' . $pages['title'] . '</a></li>';
            }
        }

        $this->navigation = $navigation;
    }

    /**
     * Render the HTML5 markup by the appData and options.
     */
    public function renderHtml()
    {
        $out = $this->createHtmlPage();

        return $out;
    }

    /**
     * Encode HTML entities for a block of text
     *
     * @param     string/array    $str
     *
     * @return    string/array
     */
    public static function encodeHtml($str)
    {
        if (is_array($str))
        {
            foreach ($str as $k => $s)
            {
                $str[$k] = self::encodeHtml($s);
            }

            return $str;
        }
        else
        {
            return htmlentities(trim($str), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Decode HTML entities from a block of text
     *
     * @param     string/array    $str
     *
     * @return    string/array
     */
    public static function decodeHtml($str)
    {
        if (is_array($str))
        {
            foreach ($str as $k => $s)
            {
                $str[$k] = self::decodeHtml($s);
            }

            return $str;
        }
        else
        {
            return html_entity_decode(trim($str), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Create the whole HTML5 markup with content specific to this page and login status.
     * http://html5doctor.com/element-index/
     *
     * Remember to validate http://validator.w3.org/
     *
     * @return    string    HTML5 markup
     */
    private function createHtmlPage()
    {
        if (!isset($this->head))
        {
            return '<p class="fail">Navigation data for this page missing</p>';
        }

        $data = $this->shikakeOji->appData;

        $out = $this->createHtmlHead($data['title'][$this->shikakeOji->language]);

        $path = '../content/' . $this->shikakeOji->language . '/' . $this->pageTitle . '.md';

        if (file_exists($path))
        {
            $markdown = file_get_contents($path);

            $out .= '<article>';
            $out .= $markdown; // TODO: Markdown converter...
            $out .= '</article>';
        }
        else
        {
            return '<p class="fail">Article data for this page missing</p>';
        }

        $out .= $this->createHtmlFooter($data['footer'][$this->shikakeOji->language]);

        return $out;
    }

    /**
     * Create HTML5 head
     * $title = $data['title'][$this->shikakeOji->language]
     */
    private function createHtmlHead($title)
    {
        // None of the OGP items validate, as well as using prefix in html element...
        $out = '<!DOCTYPE html>';
        $out .= '<html lang="' . $this->shikakeOji->language . '"';
        //$out .= ' manifest="applicaton.cache"'; // http://www.html5rocks.com/en/tutorials/appcache/beginner/
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'facebookexternalhit') !== false)
        {
            $out .= ' prefix="og:http://ogp.me/ns#"'; // http://dev.w3.org/html5/rdfa/
        }
        $out .= '>';
        $out .= '<head>';
        $out .= '<meta charset="utf-8"/>';
        $out .= '<title>' . $this->head['header'] . ' | ' . $title . '</title>';
        $out .= '<meta name="description" content="' . $this->head['description'] . '"/>';
        $out .= '<link rel="shortcut icon" href="/img/favicon.png" type="image/png"/>';

        // Web Fonts from Google.
        $out .= '<link href="http://fonts.googleapis.com/css?family=Inder|Lora&subset=latin-ext,latin" rel="stylesheet" type="text/css"/>';

        if (strpos($_SERVER['HTTP_USER_AGENT'], 'facebookexternalhit') !== false)
        {
            // http://ogp.me/
            $out .= '<meta property="og:title" content="' . $this->head['title'] . '"/>';
            $out .= '<meta property="og:description" content="' . $this->head['description'] . '"/>';
            $out .= '<meta property="og:type" content="sports_team"/>';

            // All the images referenced by og:image must be at least 200px in both dimensions.
            $out .= '<meta property="og:image" content="http://' . $_SERVER['HTTP_HOST'] . '/img/logo-200x200.png"/>';

            $out .= '<meta property="og:url" content="http://' . $_SERVER['HTTP_HOST'] . $this->shikakeOji->currentPage . '"/>';
            $out .= '<meta property="og:site_name" content="' . $this->head['title'] . '"/>';
            $out .= '<meta property="og:locale" content="fi_FI"/>'; // language_TERRITORY
            $out .= '<meta property="og:locale:alternate" content="en_GB"/>';
            $out .= '<meta property="og:locale:alternate" content="ja_JP"/>';
            //$out .= '<meta property="og:country-name" content="Finland"/>';

            // https://developers.facebook.com/docs/opengraph/
            $out .= '<meta property="fb:app_id" content="' . $this->shikakeOji->config['facebook']['app_id'] . '"/>'; // A Facebook Platform application ID that administers this page.
            $out .= '<meta property="fb:admins" content="' . $this->shikakeOji->config['facebook']['admins'] . '"/>';
        }

        // Developer guidance for websites with content for Adobe Flash Player in Windows 8
        // http://msdn.microsoft.com/en-us/library/ie/jj193557%28v=vs.85%29.aspx
        $out .= '<meta http-equiv="X-UA-Compatible" content="requiresActiveX=true" />';

        // http://microformats.org/wiki/rel-license
        $out .= '<link rel="license" href="http://creativecommons.org/licenses/by-sa/3.0/"/>';
        $out .= '<link rel="author" href="http://paazmaya.com"/>';

        // https://developer.apple.com/library/safari/#documentation/appleapplications/reference/safariwebcontent/configuringwebapplications/configuringwebapplications.html
        $out .= '<link rel="apple-touch-icon" href="/img/mobile-logo.png"/>'; // 57x57

        $base = '/css/';

        $out .= '<link rel="stylesheet" href="' . $base . $this->minifiedName . 'css" type="text/css" media="all" />';

        $out .= '</head>';

        $out .= '<body>';


        $out .= '<nav><ul>' . $this->navigation . '</ul></nav>';

        $out .= '<div id="wrapper">';

        // div#logo tag shall contain all the message data, if needed
        $out .= '<div id="logo">';

        // should be only two words
        $out .= '<p>' . $title . '</p>';
        $out .= '</div>';

        $out .= '<header>';
        $out .= '<h1>' . $this->head['header'] . '</h1>';
        $out .= '<p class="desc-transform">' . $this->head['description'] . '</p>';
        $out .= '</header>';

        return $out;
    }

    /**
     * Create HTML5 footer.
     * $data = $data['footer'][$this->shikakeOji->language]
     */
    private function createHtmlFooter($data)
    {
        $out = '</div>';

        $out .= '<footer>';
        $out .= '<p>';

        $links = array();
        foreach ($data as $item)
        {
            $a = '<a href="' . $item['url'] . '" title="' . $item['alt'] .
                '">' . $item['text'] . '</a>';
            $links[] = $a;
        }

        $out .= implode('|', $links);

        $out .= '</footer>';

        $base = '/js/';
        $out .= '<script type="text/javascript" src="' . $base . $this->minifiedName . 'js"></script>';

        $out .= '</body>';
        $out .= '</html>';

        return $out;
    }

}
