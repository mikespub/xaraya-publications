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
use xarVar;
use xarModVars;
use xarMod;
use DataObjectFactory;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications admin stylesheet_type function
 * @extends MethodClass<AdminGui>
 */
class StylesheetTypeMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    public function __invoke(array $args = [])
    {
        if (!$this->checkAccess('AdminPublications')) {
            return;
        }

        extract($args);

        if (!$this->fetch('confirm', 'int', $confirm, 0, xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!$this->fetch('ptid', 'id', $data['ptid'], $this->getModVar('defaultpubtype'), xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!$this->fetch('file', 'str', $data['file'], '', xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!$this->fetch('source_data', 'str', $data['source_data'], '', xarVar::NOT_REQUIRED)) {
            return;
        }
        $admingui = $this->getParent();

        $pubtypeobject = DataObjectFactory::getObject(['name' => 'publications_types']);
        $pubtypeobject->getItem(['itemid' => $data['ptid']]);
        $pubtype = explode('_', $pubtypeobject->properties['name']->value);
        $pubtype = $pubtype[1] ?? $pubtype[0];

        $data['object'] = DataObjectFactory::getObject(['name' => $pubtypeobject->properties['name']->value]);

        $basepath = sys::code() . "modules/publications/xarstyles";
        $sourcefile = $basepath . "/" . $data['file'] . ".css";
        $overridepath = "themes/" . xarModVars::get('themes', 'default_theme') . "/modules/publications/style";
        $overridefile = $overridepath . "/" . $data['file'] . ".css";

        // If we are saving, write the file now
        if ($confirm && !empty($data['file']) && !empty($data['source_data'])) {
            xarMod::apiFunc('publications', 'admin', 'write_file', ['file' => $overridefile, 'data' => $data['source_data']]);
        }

        // Let the template know what kind of file this is
        if (empty($data['file'])) {
            $data['filetype'] = 'empty';
            $filepath = '';
            $data['writable'] = 0;
        } elseif (file_exists($overridefile)) {
            $data['filetype'] = 'theme';
            $filepath = $overridefile;
            $data['writable'] = is_writable($overridefile);
        } elseif (file_exists($sourcefile)) {
            $data['filetype'] = 'module';
            $filepath = $sourcefile;
            $data['writable'] = $admingui->is_writeable_dir($overridepath);
        } else {
            $data['filetype'] = 'unknown';
            $filepath = $overridefile;
            $data['writable'] = $admingui->is_writeable_dir($overridepath);
        }
        $data['source_data'] = trim(xarMod::apiFunc('publications', 'admin', 'read_file', ['file' => $filepath]));

        return $data;
    }
}
