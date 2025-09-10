<?php
// status_inconsistency.php — полный файл. UTF-8 без BOM.
// Использует два файла данных в ./data :
//   1) isei08.csv        (например: isco08_tempvar,isei08_tempvar)
//   2) occupations.csv   (например: Code,Label)
// Скрипт мёржит по коду ISCO и считает расхождение субъективного статуса и объективного индекса (ISEI).

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// --------- общие утилиты ---------
function detect_delim(string $line): string {
  $candidates = [",", "\t", ";", "|"];
  $best = ","; $max = -1;
  foreach($candidates as $d){ $n = substr_count($line, $d); if($n>$max){ $max=$n; $best=$d; } }
  return $best;
}
function clean_code($raw): string {
  $raw = trim((string)$raw);
  if ($raw==='') return '';
  // допускаем коды из 1–4 цифр, иногда встречаются группы "100", "1112"
  if (!preg_match('/^\d{1,4}$/', $raw)) {
    $raw = preg_replace('/\D/','', $raw);
  }
  return $raw;
}

// --------- загрузка ISEI (код -> isei) ---------
function load_isei(string $path): array {
  $out = [];
  if (!is_readable($path)) return $out;
  $lines = file($path, FILE_IGNORE_NEW_LINES);
  if (!$lines) return $out;

  // определить разделитель и наличие заголовка
  $first = ltrim($lines[0]);
  $delim = detect_delim($first);
  $hdr   = str_getcsv($first, $delim);
  $hasHeader = false;
  $map = ['code'=>0,'isei'=>1];

  $joined = strtolower(implode(' ', $hdr));
  if (preg_match('/isco|code|isei/', $joined)) {
    $hasHeader = true;
    $idx = array_change_key_case(array_flip($hdr), CASE_LOWER);
    $map['code'] = $idx['isco08_tempvar'] ?? $idx['isco08'] ?? $idx['isco'] ?? $idx['code'] ?? 0;
    $map['isei'] = $idx['isei08_tempvar'] ?? $idx['isei08'] ?? $idx['isei'] ?? 1;
  }

  for($i=$hasHeader?1:0; $i<count($lines); $i++){
    $line = trim($lines[$i]);
    if ($line==='') continue;
    if (preg_match('/^([,\t;| ]+)$/', $line)) continue;
    $row = str_getcsv($line, $delim);
    if (!$row) continue;

    $code = isset($row[$map['code']]) ? clean_code($row[$map['code']]) : '';
    if ($code==='' || $code==='0' || $code==='0000') continue;

    $isei = isset($row[$map['isei']]) && is_numeric($row[$map['isei']]) ? (float)$row[$map['isei']] : null;
    if ($isei===null) continue;

    $out['k'.$code] = $isei;
  }
  return $out;
}

// --------- загрузка названий профессий (код -> title) ---------
function load_titles(string $path): array {
  $out = [];
  if (!is_readable($path)) return $out;
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$lines) return $out;

  $first = $lines[0];
  $delim = detect_delim($first);
  $hdr = str_getcsv($first, $delim);
  $hasHeader = false;
  if (count($hdr)>=2 && preg_match('/^(code|isco|isco08)$/i',$hdr[0]) && preg_match('/^(title|label|name)$/i',$hdr[1])) {
    $hasHeader = true;
  }

  for($i=$hasHeader?1:0; $i<count($lines); $i++){
    $line = trim($lines[$i]);
    if ($line==='') continue;
    if (preg_match('/^([,\t;| ]+)$/', $line)) continue;

    $row = str_getcsv($line, $delim);
    $code=''; $title='';

    if (count($row)>=2) {
      $code  = clean_code($row[0]);
      // если в названии есть запятые и оно разрезалось — склеим хвост
      $title = trim(implode(' ', array_slice($row,1)));
    } else {
      // свободный текст: "1112 Senior Government Officials"
      if (preg_match('/^(\d{1,4})\s+(.+)$/u', $line, $m)) {
        $code = $m[1]; $title = trim($m[2]);
      } else {
        continue;
      }
    }

    if ($code==='' || $code==='0' || $code==='0000') continue;

    // пропустить групповки
    if (preg_match('/\b(MAJOR|SUB-?MAJOR|MINOR|UNIT)\s+GROUP\b/i', $title)) continue;
    if (preg_match('/\bGROUP\b/i', $title) && !preg_match('/\d/', $title)) continue;

    $out['k'.$code] = $title;
  }
  return $out;
}

