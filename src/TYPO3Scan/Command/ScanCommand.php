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

use MichielRoos\TYPO3Scan\Service\ScannerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use TYPO3\CMS\Scanner\Domain\Model\Match;
use TYPO3\CMS\Scanner\Matcher\AbstractCoreMatcher;

/**
 * Class ScanCommand
 * @package MichielRoos\TYPO3Scan\Command
 */
class ScanCommand extends Command
{
    /**
     * Configure
     */
    protected function configure()
    {
        $this
            ->setName('scan')
            ->setDescription('Scan a path for deprecated code')
            ->setDefinition([
                new InputArgument('path', InputArgument::REQUIRED, 'Path to scan'),
                new InputOption('target', 't', InputOption::VALUE_OPTIONAL, 'TYPO3 version to target', '9'),
                new InputOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format', 'plain'),
                new InputOption('templatePath', null, InputOption::VALUE_OPTIONAL, 'Path to template folder'),
            ])
            ->setHelp(<<<EOT
The <info>scan</info> command scans a path for deprectated code</info>.

Scan a folder:
<info>php typo3scan.phar scan ~/tmp/source</info>

Scan a folder for v8 changes:
<info>php typo3scan.phar scan --target 8 ~/tmp/source</info>

Scan a folder for v7 changes and output in markdown:
<info>php typo3scan.phar scan --target 7 --format markdown ~/tmp/source</info>

Scan a folder for v9 changes and output in markdown with custom template:
<info>php typo3scan.phar scan --format markdown --templatePath ~/path/to/templates --path ~/tmp/source</info>
EOT
            );
    }

    /**
     * Execute
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return void|int
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stdErr = $output;
        if ($output instanceof ConsoleOutputInterface) {
            $stdErr = $output->getErrorOutput();
        }

        $startTime = microtime(true);
        $format = $input->getOption('format') ?: 'plain';
        $path = realpath($input->getArgument('path'));
        if (!is_dir($path)) {
            $stdErr->writeln(sprintf('Path does not exist: "%s"', $path));
            exit;
        }

        $version = $input->getOption('target');
        if ($input->getOption('templatePath') && is_dir(realpath($input->getOption('templatePath')))) {
            $templatePaths[] = realpath($input->getOption('templatePath'));
        }
        $templatePaths[] = __DIR__ . '/../../Resources/Private/Templates';

        $basePath = $path;
        $extension = '';

        if ($this->pathContainsExt($path)) {
            $extension = $this->getExtKeyFromPath($path);
            $basePath = $this->getExtPath($path) . DIRECTORY_SEPARATOR . $extension . DIRECTORY_SEPARATOR;
        }

        $scanner = new ScannerService($version);
        $directoryMatches = $scanner->scan($path);
        $total = $directoryMatches->countAll();

        $executionTime = microtime(true) - $startTime;

        $percentagesByType = $this->getPercentagesByType($this->getCountsByType($directoryMatches), $total);

        $loader = new \Twig_Loader_Filesystem($templatePaths);
        $twig = new \Twig_Environment($loader);
        $twig->addFilter($this->getChangeTitle());
        $twig->addFilter($this->getLineFromFileFilter());
        $twig->addFilter($this->getFilenameFilter());
        $twig->addFilter($this->getOnlineDocumentFilter());

        $context = [
            'title' => $extension ?: $path,
            'targetVersion' => $version,
            'total' => $total,
            'basePath' => $basePath,
            'statistics' => $percentagesByType,
            'directoryMatches' => $directoryMatches,
            'executionTime' => $executionTime
        ];

        $template = $twig->load(ucfirst($format) . '.twig');
        $output->write($template->render($context));
    }

    /**
     * TWIG filter to get line from file
     *
     * @return \Twig_Filter
     */
    protected function getLineFromFileFilter(): \Twig_Filter
    {
        return new \Twig_Filter('getLineFromFile', function ($fileName, $lineNumber) {
            return $this->getLineFromFile($fileName, $lineNumber);
        });
    }

    /**
     * TWIG filter for getting the name of a file without the extension
     *
     * @return \Twig_Filter
     */
    protected function getFilenameFilter(): \Twig_Filter
    {
        return new \Twig_Filter('getFilename', function ($path) {
            return pathinfo($path, PATHINFO_FILENAME);
        });
    }

    /**
     * TWIG filter to get link to online documentation
     *
     * @return \Twig_Filter
     */
    protected function getOnlineDocumentFilter(): \Twig_Filter
    {
        return new \Twig_Filter('getOnlineDocument', function ($path) {
            return $this->findOnlineDocumentation($path);
        });
    }

    /**
     * TWIG filter to get the title of the change
     *
     * @return \Twig_Filter
     */
    protected function getChangeTitle(): \Twig_Filter
    {
        return new \Twig_Filter('getChangeTitle', function ($path) {
            $rstPath = $this->findRstFile($path);
            return $this->extractTitleFromRstFile($rstPath);
        });
    }

    /**
     * Return a specific line from a file
     *
     * @param $fileName
     * @param $lineNumber
     * @return string
     */
    protected function getLineFromFile($fileName, $lineNumber): string
    {
        $file = new \SplFileObject($fileName);
        if (!$file->eof()) {
            $file->seek($lineNumber - 1);
            return trim($file->current());
        }
        return '';
    }

    /**
     * Check if the given path contains /ext/
     *
     * @param $path
     * @return bool
     */
    protected function pathContainsExt($path): bool
    {
        while ($dir = basename($path)) {
            if ($dir === 'ext') {
                return true;
            }
            $newPath = \dirname($path);
            if ($newPath === $path) {
                break;
            }
            $path = $newPath;
        }
        return false;
    }

