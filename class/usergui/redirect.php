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
use xarVar;
use xarMod;
use xarModHooks;
use xarController;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications user redirect function
 */
class RedirectMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    /**
     * redirect to a site based on some URL field of the item
     */
    public function __invoke(array $args = [])
    {
        // Get parameters from user
        if (!xarVar::fetch('id', 'id', $id, null, xarVar::NOT_REQUIRED)) {
            return;
        }

        // Override if needed from argument array
        extract($args);

        if (!isset($id) || !is_numeric($id) || $id < 1) {
            return xarML('Invalid publication ID');
        }

        // Load API
        if (!xarMod::apiLoad('publications', 'user')) {
            return;
        }

        // Get publication
        $publication = xarMod::apiFunc(
            'publications',
            'user',
            'get',
            ['id' => $id]
        );

        if (!is_array($publication)) {
            $msg = xarML('Failed to retrieve publication in #(3)_#(1)_#(2).php', 'user', 'get', 'publications');
            throw new DataNotFoundException(null, $msg);
        }

        $ptid = $publication['pubtype_id'];

        // Get publication types
        $pubtypes = xarMod::apiFunc('publications', 'user', 'get_pubtypes');

        // TODO: improve this e.g. when multiple URL fields are present
        // Find an URL field based on the pubtype configuration
        foreach ($pubtypes[$ptid]['config'] as $field => $value) {
            if (empty($value['label'])) {
                continue;
            }
            if ($value['format'] == 'url' && !empty($publication[$field]) && $publication[$field] != 'http://') {
                // TODO: add some verifications here !
                $hooks = xarModHooks::call(
                    'item',
                    'display',
                    $id,
                    ['module'    => 'publications',
                        'itemtype'  => $ptid,
                    ],
                    'publications'
                );
                xarController::redirect($article[$field], null, $this->getContext());
                return true;
            } elseif ($value['format'] == 'urltitle' && !empty($publication[$field]) && substr($publication[$field], 0, 2) == 'a:') {
                $array = unserialize($publication[$field]);
                if (!empty($array['link']) && $array['link'] != 'http://') {
                    $hooks = xarModHooks::call(
                        'item',
                        'display',
                        $id,
                        ['module'    => 'publications',
                            'itemtype'  => $ptid,
                        ],
                        'publications'
                    );
                    xarController::redirect($array['link'], null, $this->getContext());
                    return true;
                }
            }
        }

        return xarML('Unable to find valid redirect field');
    }
}