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
     * Unix timestamp of the last modification of the given page.
     * ...assuming there is a page set.
     */
    public $pageModified = 0;

    /**
     * How will JS and CSS files will be called once minified in to one file per type?
     *
     * Compressed files are delivered via Apache.
     */
    public $minifiedName = 'naginata.min.';

    /**
     * Cache directory
     */
    public $cacheDir = '../cache/';

    /**
     * How long should the 3rd party JSON files be cached?
     * In seconds. (60 * 60 * 24 * 7 * 2) = 2 weeks
     */
    public $cacheInterval;

    /**
     * Special fields to be prosessed in the content. It is always a 3rd party service.
     * [flickr|image id]
     * [youtube|video id]
     * [local|image name in public_html/img/]
     */
    private $specialFields = array(
        'flickr'  => 'renderFlickr',
        'youtube' => 'renderYoutube',
        'vimeo'   => 'renderVimeo',
        'local'   => 'renderLocalImage'
    );

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
     * Id of this page in table naginata_page if any.
     */
    private $pageId = -1;

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
        $sql = 'SELECT * FROM naginata_page WHERE lang = \'' . $this->shikakeOji->language . '\' ORDER BY weight ASC';
        $run = $this->shikakeOji->database->query($sql);
        if ($run)
        {
            while ($res = $run->fetch(PDO::FETCH_ASSOC))
            {
                $navigation .= '<li';
                if ($this->shikakeOji->currentPage == $res['url'])
                {
                    $navigation .= ' class="current"';
                    $this->head = $res; // head section data
                    $this->pageId = $res['id']; // page_id for naginata_article
                }
                $navigation .= '><a href="/' . $this->shikakeOji->language . $res['url'] . '" title="' . $res['header'] . '" rel="prefetch">' . $res['title'] . '</a></li>';
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

        $pdo = $this->shikakeOji->database; // used only twice but anyhow for speed...

        $out = $this->createHtmlHead($data['title'][$this->shikakeOji->language]);


        $latest = 0;
        $sql = 'SELECT content, modified FROM naginata_article WHERE page_id = \'' .
            $this->pageId . '\' AND published = 1 ORDER BY modified DESC LIMIT 1';
        $run = $pdo->query($sql);
        if ($run)
        {
            while ($res = $run->fetch(PDO::FETCH_ASSOC))
            {
                $out .= '<article data-data-modified="' . $res['modified'] . '">';
                $out .= $this->findSpecialFields(self::decodeHtml($res['content']));
                $out .= '</article>';

                $latest = max($latest, $res['modified']);
            }
        }
        else
        {
            return '<p class="fail">Article data for this page missing</p>';
        }

        // Set the latest modification time for header info
        $this->pageModified = $latest;


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

        // Last modification date
        $data[] = array(
            'url'  => 'http://github.com/paazmaya/naginata.fi',
            'alt'  => 'Tällä sivulla olevaa sisältöä on muokattu viimeksi ' . date('j.n.Y G:i', $this->pageModified),
            'text' => 'Muokattu viimeksi ' . date('j.n.Y G:i', $this->pageModified)
        );

        $links = array();
        foreach ($data as $item)
        {
            $a = '<a href="' . $item['url'] . '" title="' . $item['alt'] . '"';
            if (isset($item['data']))
            {
                // $(this).data('hover')
                $a .= ' data-hover="' . $item['data'] . '"';
            }
            $a .= '>' . $item['text'] . '</a>';
            $links[] = $a;
        }

        $out .= implode('|', $links);

        // TODO: #contribute text change per login status
        $out .= '</footer>';

        $base = '/js/';
        $out .= '<script type="text/javascript" src="' . $base . $this->minifiedName . 'js"></script>';

        $out .= '</body>';
        $out .= '</html>';

        return $out;
    }

    /**
     * Find and replace all the special fields listed in $this->specialFields
     * and call then the specific "render" method for that 3rd party service
     *
     * @param    string $str    Content to be searched
     *
     * @return    string    Replaced content, if any
     */
    private function findSpecialFields($str)
    {
        foreach ($this->specialFields as $key => $value)
        {
            $search = '/' . preg_quote('[' . $key . '|') . '(.*?)' . preg_quote(']') . '/i';
            $str = preg_replace_callback($search, array($this, $this->specialFields[$key]), $str);
        }

        return $str;
    }

    /**
     * The most simple image rendering option as the image in question
     * is stored locally at the server.
     * Also the image is shown as is, no linking to a higher resolution exists.
     * Hope that the file name is descriptive as it is used for alternative text.
     */
    private function renderLocalImage($matches)
    {
        $imgDir = '../public_html/img/';

        $out = '';

        if (isset($matches['1']) && $matches['1'] != '')
        {
            if (file_exists($imgDir . $matches['1']))
            {
                $alt = ucwords(str_replace('-', ' ', substr($matches['1'], 0, strrpos($matches['1'], '.'))));
                $size = getimagesize($imgDir . $matches['1']);
                $out .= '<div class="medialocal" data-key="local|' . $matches['1'] . '"><img src="/img/' . $matches['1'] . '" alt="' .
                    $alt . '" width="' . $size['0'] . '" height="' . $size['1'] . '" /></div>';
            }
        }

        return $out;
    }

    /**
     * Get the given Flickr data and render it as image thumbnails.
     * If there is just a single stinr, then it must be an image id.
     * If there are more parameters, separated by commas, then it should be
     * a specific API call with several pictures returned.
     */
    public function renderFlickr($matches)
    {
        $out = '';

        $params = array(
            'api_key'        => $this->shikakeOji->config['flickr']['apikey'],
            'format'         => 'json', // Always using JSON
            'nojsoncallback' => 1,
            'method'         => 'flickr.photos.search'
        );

        if (isset($matches['1']) && $matches['1'] != '')
        {
            $list = explode(',', $matches['1']);

            if (count($list) > 1)
            {
                // Multiple items, thus 'ul.imagelist'
                $params['per_page'] = 63;

                // Cache file name
                $cache = $this->cacheDir . 'flickr';

                foreach ($list as $item)
                {
                    $a = explode('=', $item);
                    if (count($a) == 2)
                    {
                        $params[$a['0']] = $a['1'];
                        $cache .= '_' . $a['0'] . '-' . $a['1'];
                    }
                }

                $cache .= '.json';
            }
            else
            {
                $cache = $this->cacheDir . 'flickr_' . $matches['1'] . '.json';
                $params['method'] = 'flickr.photos.getInfo';
                $params['photo_id'] = $matches['1'];

            }

            $url = 'http://api.flickr.com/services/rest/?' . http_build_query($params, null, '&');
            $feed = $this->getDataCache($cache, $url);
            $data = json_decode($feed, true);

            if (!isset($data))
            {
                return '<!-- flickr failed ' . $matches['1'] . ' -->';
            }

            if ($data['stat'] != 'ok')
            {
                return '<!.-- ' . $data['stat'] . ' -->';
            }

            if (count($list) > 1)
            {
                $out .= $this->renderFlickrList($data, $matches['1']);
            }
            else
            {
                // and flickr.photos.getSizes
                $cache = $this->cacheDir . 'flickr_' . $matches['1'] . '_sizes.json';
                $params['method'] = 'flickr.photos.getSizes';
                $url = 'http://api.flickr.com/services/rest/?' . http_build_query($params, null, '&');
                $feed = $this->getDataCache($cache, $url);
                $sizes = json_decode($feed, true);

                if (!isset($sizes))
                {
                    return '<!-- flickr sizes failed ' . $matches['1'] . ' -->';
                }


                $out .= $this->renderFlickrSingle($data['photo'], $sizes['sizes']['size']);
            }
        }

        return $out;
    }

    /**
     * Display a single picture from Flickr.
     * http://www.flickr.com/services/api/flickr.photos.getInfo.html
     * http://www.flickr.com/services/api/flickr.photos.getSizes.html
     */
    private function renderFlickrSingle($photo, $sizes)
    {
        // http://microformats.org/wiki/geo
        /*
        if (isset($photo['location']) && isset($photo['location']['latitude']) &&
            isset($photo['location']['longitude']))
        {
            $out .= '<span class="geo">' . $photo['location']['latitude'] 34.854133,
            $photo['location']['longitude']  134.67163,
        }
        */

        $collected = array(
            'id'          => $photo['id'],
            'title'       => $photo['title']['_content'],
            'description' => '',
            'published'   => DateTime::createFromFormat('Y-m-d H:i:s', $photo['dates']['taken'],
                new DateTimeZone('UTC')),
            'href'        => 'http://flickr.com/photos/' . $photo['owner']['nsid'] . '/' . $photo['id'],
            'owner'       => $photo['owner']['username'],
            'ownerlink'   => 'http://flickr.com/people/' . $photo['owner']['nsid']
        );

        $thumbs = array();

        foreach ($sizes as $size)
        {
            if ($size['label'] == 'Small')
            {
                $thumbs[] = array(
                    'width'  => $size['width'],
                    'height' => $size['height'],
                    'url'    => $size['source']
                );
            }
            else
            {
                if ($size['label'] == 'Large')
                {
                    $collected['inline'] = $size['source'];
                    $collected['inlinewidth'] = $size['width'];
                    $collected['inlineheight'] = $size['height'];
                }
            }
        }
        $collected['thumbs'] = $thumbs;


        return $this->createMediathumb($collected, 'flickr');
    }

    /**
     * List pictures from Flickr.
     * http://www.flickr.com/services/api/flickr.photos.search.html
     */
    private function renderFlickrList($data, $key)
    {
        if (!isset($data['photos']['photo']))
        {
            return '<!-- no pictures -->';
        }
        $out = '<ul class="imagelist" data-key="flickr|' . $key . '">';
        foreach ($data['photos']['photo'] as $photo)
        {
            // http://flic.kr/p/{base58-photo-id}
            $url = 'http://farm' . $photo['farm'] . '.static.flickr.com/' . $photo['server'] . '/' .
                $photo['id'] . '_' . $photo['secret'];
            $out .= '<li>';
            $out .= '<a href="' . $url . '_b.jpg" data-photo-page="http://www.flickr.com/photos/' .
                $photo['owner'] . '/' . $photo['id'] . '" title="' . $photo['title'] . '">';
            $out .= '<img src="' . $url . '_s.jpg" alt="' . $photo['title'] . '"/>';
            $out .= '</a>';
            $out .= '</li>';
            /*
            $filename = $this->cacheDir . 'flickr_' . $photo['id'] . '_' . $photo['secret'] . '_s.jpg';
            if (!file_exists($filename))
            {
                $img = file_get_contents($url . '_s.jpg');
                file_put_contents($filename, $img);
            }
            */
        }
        $out .= '</ul>';

        return $out;
    }

    /**
     * Show a link to a Youtube video. Javascript will handle opening it to a colorbox.
     * http://code.google.com/apis/youtube/2.0/developers_guide_php.html
     */
    private function renderYoutube($matches)
    {
        if (isset($matches['1']) && $matches['1'] != '')
        {
            $url = 'http://gdata.youtube.com/feeds/api/videos/' . $matches['1'] . '?alt=json&v=2';
            $cache = $this->cacheDir . 'youtube_' . $matches['1'] . '.json';
            $feed = $this->getDataCache($cache, $url);
            $data = json_decode($feed, true);

            if (!isset($data))
            {
                return '<!-- youtube failed ' . $matches['1'] . ' -->';
            }

            // Get the thumbs for this video
            $thumbs = array(); // store 2 which are 120x90
            foreach ($data['entry']['media$group']['media$thumbnail'] as $thumb)
            {
                /*
                $name = $this->cacheDir . 'youtube_' . $matches['1'] . '_' . substr($thumb['url'], strrpos($thumb['url'], '/') + 1);
                if (!file_exists($name))
                {
                    $img = file_get_contents($thumb['url']);
                    file_put_contents($name, $img);
                }
                */
                // Want two thumbs that are 120x90
                if ($thumb['height'] == 90 && count($thumbs) < 2)
                {
                    $thumbs[] = $thumb;
                }
            }

            // Z in the date-time stands for Coordinated Universal Time (UTC)
            $collected = array(
                'id'          => $matches['1'],
                'thumbs'      => $thumbs,
                'title'       => $data['entry']['title']['$t'],
                'description' => '',
                'published'   => DateTime::createFromFormat('Y-m-d\TH:i:s.000\Z', $data['entry']['published']['$t'],
                    new DateTimeZone('UTC')),
                'href'        => 'http://www.youtube.com/watch?v=' . $matches['1'],
                'inline'      => 'http://www.youtube.com/embed/' . $matches['1'] . '?version=3&f=videos&app=youtube_gdata',
                // type of application/x-shockwave-flash
                'iframe'      => true,
                'owner'       => $data['entry']['author']['0']['name']['$t'],
                'ownerlink'   => 'http://youtube.com/' . $data['entry']['author']['0']['name']['$t']
            );

            // https://developers.google.com/youtube/player_parameters


            return $this->createMediathumb($collected, 'youtube');
        }

        return '';
    }

    /**
     * Vimeo video link
     * According to http://vimeo.com/forums/topic:47127,
     * Vimeo time is measured at Eastern Timezone.
     */
    private function renderVimeo($matches)
    {
        if (isset($matches['1']) && $matches['1'] != '')
        {
            $url = 'http://vimeo.com/api/v2/video/' . $matches['1'] . '.json';
            $cache = $this->cacheDir . 'vimeo_' . $matches['1'] . '.json';
            $feed = $this->getDataCache($cache, $url);
            $data = json_decode($feed, true);

            if (!isset($data))
            {
                return '<!-- vimeo failed ' . $matches['1'] . ' -->';
            }

            if (is_array($data))
            {
                $data = $data['0'];
            }

            // Save all thumbnails, just for fun...
            /*
            foreach(array('thumbnail_small', 'thumbnail_medium', 'thumbnail_large') as $size)
            {
                $name = $this->cacheDir . 'vimeo_' . $matches['1'] . '_' . substr($data[$size], strrpos($data[$size], '/') + 1);

                if (!file_exists($name))
                {
                    $img = file_get_contents($data[$size]);
                    file_put_contents($name, $img);
                }
            }
            */

            $collected = array(
                'id'           => $matches['1'],
                'thumbs'       => array(array(
                    'url'    => $data['thumbnail_medium'],
                    'width'  => 200,
                    'height' => 150
                )),
                'title'        => $data['title'],
                'description'  => '',
                'published'    => DateTime::createFromFormat('Y-m-d H:i:s', $data['upload_date'],
                    new DateTimeZone('EST')),
                'href'         => 'http://vimeo.com/' . $matches['1'],
                'inline'       => 'http://player.vimeo.com/video/' . $matches['1'],
                'inlinewidth'  => $data['width'],
                'inlineheight' => $data['height'],
                'iframe'       => true,
                'owner'        => $data['user_name'],
                'ownerlink'    => $data['user_url']
            );

            // https://developer.vimeo.com/player/js-api

            return $this->createMediathumb($collected, 'vimeo');
        }

        return '';
    }

    /**
     * Create the "mediathumb" figure with the given data.
     * $data = array(
     *   'id' => '2352525252',
     *   'thumbs' => array(
     *     array(
     *       'url' => $data['thumbnail_medium'],
     *       'width' => 200,
     *       'height' => 150
     *     )
     *   ),
     *   'title' => $data['title'],
     *   'description' => '',
     *   'published' => DateTime::createFromFormat('Y-m-d H:i:s', $data['upload_date'], new DateTimeZone('EST')),
     *   'href' => 'http://vimeo.com/' . $matches['1'],
     *   'inline' => 'http://player.vimeo.com/video/' . $matches['1'],
     *   'inlinewidth' => $data['width'],
     *   'inlineheight' => $data['height'],
     *   'iframe' => true,
     *   'owner' => $data['user_name'],
     *   'ownerlink' => $data['user_url']
     * );
     * $service = 'vimeo'
     */
    private function createMediathumb($data, $service)
    {
        $out = '<figure class="mediathumb" data-key="' . $service . '|' . $data['id'] . '">';

        $out .= '<a class="' . $service . '" href="' . self::encodeHtml($data['href']) . '" data-url="' .
            self::encodeHtml($data['inline']) . '" title="' . $data['title'] . '"';

        if (isset($data['inlinewidth']) && isset($data['inlineheight']))
        {
            $out .= ' data-width="' . $data['inlinewidth'] . '" data-height="' . $data['inlineheight'] . '"';
        }
        if (isset($data['iframe']) && $data['iframe'] === true)
        {
            $out .= ' data-iframe="1"';
        }
        $out .= '>';

        // playicon will be shown when the link is hovered. hidden by default.
        $out .= '<span class="playicon"></span>';

        if (isset($data['thumbs']))
        {
            if (is_array($data['thumbs']))
            {
                foreach ($data['thumbs'] as $img)
                {
                    $out .= '<img src="' . self::encodeHtml($img['url']) . '" alt="' . $data['title'] . '"';

                    if (isset($img['width']))
                    {
                        $out .= ' width="' . $img['width'] . '"';
                    }
                    if (isset($img['height']))
                    {
                        $out .= ' height="' . $img['height'] . '"';
                    }
                    $out .= '/>';
                }
            }
        }

        $out .= '</a>';

        $out .= '<figcaption';
        if (isset($data['published']) && is_object($data['published']))
        {
            $out .= ' title="Julkaistu ' . date('j.n.Y G:i', $data['published']->getTimestamp()) . '"';
        }
        $out .= '>' . $data['title'] . ' / ';
        $out .= '<a href="' . self::encodeHtml($data['ownerlink']) . '" title="' . ucfirst($service) . ' - ' .
            $data['owner'] . '">' . $data['owner'] . '</a>';
        $out .= '</figcaption>';

        $out .= '</figure>';

        return $out;
    }

    /**
     * Get the cached data if available.
     * Update if needed as based on the cache lifetime setting.
     *
     * @return    string    JSON string
     */
    private function getDataCache($cache, $url)
    {
        $update = true;
        if (file_exists($cache))
        {
            $mtime = filemtime($cache);
            if (time() - $mtime < $this->cacheInterval)
            {
                $update = false;
            }
        }

        if ($update)
        {
            if (extension_loaded('curl'))
            {
                $data = $this->getDataCurl($url);
            }
            else
            {
                // Fall back to slower version...
                $data = file_get_contents($url);
            }
            file_put_contents($cache, $jsonstring = json_encode($data, JSON_PRETTY_PRINT));
        }
        else
        {
            $data = file_get_contents($cache);
        }

        return $data;
    }

    /**
     * Get data from the given URL by using CURL.
     *
     * @return    string    JSON string
     */
    private function getDataCurl($url)
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_STDERR         => $fh,
            CURLOPT_VERBOSE        => true,
            CURLOPT_REFERER        => 'http://naginata.fi' . $this->shikakeOji->currentPage
        ));

        //curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        //curl_setopt($ch, CURLOPT_USERAGENT, '');

        $results = curl_exec($ch);
        $headers = curl_getinfo($ch);

        $error_number = (int)curl_errno($ch);
        $error_message = curl_error($ch);

        curl_close($ch);

        fclose($fh);

        // invalid headers
        if (!in_array($headers['http_code'], array(0, 200)))
        {
            //throw new Exception('Bad headercode', (int) $headers['http_code']);
        }

        // are there errors?
        if ($error_number > 0)
        {
            //throw new Exception($error_message, $error_number);
        }

        return $results;
    }

}
