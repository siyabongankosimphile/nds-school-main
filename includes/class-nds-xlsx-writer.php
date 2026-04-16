<?php
/**
 * Minimal XLSX writer (no Composer). Produces a multi-sheet .xlsx via ZipArchive + XML.
 * Uses shared strings and supports string/numeric/null cell values.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NDS_XLSX_Writer {

    /** @var string */
    protected $path;

    /** @var ZipArchive */
    protected $zip;

    /** @var array<int, string> */
    protected $shared_strings = [];

    /** @var array<string, int> */
    protected $shared_strings_index = [];

    /** @var array<int, array{name: string, rows: array<int, array<string|int, mixed>>}> */
    protected $sheets = [];

    /** @var string */
    protected static $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function __construct($output_path) {
        $this->path = $output_path;
        $this->zip = new ZipArchive();
        if ($this->zip->open($output_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create XLSX file: ' . $output_path);
        }
    }

    /**
     * Add a sheet. Name will be truncated to 31 chars (Excel limit).
     *
     * @param string $name Sheet name
     * @param array<int, array<string|int, mixed>> $rows First row = headers (keys used as column headers), then data rows
     */
    public function addSheet($name, array $rows) {
        $name = substr(preg_replace('/[\\\\\/\*\?\:\[\]]/', '', $name), 0, 31);
        $this->sheets[] = ['name' => $name, 'rows' => $rows];
    }

    /**
     * Get shared string index (add if new).
     */
    protected function getSharedStringIndex($value) {
        $s = (string) $value;
        if (!isset($this->shared_strings_index[$s])) {
            $idx = count($this->shared_strings);
            $this->shared_strings[] = $s;
            $this->shared_strings_index[$s] = $idx;
        }
        return $this->shared_strings_index[$s];
    }

    /**
     * Column index (0-based) to Excel column letter (A, B, ..., Z, AA, ...).
     */
    protected static function colLetter($colIndex) {
        $letter = '';
        $colIndex = (int) $colIndex;
        while ($colIndex >= 0) {
            $letter = chr(($colIndex % 26) + 65) . $letter;
            $colIndex = (int) floor($colIndex / 26) - 1;
        }
        return $letter;
    }

    /**
     * Write cell value: number => inline, string => shared string.
     */
    protected function cellValue($value) {
        if ($value === null || $value === '') {
            return ['t' => null, 'v' => ''];
        }
        if (is_numeric($value) && $value !== '' && $value !== null) {
            return ['t' => null, 'v' => is_float($value) || strpos((string)$value, '.') !== false ? (float) $value : (int) $value];
        }
        return ['t' => 's', 'v' => $this->getSharedStringIndex($value)];
    }

    /**
     * Build and write all parts, then close ZIP.
     */
    public function close() {
        $this->writeRels();
        $this->writeContentTypes();
        $this->writeSharedStrings();
        $this->writeStyles();
        $workbookRels = $this->writeSheets();
        $this->writeWorkbook($workbookRels);
        $this->zip->close();
    }

    protected function writeRels() {
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $this->zip->addFromString('_rels/.rels', $rels);
    }

    protected function writeContentTypes() {
        $parts = '';
        $n = count($this->sheets);
        for ($i = 1; $i <= $n; $i++) {
            $parts .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  ' . $parts . '
</Types>';
        $this->zip->addFromString('[Content_Types].xml', $ct);
    }

    protected function writeSharedStrings() {
        $count = count($this->shared_strings);
        $items = '';
        foreach ($this->shared_strings as $s) {
            $esc = htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $items .= '<si><t>' . $esc . '</t></si>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="' . self::$ns . '" count="' . $count . '" uniqueCount="' . $count . '">' . $items . '</sst>';
        $this->zip->addFromString('xl/sharedStrings.xml', $xml);
    }

    protected function writeStyles() {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="' . self::$ns . '">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
</styleSheet>';
        $this->zip->addFromString('xl/styles.xml', $xml);
    }

    /**
     * @return array<int, string> rId => target (sheet1.xml, ...)
     */
    protected function writeSheets() {
        $workbookRels = [];
        $rid = 1;
        $relsItems = '<Relationship Id="rId' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $rid++;
        $relsItems .= '<Relationship Id="rId' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        $rid++;

        foreach ($this->sheets as $sheetIndex => $sheet) {
            $sheetNum = $sheetIndex + 1;
            $rId = 'rId' . (++$rid);
            $workbookRels[] = $rId;
            $relsItems .= '<Relationship Id="' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNum . '.xml"/>';

            $rowsXml = '';
            $rows = $sheet['rows'];
            $headers = [];
            if (!empty($rows)) {
                $first = $rows[0];
                $headers = array_keys($first);
            }
            $rowNum = 1;
            // Header row
            if (!empty($headers)) {
                $cells = [];
                foreach ($headers as $colIndex => $h) {
                    $ref = self::colLetter($colIndex) . $rowNum;
                    $cv = $this->cellValue($h);
                    $tAttr = $cv['t'] ? ' t="' . $cv['t'] . '"' : '';
                    $cells[] = '<c r="' . $ref . '"' . $tAttr . '><v>' . htmlspecialchars((string)$cv['v'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</v></c>';
                }
                $rowsXml .= '<row r="' . $rowNum . '">' . implode('', $cells) . '</row>';
                $rowNum++;
            }
            // Data rows
            foreach ($rows as $row) {
                $cells = [];
                $values = array_values($row);
                foreach ($values as $colIndex => $v) {
                    $ref = self::colLetter($colIndex) . $rowNum;
                    $cv = $this->cellValue($v);
                    if ($cv['v'] !== '' || $cv['t'] !== null) {
                        $tAttr = $cv['t'] ? ' t="' . $cv['t'] . '"' : '';
                        $cells[] = '<c r="' . $ref . '"' . $tAttr . '><v>' . htmlspecialchars((string)$cv['v'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</v></c>';
                    }
                }
                $rowsXml .= '<row r="' . $rowNum . '">' . implode('', $cells) . '</row>';
                $rowNum++;
            }
            $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="' . self::$ns . '">
  <sheetData>' . $rowsXml . '</sheetData>
  <pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>
</worksheet>';
            $this->zip->addFromString('xl/worksheets/sheet' . $sheetNum . '.xml', $sheetXml);
        }

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relsItems . '</Relationships>';
        $this->zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        return $workbookRels;
    }

    protected function writeWorkbook(array $workbookRels) {
        $sheetsXml = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheetId = $i + 1;
            $rId = $workbookRels[$i];
            $name = htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $sheetsXml .= '<sheet name="' . $name . '" sheetId="' . $sheetId . '" r:id="' . $rId . '"/>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="' . self::$ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>' . $sheetsXml . '</sheets>
</workbook>';
        $this->zip->addFromString('xl/workbook.xml', $xml);
    }
}
