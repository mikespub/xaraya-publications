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
use xarMod;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications admin waitingcontent function
 * @extends MethodClass<AdminGui>
 */
class WaitingcontentMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    /**
     * display waiting content as a hook
     */
    public function __invoke(array $args = [])
    {
        if (!$this->sec()->checkAccess('EditPublications')) {
            return;
        }

        // Get publication types
        $publinks = xarMod::apiFunc(
            'publications',
            'user',
            'getpublinks',
            ['state' => [0],
                'typemod' => 'admin', ]
        );

        $data['loop'] = $publinks;
        return $data;
    }
}
