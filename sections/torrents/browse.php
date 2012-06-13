<?
/* * **********************************************************************
 * -------------------- Browse page ---------------------------------------
 * Welcome to one of the most complicated pages in all of gazelle - the
 * browse page. 
 * 
 * This is the page that is displayed when someone visits torrents.php
 * 
 * It offers normal and advanced search, as well as enabled/disabled
 * grouping. 
 *
 * For an outdated non-Sphinx version, use /sections/torrents/browse.php.
 *
 * Don't blink.
 * Blink and you're dead.
 * Don't turn your back.
 * Don't look away.
 * And don't blink.
 * Good Luck.
 *
 * *********************************************************************** */

include(SERVER_ROOT . '/sections/bookmarks/functions.php');
include(SERVER_ROOT . '/sections/torrents/functions.php');

// The "order by x" links on columns headers
function header_link($SortKey, $DefaultWay = "desc") {
    global $OrderBy, $OrderWay;
    if ($SortKey == $OrderBy) {
        if ($OrderWay == "desc") {
            $NewWay = "asc";
        } else {
            $NewWay = "desc";
        }
    } else {
        $NewWay = $DefaultWay;
    }

    return "torrents.php?order_way=" . $NewWay . "&amp;order_by=" . $SortKey . "&amp;" . get_url(array('order_way', 'order_by'));
}

$TokenTorrents = $Cache->get_value('users_tokens_' . $UserID);
if (empty($TokenTorrents)) {
    $DB->query("SELECT TorrentID, Type FROM users_freeleeches WHERE UserID=$UserID AND Expired=FALSE");
    $TokenTorrents = $DB->to_array('TorrentID');
    $Cache->cache_value('users_tokens_' . $UserID, $TokenTorrents);
}

// Search by infohash
if (!empty($_GET['searchtext'])) {
    $InfoHash = $_GET['searchtext'];

    if ($InfoHash = is_valid_torrenthash($InfoHash)) {
        $InfoHash = db_string(pack("H*", $InfoHash));
        $DB->query("SELECT ID,GroupID FROM torrents WHERE info_hash='$InfoHash'");
        if ($DB->record_count() > 0) {
            list($ID, $GroupID) = $DB->next_record();
            header('Location: torrents.php?id=' . $GroupID . '&torrentid=' . $ID);
            die();
        }
    }
}

// Setting default search options
if (!empty($_GET['setdefault'])) {
    $UnsetList = array('page', 'setdefault');
    $UnsetRegexp = '/(&|^)(' . implode('|', $UnsetList) . ')=.*?(&|$)/i';

    $DB->query("SELECT SiteOptions FROM users_info WHERE UserID='" . db_string($LoggedUser['ID']) . "'");
    list($SiteOptions) = $DB->next_record(MYSQLI_NUM, false);
    if (!empty($SiteOptions)) {
        $SiteOptions = unserialize($SiteOptions);
    } else {
        $SiteOptions = array();
    }
    $SiteOptions['DefaultSearch'] = preg_replace($UnsetRegexp, '', $_SERVER['QUERY_STRING']);
    $LoggedUser['DefaultSearch'] = $SiteOptions['DefaultSearch'] ;
    $DB->query("UPDATE users_info SET SiteOptions='" . db_string(serialize($SiteOptions)) . "' WHERE UserID='" . db_string($LoggedUser['ID']) . "'");
    $Cache->begin_transaction('user_info_heavy_' . $UserID);
    $Cache->update_row(false, array('DefaultSearch' => $SiteOptions['DefaultSearch']));
    $Cache->commit_transaction(0);

// Clearing default search options
} elseif (!empty($_GET['cleardefault'])) {
    $DB->query("SELECT SiteOptions FROM users_info WHERE UserID='" . db_string($LoggedUser['ID']) . "'");
    list($SiteOptions) = $DB->next_record(MYSQLI_NUM, false);
    $SiteOptions = unserialize($SiteOptions);
    $SiteOptions['DefaultSearch'] = '';
    $LoggedUser['DefaultSearch'] = '';
    $DB->query("UPDATE users_info SET SiteOptions='" . db_string(serialize($SiteOptions)) . "' WHERE UserID='" . db_string($LoggedUser['ID']) . "'");
    $Cache->begin_transaction('user_info_heavy_' . $UserID);
    $Cache->update_row(false, array('DefaultSearch' => ''));
    $Cache->commit_transaction(0);

// Use default search options
} elseif ((empty($_SERVER['QUERY_STRING']) || (count($_GET) == 1 && isset($_GET['page']))) && !empty($LoggedUser['DefaultSearch'])) {
    if (!empty($_GET['page'])) {
        $Page = $_GET['page'];
        parse_str($LoggedUser['DefaultSearch'], $_GET);
        $_GET['page'] = $Page;
    } else {
        parse_str($LoggedUser['DefaultSearch'], $_GET);
    }
}

