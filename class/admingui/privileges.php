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

use Xaraya\Modules\MethodClass;
use xarSecurity;
use xarVar;
use xarMod;
use xarPrivileges;
use xarController;
use DataPropertyMaster;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications admin privileges function
 */
class PrivilegesMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    /**
     * Manage definition of instances for privileges (unfinished)
     * @return array for template
     */
    public function __invoke(array $args = [])
    {
        if (!xarSecurity::check('EditPublications')) {
            return;
        }

        extract($args);

        // fixed params
        if (!xarVar::fetch('ptid', 'isset', $ptid, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('cid', 'isset', $cid, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('uid', 'isset', $uid, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('author', 'isset', $author, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('id', 'isset', $id, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('apply', 'isset', $apply, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('extpid', 'isset', $extpid, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('extname', 'isset', $extname, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('extrealm', 'isset', $extrealm, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('extmodule', 'isset', $extmodule, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('extcomponent', 'isset', $extcomponent, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('extinstance', 'isset', $extinstance, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('extlevel', 'isset', $extlevel, null, xarVar::DONT_SET)) {
            return;
        }

        sys::import('modules.dynamicdata.class.properties.master');
        $categories = DataPropertyMaster::getProperty(['name' => 'categories']);
        $cids = $categories->returnInput('privcategories');

        if (!empty($extinstance)) {
            $parts = explode(':', $extinstance);
            if (count($parts) > 0 && !empty($parts[0])) {
                $ptid = $parts[0];
            }
            if (count($parts) > 1 && !empty($parts[1])) {
                $cid = $parts[1];
            }
            if (count($parts) > 2 && !empty($parts[2])) {
                $uid = $parts[2];
            }
            if (count($parts) > 3 && !empty($parts[3])) {
                $id = $parts[3];
            }
        }

        if (empty($ptid) || $ptid == 'All' || !is_numeric($ptid)) {
            $ptid = 0;
            if (!xarSecurity::check('AdminPublications')) {
                return;
            }
        } else {
            if (!xarSecurity::check('AdminPublications', 1, 'Publication', "$ptid:All:All:All")) {
                return;
            }
        }

        // TODO: do something with cid for security check

        // TODO: figure out how to handle more than 1 category in instances
        if (empty($cid) || $cid == 'All' || !is_numeric($cid)) {
            $cid = 0;
        }
        if (empty($cid) && isset($cids) && is_array($cids)) {
            foreach ($cids as $catid) {
                if (!empty($catid)) {
                    $cid = $catid;
                    // bail out for now
                    break;
                }
            }
        }

        if (empty($id) || $id == 'All' || !is_numeric($id)) {
            $id = 0;
        }
        $title = '';
        if (!empty($id)) {
            $article = xarMod::apiFunc(
                'publications',
                'user',
                'get',
                ['id'      => $id,
                    'withcids' => true, ]
            );
            if (empty($article)) {
                $id = 0;
            } else {
                // override whatever other params we might have here
                $ptid = $article['pubtype_id'];
                // TODO: review when we can handle multiple categories and/or subtrees in privilege instances
                if (!empty($article['cids']) && count($article['cids']) == 1) {
                    // if we don't have a category, or if we have one but this article doesn't belong to it
                    if (empty($cid) || !in_array($cid, $article['cids'])) {
                        // we'll take that category
                        $cid = $article['cids'][0];
                    }
                } else {
                    // we'll take no categories
                    $cid = 0;
                }
                $uid = $article['owner'];
                $title = $article['title'];
            }
        }

        // TODO: figure out how to handle groups of users and/or the current user (later)
        if (strtolower($uid) == 'myself') {
            $uid = 'Myself';
            $author = 'Myself';
        } elseif (empty($uid) || $uid == 'All' || (!is_numeric($uid) && (strtolower($uid) != 'myself'))) {
            $uid = 0;
            if (!empty($author)) {
                $user = xarMod::apiFunc(
                    'roles',
                    'user',
                    'get',
                    ['name' => $author]
                );
                if (!empty($user) && !empty($user['uid'])) {
                    if (strtolower($author) == 'myself') {
                        $uid = 'Myself';
                    } else {
                        $uid = $user['uid'];
                    }
                } else {
                    $author = '';
                }
            }
        } else {
            $author = '';
            /*
                    $user = xarMod::apiFunc('roles', 'user', 'get',
                                          array('uid' => $uid));
                    if (!empty($user) && !empty($user['name'])) {
                        $author = $user['name'];
                    }
            */
        }

        // define the new instance
        $newinstance = [];
        $newinstance[] = empty($ptid) ? 'All' : $ptid;
        $newinstance[] = empty($cid) ? 'All' : $cid;
        $newinstance[] = empty($uid) ? 'All' : $uid;
        $newinstance[] = empty($id) ? 'All' : $id;

        if (!empty($apply)) {
            // create/update the privilege
            $id = xarPrivileges::external($extpid, $extname, $extrealm, $extmodule, $extcomponent, $newinstance, $extlevel);
            if (empty($id)) {
                return;
            } // throw back

            // redirect to the privilege
            xarController::redirect(xarController::URL(
                'privileges',
                'admin',
                'modifyprivilege',
                ['id' => $id]
            ), null, $this->getContext());
            return true;
        }

        // get the list of current authors
        $authorlist =  xarMod::apiFunc(
            'publications',
            'user',
            'getauthors',
            ['ptid' => $ptid,
                'cids' => empty($cid) ? [] : [$cid], ]
        );
        if (!empty($author) && isset($authorlist[$uid])) {
            $author = '';
        }

        if (empty($id)) {
            $numitems = xarMod::apiFunc(
                'publications',
                'user',
                'countitems',
                ['ptid' => $ptid,
                    'cids' => empty($cid) ? [] : [$cid],
                    'owner' => $uid, ]
            );
        } else {
            $numitems = 1;
        }
        $data = [
            'ptid'         => $ptid,
            'cid'          => $cid,
            'uid'          => $uid,
            'author'       => xarVar::prepForDisplay($author),
            'authorlist'   => $authorlist,
            'id'          => $id,
            'title'        => xarVar::prepForDisplay($title),
            'numitems'     => $numitems,
            'extpid'       => $extpid,
            'extname'      => $extname,
            'extrealm'     => $extrealm,
            'extmodule'    => $extmodule,
            'extcomponent' => $extcomponent,
            'extlevel'     => $extlevel,
            'extinstance'  => xarVar::prepForDisplay(join(':', $newinstance)),
        ];

        // Get publication types
        $data['pubtypes'] = xarMod::apiFunc('publications', 'user', 'get_pubtypes');

        $catlist = [];
        if (!empty($ptid)) {
            $basecats = xarMod::apiFunc('categories', 'user', 'getallcatbases', ['module' => 'publications', 'itemtype' => $ptid]);
            foreach ($basecats as $catid) {
                $catlist[$catid['id']] = 1;
            }
            if (empty($data['pubtypes'][$ptid]['config']['owner']['label'])) {
                $data['showauthor'] = 0;
            } else {
                $data['showauthor'] = 1;
            }
        } else {
            foreach (array_keys($data['pubtypes']) as $pubid) {
                $basecats = xarMod::apiFunc('categories', 'user', 'getallcatbases', ['module' => 'publications', 'itemtype' => $pubid]);
                foreach ($basecats as $catid) {
                    $catlist[$catid['id']] = 1;
                }
            }
            $data['showauthor'] = 1;
        }

        $seencid = [];
        if (!empty($cid)) {
            $seencid[$cid] = 1;
        }

        $data['cids'] = $cids;
        $data['cats'] = $catlist;
        $data['refreshlabel'] = xarML('Refresh');
        $data['applylabel'] = xarML('Finish and Apply to Privilege');
        return $data;
    }
}
