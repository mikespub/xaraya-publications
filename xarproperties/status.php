<?php
/**
 * Article Status Property
 *
 * @package Xaraya eXtensible Management System
 * @copyright (C) 2003 by the Xaraya Development Team.
 * @license GPL {http://www.gnu.org/licenses/gpl.html}
 * @link http://www.xaraya.com
 *
 * @subpackage dynamicdata properties
 * @author mikespub <mikespub@xaraya.com>
 */
/**
 * Include the base class
 *
 */
sys::import('modules.base.xarproperties.dropdown');

/**
 * handle the status property
 *
 * @package dynamicdata
 */
class StatusProperty extends SelectProperty
{
    public $id         = 10;
    public $name       = 'status';
    public $desc       = 'Article Status';
    public $reqmodules = array('articles');

    function __construct(ObjectDescriptor $descriptor)
    {
        parent::__construct($descriptor);
        $this->filepath   = 'modules/articles/xarproperties';
    }

    function getOptions()
    {
        $options = array(
             array('id' => 0, 'name' => xarML('Submitted')),
             array('id' => 1, 'name' => xarML('Rejected')),
             array('id' => 2, 'name' => xarML('Approved')),
             array('id' => 3, 'name' => xarML('Front Page')),
         );
        return $options;
    }
}

?>
