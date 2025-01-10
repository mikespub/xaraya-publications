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
use xarSec;
use xarMod;
use xarModVars;
use xarUser;
use xarConfigVars;
use xarTpl;
use xarHooks;
use xarSession;
use xarController;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications admin create function
 */
class CreateMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    public function __invoke(array $args = [])
    {
        if (!xarSecurity::check('AddPublications')) {
            return;
        }

        if (!xarVar::fetch('ptid', 'id', $data['ptid'])) {
            return;
        }
        if (!xarVar::fetch('new_cids', 'array', $cids, null, xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!xarVar::fetch('preview', 'str', $data['preview'], null, xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!xarVar::fetch('save', 'str', $save, null, xarVar::NOT_REQUIRED)) {
            return;
        }

        // Confirm authorisation code
        // This has been disabled for now
        // if (!xarSec::confirmAuthKey()) return;

        $data['items'] = [];
        $pubtypeobject = DataObjectFactory::getObject(['name' => 'publications_types']);
        $pubtypeobject->getItem(['itemid' => $data['ptid']]);
        $data['object'] = DataObjectFactory::getObject(['name' => $pubtypeobject->properties['name']->value]);

        $isvalid = $data['object']->checkInput();

        $data['settings'] = xarMod::apiFunc('publications', 'user', 'getsettings', ['ptid' => $data['ptid']]);

        if ($data['preview'] || !$isvalid) {
            // Show debug info if called for
            if (!$isvalid &&
                xarModVars::get('publications', 'debugmode') &&
                in_array(xarUser::getVar('id'), xarConfigVars::get(null, 'Site.User.DebugAdmins'))) {
                var_dump($data['object']->getInvalids());
            }
            // Preview or bad data: redisplay the form
            $data['properties'] = $data['object']->getProperties();
            if ($data['preview']) {
                $data['tab'] = 'preview';
            }
            $data['context'] ??= $this->getContext();
            return xarTpl::module('publications', 'admin', 'new', $data);
        }

        // Create the object
        $itemid = $data['object']->createItem();

        // Inform the world via hooks
        $item = ['module' => 'publications', 'itemid' => $itemid, 'itemtype' => $data['object']->properties['itemtype']->value];
        xarHooks::notify('ItemCreate', $item);

        // Redirect if we came from somewhere else
        $current_listview = xarSession::getVar('publications_current_listview');
        if (!empty($cuurent_listview)) {
            xarController::redirect($current_listview, null, $this->getContext());
        }

        xarController::redirect(xarController::URL(
            'publications',
            'admin',
            'view',
            ['ptid' => $data['ptid']]
        ), null, $this->getContext());
        return true;
    }
}