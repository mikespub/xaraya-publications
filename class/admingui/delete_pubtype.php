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
use xarController;
use xarSec;
use xarTpl;
use DataObjectFactory;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications admin delete_pubtype function
 */
class DeletePubtypeMethod extends MethodClass
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
        if (!xarSecurity::check('AdminPublications')) {
            return;
        }

        if (!xarVar::fetch('confirmed', 'int', $confirmed, null, xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!xarVar::fetch('itemid', 'str', $itemid, null, xarVar::DONT_SET)) {
            return;
        }
        if (!xarVar::fetch('idlist', 'str', $idlist, null, xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!xarVar::fetch('returnurl', 'str', $returnurl, null, xarVar::DONT_SET)) {
            return;
        }

        if (!empty($itemid)) {
            $idlist = $itemid;
        }
        $ids = explode(',', trim($idlist, ','));

        if (empty($idlist)) {
            if (isset($returnurl)) {
                xarController::redirect($returnurl, null, $this->getContext());
            } else {
                xarController::redirect(xarController::URL('publications', 'admin', 'view_pubtypes'), null, $this->getContext());
            }
        }

        $data['message'] = '';
        $data['itemid']  = $itemid;

        /*------------- Ask for Confirmation.  If yes, action ----------------------------*/

        sys::import('modules.dynamicdata.class.objects.factory');
        $pubtype = DataObjectFactory::getObject(['name' => 'publications_types']);
        if (!isset($confirmed)) {
            $data['idlist'] = $idlist;
            if (count($ids) > 1) {
                $data['title'] = xarML("Delete Publication Types");
            } else {
                $data['title'] = xarML("Delete Publication Type");
            }
            $data['authid'] = xarSec::genAuthKey();
            $items = [];
            foreach ($ids as $i) {
                $pubtype->getItem(['itemid' => $i]);
                $item = $pubtype->getFieldValues();
                $items[] = $item;
            }
            $data['items'] = $items;
            $data['yes_action'] = xarController::URL('publications', 'admin', 'delete_pubtype', ['idlist' => $idlist]);
            $data['context'] ??= $this->getContext();
            return xarTpl::module('publications', 'admin', 'delete_pubtype', $data);
        } else {
            if (!xarSec::confirmAuthKey()) {
                return;
            }
            foreach ($ids as $id) {
                $itemid = $pubtype->deleteItem(['itemid' => $id]);
                $data['message'] = "Publication Type deleted [ID $id]";
            }
            if (isset($returnurl)) {
                xarController::redirect($returnurl, null, $this->getContext());
            } else {
                xarController::redirect(xarController::URL('publications', 'admin', 'view_pubtypes', $data), null, $this->getContext());
            }
            return true;
        }
    }
}
