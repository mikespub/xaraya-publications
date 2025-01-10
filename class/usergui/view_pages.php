<?php

/**
 * @package modules\publications
 * @category Xaraya Web Applications Framework
 * @version 2.5.7
 * @copyright see the html/credits.html file in this release
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link https://github.com/mikespub/xaraya-modules
**/

namespace Xaraya\Modules\Publications\UserGui;

use Xaraya\Modules\MethodClass;
use xarSecurity;
use xarVar;
use xarMod;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications user view_pages function
 */
class ViewPagesMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    /**
     * Publications Module
     * @package modules
     * @subpackage publications module
     * @category Third Party Xaraya Module
     * @version 2.0.0
     * @copyright (C) 2012 Netspan AG
     * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
     * @author Marc Lutolf <mfl@netspan.ch>
     */
    public function __invoke(array $args = [])
    {
        extract($args);

        if (!xarSecurity::check('ManagePublications')) {
            return;
        }

        // Accept a parameter to allow selection of a single tree.
        xarVar::fetch('contains', 'id', $contains, 0, xarVar::NOT_REQUIRED);

        $data = xarMod::apiFunc(
            'publications',
            'user',
            'getpagestree',
            ['key' => 'index', 'dd_flag' => false, 'tree_contains_pid' => $contains]
        );

        if (empty($data['pages'])) {
            // TODO: pass to template.
            return $data; //xarML('NO PAGES DEFINED');
        } else {
            $data['pages'] = xarMod::apiFunc('publications', 'tree', 'array_maptree', $data['pages']);
        }

        $data['contains'] = $contains;

        // Check modify and delete privileges on each page.
        // EditPage - allows basic changes, but no moving or renaming (good for sub-editors who manage content)
        // AddPage - new pages can be added (further checks may limit it to certain page types)
        // DeletePage - page can be renamed, moved and deleted
        if (!empty($data['pages'])) {
            // Bring in the access property for security checks
            sys::import('modules.dynamicdata.class.properties.master');
            $accessproperty = DataPropertyMaster::getProperty(['name' => 'access']);
            $accessproperty->module = 'publications';
            $accessproperty->component = 'Page';
            foreach ($data['pages'] as $key => $page) {
                $thisinstance = $page['name'] . ':' . $page['pubtype_name'];

                // Do we have admin access?
                $args = [
                    'instance' => $thisinstance,
                    'level' => 800,
                ];
                $adminaccess = $accessproperty->check($args);

                // Decide whether this page can be modified by the current user
                /*try {
                    $args = array(
                        'instance' => $thisinstance,
                        'group' => $page['access']['modify_access']['group'],
                        'level' => $page['access']['modify_access']['level'],
                    );
                } catch (Exception $e) {
                    $args = array();
                }*/
                $data['pages'][$key]['edit_allowed'] = $adminaccess || $accessproperty->check($args);
                /*
                // Decide whether this page can be deleted by the current user
               try {
                    $args = array(
                        'instance' => $thisinstance,
                        'group' => $page['access']['delete_access']['group'],
                        'level' => $page['access']['delete_access']['level'],
                    );
                } catch (Exception $e) {
                    $args = array();
                }*/
                $data['pages'][$key]['delete_allowed'] = $adminaccess ||  $accessproperty->check($args);
            }
        }

        return $data;
    }
}