$AdvancedSearch = false;
$Action = 'action=basic';
if (((!empty($_GET['action']) && strtolower($_GET['action']) == "advanced") || (!empty($LoggedUser['SearchType']) && ((!empty($_GET['action']) && strtolower($_GET['action']) != "basic") || empty($_GET['action'])))) && check_perms('site_advanced_search')) {
    $AdvancedSearch = true;
    $Action = 'action=advanced';
}

$Queries = array();

// Simple Search
if (!$AdvancedSearch) {
    if (!empty($_GET['searchtext'])) {
        $SearchList = explode(' ', $_GET['searchtext']);
        $SearchListEx = array();
        foreach ($SearchList as $Key => &$Word) {
            $Word = trim($Word);
            if (strlen($Word) >= 2) {
                if ($Word[0] == '-' && strlen($Word) >= 3) {
                    $SearchListEx[] = '!' . $SS->EscapeString(substr($Word, 1));
                    unset($SearchList[$Key]);
                } else {
                    $Word = $SS->EscapeString($Word);
                }
            } else {
                unset($SearchList[$Key]);
            }
        }
        unset($Word);
    }

    if (empty($_GET['search_type']) && !empty($SearchList) && count($SearchList) > 1) {
        $_GET['search_type'] = '0';
        if (!empty($SearchListEx)) {
            $Queries[] = '@searchtext ( ' . implode(' | ', $SearchList) . ' ) ' . implode(' ', $SearchListEx);
        } else {
            $Queries[] = '@searchtext ( ' . implode(' | ', $SearchList) . ' )';
        }
    } elseif (!empty($SearchList)) {
        $Queries[] = '@searchtext ' . implode(' ', array_merge($SearchList, $SearchListEx));
    } else {
        $_GET['search_type'] = '1';
    }

    if (!empty($_GET['taglist'])) {
        $_GET['taglist'] = cleanup_tags($_GET['taglist']);
        //$_GET['taglist'] = str_replace('.', '_', $_GET['taglist']);
        $TagList = explode(' ', $_GET['taglist']);
        $TagListEx = array();
        foreach ($TagList as $Key => &$Tag) {
            $Tag = strtolower(trim($Tag)) ;
            
            if ( ($Tag[0] != '-' && strlen($Tag)>= 2) || strlen($Tag)>= 3 ) {
                if ($Tag[0] == '-') {
                    $Tag = '-'. get_tag_synomyn( substr($Tag, 1), false);
                    $Tag = str_replace('.', '_', $Tag);
                    $TagListEx[] = '!' . $SS->EscapeString(substr($Tag, 1));
                    unset($TagList[$Key]);
                } else {
                    $Tag = get_tag_synomyn($Tag, false);
                    $Tag = str_replace('.', '_', $Tag);
                    $Tag = $SS->EscapeString($Tag);
                }
            } else {
                unset($TagList[$Key]);
            }
        }
        unset($Tag);
        // moved this to bottom so we can grab synomyns more easily
        $_GET['taglist'] = str_replace('.', '_', $_GET['taglist']);
    }

    if (empty($_GET['tags_type']) && !empty($TagList) && count($TagList) > 1) {
        $_GET['tags_type'] = '0';
        if (!empty($TagListEx)) {
            $Queries[] = '@taglist ( ' . implode(' | ', $TagList) . ' ) ' . implode(' ', $TagListEx);
        } else {
            $Queries[] = '@taglist ( ' . implode(' | ', $TagList) . ' )';
        }
    } elseif (!empty($TagList)) {
        $Queries[] = '@taglist ' . implode(' ', array_merge($TagList, $TagListEx));
    } else {
        $_GET['tags_type'] = '1';
    }
} else {
    // Advanced search, and yet so much simpler in code.
    if (!empty($_GET['searchtext'])) {
        $SearchText = ' ' . trim($_GET['searchtext']);
        $SearchText = preg_replace(array('/ -/','/ not /i', '/ or /i', '/ and /i'), array(' !', ' -', ' | ', ' & '), $SearchText);
        $SearchText = trim($SearchText);
        
        $Queries[] = '@searchtext ' . $SearchText;
    }
    
    if (!empty($_GET['taglist'])) {
        // do synomyn replacement and skip <=2 length tags
        $TagList = explode(' ', $_GET['taglist']);
        //$TagListEx = array();
        foreach ($TagList as $Key => &$Tag) {
            $Tag = strtolower(trim($Tag)) ;
           
            if ( ($Tag[0] != '-' && strlen($Tag)>= 2) || strlen($Tag)>= 3 ) {
                if ($Tag[0] == '-') {
                    $Tag = '-'. get_tag_synomyn( substr($Tag, 1), false);
                    //$TagListEx[] = '!' . $SS->EscapeString(substr($Tag, 1));
                    //unset($TagList[$Key]);
                } else {
                    $Tag = get_tag_synomyn($Tag, false);
                    //$Tag = $SS->EscapeString($Tag);
                }
            } else {
                unset($TagList[$Key]);
            }
        }
        unset($Tag);
        $TagList = implode(' ', $TagList);
        $TagList = trim(str_replace('.', '_', $TagList));       // $_GET['taglist']));
        $TagList = preg_replace(array('/ -/','/ not /i', '/ or /i', '/ and /i'), array(' !', ' -', ' | ', ' & '), $TagList);
        $TagList = trim($TagList);
        
        $Queries[] = '@taglist ' . $TagList;
        
    }
}

