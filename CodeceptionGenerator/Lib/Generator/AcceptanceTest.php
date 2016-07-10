<?php
namespace CodeceptionGenerator\Lib\Generator;

class AcceptanceTest
{
    private $domDocument;
    private $inputDir;
    private $outputDir;
    private $template = <<<EOF
<?php

class {{outputClassName}}Cest
{
    // TODO Please rename the following function name.
    public function xxxTest(\AcceptanceTester \$I)
    {
{{testCode}}
    }
}
EOF;

    /**
     * AcceptanceTest constructor.
     *
     * @param \DOMDocument $domDocument
     * @param string $inputDir
     * @param string $outputDir
     */
    public function __construct(\DOMDocument $domDocument, $inputDir = '', $outputDir = '')
    {
        $this->domDocument = $domDocument;
        $this->inputDir = !empty($inputDir) ? $inputDir : $this->getBaseDir() . 'input/';
        $this->outputDir = !empty($outputDir) ? $outputDir : $this->getBaseDir() . 'output/';
    }

    /**
     * Generate test codes and output to PHP files.
     *
     * @throws Exception
     */
    public function execute()
    {
        try {
            // Confirm directories.
            $this->confirmInputDir();
            $this->confirmOutputDir();

            // Get input file paths.
            $inputFilePaths = $this->getInputFilePaths();
            $this->confirmInputFilePaths($inputFilePaths);

            foreach ($inputFilePaths as $inputFilePath) {
                $inputFileName = $this->getInputFileName($inputFilePath);

                // Validate an input file name.
                $this->validateFileName($inputFileName);

                // Get an output class name and a file path.
                $outputClassName = $this->getOutputClassName($inputFileName);
                $outputFilePath = $this->getOutputFilePath($outputClassName);

                // Get HTML string.
                $htmlString = $this->getContentsFromFilePath($inputFilePath);

                // Convert HTML string to an array.
                $htmlArray = $this->convertHtmlToArray($htmlString);

                // Get a URL from HTML array.
                $url = $this->getUrl($htmlArray);

                // Get all rows from HTML array.
                $rows = $this->getRows($htmlArray);

                // Convert to a test code.
                $testCode = $this->getTestCode($outputClassName, $rows);

                // Output to a PHP file.
                $outputResult = file_put_contents($outputFilePath, $testCode);

                // Confirm output result
                $this->confirmOutputResult($outputResult, $outputFilePath);
            }
        } catch (\Exception $e) {
            echo $e->getMessage(), PHP_EOL;
        }
    }

    /**
     * Get a base directory
     *
     * @return string
     */
    private function getBaseDir()
    {
        return dirname(dirname(dirname(__DIR__))) . '/';
    }

    /**
     * Confirm an input directory.
     */
    private function confirmInputDir()
    {
        if (!file_exists($this->inputDir)) {
            throw new \Exception('There is not a directory of input files.');
        }
    }

    /**
     * Confirm an output directory. if it does not exists, create it.
     */
    private function confirmOutputDir()
    {
        if (!file_exists($this->outputDir)) {
            mkdir($this->outputDir, 0777);
        }
    }

    /**
     * Confirm count of input file paths.
     *
     * @param $inputFilePaths
     * @throws \Exception
     */
    private function confirmInputFilePaths($inputFilePaths)
    {
        if (count($inputFilePaths) === 0) {
            throw new \Exception('There are not any input files.');
        }
    }

    /**
     * Get an array of input file paths.
     *
     * @return array
     */
    private function getInputFilePaths()
    {
        return glob($this->inputDir . '*.html');
    }

    /**
     * Get an input file name.
     *
     * @param $inputFilePath
     * @return string
     */
    private function getInputFileName($inputFilePath)
    {
        return basename($inputFilePath);
    }

