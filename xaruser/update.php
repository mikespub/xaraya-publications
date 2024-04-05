<?php
/**
 * Publications Module
 *
 * @package modules
 * @subpackage publications module
 * @category Third Party Xaraya Module
 * @version 2.0.0
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @author mikespub
 */
/**
 * update item from publications_user_modify
 *
 * @param id     ptid       The publication Type ID for this new article
 * @param array  new_cids   An array with the category ids for this new article (OPTIONAL)
 * @param string preview    Are we gonna see a preview? (OPTIONAL)
 * @param string save       Call the save action (OPTIONAL)
 * @param string return_url The URL to return to (OPTIONAL)
 * @return  bool true on success, or mixed on failure
 */

sys::import('modules.dynamicdata.class.objects.factory');

function publications_user_update(array $args = [], $context = null)
{
    if (!xarSecurity::check('ModeratePublications')) {
        return;
    }

    // Get parameters
    if (!xarVar::fetch('itemid', 'isset', $data['itemid'], null, xarVar::DONT_SET)) {
        return;
    }
    if (!xarVar::fetch('items', 'str', $items, '', xarVar::DONT_SET)) {
        return;
    }
    if (!xarVar::fetch('ptid', 'isset', $data['ptid'], null, xarVar::DONT_SET)) {
        return;
    }
    if (!xarVar::fetch('modify_cids', 'isset', $cids, null, xarVar::DONT_SET)) {
        return;
    }
    if (!xarVar::fetch('preview', 'isset', $data['preview'], null, xarVar::DONT_SET)) {
        return;
    }
    if (!xarVar::fetch('returnurl', 'str:1', $data['returnurl'], '', xarVar::NOT_REQUIRED)) {
        return;
    }
    if (!xarVar::fetch('quit', 'isset', $data['quit'], null, xarVar::DONT_SET)) {
        return;
    }
    if (!xarVar::fetch('front', 'isset', $data['front'], null, xarVar::DONT_SET)) {
        return;
    }
    if (!xarVar::fetch('tab', 'str:1', $data['tab'], '', xarVar::NOT_REQUIRED)) {
        return;
    }

    // Confirm authorisation code
    // This has been disabled for now
    //    if (!xarSec::confirmAuthKey()) return;

    $items = explode(',', $items);
    $pubtypeobject = DataObjectFactory::getObject(['name' => 'publications_types']);
    $pubtypeobject->getItem(['itemid' => $data['ptid']]);
    $data['object'] = DataObjectFactory::getObject(['name' => $pubtypeobject->properties['name']->value]);

    // First we need to check all the data on the template
    // If checkInput fails, don't bail
    $itemsdata = [];
    $isvalid = true;
    foreach ($items as $prefix) {
        $data['object']->setFieldPrefix($prefix);

        // Disable the celkoposition property according if this is not the base document
        $fieldname = $prefix . '_dd_' . $data['object']->properties['parent']->id;
        $data['object']->properties['parent']->checkInput($fieldname);
        if (empty($data['object']->properties['parent']->value)) {
            $data['object']->properties['position']->setDisplayStatus(DataPropertyMaster::DD_DISPLAYSTATE_DISPLAYONLY);
        } else {
            $data['object']->properties['position']->setDisplayStatus(DataPropertyMaster::DD_DISPLAYSTATE_DISABLED);
        }

        // Now get the input from the form
        $thisvalid = $data['object']->checkInput();
        $isvalid = $isvalid && $thisvalid;
        // Store each item for later processing
        $itemsdata[$prefix] = $data['object']->getFieldValues([], 1);
    }

    if ($data['preview'] || !$isvalid) {
        // Show debug info if called for
        if (!$isvalid &&
            xarModVars::get('publications', 'debugmode') &&
            in_array(xarUser::getVar('id'), xarConfigVars::get(null, 'Site.User.DebugAdmins'))) {
            echo xarML('The following were invalid fields:');
            echo "<br/>";
            var_dump($data['object']->getInvalids());
        }
        // Preview or bad data: redisplay the form
        $data['properties'] = $data['object']->getProperties();
        if ($data['preview']) {
            $data['tab'] = 'preview';
        }
        $data['items'] = $itemsdata;
        // Get the settings of the publication type we are using
        $data['settings'] = xarMod::apiFunc('publications', 'user', 'getsettings', ['ptid' => $data['ptid']]);

        return xarTpl::module('publications', 'user', 'modify', $data);
    }

    // call transform input hooks
    $article['transform'] = ['summary','body','notes'];
    $article = xarModHooks::call(
        'item',
        'transform-input',
        $data['itemid'],
        $article,
        'publications',
        $data['ptid']
    );

    // Now talk to the database. Loop through all the translation pages
    foreach ($itemsdata as $id => $itemdata) {
        // Get the data for this item
        $data['object']->setFieldValues($itemdata, 1);

        // Save or create the item (depends whether this translation is new)
        if (empty($id)) {
            $item = $data['object']->createItem();
        } else {
            $item = $data['object']->updateItem();
        }

        // Check if we have an alias and set it as an alias of the publications module
        $alias_flag = $data['object']->properties['alias_flag']->value;
        if ($alias_flag == 1) {
            $alias = $data['object']->properties['alias']->value;
            if (!empty($alias)) {
                xarModAlias::set($alias, 'publications');
            }
        } elseif ($alias_flag == 2) {
            $alias = $data['object']->properties['name']->value;
            if (!empty($alias)) {
                xarModAlias::set($alias, 'publications');
            }
        }

        // Clear the itemid property in preparation for the next round
        unset($data['object']->itemid);
    }

    // Success
    xarSession::setVar('statusmsg', xarML('Publication Updated'));

    // Inform the world via hooks
    $item = ['module' => 'publications', 'itemid' => $data['itemid'], 'itemtype' => $data['object']->properties['itemtype']->value];
    xarHooks::notify('ItemUpdate', $item);

    if ($data['quit']) {
        // Redirect if needed
        if (!xarVar::fetch('return_url', 'str', $return_url, '', xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!empty($return_url)) {
            // FIXME: this is a hack for short URLS
            $delimiter = (strpos($return_url, '&')) ? '&' : '?';
            xarController::redirect($return_url . $delimiter . 'itemid=' . $data['itemid'], null, $context);
        }

        // Redirect if we came from somewhere else
        $current_listview = xarSession::getVar('publications_current_listview');
        if (!empty($current_listview)) {
            xarController::redirect($current_listview, null, $context);
        }
        xarController::redirect(xarController::URL(
            'publications',
            'user',
            'view',
            ['ptid' => $data['ptid']]
        ), null, $context);
        return true;
    } elseif ($data['front']) {
        xarController::redirect(xarController::URL(
            'publications',
            'user',
            'display',
            ['name' => $pubtypeobject->properties['name']->value, 'itemid' => $data['itemid']]
        ), null, $context);
    } else {
        if (!empty($data['returnurl'])) {
            xarController::redirect($data['returnurl'], null, $context);
        } else {
            xarController::redirect(xarController::URL(
                'publications',
                'user',
                'modify',
                ['name' => $pubtypeobject->properties['name']->value, 'itemid' => $data['itemid']]
            ), null, $context);
        }
        return true;
    }
}
