<?php
namespace MichielRoos\TYPO3Scan\Command;

/**
 * Copyright (c) 2018 Michiel Roos
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use Symfony\Component\Console\Application as BaseApplication;

/**
 * Class Application
 * @package MichielRoos\TYPO3Scan\Command
 */
class Application extends BaseApplication
{
    /**
     *
     * @var string
     */
    private static $logo = "   ________  ______  ____ _____
  /_  __/\ \/ / __ \/ __ \__  /   ______________ _____
   / /    \  / /_/ / / / //_ <   / ___/ ___/ __ `/ __ \
  / /     / / ____/ /_/ /__/ /  (__  ) /__/ /_/ / / / /
 /_/     /_/_/    \____/____/  /____/\___/\__,_/_/ /_/

        https://github.com/tuurlijk/typo3scan

          Hand coded with %s️ by Michiel Roos 

";

    /**
     * @return string
     */
    public function getHelp()
    {
        $love = $this->isColorSupported() ? "\e[31m♥\e[0m" : "♥";
        return sprintf(self::$logo, $love) . parent::getHelp();
    }

    /**
     * Check if color output is supported
     * @return bool
     */
    private function isColorSupported()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON';
        }
        return \function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
