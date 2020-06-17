<?php
namespace MichielRoos\TYPO3Scan\Service;

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

use TYPO3\CMS\Scanner\Domain\Model\MatcherBundleCollection;
use TYPO3\CMS\Scanner\Matcher;
use TYPO3\CMS\Scanner\ScannerFactory;

/**
 * Class ScannerService
 * @package MichielRoos\TYPO3Scan\Service
 */
class ScannerService
{
    /**
     * @var string
     */
    private static $matcherBundleBasePath = '';

    /**
     * @var MatcherBundleCollection
     */
    private $collection;

    /**
     * @var \TYPO3\CMS\Scanner\Scanner
     */
    private $scanner;

    /**
     * Scanner constructor.
     */
    public function __construct($version)
    {
        $this->setMatcherBundlePath();

        switch ($version) {
            case '10':
                $this->collection = new MatcherBundleCollection(
                    new \TYPO3\CMS\Scanner\Domain\Model\MatcherBundle(
                        self::$matcherBundleBasePath . $version,
                        '',

                        Matcher\ArrayDimensionMatcher::class,
                        Matcher\ArrayGlobalMatcher::class,
                        Matcher\ClassConstantMatcher::class,
                        Matcher\ClassNameMatcher::class,
                        Matcher\ConstantMatcher::class,
                        Matcher\ConstructorArgumentMatcher::class,
                        Matcher\FunctionCallMatcher::class,
                        Matcher\InterfaceMethodChangedMatcher::class,
                        Matcher\MethodAnnotationMatcher::class,
                        Matcher\MethodArgumentDroppedMatcher::class,
                        Matcher\MethodArgumentDroppedStaticMatcher::class,
                        Matcher\MethodArgumentRequiredMatcher::class,
                        Matcher\MethodArgumentRequiredStaticMatcher::class,
                        Matcher\MethodArgumentUnusedMatcher::class,
                        Matcher\MethodCallMatcher::class,
                        Matcher\MethodCallStaticMatcher::class,
                        Matcher\PropertyAnnotationMatcher::class,
                        Matcher\PropertyExistsStaticMatcher::class,
                        Matcher\PropertyProtectedMatcher::class,
                        Matcher\PropertyPublicMatcher::class
                    )
                );
                break;
            case '9':
                $this->collection = new MatcherBundleCollection(
                    new \TYPO3\CMS\Scanner\Domain\Model\MatcherBundle(
                        self::$matcherBundleBasePath . $version,
                        '',

                        Matcher\ArrayDimensionMatcher::class,
                        Matcher\ArrayGlobalMatcher::class,
                        Matcher\ClassConstantMatcher::class,
                        Matcher\ClassNameMatcher::class,
                        Matcher\ConstantMatcher::class,
                        Matcher\FunctionCallMatcher::class,
                        Matcher\InterfaceMethodChangedMatcher::class,
                        Matcher\MethodAnnotationMatcher::class,
                        Matcher\MethodArgumentDroppedMatcher::class,
                        Matcher\MethodArgumentDroppedStaticMatcher::class,
                        Matcher\MethodArgumentRequiredMatcher::class,
                        Matcher\MethodArgumentRequiredStaticMatcher::class,
                        Matcher\MethodArgumentUnusedMatcher::class,
                        Matcher\MethodCallMatcher::class,
                        Matcher\MethodCallStaticMatcher::class,
                        Matcher\PropertyAnnotationMatcher::class,
                        Matcher\PropertyExistsStaticMatcher::class,
                        Matcher\PropertyProtectedMatcher::class,
                        Matcher\PropertyPublicMatcher::class
                    )
                );
                break;
            case '8':
                $this->collection = new MatcherBundleCollection(
                    new \TYPO3\CMS\Scanner\Domain\Model\MatcherBundle(
                        self::$matcherBundleBasePath . $version,
                        '',

                        Matcher\ArrayDimensionMatcher::class,
                        Matcher\ArrayGlobalMatcher::class,
                        Matcher\ClassNameMatcher::class,
                        Matcher\ConstantMatcher::class,
                        Matcher\MethodArgumentDroppedMatcher::class,
                        Matcher\MethodArgumentDroppedStaticMatcher::class,
                        Matcher\MethodArgumentRequiredMatcher::class,
                        Matcher\MethodArgumentUnusedMatcher::class,
                        Matcher\MethodCallMatcher::class,
                        Matcher\MethodCallStaticMatcher::class,
                        Matcher\PropertyPublicMatcher::class
                    )
                );
                break;
            case '7':
            default:
                $this->collection = new MatcherBundleCollection(
                    new \TYPO3\CMS\Scanner\Domain\Model\MatcherBundle(
                        self::$matcherBundleBasePath . $version,
                        '',

                        Matcher\ArrayMatcher::class,
                        Matcher\ArrayDimensionMatcher::class,
                        Matcher\ArrayGlobalMatcher::class,
                        Matcher\ClassConstantMatcher::class,
                        Matcher\ClassNameMatcher::class,
                        Matcher\ClassNamePatternMatcher::class,
                        Matcher\ConstantMatcher::class,
                        Matcher\GlobalMatcher::class,
                        Matcher\MethodArgumentDroppedMatcher::class,
                        Matcher\MethodArgumentRequiredMatcher::class,
                        Matcher\MethodArgumentUnusedStaticMatcher::class,
                        Matcher\MethodCallMatcher::class,
                        Matcher\MethodCallStaticMatcher::class,
                        Matcher\PropertyProtectedMatcher::class,
                        Matcher\PropertyPublicMatcher::class
                    )
                );
                break;
        }

        $this->scanner = ScannerFactory::create()->createFor(\PhpParser\ParserFactory::PREFER_PHP7);
    }

    public function scan($path)
    {
        $result = $this->scanner->scanPath(
            $path,
            $this->collection
        );
        return $result;
    }

    /**
     * Find the matcher bundles, either inside of the phar file,
     * or from the upper dir in case we are installed via composer
     */
    private function setMatcherBundlePath()
    {
        foreach ([__DIR__ . '/../../../vendor/typo3/cms-scanner/config/Matcher/', __DIR__ . '/../../../../../typo3/cms-scanner/config/Matcher/'] as $file) {
            if (is_dir($file)) {
                $this::$matcherBundleBasePath = $file;
                break;
            }
        }
    }
}
