<?php
/**
 * Publications module
 *
 * @package modules
 * @copyright (C) copyright-placeholder
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link http://www.xaraya.com
 *
 * @subpackage Publications Module
 
 * @author mikespub
 */
/**
 * update item from publications_admin_modify
 *
 * @param id     ptid       The publication Type ID for this new article
 * @param array  new_cids   An array with the category ids for this new article (OPTIONAL)
 * @param string preview    Are we gonna see a preview? (OPTIONAL)
 * @param string save       Call the save action (OPTIONAL)
 * @param string return_url The URL to return to (OPTIONAL)
 * @return  bool true on success, or mixed on failure
 */

sys::import('modules.dynamicdata.class.objects.master');

function publications_admin_update()
{
    // Get parameters
    if(!xarVarFetch('itemid',       'isset', $itemid,       NULL, XARVAR_DONT_SET)) {return;}
    if(!xarVarFetch('items',        'str',   $items,       '', XARVAR_DONT_SET)) {return;}
    if(!xarVarFetch('ptid',         'isset', $data['ptid'],      NULL, XARVAR_DONT_SET)) {return;}
    if(!xarVarFetch('modify_cids',  'isset', $cids,      NULL, XARVAR_DONT_SET)) {return;}
    if(!xarVarFetch('preview',      'isset', $data['preview'],   NULL, XARVAR_DONT_SET)) {return;}
    if(!xarVarFetch('quit',         'isset', $data['quit'],      NULL, XARVAR_DONT_SET)) {return;}
    if(!xarVarFetch('tab',          'str:1', $data['tab'], '', XARVAR_NOT_REQUIRED)) {return;}
    if(!xarVarFetch('returnurl',    'str:1', $data['returnurl'], 'view', XARVAR_NOT_REQUIRED)) {return;}

    // Confirm authorisation code
    // This has been disabled for now
//    if (!xarSecConfirmAuthKey()) return;

    $items = explode(',',$items);
    $pubtypeobject = DataObjectMaster::getObject(array('name' => 'publications_types'));
    $pubtypeobject->getItem(array('itemid' => $data['ptid']));
    $data['object'] = DataObjectMaster::getObject(array('name' => $pubtypeobject->properties['name']->value));
    
    //FIXME This should be configuration in the celko property itself
    $data['object']->properties['position']->initialization_celkoparent_id = 'parentpage_id';
    $data['object']->properties['position']->initialization_celkoright_id = 'rightpage_id';
    $data['object']->properties['position']->initialization_celkoleft_id  = 'leftpage_id';
    $xartable = xarDB::getTables();
    $data['object']->properties['position']->initialization_itemstable = $xartable['publications'];

    // First we need to check all the data on the template
    // If checkInput fails, don't bail
    $itemsdata = array();
    $isvalid = true;
    foreach ($items as $prefix) {
        $data['object']->setFieldPrefix($prefix);
        $thisvalid = $data['object']->checkInput();
        $isvalid = $isvalid && $thisvalid;
    // Store each item for later processing
        $itemsdata[$prefix] = $data['object']->getFieldValues();
    }
    
    if ($data['preview'] || !$isvalid) {
        // Preview or bad data: redisplay the form
        $data['properties'] = $data['object']->getProperties();
        if ($data['preview']) $data['tab'] = 'preview';
        $data['items'] = $itemsdata;
        // Get the settings of the publication type we are using
        $data['settings'] = xarModAPIFunc('publications','user','getsettings',array('ptid' => $data['ptid']));
        
        return xarTplModule('publications','admin','modify', $data);
    }
    
/*    if (empty($itemid) || !is_numeric($itemid)) {
        $msg = xarML('Invalid #(1) for #(2) function #(3)() in module #(4)',
                     'item id', 'user', 'update', 'Publications');
        throw new BadParameterException(null,$msg);
    }

    if (!empty($cids) && count($cids) > 0) {
        $article['cids'] = array_values(preg_grep('/\d+/',$cids));
    } else {
        $article['cids'] = array();
    }

    // for preview
    $article['pubtype_id'] = $data['ptid'];
    $article['id'] = $id;

    if ($preview || count($invalid) > 0) {
        $data = xarModFunc('publications','admin','modify',
                             array('preview' => true,
                                   'article' => $article,
                                   'return_url' => $return_url,
                                   'invalid' => $invalid));
        unset($article);
        if (is_array($data)) {
            return xarTplModule('publications','admin','modify',$data);
        } else {
            return $data;
        }
    }
*/
    // call transform input hooks
    $article['transform'] = array('summary','body','notes');
    $article = xarModCallHooks('item', 'transform-input', $itemid, $article,
                               'publications', $data['ptid']);

    // Now talk to the database
    foreach ($itemsdata as $itemid => $itemdata) {
        $data['object']->setFieldValues($itemdata);
        if (empty($itemid)) $item = $data['object']->createItem();
        else $item = $data['object']->updateItem();
    // Clear the itemid property in preparation for the next round
        unset($data['object']->itemid);
    }
    
    // Success
    xarSession::setVar('statusmsg', xarML('Publication Updated'));

    // If quitting, go to admin view; otherwise redisplay the page
    if (xarSecurityCheck('EditPublications',0,'Publication',$data['ptid'].':All:All:All')) {
        if ($data['quit']) {
            xarController::redirect(xarModURL('publications', 'admin', 'view',
                                          array('ptid' => $data['ptid'])));
            return true;
        } else {
            xarController::redirect(xarModURL('publications', 'admin', 'modify',
                                          array('itemid' => $itemid)));
            return true;
        }
    }
    
}

?>