foreach (array('filelist') as $Search) {
    if (!empty($_GET[$Search])) {
        $_GET[$Search] = str_replace(array('%'), '', $_GET[$Search]);
        if ($Search == 'filelist') {
            $Queries[] = '@filelist "' . $SS->EscapeString($_GET['filelist']) . '"~20';
        } else {
            $Words = explode(' ', $_GET[$Search]);
            foreach ($Words as $Key => &$Word) {
                if ($Word[0] == '-' && strlen($Word) >= 3 && count($Words) >= 2) {
                    $Word = '!' . $SS->EscapeString(substr($Word, 1));
                } elseif (strlen($Word) >= 2) {
                    $Word = $SS->EscapeString($Word);
                } else {
                    unset($Words[$Key]);
                }
            }
            $Words = trim(implode(' ', $Words));
            if (!empty($Words)) {
                $Queries[] = "@$Search " . $Words;
            }
        }
    }
}

foreach (array('freetorrent') as $Search) {
    if (isset($_GET[$Search]) && $_GET[$Search] !== '') {
        if ($Search == 'freetorrent') {
            switch ($_GET[$Search]) {
                case 0: $SS->set_filter($Search, array(0));
                    break;
                case 1: $SS->set_filter($Search, array(1));
                    break;
                case 2: $SS->set_filter($Search, array(2));
                    break;
                case 3: $SS->set_filter($Search, array(0), true);
                    break;
            }
        } else {
            $SS->set_filter($Search, array($_GET[$Search]));
        }
    }
}