// --------- нормировки ---------
function range_vals(array $values): array {
  if (!$values) return [0.0,1.0];
  return [min($values), max($values)];
}
function to10(float $v, float $min, float $max): float {
  return 1 + (($v - $min) * 9 / max(1e-9, $max - $min));
}

// ================== НАСТРОЙКА ПУТЕЙ ==================
$dir = __DIR__ . '/data';
$isei_map   = load_isei($dir.'/isei08.csv');        // код → ISEI
$title_map  = load_titles($dir.'/occupations.csv'); // код → название

// собрать единый справочник профессий
$jobs = [];
$keys = array_unique(array_merge(array_keys($title_map), array_keys($isei_map)));
foreach($keys as $k){
  $code = substr($k,1);
  $jobs[$k] = [
    'code'  => $code,
    'title' => $title_map[$k] ?? ('ISCO '.$code),
    'isei'  => $isei_map[$k]  ?? null
  ];
}
// если пусто — минимальный фолбэк, но лучше загрузить файлы
if (!$jobs){
  $jobs = [
    'k1112'=>['code'=>'1112','title'=>'Senior Government Officials','isei'=>86],
    'k2310'=>['code'=>'2310','title'=>'University and Higher Education Teachers','isei'=>71],
    'k5221'=>['code'=>'5221','title'=>'Shop Sales Assistants','isei'=>32],
  ];
}

// диапазон ISEI для нормировки
$isei_vals = array_values(array_filter(array_map(fn($j)=>$j['isei'], $jobs), 'is_numeric'));
list($ISEI_MIN,$ISEI_MAX) = range_vals($isei_vals);

