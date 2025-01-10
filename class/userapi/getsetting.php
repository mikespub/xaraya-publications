<?php

/**
 * @package modules\publications
 * @category Xaraya Web Applications Framework
 * @version 2.5.7
 * @copyright see the html/credits.html file in this release
 * @license GPL {@link http://www.gnu.org/licenses/gpl.html}
 * @link https://github.com/mikespub/xaraya-modules
**/

namespace Xaraya\Modules\Publications\UserApi;

use Xaraya\Modules\MethodClass;
use xarMod;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications userapi getsetting function
 */
class GetsettingMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    /**
     * retrieve the settings of a publication type
     * @param mixed $args array containing the publication type
     * @return array of setting keys and values
     */
    public function __invoke($data)
    {
        $settings = xarMod::apiFunc('publications', 'user', 'getsettings', $data);

        if (isset($settings[$data['setting']])) {
            return $settings[$data['setting']];
        }
        return null;
    }
}