if (!empty($_GET['filter_cat'])) {
    $SS->set_filter('newcategoryid', array_keys($_GET['filter_cat']));
}


if (!empty($_GET['page']) && is_number($_GET['page'])) {
    if (check_perms('site_search_many')) {
        $Page = $_GET['page'];
    } else {
        $Page = min(SPHINX_MAX_MATCHES / TORRENTS_PER_PAGE, $_GET['page']);
    }
    $MaxMatches = min(SPHINX_MAX_MATCHES, SPHINX_MATCHES_START + SPHINX_MATCHES_STEP * floor(($Page - 1) * TORRENTS_PER_PAGE / SPHINX_MATCHES_STEP));
    $SS->limit(($Page - 1) * TORRENTS_PER_PAGE, TORRENTS_PER_PAGE, $MaxMatches);
} else {
    $Page = 1;
    $MaxMatches = SPHINX_MATCHES_START;
    $SS->limit(0, TORRENTS_PER_PAGE);
}

if (!empty($_GET['order_way']) && $_GET['order_way'] == 'asc') {
    $Way = SPH_SORT_ATTR_ASC;
    $OrderWay = 'asc'; // For header links
} else {
    $Way = SPH_SORT_ATTR_DESC;
    $_GET['order_way'] = 'desc';
    $OrderWay = 'desc';
}

if (empty($_GET['order_by']) || !in_array($_GET['order_by'], array('time', 'size', 'seeders', 'leechers', 'snatched', 'random'))) {
    $_GET['order_by'] = 'time';
    $OrderBy = 'time'; // For header links
} elseif ($_GET['order_by'] == 'random') {
    $OrderBy = '@random';
    $Way = SPH_SORT_EXTENDED;
    $SS->limit(0, TORRENTS_PER_PAGE, TORRENTS_PER_PAGE);
} else {
    $OrderBy = $_GET['order_by'];
}

$SS->SetSortMode($Way, $OrderBy);


if (count($Queries) > 0) {
    $Query = implode(' ', $Queries);
} else {
    $Query = '';
    if (empty($SS->Filters)) {
        $SS->set_filter('size', array(0), true);
    }
}

$SS->set_index(SPHINX_INDEX . ' delta');
$Results = $SS->search($Query, '', 0, array(), '', '');
$TorrentCount = $SS->TotalResults;

// These ones were not found in the cache, run SQL
if (!empty($Results['notfound'])) {

    $SQLResults = get_groups($Results['notfound']);

    if (is_array($SQLResults['notfound'])) { // Something wasn't found in the db, remove it from results
        reset($SQLResults['notfound']);
        foreach ($SQLResults['notfound'] as $ID) {
            unset($SQLResults['matches'][$ID]);
            unset($Results['matches'][$ID]);
        }
    }
    // Merge SQL results with sphinx/memcached results
    foreach ($SQLResults['matches'] as $ID => $SQLResult) {
        $Results['matches'][$ID] = array_merge($Results['matches'][$ID], $SQLResult);
        ksort($Results['matches'][$ID]);
    }
}

$Results = $Results['matches'];

show_header('Browse Torrents', 'browse,overlib,jquery,jquery.cookie');



// List of pages  
$Pages = get_pages($Page, $TorrentCount, TORRENTS_PER_PAGE);
?>

