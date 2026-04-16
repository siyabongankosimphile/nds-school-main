<?php
/**
 * Simple XLSX reader using ZipArchive + XML (no Composer required).
 * Reads sheet names and row data; supports shared strings and numeric values.
 */

if (!defined('ABSPATH')) {
    exit;
}

class NDS_XLSX_Reader {

    /** @var string */
    protected $path;

    /** @var ZipArchive */
    protected $zip;

    /** @var array<int, string> Shared strings from xl/sharedStrings.xml */
    protected $shared_strings = [];

    /** @var array Sheet index => sheet name */
    protected $sheet_names = [];

    public function __construct($xlsx_path) {
        $this->path = $xlsx_path;
        if (!file_exists($xlsx_path) || !is_readable($xlsx_path)) {
            throw new InvalidArgumentException('XLSX file not found or not readable: ' . $xlsx_path);
        }
        $this->zip = new ZipArchive();
        if ($this->zip->open($xlsx_path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Cannot open XLSX as ZIP: ' . $xlsx_path);
        }
        $this->loadSharedStrings();
        $this->loadSheetNames();
    }

    public function __destruct() {
        if ($this->zip) {
            $this->zip->close();
        }
    }

    /**
     * Load shared strings from xl/sharedStrings.xml
     */
    protected function loadSharedStrings() {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sxe = @simplexml_load_string($xml);
        if ($sxe === false) {
            return;
        }
        $sxe->registerXPathNamespace('s', $ns);
        $nodes = $sxe->xpath('//s:si');
        if ($nodes === false) {
            $nodes = $sxe->xpath('//*[local-name()="si"]');
        }
        $nodes = $nodes ?: [];
        foreach ($nodes as $si) {
            $si->registerXPathNamespace('s', $ns);
            $tNodes = $si->xpath('.//*[local-name()="t"]');
            if ($tNodes === false || empty($tNodes)) {
                $tNodes = $si->xpath('.//s:t');
            }
            $tNodes = $tNodes ?: [];
            $text = '';
            foreach ($tNodes as $t) {
                $text .= (string) $t;
            }
            $this->shared_strings[] = $text;
        }
    }

    /**
     * Load sheet names from xl/workbook.xml
     */
    protected function loadSheetNames() {
        $xml = $this->zip->getFromName('xl/workbook.xml');
        if ($xml === false) {
            return;
        }
        $sxe = @simplexml_load_string($xml);
        if ($sxe === false) {
            return;
        }
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sxe->registerXPathNamespace('s', $ns);
        $sheets = $sxe->xpath('//s:sheet');
        if ($sheets === false) {
            $sheets = $sxe->xpath('//*[local-name()="sheet"]');
        }
        $sheets = $sheets ?: [];
        foreach ($sheets as $sheet) {
            $this->sheet_names[] = (string) $sheet['name'];
        }
    }

    /**
     * @return array<int, string> Sheet index (0-based) => sheet name
     */
    public function getSheetNames() {
        return $this->sheet_names;
    }

    /**
     * Get all rows from a sheet (0-based index). First row is headers.
     *
     * @param int $sheet_index 0-based sheet index
     * @return array{headers: array<int, string>, rows: array<int, array<string, mixed>>}
     */
    public function getSheetData($sheet_index) {
        $sheet_file = 'xl/worksheets/sheet' . ($sheet_index + 1) . '.xml';
        $xml = $this->zip->getFromName($sheet_file);
        if ($xml === false) {
            return ['headers' => [], 'rows' => []];
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sxe = @simplexml_load_string($xml);
        if ($sxe === false) {
            return ['headers' => [], 'rows' => []];
        }

        $sxe->registerXPathNamespace('s', $ns);
        $rowNodes = $sxe->xpath('//*[local-name()="row"]');
        if ($rowNodes === false || empty($rowNodes)) {
            $rowNodes = $sxe->xpath('//s:row');
        }
        $rowNodes = $rowNodes ?: [];

        $headers = [];
        $rows = [];

        foreach ($rowNodes as $rowIdx => $row) {
            $rowNum = (int) $row['r'];
            $cells = [];
            $row->registerXPathNamespace('s', $ns);
            $cellNodes = $row->xpath('.//*[local-name()="c"]');
            if ($cellNodes === false || empty($cellNodes)) {
                $cellNodes = $row->xpath('.//s:c');
            }
            $cellNodes = $cellNodes ?: [];
            foreach ($cellNodes as $c) {
                $ref = (string) $c['r'];
                $vNode = $c->xpath('.//*[local-name()="v"]');
                $v = ($vNode && count($vNode)) ? (string) $vNode[0] : '';
                $t = (string) ($c['t'] ?? '');
                $value = $v;
                if ($t === 's' && $v !== '' && is_numeric($v)) {
                    $idx = (int) $v;
                    $value = $this->shared_strings[$idx] ?? $v;
                } elseif ($v !== '' && is_numeric($v)) {
                    $value = strpos($v, '.') !== false ? (float) $v : (int) $v;
                }
                $cells[$ref] = $value;
            }

            if ($rowNum === 1) {
                $headers = $this->orderCellsToRow($cells);
            } else {
                $ordered = $this->orderCellsToRow($cells);
                $rowAssoc = [];
                foreach ($headers as $colIdx => $h) {
                    $rowAssoc[$h] = $ordered[$colIdx] ?? null;
                }
                $rows[] = $rowAssoc;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Convert cell map (A1=>x, B1=>y) to ordered row by column letter (A, B, ..., Z, AA, AB...).
     */
    protected function orderCellsToRow(array $cells) {
        $colToVal = [];
        foreach ($cells as $ref => $val) {
            $col = preg_replace('/[0-9]+/', '', $ref);
            $colToVal[$col] = $val;
        }
        uksort($colToVal, function ($a, $b) {
            return $this->colLetterToIndex($a) <=> $this->colLetterToIndex($b);
        });
        return array_values($colToVal);
    }

    /** Column letter to 0-based index: A=0, Z=25, AA=26 */
    protected function colLetterToIndex($col) {
        $idx = 0;
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $idx - 1;
    }
}