// ================== ФОРМА ==================
$education_options=[1=>'Начальное',2=>'Неполное среднее',3=>'Полное среднее',4=>'Среднее проф./колледж',5=>'Высшее (бакалавр)',6=>'Высшее (магистр/специалист)',7=>'Аспирантура/докторская'];
$income_options=[1=>'Нижние 25%',2=>'25–50%',3=>'50–75%',4=>'75–90%',5=>'90%+'];
$submitted = ($_SERVER['REQUEST_METHOD']==='POST');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Status inconsistency — ISEI (ISCO-08)</title>
<style>
 body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:960px;margin:24px auto;padding:0 12px;line-height:1.45}
 fieldset{border:1px solid #ccc;border-radius:8px;margin:16px 0;padding:12px}
 select,input[type=range]{width:100%;padding:8px;font-size:14px}
 .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
 .result{border:2px solid #333;border-radius:8px;padding:12px;margin-top:16px}
 .muted{color:#666}
 table{width:100%;border-collapse:collapse;margin:8px 0}
 th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}
</style>
</head>
<body>
<h1>Анкета: статус и самооценка (ISEI)</h1>

<form method="post">
  <fieldset>
    <legend>Образование</legend>
    <select name="education" required>
      <option value="" disabled <?=!$submitted?'selected':''?>>Выберите…</option>
      <?php foreach($education_options as $v=>$l): ?>
        <option value="<?=$v?>" <?=($submitted && ($_POST['education']??'')==$v)?'selected':''?>><?=h($l)?></option>
      <?php endforeach; ?>
    </select>
  </fieldset>

  <fieldset>
    <legend>Доход</legend>
    <select name="income" required>
      <option value="" disabled <?=!$submitted?'selected':''?>>Выберите…</option>
      <?php foreach($income_options as $v=>$l): ?>
        <option value="<?=$v?>" <?=($submitted && ($_POST['income']??'')==$v)?'selected':''?>><?=h($l)?></option>
      <?php endforeach; ?>
    </select>
  </fieldset>

  <fieldset>
    <legend>Профессия (occupations.csv + isei08.csv)</legend>
    <select name="job" required>
      <option value="" disabled <?=!$submitted?'selected':''?>>Выберите…</option>
      <?php ksort($jobs,SORT_NATURAL); foreach($jobs as $k=>$j):
        $tagI = is_numeric($j['isei']) ? number_format($j['isei'],2) : '—';
      ?>
        <option value="<?=$k?>" <?=($submitted && ($_POST['job']??'')===$k)?'selected':''?>>
          <?=h($j['title'])?> [ISCO <?=$j['code']?>] (ISEI <?=$tagI?>)
        </option>
      <?php endforeach; ?>
    </select>
    <?php if(!$isei_vals): ?>
      <p class="muted">В isei08.csv нет ни одного числового значения ISEI. Проверьте файл.</p>
    <?php else: ?>
      <p class="muted">Нормировка ISEI по диапазону: <?=number_format($ISEI_MIN,2)?> … <?=number_format($ISEI_MAX,2)?></p>
    <?php endif; ?>
  </fieldset>

  <fieldset>
    <legend>Субъективная самооценка</legend>
    <div class="grid">
      <div><input type="range" name="ladder" min="1" max="10" value="<?= $submitted?(int)($_POST['ladder']??5):5 ?>" oninput="document.getElementById('ladOut').textContent=this.value;"></div>
      <div><span class="muted">Выбор: <b id="ladOut"><?= $submitted?(int)($_POST['ladder']??5):5 ?></b>/10</span></div>
    </div>
  </fieldset>

  <button type="submit">Посчитать</button>
</form>

<?php
if($submitted){
  $errors=[];
  $edu=(int)($_POST['education']??0);
  $inc=(int)($_POST['income']??0);
  $lad=(int)($_POST['ladder']??0);
  $jobKey=$_POST['job']??'';
  if($edu<1||$edu>7) $errors[]='Образование некорректно.';
  if($inc<1||$inc>5) $errors[]='Доход некорректен.';
  if($lad<1||$lad>10) $errors[]='Самооценка некорректна.';
  if(!isset($jobs[$jobKey])) $errors[]='Профессия не выбрана.';

  if(!$errors){
    $job=$jobs[$jobKey];
    $edu10 = 1 + ($edu-1)*(9/6);
    $inc10 = 1 + ($inc-1)*(9/4);

    echo '<div class="result">';
    echo '<div><b>Результат</b></div>';
    echo '<div>Профессия: <b>'.h($job['title']).'</b> [ISCO '.h($job['code']).']</div>';

    if (is_numeric($job['isei']) && $isei_vals){
      $occ10 = to10((float)$job['isei'], $ISEI_MIN,$ISEI_MAX);
      $objective = round( ($edu10+$inc10+$occ10)/3, 3 );
      $diff = round($lad - $objective, 3);
      $lvl = (abs($diff)<=1?'низкая':(abs($diff)<=2?'умеренная':'высокая'));

      echo '<table><thead><tr><th>Шкала</th><th>Исходное</th><th>Нормировано</th><th>Объективный</th><th>Субъективный</th><th>Расхождение</th><th>Несогласованность</th></tr></thead><tbody>';
      echo '<tr>';
      echo '<td>ISEI</td>';
      echo '<td>'.number_format($job['isei'],2).'</td>';
      echo '<td>'.number_format($occ10,3).'</td>';
      echo '<td>'.number_format($objective,3).'</td>';
      echo '<td>'.number_format($lad,3).'</td>';
      echo '<td>'.($diff>0?'+':'').number_format($diff,3).'</td>';
      echo '<td>'.$lvl.'</td>';
      echo '</tr></tbody></table>';
      echo '<div class="muted">Компоненты усреднены поровну.</div>';
    } else {
      echo '<p class="muted">Для выбранной профессии нет ISEI в isei08.csv. Расчёт невозможен.</p>';
    }
    echo '</div>';

  } else {
    echo '<div class="result"><b>Ошибка:</b> '.h(implode(' ',$errors)).'</div>';
  }
}
?>
</body>
</html>
