<?php
/** Redirect from old http URLs to new https versions */
$uri = strtok($_SERVER['REQUEST_URI'], '?');
$query = $_SERVER['QUERY_STRING'];
$extension = pathinfo($uri, PATHINFO_EXTENSION);
if ($extension === 'html') {
    $uri = substr($uri, 0, -5); // remove .html
    if ($uri === '/fixtures') {
        if (!empty($_GET['comp'])) {
            $comp = $_GET['comp'];
            $comps = [
                'Prem' => 'Premier+Division',
                'E-N' => 'East+(North)',
                'E-S' => 'East+(South)',
                'L-HC' => 'Local+Home+Counties',
                'L-SC' => 'Local+South+Central',
                'L-Pen' => 'Local+Peninsula',
                'L-Cot' => 'Local+Cotswolds',
                'L-Mid' => 'Local+Midlands',
                'L-EA' => 'Local+East+Anglia',
                'Snr Flags' => 'Senior+Flags',
                'Int Flags' => 'Intermediate+Flags',
                'Mnr Flags' => 'Minor+Flags',
            ];
            $new_comp = $comps[$comp] ?? '';
            if (!$new_comp) {
                if (str_starts_with($comp,'D')) {
                    $new_comp = 'Division+' . substr($comp,1);
                } elseif (str_starts_with($comp,'L-Lon')) {
                    $new_comp = 'Local+London+D' . substr($comp,5);
                }
            }
            if ($new_comp) {
                $query = "comp=$new_comp";
            }
        }
    } else {
        if (substr($uri, 0, 6) === "/rest/") {
            header("Access-Control-Allow-Origin: *");
        }
        $redirects = [
            // '/rest/clubs/spencer/fixtures' => '/api/semla/v1/clubs/Spencer/fixtures',
            
            '/history/flags-int' => '/history/flags-intermediate',
            '/developers' => '/data-resources',
            '/links' => '/about#Other-Useful-Websites',
            
            '/clubs/poi' => '/data-resources#Club-GPS-Location-Data',
            '/history/county' => '/history/southern-counties',
            '/history/division3-knockout-trophy' => '/history/division-3-knockout-trophy',
            '/history/division4-flags' => '/history/division-4-flags',
            '/history/westmidscup/westmidscup-2008' => '/history/westmidscup-2008',
            '/history/north-south-junior' => '/history/north-south-juniors',

            '/news/2015-16/black-hawks-down-hillcroft' => '/2015/10/black-hawks-down-hillcroft',
            '/news/2015-16/flags-spencer-and-hampstead-on-flags-collision-course' => '/2015/11/flags-spencer-and-hampstead-on-flags-collision-course',
            '/news/2015-16/hillcroft-top-of-the-tree-for-christmas' => '/2015/12/hillcroft-top-of-the-tree-for-christmas',
            '/news/2015-16/lethal-spencer-draw-first-blood' => '/2016/02/lethal-spencer-draw-first-blood',
            '/news/2015-16/presidents-report' => '/2016/06/presidents-report',
            '/news/2015-16/semla-preview-15-16' => '/2015/10/semla-preview-15-16',
            '/news/2015-16/spencer-and-hampstead-hurtle-towards-a-semla-showdown' => '/2016/01/spencer-and-hampstead-hurtle-towards-a-semla-showdown',
            '/news/2015-16/week-1-spencer-and-hampstead-fire-out-title-warnings' => '/2015/10/week-1-spencer-and-hampstead-fire-out-title-warnings',
            '/news/2015-16/week-2-hillcroft-book-room-at-the-top' => '/2015/10/week-2-hillcroft-book-room-at-the-top',
            '/news/2015-16/week-3-wildcats-win-shoot-out-after-dragons-roar-into-life' => '/2015/11/week-3-wildcats-win-shoot-out-after-dragons-roar-into-life',
            '/news/2015-16/week-4-roundup' => '/2015/11/week-4-roundup',
            '/news/2015-16/week-6-hampstead-close-in-on-the-century' => '/2015/11/week-6-hampstead-close-in-on-the-century',
            '/news/2016-17/semla-showdown' => '/2017/02/semla-showdown',
            '/news/2016-17/watch-semla-matches-on-youtube' => '/2017/04/watch-semla-matches-on-youtube',
            '/news/2016-17/wizards-from-the-lizards-cast-spells-over-spencers-title-charge' => '/2017/02/wizards-from-the-lizards-cast-spells-over-spencers-title-charge',
            '/news/2017-18/hampstead-top-of-the-tree-for-christmas' => '/2017/12/hampstead-top-of-the-tree-for-christmas',
            '/news/2017-18/lessons-in-lax' => '/2017/10/lessons-in-lax',
            '/news/2017-18/semla-2017-18-season-preview' => '/2017/09/semla-2017-18-season-preview',
            '/news/2017-18/the-warriors-come-out-to-play' => '/2017/10/the-warriors-come-out-to-play',
            '/news/2017-18/waiting-game-for-triumphant-hillcroft' => '/2017/10/waiting-game-for-triumphant-hillcroft',
            '/news/2017-18/week-3-roundup-2017' => '/2017/10/week-3-roundup-2017',
            '/news/2017-18/week-5-roundup-2017' => '/2017/11/week-5-roundup-2017',
            '/news/2017-18/week-6-the-flags-are-flying-for-an-epic' => '/2017/11/week-6-the-flags-are-flying-for-an-epic',
            '/news/2018-19/2018-19-league-structure' => '/2018/06/2018-19-league-structure',
            '/news/2019-20/coronavirus' => '/2020/03/coronavirus-season-suspended',
            '/news/2019-20/lacrosse-talk-magazines' => '/2020/01/lacrossetalk-magazines',
            '/news/2020-21/semla-ready-for-the-new-season' => '/2020/08/semla-ready-for-the-new-season',
            '/regs/check-list' => '/club-responsibilities',
            '/regs/disciplinary' => '/disciplinary',
            '/regs/forms' => '/forms',
        ];
        $new_uri = $redirects[$uri] ?? '';
        if ($new_uri) {
            $uri = $new_uri;
        } else {
            $uri = strtr($uri, [
                '/flags/flags' => '/flags',
                '/history/league/' => '/history/',
            ]);
            foreach ([
                '!/rest/fixtures/mens/([^\.]*)!' => '/api/semla/v1/teams/$1/fixtures',
                '!/(.*?)flags(-\d\d\d\d|)-tables$!' => '/$1flags$2-rounds',
            ] as $regex => $replace) {
                $uri = preg_replace($regex, $replace, $uri, 1, $count);
                if ($count) break;
            };
        }
    }
} elseif ($extension === 'ics') {
    $uri = preg_replace('!/fixtures_([^/\.]*)\.ics$!',
        '/api/semla/v1/teams/$1/fixtures.ics', $uri, 1);
} elseif ($extension === 'pl') {
    if ($uri === '/search/search.pl') $uri = '/search';
} elseif ($extension === 'php') {
    $uri = '/';
    $query = '';
} elseif ($extension === '') {
    if ($uri === '/juniors/') $uri = '/juniors';
}

$url = 'https://' . (str_starts_with($_SERVER['HTTP_HOST'], 'south') ? 'www.' : '')
        . $_SERVER['HTTP_HOST'] . $uri . ($query ? "?$query" : '');
header("Location: $url", true, 301);
exit;
