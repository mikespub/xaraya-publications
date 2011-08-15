<?php
/**
 * Publications Module
 *
 * @package modules
 * @subpackage publications module
 * @category Third Party Xaraya Module
 * @version 2.0.0
 * @copyright (C) 2011 Netspan AG
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @author Marc Lutolf <mfl@netspan.ch>
 */
/**
 * Display publication
 *
 * @param int itemid
 * @param str name
 * @param int page
 * @param int ptid The publication type ID
 * @return array with template information
 */

/**
 * Notes
 * If passed an itemid this function will return the page with that ID
 * If instead passed a name or ID of a publication type it will show the view of that publication type
 * If nothing is passed it will show the default page of this module
 * If no default page is defined it will show the custom 404 page of this module
 * If no 404 page is defined it will show the standard Xaraya 404 page
 */

sys::import('modules.dynamicdata.class.objects.master');

function publications_admin_display($args)
{
    // Get parameters from user
// this is used to determine whether we come from a pubtype-based view or a
// categories-based navigation
    if(!xarVarFetch('name',    'str',   $name,  '', XARVAR_NOT_REQUIRED)) {return;}
    if (!xarVarFetch('ptid',   'id',    $ptid,  NULL, XARVAR_DONT_SET)) {return;}
    if(!xarVarFetch('itemid',  'id',    $id,    NULL, XARVAR_NOT_REQUIRED)) {return;}
    if(!xarVarFetch('page',    'int:1', $page,  NULL, XARVAR_NOT_REQUIRED)) {return;}
    
    // Override xarVarFetch
    extract ($args);
    
    // If no id supplied, get the default id.
    if (empty($id)) $id = xarModVars::get('publications', 'defaultpage');
    $id = xarMod::apiFunc('publications','user','gettranslationid',array('id' => $id));
    
    // If the document doesn't exist, we might be viewing a pubtype
    if (empty($name) && empty($ptid) && empty($id)) {
        // Nothing to be done
        $id = xarModVars::get('publications', 'notfoundpage');
    } elseif (empty($id)) {
        // We're missing an id but can get a pubtype: jump to the pubtype view
        xarController::redirect(xarModURL('publications','user','view'));
    }
    
    // We have come to the end of the line
    if (empty($id)) return xarResponse::NotFound();

    // Here we get the publication type first, and then from that the page
    // Perhaps more efficient to get the page directly?
    $ptid = xarMod::apiFunc('publications','user','getitempubtype',array('itemid' => $id));

    // An empty publication type means the page does not exist
    if (empty($ptid)) return xarResponse::NotFound();

/*    if (empty($name) && empty($ptid)) return xarResponse::NotFound();

    if(empty($ptid)) {
        $publication_type = DataObjectMaster::getObjectList(array('name' => 'publications_types'));
        $where = 'name = ' . $name;
        $items = $publication_type->getItems(array('where' => $where));
        $item = current($items);
        $ptid = $item['id'];
    }
*/
    $pubtypeobject = DataObjectMaster::getObject(array('name' => 'publications_types'));
    $pubtypeobject->getItem(array('itemid' => $ptid));
    $data['object'] = DataObjectMaster::getObject(array('name' => $pubtypeobject->properties['name']->value));
    $id = xarMod::apiFunc('publications','user','gettranslationid',array('id' => $id));
    $itemid = $data['object']->getItem(array('itemid' => $id));
    
    // If this is a redirect page, then send it on its way now
    if ($data['object']->properties['redirect_flag']->value) {
    
        // If this is from a link of a redirect child page, use the child param as new URL
        if(!xarVarFetch('child',    'str', $child,  NULL, XARVAR_NOT_REQUIRED)) {return;}
        if (!empty($child)) {
            // Turn entities into amps
            $url = urldecode($child);
        } else {
            $url = $data['object']->properties['redirect_url']->value;
        }
        $params = parse_url($url);
        $params['query'] = preg_replace('/&amp;/','&',$params['query']);
        
        // If this is an external link, show it without further processing
        if (!empty($params['host']) && $params['host'] != xarServer::getHost() && $params['host'].":".$params['port'] != xarServer::getHost()) {
            xarController::redirect($url);
        } else{
            parse_str($params['query'], $info);
            $other_params = $info;
            unset($other_params['module']);
            unset($other_params['type']);
            unset($other_params['func']);
            unset($other_params['child']);
            $page = xarMod::guiFunc($info['module'],'user',$info['func'],$other_params);
            
            // Debug
            // echo xarModURL($info['module'],'user',$info['func'],$other_params);
# --------------------------------------------------------
#
# The transform of the subordinate function's template
#
            // Find the URLs in submits
            $pattern='/(action)="([^"\r\n]*)"/';
            preg_match_all($pattern,$page,$matches);
            $pattern = array();
            $replace = array();
            foreach ($matches[2] as $match) {
                $pattern[] = '%</form%';
                $replace[] = '<input type="hidden" name="return_url" id="return_url" value="' . urlencode(xarServer::getCurrentURL()) . '"/><input type="hidden" name="child" value="' . urlencode($match) . '"/></form';
            }
            $page = preg_replace($pattern,$replace,$page);

            $pattern='/(action)="([^"\r\n]*)"/';
            $page = preg_replace_callback($pattern,
                create_function(
                    '$matches',
                    'return $matches[1]."=\"".xarServer::getCurrentURL()."\"";'
                ),
                $page
            );

            // Find the URLs in links
            $pattern='/(href)="([^"\r\n]*)"/';
            $page = preg_replace_callback($pattern,
                create_function(
                    '$matches',
                    'return $matches[1]."=\"".xarServer::getCurrentURL(array("child" => urlencode($matches[2])))."\"";'
                ),
                $page
            );

# --------------------------------------------------------
            
            return $page;
        }
    }

    // Get the complete tree for this section of pages.
    // We need this for blocks etc.
    $tree = xarMod::apiFunc(
        'publications', 'user', 'getpagestree',
        array(
            'tree_contains_pid' => $id,
            'key' => 'id',
            'status' => 'ACTIVE,FRONTPAGE,PLACEHOLDER'
        )
    );
        
    // If this page is of type PLACEHOLDER, then look in its descendents
    if ($data['object']->properties['state']->value == 5) {
    
        // Scan for a descendent that is ACTIVE or FRONTPAGE
        if (!empty($tree['pages'][$id]['child_keys'])) {
            foreach($tree['pages'][$id]['child_keys'] as $scan_key) {
                // If the page is displayable, then treat it as the new page.
                if ($tree['pages'][$scan_key]['status'] == 3 || $tree['pages'][$scan_key]['status'] == 4) {
                    $id = $tree['pages'][$scan_key]['id'];
                    $id = xarMod::apiFunc('publications','user','gettranslationid',array('id' => $id));
                    $itemid = $data['object']->getItem(array('itemid' => $id));
                    break;
                }
            }
        }
    }
    
    // Specific layout within a template (optional)
    if (isset($layout)) {
        $data['layout'] = $layout;
    } else {
        $data['layout'] = 'detail';
    }

    // Get the settings for this publication type;
    $data['settings'] = xarModAPIFunc('publications','user','getsettings',array('ptid' => $ptid));

    // Set the theme if needed
    if (!empty($data['object']->properties['theme']->value)) xarTplSetThemeName($data['object']->properties['theme']->value);
    
    // Set the page template if needed
    if (!empty($data['object']->properties['page_template']->value)) xarTplSetPageTemplateName($data['object']->properties['page_template']->value);

    // Now we can cache all this data away for the blocks.
    // The blocks should have access to most of the same data as the page.
    xarVarSetCached('Blocks.publications', 'pagedata', $tree);

    // The 'serialize' hack ensures we have a proper copy of the
    // paga data, which is a self-referencing array. If we don't
    // do this, then any changes we make will affect the stored version.
    $data = unserialize(serialize($data));

    // Save some values. These are used by blocks in 'automatic' mode.
    xarVarSetCached('Blocks.publications', 'current_id', $id);
    xarVarSetCached('Blocks.publications', 'ptid', $ptid);
    xarVarSetCached('Blocks.publications', 'author', $data['object']->properties['author']->value);

    return $data;


/*
    // TEST - highlight search terms
    if(!xarVarFetch('q',     'str',  $q,     NULL, XARVAR_NOT_REQUIRED)) {return;}
*/

    // Override if needed from argument array (e.g. preview)
    extract($args);

    // Defaults
    if (!isset($page)) $page = 1;

    // via arguments only
    if (!isset($preview)) $preview = 0;

/*
    if ($preview) {
        if (!isset($publication)) {
            return xarML('Invalid publication');
        }
        $id = $publication->properties['id']->value;
    } elseif (!isset($id) || !is_numeric($id) || $id < 1) {
        return xarML('Invalid publication ID');
    }
*/

/*    // Get publication
    if (!$preview) {
        $publication = xarModAPIFunc('publications',
                                'user',
                                'get',
                                array('id' => $id,
                                      'withcids' => true));
    }

    if (!is_array($publication)) {
        $msg = xarML('Failed to retrieve publication in #(3)_#(1)_#(2).php', 'userapi', 'get', 'publications');
        throw new DataNotFoundException(null, $msg);
    }
    // Get publication types
    $pubtypes = xarModAPIFunc('publications','user','get_pubtypes');

    // Check that the publication type is valid, otherwise use the publication's pubtype
    if (!empty($ptid) && !isset($pubtypes[$ptid])) {
        $ptid = $publication['pubtype_id'];
    }

*/
// keep original ptid (if any)
//    $ptid = $publication['pubtype_id'];
//    $pubtype_id = $publication->properties['itemtype']->value;
//    $owner = $publication->properties['author']->value;
/*    if (!isset($publication['cids'])) {
        $publication['cids'] = array();
    }
    $cids = $publication['cids'];
*/
    // Get the publication settings for this publication type
    if (empty($ptid)) {
        $settings = unserialize(xarModVars::get('publications', 'settings'));
    } else {
        $settings = unserialize(xarModVars::get('publications', 'settings.'.$ptid));
    }

    // show the number of publications for each publication type
    if (!isset($showpubcount)) {
        if (!isset($settings['showpubcount']) || !empty($settings['showpubcount'])) {
            $showpubcount = 1; // default yes
        } else {
            $showpubcount = 0;
        }
    }
    // show the number of publications for each category
    if (!isset($showcatcount)) {
        if (empty($settings['showcatcount'])) {
            $showcatcount = 0; // default no
        } else {
            $showcatcount = 1;
        }
    }

    // Initialize the data array
    $data = $publication->getFieldValues();
    $data['ptid'] = $ptid; // navigation pubtype
    $data['pubtype_id'] = $pubtype_id; // publication pubtype

// TODO: improve the case where we have several icons :)
    $data['topic_icons'] = '';
    $data['topic_images'] = array();
    $data['topic_urls'] = array();
    $data['topic_names'] = array();
    /*
    if (count($cids) > 0) {
        if (!xarModAPILoad('categories', 'user')) return;
        $catlist = xarModAPIFunc('categories',
                                'user',
                                'getcatinfo',
                                array('cids' => $cids));
        foreach ($catlist as $cat) {
            $link = xarModURL('publications','user','view',
                             array(//'state' => array(PUBLICATIONS_STATE_FRONTPAGE,PUBLICATIONS_STATE_APPROVED).
                                   'ptid' => $ptid,
                                   'catid' => $cat['cid']));
            $name = xarVarPrepForDisplay($cat['name']);

            $data['topic_urls'][] = $link;
            $data['topic_names'][] = $name;

            if (!empty($cat['image'])) {
                $image = xarTplGetImage($cat['image'],'categories');
                $data['topic_icons'] .= '<a href="'. $link .'">'.
                                        '<img src="'. $image .
                                        '" alt="'. $name .'" />'.
                                        '</a>';
                $data['topic_images'][] = $image;

                break;
            }
        }
    }
*/
    // multi-page output for 'body' field (mostly for sections at the moment)
    $themeName = xarVarGetCached('Themes.name','CurrentTheme');
    if ($themeName != 'print'){
        if (strstr($publication->properties['body']->value,'<!--pagebreak-->')) {
            if ($preview) {
                $publication['body'] = preg_replace('/<!--pagebreak-->/',
                                                '<hr/><div style="text-align: center;">'.xarML('Page Break').'</div><hr/>',
                                                $publication->properties['body']->value);
                $data['previous'] = '';
                $data['next'] = '';
            } else {
                $pages = explode('<!--pagebreak-->',$publication->properties['body']->value);

                // For documents with many pages, the pages can be
                // arranged in blocks.
                $pageBlockSize = 10;

                // Get pager information: one item per page.
                $pagerinfo = xarTplPagerInfo((empty($page) ? 1 : $page), count($pages), 1, $pageBlockSize);

                // Retrieve current page and total pages from the pager info.
                // These will have been normalised to ensure they are in range.
                $page = $pagerinfo['currentpage'];
                $numpages = $pagerinfo['totalpages'];

                // Discard everything but the current page.
                $publication['body'] = $pages[$page - 1];
                unset($pages);

                if ($page > 1) {
                    // Don't count page hits after the first page.
                    xarVarSetCached('Hooks.hitcount','nocount',1);
                }

                // Pass in the pager info so a complete custom pager
                // can be created in the template if required.
                $data['pagerinfo'] = $pagerinfo;

                // Get the rendered pager.
                // The pager template (last parameter) could be an
                // option for the publication type.
                $urlmask = xarModURL(
                    'publications','user','display',
                    array('ptid' => $ptid, 'id' => $id, 'page' => '%%')
                );
                $data['pager'] = xarTplGetPager(
                    $page, $numpages, $urlmask,
                    1, $pageBlockSize, 'multipage'
                );

                // Next two assignments for legacy templates.
                // TODO: deprecate them?
                $data['next'] = xarTplGetPager(
                    $page, $numpages, $urlmask,
                    1, $pageBlockSize, 'multipagenext'
                );
                $data['previous'] = xarTplGetPager(
                    $page, $numpages, $urlmask,
                    1, $pageBlockSize, 'multipageprev'
                );
            }
        } else {
            $data['previous'] = '';
            $data['next'] = '';
        }
    } else {
        $publication['body'] = preg_replace('/<!--pagebreak-->/',
                                        '',
                                        $publication['body']);
    }

    // TEST
    if (isset($prevnextart)) {
        $settings['prevnextart'] = $prevnextart;
    }
    if (!empty($settings['prevnextart']) && ($preview == 0)) {
        if(!array_key_exists('defaultsort',$settings)) {
            $settings['defaultsort'] = 'id';
        }
        $prevart = xarModAPIFunc('publications','user','getprevious',
                                 array('id' => $id,
                                       'ptid' => $ptid,
                                       'sort' => $settings['defaultsort'],
                                       'state' => array(PUBLICATIONS_STATE_FRONTPAGE,PUBLICATIONS_STATE_APPROVED),
                                       'enddate' => time()));
        if (!empty($prevart['id'])) {
            //Make all previous publication info available to template
            $data['prevartinfo'] = $prevart;

            $data['prevart'] = xarModURL('publications','user','display',
                                         array('ptid' => $prevart['pubtype_id'],
                                               'id' => $prevart['id']));
        } else {
            $data['prevart'] = '';
        }
        $nextart = xarModAPIFunc('publications','user','getnext',
                                 array('id' => $id,
                                       'ptid' => $ptid,
                                       'sort' => $settings['defaultsort'],
                                       'state' => array(PUBLICATIONS_STATE_FRONTPAGE,PUBLICATIONS_STATE_APPROVED),
                                       'enddate' => time()));
        if (!empty($nextart['id'])) {
            //Make all next art info available to template
            $data['nextartinfo'] = $nextart;

            $data['nextart'] = xarModURL('publications','user','display',
                                         array('ptid' => $nextart['pubtype_id'],
                                               'id' => $nextart['id']));
        } else {
            $data['nextart'] = '';
        }
    } else {
        $data['prevart'] = '';
        $data['nextart'] = '';
    }

    // Display publication
    unset($publication);

    // temp. fix to include dynamic data fields without changing templates
    if (xarModIsHooked('dynamicdata','publications',$pubtype_id)) {
        list($properties) = xarModAPIFunc('dynamicdata','user','getitemfordisplay',
                                          array('module'   => 'publications',
                                                'itemtype' => $pubtype_id,
                                                'itemid'   => $id,
                                                'preview'  => $preview));
        if (!empty($properties) && count($properties) > 0) {
            foreach (array_keys($properties) as $field) {
                $data[$field] = $properties[$field]->getValue();
                // POOR mans flagging for transform hooks
                try {
                $configuration = $properties[$field]->configuration;
                if(substr($configuration,0,10) == 'transform:') {
                    $data['transform'][] = $field;
                }
                } catch (Exception $e) {}
                // TODO: clean up this temporary fix
                $data[$field.'_output'] = $properties[$field]->showOutput();
            }
        }
    }

    // Let any transformation hooks know that we want to transform some text.
    // You'll need to specify the item id, and an array containing all the
    // pieces of text that you want to transform (e.g. for autolinks, wiki,
    // smilies, bbcode, ...).
    $data['itemtype'] = $pubtype_id;
    // TODO: what about transforming DDfields ?
    // <mrb> see above for a hack, needs to be a lot better.

    // Summary is always included, is that handled somewhere else? (publication config says i can ex/include it)
    // <mikespub> publications config allows you to call transforms for the publications summaries in the view function
    if (!isset($titletransform)) {
        if (empty($settings['titletransform'])) {
            $data['transform'][] = 'summary';
            $data['transform'][] = 'body';
            $data['transform'][] = 'notes';

        } else {
            $data['transform'][] = 'title';
            $data['transform'][] = 'summary';
            $data['transform'][] = 'body';
            $data['transform'][] = 'notes';
        }
    }
    $data = xarModCallHooks('item', 'transform', $id, $data, 'publications');

    return xarTplModule('publications', 'user', 'display', $data);


    if (!empty($data['title'])) {
        // CHECKME: <rabbit> Strip tags out of the title - the <title> tag shouldn't have any other tags in it.
        $title = strip_tags($data['title']);
        xarTplSetPageTitle(xarVarPrepForDisplay($title), xarVarPrepForDisplay($pubtypes[$data['itemtype']]['description']));

        // Save some variables to (temporary) cache for use in blocks etc.
        xarVarSetCached('Comments.title','title',$data['title']);
    }

/*
    if (!empty($q)) {
    // TODO: split $q into search terms + add style (cfr. handlesearch in search module)
        foreach ($data['transform'] as $field) {
            $data[$field] = preg_replace("/$q/","<span class=\"xar-search-match\">$q</span>",$data[$field]);
        }
    }
*/

    // Navigation links
    $data['publabel'] = xarML('Publication');
    $data['publinks'] = array(); //xarModAPIFunc('publications','user','getpublinks',
                                 //    array('state' => array(PUBLICATIONS_STATE_FRONTPAGE,PUBLICATIONS_STATE_APPROVED),
                                 //          'count' => $showpubcount));
    if (isset($showmap)) {
        $settings['showmap'] = $showmap;
    }
    if (!empty($settings['showmap'])) {
        $data['maplabel'] = xarML('View Publication Map');
        $data['maplink'] = xarModURL('publications','user','viewmap',
                                    array('ptid' => $ptid));
    }
    if (isset($showarchives)) {
        $settings['showarchives'] = $showarchives;
    }
    if (!empty($settings['showarchives'])) {
        $data['archivelabel'] = xarML('View Archives');
        $data['archivelink'] = xarModURL('publications','user','archive',
                                        array('ptid' => $ptid));
    }
    if (isset($showpublinks)) {
        $settings['showpublinks'] = $showpublinks;
    }
    if (!empty($settings['showpublinks'])) {
        $data['showpublinks'] = 1;
    } else {
        $data['showpublinks'] = 0;
    }
    $data['showcatcount'] = $showcatcount;

    // Tell the hitcount hook not to display the hitcount, but to save it
    // in the variable cache.
    if (xarModIsHooked('hitcount','publications',$pubtype_id)) {
        xarVarSetCached('Hooks.hitcount','save',1);
        $data['dohitcount'] = 1;
    } else {
        $data['dohitcount'] = 0;
    }

    // Tell the ratings hook to save the rating in the variable cache.
    if (xarModIsHooked('ratings','publications',$pubtype_id)) {
        xarVarSetCached('Hooks.ratings','save',1);
        $data['doratings'] = 1;
    } else {
        $data['doratings'] = 0;
    }


    // Retrieve the current hitcount from the variable cache
    if ($data['dohitcount'] && xarVarIsCached('Hooks.hitcount','value')) {
        $data['counter'] = xarVarGetCached('Hooks.hitcount','value');
    } else {
        $data['counter'] = '';
    }

    // Retrieve the current rating from the variable cache
    if ($data['doratings'] && xarVarIsCached('Hooks.ratings','value')) {
        $data['rating'] = intval(xarVarGetCached('Hooks.ratings','value'));
    } else {
        $data['rating'] = '';
    }

    // Save some variables to (temporary) cache for use in blocks etc.
    xarVarSetCached('Blocks.publications','title',$data['title']);

    // Generating keywords from the API now instead of setting the entire
    // body into the cache.
    $keywords = xarModAPIFunc('publications',
                              'user',
                              'generatekeywords',
                              array('incomingkey' => $data['body']));

    xarVarSetCached('Blocks.publications','body',$keywords);
    xarVarSetCached('Blocks.publications','summary',$data['summary']);
    xarVarSetCached('Blocks.publications','id',$id);
    xarVarSetCached('Blocks.publications','ptid',$ptid);
    xarVarSetCached('Blocks.publications','cids',$cids);
    xarVarSetCached('Blocks.publications','owner',$owner);
    if (isset($data['author'])) {
        xarVarSetCached('Blocks.publications','author',$data['author']);
    }
// TODO: add this to publications configuration ?
//if ($shownavigation) {
    $data['id'] = $id;
    $data['cids'] = $cids;
    xarVarSetCached('Blocks.categories','module','publications');
    xarVarSetCached('Blocks.categories','itemtype',$ptid);
    xarVarSetCached('Blocks.categories','itemid',$id);
    xarVarSetCached('Blocks.categories','cids',$cids);

    if (!empty($ptid) && !empty($pubtypes[$ptid]['description'])) {
        xarVarSetCached('Blocks.categories','title',$pubtypes[$ptid]['description']);
    }

    // optional category count
    if ($showcatcount && !empty($ptid)) {
        $pubcatcount = xarModAPIFunc('publications',
                                    'user',
                                    'getpubcatcount',
                                    // frontpage or approved
                                    array('state' => array(PUBLICATIONS_STATE_FRONTPAGE,PUBLICATIONS_STATE_APPROVED),
                                          'ptid' => $ptid));
        if (!empty($pubcatcount[$ptid])) {
            xarVarSetCached('Blocks.categories','catcount',$pubcatcount[$ptid]);
        }
    } else {
    //    xarVarSetCached('Blocks.categories','catcount',array());
    }
//}

    // Module template depending on publication type
    $template = $pubtypes[$pubtype_id]['name'];

    // Page template depending on publication type (optional)
    // Note : this cannot be overridden in templates
    if (empty($preview) && !empty($settings['page_template'])) {
        xarTplSetPageTemplateName($settings['page_template']);
    }

    // Specific layout within a template (optional)
    if (isset($layout)) {
        $data['layout'] = $layout;
    }

    $pubtypeobject = DataObjectMaster::getObject(array('name' => 'publications_types'));
    $pubtypeobject->getItem(array('itemid' => $ptid));
    $data['object'] = DataObjectMaster::getObject(array('name' => $pubtypeobject->properties['name']->value));
    $id = xarMod::apiFunc('publications','user','getranslationid',array('id' => $id));
    $data['object']->getItem(array('itemid' => $id));

    return xarTplModule('publications', 'user', 'display', $data, $template);
}

?>
