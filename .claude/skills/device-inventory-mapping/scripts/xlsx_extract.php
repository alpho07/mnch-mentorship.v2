<?php
/**
 * Extract dispatch tables from an .xlsx workbook without PhpSpreadsheet.
 *
 *   php xlsx_extract.php <workbook.xlsx> <out.json> [sheet-name-regex] [skip-sheet,skip-sheet]
 *
 * Looks on every matching sheet for a header row carrying Facility + DeviceSeq + Tag
 * and emits {county, facility, seq, tag, sheet} tuples. Cross-checks each sheet
 * against its own "Total Devices" cell and reports any shortfall.
 */
$xlsx = $argv[1] ?? exit("usage: xlsx_extract.php <workbook.xlsx> <out.json> [sheetRegex] [skipList]\n");
$out  = $argv[2] ?? exit("missing output path\n");
$rx   = $argv[3] ?? '/Summary$/i';
$skip = array_filter(explode(',', $argv[4] ?? ''));

$tmp = sys_get_temp_dir().'/xlsx_'.getmypid();
$zip = new ZipArchive();
if ($zip->open($xlsx) !== true) exit("cannot open $xlsx\n");
$zip->extractTo($tmp); $zip->close();

$ss = [];
if (is_file("$tmp/xl/sharedStrings.xml")) {
    $x = new XMLReader(); $x->open("$tmp/xl/sharedStrings.xml");
    while ($x->read()) if ($x->nodeType == XMLReader::ELEMENT && $x->name == 'si') {
        $n = simplexml_load_string($x->readOuterXml()); $t = '';
        foreach ($n->xpath('.//*[local-name()="t"]') as $tt) $t .= (string)$tt;
        $ss[] = $t;
    }
}
$colnum = function ($ref) { preg_match('/^([A-Z]+)/', $ref, $m); $n = 0;
    foreach (str_split($m[1]) as $c) $n = $n * 26 + ord($c) - 64; return $n; };

$wb   = simplexml_load_file("$tmp/xl/workbook.xml");
$rels = simplexml_load_file("$tmp/xl/_rels/workbook.xml.rels");
$map  = []; foreach ($rels->Relationship as $r) $map[(string)$r['Id']] = (string)$r['Target'];
$sheets = [];
foreach ($wb->sheets->sheet as $s) {
    $rid = (string)$s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
    $sheets[(string)$s['name']] = basename($map[$rid] ?? '', '.xml');
}

$grid = function ($file) use ($tmp, $ss, $colnum) {
    $g = []; $x = new XMLReader(); $x->open("$tmp/xl/worksheets/$file.xml");
    while ($x->read()) if ($x->nodeType == XMLReader::ELEMENT && $x->name == 'row') {
        $r = simplexml_load_string($x->readOuterXml()); $rn = (int)$r['r'];
        foreach ($r->c as $c) {
            $t = (string)$c['t']; $v = (string)$c->v;
            if ($t === 's') $v = $ss[(int)$v] ?? ''; elseif ($t === 'inlineStr') $v = (string)$c->is->t;
            $g[$rn][$colnum((string)$c['r'])] = trim($v);
        }
    }
    return $g;
};

$all = []; $report = [];
foreach ($sheets as $name => $file) {
    if (!preg_match($rx, $name) || in_array($name, $skip, true)) continue;
    $g = $grid($file);

    $declared = null;
    foreach ($g as $row) { foreach ($row as $ci => $v)
        if (preg_match('/^Total Devices/i', $v)) {
            for ($k = $ci + 1; $k <= $ci + 4; $k++)
                if (isset($row[$k]) && $row[$k] !== '') { $declared = (int)$row[$k]; break 3; } } }

    $hdr = null; $cols = [];
    foreach ($g as $rn => $row) {
        $low = array_map(fn($v) => strtolower(preg_replace('/\s+/', '', $v)), $row);
        // require facility too, or stray DeviceSeq/Tag labels in data rows win
        if (in_array('deviceseq', $low, true) && in_array('tag', $low, true) && in_array('facility', $low, true)) {
            $hdr = $rn; ksort($row);
            foreach ($row as $ci => $v) { $k = strtolower(preg_replace('/\s+/', '', $v));
                if ($k !== '' && !isset($cols[$k])) $cols[$k] = $ci; }   // FIRST occurrence wins
            break;
        }
    }
    if ($hdr === null) { $report[] = sprintf('%-26s NO DISPATCH TABLE', $name); continue; }

    $n = 0;
    for ($rn = $hdr + 1; $rn <= max(array_keys($g)); $rn++) {
        if (!isset($g[$rn])) continue;
        $fac = $g[$rn][$cols['facility']] ?? ''; $tag = $g[$rn][$cols['tag']] ?? '';
        if ($fac === '' && $tag === '') continue;
        if (strcasecmp($fac, 'total') === 0) continue;
        $all[] = ['county' => $g[$rn][$cols['county'] ?? -1] ?? '', 'facility' => $fac,
                  'seq' => $g[$rn][$cols['deviceseq'] ?? -1] ?? '', 'tag' => $tag, 'sheet' => $name];
        $n++;
    }
    $report[] = sprintf('%-26s %4d%s', $name, $n,
        ($declared !== null && $declared !== $n) ? "   <<< declared $declared, extracted $n" : '');
}
foreach ($report as $r) echo $r, "\n";
$fac = []; foreach ($all as $a) $fac[$a['facility']] = 1;
printf("\nTOTAL devices: %d   distinct facilities: %d\n", count($all), count($fac));
$tags = array_filter(array_column($all, 'tag'), fn($t) => $t !== '' && $t !== 'TBC');
$dup  = array_filter(array_count_values($tags), fn($n) => $n > 1);
if ($dup) printf("DUPLICATE TAGS: %s\n", implode(', ', array_keys($dup)));
printf("rows with blank/TBC tag: %d\n", count($all) - count($tags));
file_put_contents($out, json_encode($all, JSON_PRETTY_PRINT));
echo "wrote $out\n";