    /**
     * Return the base path with /ext/ on the end
     *
     * @param $path
     * @return string
     */
    protected function getExtPath($path): string
    {
        while ($dir = basename($path)) {
            if ($dir === 'ext') {
                return $path;
            }
            $path = \dirname($path);
        }
        return $path;
    }

    /**
     * Return the extension key from the path
     *
     * @param $path
     * @return string
     */
    protected function getExtKeyFromPath($path): string
    {
        $extensionName = '';
        while ($dir = basename($path)) {
            if ($dir === 'ext') {
                return $extensionName;
            }
            $extensionName = $dir;
            $path = \dirname($path);
        }
        return $extensionName;
    }

    /**
     * Count the number of total weak, strong, deprecation, breaking etc.
     *
     * @param $directoryMatches
     * @return array
     */
    protected function getCountsByType($directoryMatches): array
    {
        $countsByType = [
            AbstractCoreMatcher::INDICATOR_STRONG => 0,
            AbstractCoreMatcher::INDICATOR_WEAK => 0
        ];
        foreach ($directoryMatches as $fileMatches) {
            /** @var Match $fileMatch */
            foreach ($fileMatches as $fileMatch) {
                if (!array_key_exists($fileMatch->getIndicator(), $countsByType)) {
                    $countsByType[$fileMatch->getIndicator()] = 0;
                }
                $countsByType[$fileMatch->getIndicator()]++;
                if (!array_key_exists($fileMatch->getType(), $countsByType)) {
                    $countsByType[$fileMatch->getType()] = 0;
                }
                $countsByType[$fileMatch->getType()]++;
            }
        }
        return $countsByType;
    }

    /**
     * Get the percentages by type of total
     * @param array $counts
     * @param int $total
     * @return array
     */
    protected function getPercentagesByType($counts, $total): array
    {
        $result = [];
        foreach ($counts as $type => $count) {
            if ($total <= 0) {
                $result[$type] = 0;
            } else {
                $result[$type] = number_format(100 * $count / $total, 1) . '%';
            }
        }
        return $result;
    }

    /**
     * Find the real path to the rst file
     * The Matchers sometimes include the version number of the TYPO3 version,
     * other times they don't. So we can't rely on the rst file path given in
     * the matcher.
     *
     * Instead we take the last part (basename) of the rst file and then look up
     * the file in the Changelog dir.
     *
     * @param $path
     * @return string
     */
    protected function findRstFile($path): string
    {
        static $restFiles = [];
        if (empty($restFiles)) {
            $restFinder = new Finder();
            $restFilesList = $restFinder->files()->in(__DIR__ . '/../../Resources/Private/Changelog')->name('*.rst');
            /** @var \SplFileInfo $restFile */
            foreach ($restFilesList as $restFile) {
                $restFiles[basename($restFile->getPathname())] = $restFile->getPathname();
            }
            // Special case for the ClassNamePatternMatcher Rules from v7
            $restFiles['Deprecation-legacy-files.md'] = 'Deprecation-legacy-files.md';
            $restFiles['Deprecation-non-namespaced.md'] = 'Deprecation-non-namespaced.md';
        }
        return $restFiles[basename($path)];
    }

    /**
     * Extract the title from the rst file
     *
     * @param string $path
     * @return string
     */
    protected function extractTitleFromRstFile($path): string
    {
        static $restFileTitles = [
            // Special case for the ClassNamePatternMatcher Rules from v7
            3899088142 => 'Renamed TYPO3 core libraries', // key: crc32('Deprecation-legacy-files.md')
            3619663099 => 'Use of non-namespaced classes' // key: crc32('Deprecation-non-namespaced.md')
        ];
        $cacheKey = crc32($path);
        if (array_key_exists($cacheKey, $restFileTitles)) {
            return $restFileTitles[$cacheKey];
        }
        $result = '';
        $thisShouldBeTheHeader = false;
        $fileHandle = fopen($path, 'r');

        while (($line = fgets($fileHandle)) !== false) {
            if ($thisShouldBeTheHeader) {
                $result = trim($line);
                break;
            }
            if (strpos($line, '============') === 0) {
                $thisShouldBeTheHeader = true;
            }
        }
        $restFileTitles[$cacheKey] = $result;
        return $result;
    }

    /**
     * Find the real path to the online documentation
     * The Matchers sometimes include the version number of the TYPO3 version,
     * other times they don't. So we can't rely on the rst file path given in
     * the matcher.
     *
     * Instead we take the last part (basename) of the rst file and then look up
     * the file in the Changelog dir.
     *
     * @param $path
     * @return string
     */
    protected function findOnlineDocumentation($path): string
    {
        static $onlineDocumentationLinks = [
            // Special case for the ClassNamePatternMatcher Rules from v7
            1299497039 => 'https://gist.github.com/Tuurlijk/f857bf41e559ce3908290fb96d98b5e4', // key: crc32('/Deprecation-legacy-files.md')
            2959317077 => 'https://gist.github.com/Tuurlijk/79aba880880e6340ffd2720ff1c5b623' // key: crc32('/Deprecation-non-namespaced.md')
        ];
        $cacheKey = crc32($path);
        if (array_key_exists($cacheKey, $onlineDocumentationLinks)) {
            return $onlineDocumentationLinks[$cacheKey];
        }
        $onlineDocument = '';
        $base = 'https://docs.typo3.org/typo3cms/extensions/core/';
        $links = file(__DIR__ . '/../../Resources/Private/links.txt');
        $filename = basename($path);
        $filename = str_replace('.rst', '.html', $filename);
        foreach ($links as $link) {
            $link = rtrim($link);
            if (substr($link, -\strlen($filename)) === $filename) {
                $onlineDocument = $base . $link;
                break;
            }
        }
        $onlineDocumentationLinks[$cacheKey] = $onlineDocument;
        return $onlineDocument;
    }
}