<form name="filter" method="get" action=''>  
    <div id="search_box" class="filter_torrents"> 
    <div class="head">
        Filter		
        <? if ($AdvancedSearch) { ?>
            (<a href="torrents.php?action=basic&amp;<?=get_url(array('action')); ?>">Basic Search</a>)
        <? } else { ?>
            (<a href="torrents.php?action=advanced&amp;<?= get_url(array('action')) ?>">Advanced Search</a>)
        <? } ?>
    </div>

        <div class="box pad">
            <table class="cat_list">
                <?
                $row = 'a';
                $x = 0;
                reset($NewCategories);
                foreach ($NewCategories as $Cat) {
                    if ($x % 7 == 0) {
                        if ($x > 0) {
                            ?>
                            </tr>
                        <? } ?>
                        <tr class="row<?=$row?>">
                            <?
                            $row = $row == 'a' ? 'b' : 'a';
                        }
                        $x++;
                        ?>
                        <td>
                            <input type="checkbox" name="filter_cat[<?= ($Cat['id']) ?>]" id="cat_<?= ($Cat['id']) ?>" value="1" <? if (isset($_GET['filter_cat'][$Cat['id']])) { ?>checked="checked"<? } ?>/>
                            <label for="cat_<?= ($Cat['id']) ?>"><a href="torrents.php?filter_cat[<?=$Cat['id']?>]=1"><?= $Cat['name'] ?></a></label>
                        </td>
                    <? } ?>                           
                    <td colspan="<?= 7 - ($x % 7) ?>"></td>
                </tr>
                <tr>
                    <td colspan="7" style="text-align:right"> 
                        <input type="button" value="Reset" onclick="location.href='torrents.php<? if (isset($_GET['action']) && $_GET['action'] == "advanced") { ?>?action=advanced<? } ?>'" />
                &nbsp;&nbsp;
                <? if (count($Queries) > 0 || count($SS->Filters) > 0) { ?>
                    <input type="submit" name="setdefault" value="Make Default" />
                    <?
                }

                if (!empty($LoggedUser['DefaultSearch'])) {
                    ?>
                    <input type="submit" name="cleardefault" value="Clear Default" />
                <? } ?>
                    </td>
                </tr>
            </table>
            <table class="noborder" width="100%">
                <tr>
                    <td class="label">
                        <a href="#" onclick="$('#taglist').toggle(); if(this.innerHTML=='(View Tags)'){this.innerHTML='(Hide Tags)';} else {this.innerHTML='(View Tags)';}; return false;"><?= (empty($LoggedUser['ShowTags'])) ? '(View Tags)' : '(Hide Tags)' ?></a>
                    </td>
                </tr>
            </table>
            <table class="taglist <? if (empty($LoggedUser['ShowTags'])) { ?>hidden<? } ?>" id="taglist">
                <tr>
                    <?
                    $GenreTags = $Cache->get_value('genre_tags');
                    if (!$GenreTags) {
                        $DB->query('SELECT Name FROM tags WHERE TagType=\'genre\' ORDER BY Name');
                        $GenreTags = $DB->collect('Name');
                        $Cache->cache_value('genre_tags', $GenreTags, 3600 * 6);
                    }

                    $x = 0;
                    foreach ($GenreTags as $Tag) {
                        ?>
                        <td width="12.5%"><a href="#" onclick="add_tag('<?= $Tag ?>');return false;"><?= $Tag ?></a></td>
                        <?
                        $x++;
                        if ($x % 7 == 0) {
                            ?>
                        </tr>
                        <tr>
                            <?
                        }
                    }
                    if ($x % 7 != 0) { // Padding
                        ?>
                        <td colspan="<?= 7 - ($x % 7) ?>"> </td>
                    <? } ?>
                </tr>
            </table>
            <table class="noborder">
                <tr>
                    <td class="label">Order by:</td>
                    <td colspan="<?= ($AdvancedSearch) ? '3' : '1' ?>">
                        <select name="order_by" style="width:auto;">
                            <option value="time"<? selected('order_by', 'time') ?>>Time added</option>
                            <option value="size"<? selected('order_by', 'size') ?>>Size</option>
                            <option value="snatched"<? selected('order_by', 'snatched') ?>>Snatched</option>
                            <option value="seeders"<? selected('order_by', 'seeders') ?>>Seeders</option>
                            <option value="leechers"<? selected('order_by', 'leechers') ?>>Leechers</option>
                            <option value="random"<? selected('order_by', 'random') ?>>Random</option>
                        </select>
                        <select name="order_way">
                            <option value="desc"<? selected('order_way', 'desc') ?>>Descending</option>
                            <option value="asc" <? selected('order_way', 'asc') ?>>Ascending</option>
                        </select>
                    <? if (check_perms('site_search_many')) { ?>
                            <label style="margin-left:40px;" for="limit_matches"><strong>Limited search results:</strong></label>
                            <input type="checkbox" value="1" name="limit_matches" <? selected('limit_matches', 1, 'checked') ?> />
                    <? } ?>
                    </td>
                </tr>
                <? if ($AdvancedSearch) { ?>
                    <tr>
                        <td colspan="3">
                            The advanced search supports full boolean search, click <a href="articles.php?topic=search">here</a> for more information.
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Search Term:</td>
                        <td colspan="3">
                            <input type="text" spellcheck="false" size="40" name="searchtext" class="inputtext" title="Supports full boolean search" value="<? form('searchtext') ?>" />
                            <input type="hidden" name="action" value="advanced" />
                        </td>
                    </tr>
                    <tr>
                        <td class="label">File List:</td>
                        <td colspan="3">
                            <input type="text" spellcheck="false" size="40" name="filelist" class="inputtext" value="<? form('filelist') ?>" />
                        </td>
                    </tr>
                <? } else { // BASIC SEARCH ?>
                    <tr>
                        <td class="label">Search terms:</td>
                        <td colspan="3">
                            <input type="text" spellcheck="false" size="40" name="searchtext" class="inputtext" title="Use -word to exclude a word" value="<? form('searchtext') ?>" />
                            <input type="radio" name="search_type" id="search_type0" value="0" <? selected('search_type', 0, 'checked') ?> /><label for="search_type0"> Any</label>&nbsp;&nbsp;
                            <input type="radio" name="search_type" id="search_type1" value="1"  <? selected('search_type', 1, 'checked') ?> /><label for="search_type1"> All</label>
                            <? if (!empty($LoggedUser['SearchType'])) { ?>
                                <input type="hidden" name="action" value="basic" />
                            <? } ?>
                        </td>
                    </tr>
                <? } ?>
                <? if ($AdvancedSearch) { ?>                    
                <tr>                    
                    <td class="label">Tags:</td>
                    <td colspan="3">
                        <input type="text" size="40" id="tags" name="taglist" class="inputtext" title="Supports full boolean search" value="<?= str_replace('_', '.', form('taglist', true)) ?>" />&nbsp;					
                    </td>
                </tr>
                <? } else { // BASIC SEARCH ?>
                <tr>                    
                    <td class="label">Tags:</td>
                    <td colspan="3">
                        <input type="text" size="40" id="tags" name="taglist" class="inputtext" title="Use -tag to exclude tag" value="<?= str_replace('_', '.', form('taglist', true)) ?>" />&nbsp;					
                        <input type="radio" name="tags_type" id="tags_type0" value="0" <? selected('tags_type', 0, 'checked') ?> /><label for="tags_type0"> Any</label>&nbsp;&nbsp;
                        <input type="radio" name="tags_type" id="tags_type1" value="1"  <? selected('tags_type', 1, 'checked') ?> /><label for="tags_type1"> All</label>
                    
                        <input style="float:right" type="submit" value="Filter Torrents" />
               
                    </td>
                </tr>
                <? } ?>
            </table>
            <div>
                <span ><?= number_format($TorrentCount) . ($TorrentCount < SPHINX_MAX_MATCHES && $TorrentCount == $MaxMatches ? '+' : '') ?> Results</span>
                
            </div>
        </div>
    </div>
