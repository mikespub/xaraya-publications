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
 * publications admin templates_type function
 * @extends MethodClass<AdminGui>
 */
class TemplatesTypeMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    public function __invoke(array $args = [])
    {
        if (!xarSecurity::check('AdminPublications')) {
            return;
        }

        extract($args);

        if (!xarVar::fetch('confirm', 'int', $confirm, 0, xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!xarVar::fetch('ptid', 'id', $data['ptid'], xarModVars::get('publications', 'defaultpubtype'), xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!xarVar::fetch('file', 'str', $data['file'], 'summary', xarVar::NOT_REQUIRED)) {
            return;
        }
        if (!xarVar::fetch('source_data', 'str', $data['source_data'], '', xarVar::NOT_REQUIRED)) {
            return;
        }

        $pubtypeobject = DataObjectFactory::getObject(['name' => 'publications_types']);
        $pubtypeobject->getItem(['itemid' => $data['ptid']]);
        $pubtype = explode('_', $pubtypeobject->properties['name']->value);
        $pubtype = $pubtype[1] ?? $pubtype[0];

        $data['object'] = DataObjectFactory::getObject(['name' => $pubtypeobject->properties['name']->value]);

        $basepath = sys::code() . "modules/publications/xartemplates/objects/" . $pubtype;
        $sourcefile = $basepath . "/" . $data['file'] . ".xt";
        $overridepath = "themes/" . xarModVars::get('themes', 'default_theme') . "/modules/publications/objects/" . $pubtype;
        $overridefile = $overridepath . "/" . $data['file'] . ".xt";

        // If we are saving, write the file now
        if ($confirm && !empty($data['source_data'])) {
            xarMod::apiFunc('publications', 'admin', 'write_file', ['file' => $overridefile, 'data' => $data['source_data']]);
        }

        // Let the template know what kind of file this is
        if (file_exists($overridefile)) {
            $data['filetype'] = 'theme';
            $filepath = $overridefile;
            $data['writable'] = is_writable($overridefile);
        } else {
            $data['filetype'] = 'module';
            $filepath = $sourcefile;
            $data['writable'] = $this->getParent()->is_writeable_dir($overridepath);
        }

        $data['source_data'] = trim(xarMod::apiFunc('publications', 'admin', 'read_file', ['file' => $filepath]));
        $data['filepath'] = $filepath;

        // Initialize the template
        if (empty($data['source_data'])) {
            $source_dist = $basepath . "/" . $data['file'] . "_dist.xt";
            $data['source_data'] = xarMod::apiFunc('publications', 'admin', 'read_file', ['file' => $source_dist]);
            xarMod::apiFunc('publications', 'admin', 'write_file', ['file' => $sourcefile, 'data' => $data['source_data']]);
        }

        $data['files'] = [
            ['id' => 'summary', 'name' => 'summary display'],
            ['id' => 'detail',  'name' => 'detail display'],
            ['id' => 'input',   'name' => 'input form'],
        ];
        return $data;
    }
}
