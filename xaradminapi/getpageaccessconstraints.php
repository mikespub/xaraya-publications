<?php
/**
 * Publications Module
 *
 * @package modules
 * @subpackage publications module
 * @category Third Party Xaraya Module
 * @version 2.0.0
 * @copyright (C) 2012 Netspan AG
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @author Marc Lutolf <mfl@netspan.ch>
 */

sys::import('modules.dynamicdata.class.objects.master');

function publications_adminapi_getpageaccessconstraints($args)
{
    if (!isset($args['property'])) throw new Exception(xarML('Missing property param in publications_adminapi_getpageaccessconstraints'));

    $constraints = array(
        'display' => (array('level' => 800, 'group' => 0, 'failure' => 1),
        'add'     => (array('level' => 800, 'group' => 0, 'failure' => 1),
        'modify'  => (array('level' => 800, 'group' => 0, 'failure' => 1),
        'delete'  => (array('level' => 800, 'group' => 0, 'failure' => 1),
        );
    );
    
    if (empty($args['property']->value)) return $constraints;
    
    try {
        $constraints = unserialize($args['property']->value);
    } catch (Exception $e) {
    }

    return $constraints;
}

?>