    /**
     * Validation of a file name.
     * A Security measure of NULL byte attack and directory travasal attack.
     *
     * @param $inputFileName
     * @throws Exception
     */
    private function validateFileName($inputFileName)
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]+\.html\z/', $inputFileName) === 1) {
            return;
        }

        throw new Exception('The file name is invalid. ' . $inputFileName);
    }

    /**
     * Get an output class name.
     *
     * @param $inputFileName
     * @return string
     */
    private function getOutputClassName($inputFileName)
    {
        $tmpOutputClassName = str_replace('.html', '', $inputFileName);

        return $this->camelize($tmpOutputClassName);
    }

    /**
     * Convert camel case.
     *
     * @param $string
     * @return string
     */
    private function camelize($string)
    {
        if (is_string($string) === false || strlen($string) === 0) {
            return '';
        }

        $string = str_replace(['_', '-'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        return $string;
    }

    /**
     * Get an output file path.
     *
     * @param $outputClassName
     * @return string
     */
    private function getOutputFilePath($outputClassName)
    {
        return $this->outputDir . $outputClassName . 'Cest.php';
    }

    /**
     * Get contents from a file path.
     *
     * @param $filePath
     * @return string
     * @throws Exception
     */
    private function getContentsFromFilePath($filePath)
    {
        $contents = file_get_contents($filePath);

        if ($contents !== false) {
            return $contents;
        }

        throw new Exception('Failed to get HTML\'s contents.');
    }

    /**
     * Convert html into an array.
     *
     * @param $htmlString
     * @return array
     */
    private function convertHtmlToArray($htmlString)
    {
        // A measure against garbling
        $htmlString = mb_convert_encoding($htmlString, 'HTML-ENTITIES', 'UTF-8');

        // Exclude some unnecessary strings
        $excludingString = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'
        ];

        $htmlString = str_replace($excludingString, '', $htmlString);

        // Convert HTML string into XML string
        $this->domDocument->loadHTML($htmlString);
        $xmlString = $this->domDocument->saveXML();
        $xmlString = str_replace('<br/>', '\n', $xmlString);

        // Convert XML string into XML object
        $xmlObject = simplexml_load_string($xmlString);

        // Convert XML object into an array
        $array = json_decode(json_encode($xmlObject), true);

        return $array;
    }

    /**
     * Get URL from HTML array.
     *
     * @param $array
     * @return string
     * @throws Exception
     */
    private function getUrl($array)
    {
        $exceptionMessage = 'There is not a url in the html file.';

        if (!isset($array['head'])) {
            throw new Exception($exceptionMessage);
        }

        if (!isset($array['head']['link'])) {
            throw new Exception($exceptionMessage);
        }

        if (!isset($array['head']['link']['@attributes'])) {
            throw new Exception($exceptionMessage);
        }

        if (!isset($array['head']['link']['@attributes']['href'])) {
            throw new Exception($exceptionMessage);
        }

        return $array['head']['link']['@attributes']['href'];
    }

    /**
     * Get all rows from HTML array.
     *
     * @param $array
     * @return array
     * @throws Exception
     */
    private function getRows($array)
    {
        $exceptionMessage = 'There is not a test case in the html file.';

        if (!isset($array['body'])) {
            throw new Exception($exceptionMessage);
        }

        if (!isset($array['body']['table'])) {
            throw new Exception($exceptionMessage);
        }

        if (!isset($array['body']['table']['tbody'])) {
            throw new Exception($exceptionMessage);
        }

        if (!isset($array['body']['table']['tbody']['tr'])) {
            throw new Exception($exceptionMessage);
        }

        return $array['body']['table']['tbody']['tr'];
    }

    /**
     * Get test code.
     *
     * @param $outputClassName
     * @param $rows
     * @return string
     */
    private function getTestCode($outputClassName, $rows)
    {
        $testCode = $this->convertTestCode($rows);
        $result = str_replace('{{outputClassName}}', $outputClassName, $this->template);
        $result = str_replace('{{testCode}}', $testCode, $result);

        return $result;
    }

    /**
     * Convert all rows to a test code.
     *
     * @param $rows
     * @return string
     */
    private function convertTestCode($rows)
    {
        $result = [];
        $indent = '        ';

        foreach ($rows as $tmpRow) {
            if (!isset($tmpRow['td'])) {
                continue;
            }

            $row = $tmpRow['td'];

            if (!isset($row[0]) || !isset($row[1]) || !isset($row[2])) {
                continue;
            }

            $command = $row[0];
            $target = ($command === 'open') ? $row[1] : $this->formatTarget($row[1]);
            $value = $this->formatValue($row[2]);

            if (strpos($target, '>') !== false) {
                $result[] = $indent . '// TODO X Path is deprecated.';
            }

            switch ($command) {
                case 'open':
                    $result[] = $indent . "\$I->amOnPage('" . $target . "');";
                    break;
                case 'click':
                    $result[] = $indent . "\$I->click('" . $target . "');";
                    break;
                case 'type':
                    $result[] = $indent . "\$I->fillField('" . $target . "', '" . $value . "');";
                    break;
                case 'select':
                    $result[] = $indent . "\$I->selectOption('" . $target . "', '" . $value . "');";
                    break;
                case 'clickAndWait':
                    $result[] = $indent . '// TODO Please refer to the following examples and implement "waiting".';
                    $result[] = $indent . "// \$I->waitForJS('return $.active == 0;', 60);";
                    $result[] = $indent . "// \$I->waitForText('foo', 30);";
                    $result[] = $indent . "\$I->click('" . $target . "');";
                    break;
            }
        }

        return implode(PHP_EOL, $result);
    }

    /**
     * Format target string.
     *
     * @param $target
     * @return string
     */
    private function formatTarget($target)
    {
        if (!is_string($target) || strlen($target) === 0) {
            return '';
        }

        if (strpos($target, 'id=') === 0) {
            return str_replace('id=', '#', $target);
        }

        if (strpos($target, 'class=') === 0) {
            return str_replace('class=', '.', $target);
        }

        if (strpos($target, 'css=') === 0) {
            return str_replace('css=', '', $target);
        }

        return $target;
    }

    /**
     * Format value string.
     *
     * @param $value
     * @return string
     */
    private function formatValue($value)
    {
        if (is_array($value) && count($value) === 0) {
            return '';
        }

        if (strpos($value, 'label=') === 0) {
            return str_replace('label=', '', $value);
        }

        return $value;
    }

    /**
     * Confirm output result.
     *
     * @param $outputResult
     * @param $outputFilePath
     * @throws \Exception
     */
    private function confirmOutputResult($outputResult, $outputFilePath)
    {
        if ($outputResult === false) {
            $message = 'This program failed to output the following file. ' . $outputFilePath;
            throw new \Exception($message);
        }
    }
}