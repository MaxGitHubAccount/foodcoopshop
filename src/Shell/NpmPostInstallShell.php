<?php
/**
 * FoodCoopShop - The open source software for your foodcoop
 *
 * Licensed under the GNU Affero General Public License version 3
 * For full copyright and license information, please see LICENSE
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         FoodCoopShop 1.0.0
 * @license       https://opensource.org/licenses/AGPL-3.0
 * @author        Mario Rothauer <office@foodcoopshop.com>
 * @copyright     Copyright (c) Mario Rothauer, https://www.rothauer-it.com
 * @link          https://www.foodcoopshop.com
 */

namespace App\Shell;

use Cake\Filesystem\File;
use Cake\Filesystem\Folder;

class NpmPostInstallShell extends AppShell
{

    public $vendorDir;
    /**
     * do not call parent::main because db connection might not be available
     * @see AppShell::main()
     */
    public function main()
    {
        $this->vendorDir = WWW_ROOT . 'node_modules';

        $this->fontawesomePath = $this->vendorDir . DS . '@fortawesome' . DS . 'fontawesome-free' . DS;
        $this->jqueryBackstretchPath = $this->vendorDir . DS . 'jquery-backstretch' . DS;
        $this->jqueryUiPath = $this->vendorDir . DS . 'jquery-ui' . DS;
        $this->tooltipsterPath = $this->vendorDir . DS . 'tooltipster' . DS;

        $this->cleanOverheadFromDependencies();
        $this->copyAdaptedElfinderFiles();
        $this->copyJqueryUiImages();
        $this->copyFontawesomeFonts();
    }

    private function cleanOverheadFromDependencies()
    {

        $folder = new Folder();

        $folder->delete($this->jqueryBackstretchPath . DS . 'examples');
        $folder->delete($this->jqueryBackstretchPath . DS . 'test');

        $folder->delete($this->fontawesomePath . 'js');

        $file = new File($this->fontawesomePath . 'css' . DS . 'all.min.css');
        $file->delete();
        $file = new File($this->fontawesomePath . 'css' . DS . 'fontawesome.css');
        $file->delete();
        $file = new File($this->fontawesomePath . 'css' . DS . 'fontawesome.min.css');
        $file->delete();
        $file = new File($this->fontawesomePath . 'css' . DS . 'v4-shims.css');
        $file->delete();
        $file = new File($this->fontawesomePath . 'css' . DS . 'v4-shims.min.css');
        $file->delete();

        $folder->delete($this->jqueryUiPath . 'external');

        $folder->delete($this->tooltipsterPath . 'demo');
        $folder->delete($this->tooltipsterPath . 'doc');

    }

    private function copyFontawesomeFonts()
    {
        $folder = new Folder($this->fontawesomePath . 'webfonts' . DS);
        $folder->copy(WWW_ROOT . 'webfonts');
        $this->out('Fontawesome fonts copied.');
    }

    /**
     * if asset compress is on (debug=0=)
     * images linked in css files have to be located in WEBROOT/cache
     */
    private function copyJqueryUiImages()
    {
        $folder = new Folder($this->jqueryUiPath . 'dist' . DS . 'themes' . DS . 'smoothness' . DS . 'images' . DS);
        $folder->copy(WWW_ROOT . 'cache' . DS . 'images');
        $this->out('JQueryUI images copied.');
    }

    private function copyAdaptedElfinderFiles()
    {
        $elfinderConfigDir = ROOT . DS . 'config' . DS . 'elfinder' . DS;

        $adaptedFiles = [
            $elfinderConfigDir . 'elfinder.html',
            $elfinderConfigDir . 'php' . DS . 'connector.minimal.php'
        ];

        foreach ($adaptedFiles as $file) {
            copy($file, preg_replace('/config/', 'webroot' . DS . 'js', $file, 1));
            $this->out('Elfinder config file ' . $file . ' copied successfully.');
        }
    }
}
