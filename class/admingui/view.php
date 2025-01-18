<?php

/**
 * @package modules\publications
 * @category Xaraya Web Applications Framework
 * @version 2.5.7
 * @copyright see the html/credits.html file in this release
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link https://github.com/mikespub/xaraya-modules
**/

namespace Xaraya\Modules\Publications\AdminGui;


use Xaraya\Modules\Publications\AdminGui;
use Xaraya\Modules\MethodClass;
use xarSecurity;
use xarVar;
use xarSession;
use xarModVars;
use xarMod;
use xarController;
use xarTplPager;
use xarCoreCache;
use xarRoles;
use xarUser;
use xarServer;
use xarTpl;
use DataObjectFactory;
use Query;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications admin view function
 * @extends MethodClass<AdminGui>
 */
class ViewMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    /**
     * view items
     */
    public function __invoke(array $args = [])
    {
        if (!$this->sec()->checkAccess('EditPublications')) {
            return;
        }

        // Get parameters
        if (!$this->var()->find('startnum', $startnum, 'isset', 1)) {
            return;
        }
        if (!$this->var()->find('ptid', $ptid)) {
            return;
        }
        if (!$this->var()->check('state', $state)) {
            return;
        }
        if (!$this->var()->check('itemtype', $itemtype)) {
            return;
        }
        if (!$this->var()->check('catid', $catid)) {
            return;
        }
        if (!$this->var()->get('sort', :pre', 'strlist:, $sort, null, xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!$this->var()->check('owner', $owner)) {
            return;
        }
        if (!$this->var()->check('lang', $lang)) {
            return;
        }
        if (!$this->var()->find('pubdate', $pubdate, 'str:1')) {
            return;
        }
        if (!$this->var()->find('object', $object, 'str:1')) {
            return;
        }

        extract($args);

        if (null === $ptid) {
            $ptid = xarSession::getVar('publications_current_pubtype');
            if (empty($ptid)) {
                $ptid = $this->mod()->getVar('defaultpubtype');
            }
        }
        xarSession::setVar('publications_current_pubtype', $ptid);

        $pubtypes = xarMod::apiFunc('publications', 'user', 'get_pubtypes');

        // Default parameters
        if (!isset($ptid)) {
            if (!empty($itemtype) && is_numeric($itemtype)) {
                // when we use some categories filter
                $ptid = $itemtype;
            } else {
                // we default to this for convenience
                $default = $this->mod()->getVar('defaultpubtype');
                if (!empty($default) && !xarSecurity::check('EditPublications', 0, 'Publication', "$default:All:All:All")) {
                    // try to find some alternate starting pubtype if necessary
                    foreach ($pubtypes as $id => $pubtype) {
                        if (xarSecurity::check('EditPublications', 0, 'Publication', "$id:All:All:All")) {
                            $ptid = $id;
                            break;
                        }
                    }
                } else {
                    $ptid = $default;
                }
            }
        }
        if (empty($ptid)) {
            $ptid = null;
        }
        if (empty($sort)) {
            $sort = 'date';
        }
        $data = [];
        $data['ptid'] = $ptid;
        $data['sort'] = $sort;
        $data['owner'] = $owner;
        $data['locale'] = $lang;
        $data['pubdate'] = $pubdate;

        if (!empty($catid)) {
            if (strpos($catid, ' ')) {
                $cids = explode(' ', $catid);
                $andcids = true;
            } elseif (strpos($catid, '+')) {
                $cids = explode('+', $catid);
                $andcids = true;
            } else {
                $cids = explode('-', $catid);
                $andcids = false;
            }
        } else {
            $cids = [];
            $andcids = false;
        }
        $data['catid'] = $catid;

        if (empty($ptid)) {
            if (!xarSecurity::check('EditPublications', 1, 'Publication', "All:All:All:All")) {
                return;
            }
        } elseif (!is_numeric($ptid) || !isset($pubtypes[$ptid])) {
            return xarController::notFound(null, $this->getContext());
        } elseif (!xarSecurity::check('EditPublications', 1, 'Publication', "$ptid:All:All:All")) {
            return;
        }

        $settings = [];
        if (!empty($ptid)) {
            $string = $this->mod()->getVar('settings.' . $ptid);
            if (!empty($string)) {
                $settings = unserialize($string);
            }
        }
        if (empty($settings)) {
            $string = $this->mod()->getVar('settings');
            if (!empty($string)) {
                $settings = unserialize($string);
            }
        }
        if (isset($settings['admin_items_per_page'])) {
            $numitems = $settings['admin_items_per_page'];
        } else {
            $numitems = 30;
        }

        /*
        // Get item information
        $publications = xarMod::apiFunc('publications',
                                 'user',
                                 'getall',
                                 array('startnum' => $startnum,
                                       'numitems' => $numitems,
                                       'ptid'     => $ptid,
                                       'owner' => $owner,
                                       'locale' => $lang,
                                       'pubdate'  => $pubdate,
                                       'cids'     => $cids,
                                       'sort'     => $sort,
                                       'andcids'  => $andcids,
                                       'extra'  => array('cids'),
                                       'state'   => $state));
    */
        // Save the current admin view, so that we can return to it after update
        $lastview = ['ptid' => $ptid,
            'owner' => $owner,
            'locale' => $lang,
            'catid' => $catid,
            'state' => $state,
            'pubdate' => $pubdate,
            'startnum' => $startnum > 1 ? $startnum : null, ];
        xarSession::setVar('Publications.LastView', serialize($lastview));

        $labels = [];
        $data['labels'] = $labels;

        // only show the date if this publication type has one
        $showdate = !empty($labels['pubdate']);
        $data['showdate'] = $showdate;
        // only show the state if this publication type has one
        $showstate = !empty($labels['state']);
        // and if we're not selecting on it already
        //&& (!is_array($state) || !isset($state[0]));
        $data['showstate'] = $showstate;

        $data['states'] = xarMod::apiFunc('publications', 'user', 'getstates');

        $items = [];
        /*
        if ($publications != false) {
            foreach ($publications as $article) {

                $item = array();

    // TODO: adapt according to pubtype configuration
                // Title and pubdate
                $item['title'] = $article['title'];
                $item['summary'] = $article['summary'];
                $item['id'] = $article['id'];
                if (!empty($article['cids'])) {
                     $item['cids'] = $article['cids'];
                } else {
                     $item['cids'] = array();
                }

                if ($showdate) {
                    $item['pubdate'] = $article['pubdate']; //strftime('%x %X %z', $article['pubdate']);
                }
                if ($showstate) {
                    $item['state'] = $data['states'][$article['state']];
                    // pre-select all submitted items
                    if ($article['state'] == 0) {
                        $item['selected'] = 'checked';
                    } else {
                        $item['selected'] = '';
                    }
                }

                // Security check
                $input = array();
                $input['article'] = $article;
                $input['mask'] = 'ManagePublications';
                if (xarMod::apiFunc('publications','user','checksecurity',$input)) {
                    $item['deleteurl'] = $this->mod()->getURL(
                                                  'admin',
                                                  'delete',
                                                  array('id' => $article['id']));
                    $item['editurl'] = $this->mod()->getURL(
                                                'admin',
                                                'modify',
                                                array('id' => $article['id']));
                    $item['viewurl'] = $this->mod()->getURL(
                                                'user',
                                                'display',
                                                array('id' => $article['id'],
                                                      'ptid' => $article['pubtype_id']));
                } else {
                    $item['deleteurl'] = '';

                    $input['mask'] = 'EditPublications';
                    if (xarMod::apiFunc('publications','user','checksecurity',$input)) {
                        $item['editurl'] = $this->mod()->getURL(
                                                    'admin',
                                                    'modify',
                                                    array('id' => $article['id']));
                        $item['viewurl'] = $this->mod()->getURL(
                                                    'user',
                                                    'display',
                                                    array('id' => $article['id'],
                                                          'ptid' => $article['pubtype_id']));
                    } else {
                        $item['editurl'] = '';

                        $input['mask'] = 'ReadPublications';
                        if (xarMod::apiFunc('publications','user','checksecurity',$input)) {
                            $item['viewurl'] = $this->mod()->getURL(
                                                        'user',
                                                        'display',
                                                        array('id' => $article['id'],
                                                              'ptid' => $article['pubtype_id']));
                        } else {
                            $item['viewurl'] = '';
                        }
                    }
                }

                $item['deletetitle'] = $this->ml('Delete');
                $item['viewtitle'] = $this->ml('View');

                $items[] = $item;
            }
        }
        */
        $data['items'] = $items;

        /*
            // Add pager
            $data['pager'] = xarTplPager::getPager($startnum,
                                    xarMod::apiFunc('publications', 'user', 'countitems',
                                                  array('ptid' => $ptid,
                                                        'owner' => $owner,
                                                        'locale' => $lang,
                                                        'pubdate' => $pubdate,
                                                        'cids' => $cids,
                                                        'andcids' => $andcids,
                                                        'state' => $state)),
                                    $this->mod()->getURL( 'admin', 'view',
                                              array('startnum' => '%%',
                                                    'ptid' => $ptid,
                                                    'owner' => $owner,
                                                    'locale' => $lang,
                                                    'pubdate' => $pubdate,
                                                    'catid' => $catid,
                                                    'state' => $state)),
                                    $numitems);

            // Create filters based on publication type
            */
        $pubfilters = [];
        /*
        foreach ($pubtypes as $id => $pubtype) {
            if (!xarSecurity::check('EditPublications',0,'Publication',"$id:All:All:All")) {
                continue;
            }
            $pubitem = array();
            if ($id == $ptid) {
                $pubitem['plink'] = '';
            } else {
                $pubitem['plink'] = $this->mod()->getURL('admin','view',
                                             array('ptid' => $id));
            }
            $pubitem['ptitle'] = $pubtype['description'];
            $pubfilters[] = $pubitem;
        }
    */
        $data['pubfilters'] = $pubfilters;
        // Create filters based on article state
        $statefilters = [];
        if (!empty($labels['state'])) {
            $statefilters[] = ['stitle' => $this->ml('All'),
                'slink' => !is_array($state) ? '' :
                               $this->mod()->getURL(
                                   'admin',
                                   'view',
                                   ['ptid' => $ptid,
                                       'catid' => $catid, ]
                               ), ];
            foreach ($data['states'] as $id => $name) {
                $statefilters[] = ['stitle' => $name,
                    'slink' => (is_array($state) && $state[0] == $id) ? '' :
                                   $this->mod()->getURL(
                                       'admin',
                                       'view',
                                       ['ptid' => $ptid,
                                           'catid' => $catid,
                                           'state' => [$id], ]
                                   ), ];
            }
        }
        $data['statefilters'] = $statefilters;
        $data['changestatelabel'] = $this->ml('Change Status');
        // Add link to create new article
        if (xarSecurity::check('SubmitPublications', 0, 'Publication', "$ptid:All:All:All")) {
            $newurl = $this->mod()->getURL(
                'admin',
                'new',
                ['ptid' => $ptid]
            );
            $data['shownewlink'] = true;
        } else {
            $newurl = '';
            $data['shownewlink'] = false;
        }
        $data['newurl'] = $newurl;
        // TODO: Hook category block someday ?
        $this->var()->setCached('Blocks.categories', 'module', 'publications');
        $this->var()->setCached('Blocks.categories', 'type', 'admin');
        $this->var()->setCached('Blocks.categories', 'func', 'view');
        $this->var()->setCached('Blocks.categories', 'itemtype', $ptid);
        if (!empty($ptid) && !empty($pubtypes[$ptid]['description'])) {
            $this->var()->setCached('Blocks.categories', 'title', $pubtypes[$ptid]['description']);
        }
        $this->var()->setCached('Blocks.categories', 'cids', $cids);

        if (!empty($ptid)) {
            $template = $pubtypes[$ptid]['name'];
        } else {
            // TODO: allow templates per category ?
            $template = null;
        }

        // Get the available publications objects
        $object = DataObjectFactory::getObjectList(['objectid' => 1]);
        $items = $object->getItems();
        $options = [];
        foreach ($items as $item) {
            if (strpos($item['name'], 'publications_') !== false) {
                $options[] = ['id' => $item['objectid'], 'name' => $item['name'], 'title' => $item['label']];
            }
        }
        $data['objects'] = $options;

        // Only show top level documents, not translations
        sys::import('xaraya.structures.query');
        $q = new Query();
        $q->eq('parent_id', 0);
        $q->eq('pubtype_id', $ptid);

        // Suppress deleted items if not an admin
        // Remove this once listing property works with dataobject access
        if (!xarRoles::isParent('Administrators', xarUser::getVar('uname'))) {
            $q->ne('state', 0);
        }
        $data['conditions'] = $q;

        $pubtypeobject = DataObjectFactory::getObject(['name' => 'publications_types']);
        $pubtypeobject->getItem(['itemid' => $ptid]);
        $data['object'] = DataObjectFactory::getObjectList(['name' => $pubtypeobject->properties['name']->value]);

        // Flag this as the current list view
        xarSession::setVar('publications_current_listview', xarServer::getCurrentURL(['ptid' => $ptid]));

        $data['context'] ??= $this->getContext();
        return $this->mod()->template('view', $data, $template);
    }
}
