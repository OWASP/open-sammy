<?php
/**
 * This is automatically generated file using the Codific Prototizer.
 *
 * PHP version 8
 *
 * @category PHP
 *
 * @author   CODIFIC <info@codific.com>
 *
 * @see     http://codific.com
 */

declare(strict_types=1);

namespace App\Service\Processing;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

abstract class ExcelImporter
{
    protected function loadPhpExcelObject(string $fileName): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($fileName);
        $reader->setReadDataOnly(true);

        return $reader->load($fileName);
    }
}