</form>
 <div class="filter_slidetoggle"><a href="#" id="search_button">Open Search Center</a> </div>
 <div class="linkbox"><?= $Pages ?></div>
<?
if (count($Results) == 0) {
    $DB->query("SELECT 
	tags.Name,
	((COUNT(tags.Name)-2)*(SUM(tt.PositiveVotes)-SUM(tt.NegativeVotes)))/(tags.Uses*0.8) AS Score
	FROM xbt_snatched AS s 
	INNER JOIN torrents AS t ON t.ID=s.fid 
	INNER JOIN torrents_group AS g ON t.GroupID=g.ID 
	INNER JOIN torrents_tags AS tt ON tt.GroupID=g.ID
	INNER JOIN tags ON tags.ID=tt.TagID
	WHERE s.uid='$LoggedUser[ID]'
	AND tt.TagID<>'13679'
	AND tt.TagID<>'4820'
	AND tt.TagID<>'2838'
	AND tags.Uses > '10'
	GROUP BY tt.TagID
	ORDER BY Score DESC
	LIMIT 8");
    ?>
    <div class="box pad" align="center">
        <h2>Your search did not match anything.</h2>
        <p>Make sure all names are spelled correctly, or try making your search less specific.</p>
        <p>You might like (Beta): <? while (list($Tag) = $DB->next_record()) { ?><a href="torrents.php?taglist=<?= $Tag ?>"><?= $Tag ?></a> <? } ?></p>
    </div>
    <?
    show_footer();
    die();
}

$Bookmarks = all_bookmarks('torrent');
?>

 <div class="head">Torrents</div>
<table class="torrent_table grouping" id="torrent_table">
    <tr class="colhead">
        <td class="small cats_col"></td>
        <td width="100%">Name</td>
        <td>Files</td>
        <td><a href="<?= header_link('time') ?>">Time</a></td>
        <td><a href="<?= header_link('size') ?>">Size</a></td>
        <td class="sign"><a href="<?= header_link('snatched') ?>"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" /></a></td>
        <td class="sign"><a href="<?= header_link('seeders') ?>"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" /></a></td>
        <td class="sign"><a href="<?= header_link('leechers') ?>"><img src="static/styles/<?= $LoggedUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" /></a></td>
        <td>Uploader</td>
    </tr>
    <?
// Start printing torrent list
$row='a';
    foreach ($Results as $GroupID => $Data) {   
        list($GroupID2, $GroupName, $TagList, $Torrents, $FreeTorrent, $Image, $TotalLeechers, $NewCategoryID, $SearchText, $TotalSeeders, $MaxSize, $TotalSnatched, $GroupTime) = array_values($Data);

        $TagList = explode(' ', str_replace('_', '.', $TagList));

        $TorrentTags = array();
        foreach ($TagList as $Tag) {
            $TorrentTags[] = '<a href="torrents.php?' . $Action . '&amp;taglist=' . $Tag . '">' . $Tag . '</a>';
        }
        $TorrentTags = implode(', ', $TorrentTags);


        // Viewing a type that does not require grouping

        list($TorrentID, $Data) = each($Torrents);

        $OverImage = $Image != '' ? $Image : '/static/common/noartwork/noimage.png';
        $OverName = strlen($GroupName) <= 60 ? $GroupName : substr($GroupName, 0, 56) . '...';
        $SL = ($TotalSeeders == 0 ? "<span class=r00>" . number_format($TotalSeeders) . "</span>" : number_format($TotalSeeders)) . "/" . number_format($TotalLeechers);
        $Overlay = "<table class=overlay><tr><td class=overlay colspan=2><strong>" . $OverName . "</strong></td><tr><td class=leftOverlay><img style='max-width: 150px;' src=" . $OverImage . "></td><td class=rightOverlay><strong>Uploader:</strong><br />{$Data['Username']}<br /><br /><strong>Size:</strong><br />" . get_size($Data['Size']) . "<br /><br /><strong>Snatched:</strong><br />" . number_format($TotalSnatched) . "<br /><br /><strong>Seeders/Leechers:</strong><br />" . $SL . "</td></tr></table>";
        $AddExtra = '';
        if ($Data['FreeTorrent'] == '1') {
            $AddExtra .= ' <strong>/ Freeleech!</strong>';
        } elseif ($Data['FreeTorrent'] == '2') {
            $AddExtra .= ' <strong>/ Neutral Leech!</strong>';
        }
        if ($Data[double_seed] == '1') {
            $AddExtra .= ' <strong>/ Doubleseed!</strong>';
        }
        
        if (!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['Type'] == 'leech') {
            $AddExtra .= ' <strong>/ Personal Freeleech!</strong>';
        } elseif (!empty($TokenTorrents[$TorrentID]) && $TokenTorrents[$TorrentID]['Type'] == 'seed') {
            $AddExtra .= ' <strong>/ Personal Doubleseed!</strong>';
        }
        if ($Data['ReportCount'] > 0) {
            $Title = "This torrent has ".$Data['ReportCount']." active ".($Data['ReportCount'] > 1 ?'reports' : 'report');
            $AddExtra .= ' /<span class="reported" title="'.$Title.'"> Reported</span>';
        }
        $row = ($row == 'a'? 'b' : 'a');
        $IsMarkedForDeletion = $Data['Status'] == 'Warned' || $Data['Status'] == 'Pending';
        ?> 
        <tr class="torrent <?=($IsMarkedForDeletion?'redbar':"row$row")?>">
            <td class="center cats_col">
                <? $CatImg = 'static/common/caticons/' . $NewCategories[$NewCategoryID]['image']; ?>
                <div title="<?= $NewCategories[$NewCategoryID]['tag'] ?>"><a href="torrents.php?filter_cat[<?=$NewCategoryID?>]=1"><img src="<?= $CatImg ?>" /></a></div>
            </td>
            <td>
                <span>
                    [ <a href="torrents.php?action=download&amp;id=<?= $TorrentID ?>&amp;authkey=<?= $LoggedUser['AuthKey'] ?>&amp;torrent_pass=<?= $LoggedUser['torrent_pass'] ?>" title="Download">DL</a>
                    <? if (($LoggedUser['FLTokens'] > 0) && $Data['HasFile']
                            && (empty($TokenTorrents[$TorrentID]) || $TokenTorrents[$TorrentID]['Type'] != 'leech') && empty($Data['FreeTorrent']) && ($LoggedUser['CanLeech'] == '1')) {
                        ?>
                        | <a href="torrents.php?action=download&amp;id=<?= $TorrentID ?>&amp;authkey=<?= $LoggedUser['AuthKey'] ?>&amp;torrent_pass=<?= $LoggedUser['torrent_pass'] ?>&usetoken=1" title="Use a FL Token" onClick="return confirm('Are you sure you want to use a freeleech token here?');">FL</a>
    <? } ?>				
                    | <a href="reportsv2.php?action=report&amp;id=<?= $TorrentID ?>" title="Report">RP</a>]
               
<?                if (check_perms('torrents_review') && $Data['Status'] == 'Okay') { 
                        echo  '&nbsp;'.get_status_icon('Okay');
                  }        
?>
                </span>

                <script>
                    var overlay<?=$GroupID?> = <?=json_encode($Overlay)?>
                </script>
                
                <a href="torrents.php?id=<?=$GroupID?>" onmouseover="return overlib(overlay<?=$GroupID?>, FULLHTML);" onmouseout="return nd();"><?=$GroupName?></a> <?=$AddExtra?>
                <br />
                <div class="tags">
    <?= $TorrentTags ?>
                </div>
            </td>
            <td><?= $Data['FileCount'] ?></td>
            <td class="nobr"><?= time_diff($GroupTime, 1) ?></td>
            <td class="nobr"><?= get_size($Data['Size']) ?></td>
            <td><?= number_format($TotalSnatched) ?></td>
            <td<?= ($TotalSeeders == 0) ? ' class="r00"' : '' ?>><?= number_format($TotalSeeders) ?></td>
            <td><?= number_format($TotalLeechers) ?></td>
            <td class="user"><a href="user.php?id=<?= $Data['UserID'] ?>" class="user"><?= $Data['Username'] ?></a></td>
        </tr>
        <?
    }
    ?>
</table>
<div class="linkbox"><?= $Pages ?></div>
<? show_footer(array('disclaimer' => false)); ?>
