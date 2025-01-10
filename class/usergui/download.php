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
use xarSecurity;
use sys;
use BadParameterException;

sys::import('xaraya.modules.method');

/**
 * publications user download function
 */
class DownloadMethod extends MethodClass
{
    /** functions imported by bermuda_cleanup */

    /**
     * Download a template or stylesheet
     */
    public function __invoke(array $args = [])
    {
        if (!xarVar::fetch('filepath', 'str', $filepath, '', xarVar::NOT_REQUIRED)) {
            return;
        }

        # --------------------------------------------------------
        # Check the input
        #
        if (empty($filepath)) {
            throw new Exception(xarML('No file path passed'));
        }
        $filepath = urldecode($filepath);
        $filesize = filesize($filepath);
        $filetype = filetype($filepath);
        $filename = basename($filepath);

        // Xaraya security
        if (!xarSecurity::check('ManagePublications')) {
            return;
        }

        # --------------------------------------------------------
        # Start buffering for the file
        #
        ob_start();

        $fp = @fopen($filepath, 'rb');
        if (is_resource($fp)) {
            do {
                $data = fread($fp, 65536);
                if (strlen($data) == 0) {
                    break;
                } else {
                    print("$data");
                }
            } while (true);

            fclose($fp);
        }

        # --------------------------------------------------------
        # Send the header
        #
        // Headers -can- be sent after the actual data
        // Why do it this way? So we can capture any errors and return if need be :)
        // not that we would have any errors to catch at this point but, mine as well
        // do it in case I think of some errors to catch
        header("Pragma: ");
        header("Cache-Control: ");
        header("Content-type: " . $filetype);
        header("Content-disposition: attachment; filename=\"" . $filename . "\"");
        if (!empty($filesize)) {
            header("Content-length: " . $filesize);
        }
        # --------------------------------------------------------
        # Stop here
        #
        exit(0);
    }
}