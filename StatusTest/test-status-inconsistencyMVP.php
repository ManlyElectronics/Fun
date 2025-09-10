<?php
// status_inconsistency.php
// Один файл: форма + расчёт. Сохраните в UTF-8 без BOM.

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$jobs = [
  'unemployed'         => ['label'=>'Безработный/вне рабочей силы','score'=>2],
  'laborer'            => ['label'=>'Подсобный рабочий, неквалифицированный труд','score'=>3],
  'service'            => ['label'=>'Сфера услуг/продажи','score'=>4],
  'clerical'           => ['label'=>'Офисный сотрудник/администратор','score'=>5],
  'skilled'            => ['label'=>'Квалифицированный рабочий (слесарь, электрик)','score'=>6],
  'technician'         => ['label'=>'Техник/оператор, ИТ/связь','score'=>7],
  'teacher_nurse'      => ['label'=>'Учитель/медсестра/соцработник','score'=>7],
  'engineer_it'        => ['label'=>'Инженер/разработчик ИТ','score'=>8],
  'manager'            => ['label'=>'Менеджер среднего звена','score'=>8],
  'executive'          => ['label'=>'Топ-менеджер/директор','score'=>9],
  'doctor_lawyer'      => ['label'=>'Врач/юрист/учёный','score'=>9],
  'entrepreneur_small' => ['label'=>'Предприниматель (мелкий бизнес)','score'=>7],
  'entrepreneur_medium'=> ['label'=>'Предприниматель (средний бизнес)','score'=>8],
  'entrepreneur_large' => ['label'=>'Предприниматель (крупный бизнес)','score'=>10],
];

$education_options = [
  1=>'Начальное (1–4 классы)',
  2=>'Неполное среднее (5–9)',
  3=>'Полное среднее (10–11)',
  4=>'Среднее проф./колледж',
  5=>'Высшее (бакалавр)',
  6=>'Высшее (магистр/специалист)',
  7=>'Аспирантура/докторская',
];

$income_options = [
  1=>'Нижние 25% по стране',
  2=>'25–50% (ниже среднего)',
  3=>'50–75% (средний)',
  4=>'75–90% (выше среднего)',
  5=>'90%+ (очень высокий)',
];

$submitted = ($_SERVER['REQUEST_METHOD']==='POST');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Status Inconsistency — мини-опросник</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:800px;margin:24px auto;padding:0 12px;line-height:1.4}
  h1{font-size:20px;margin:12px 0}
  fieldset{border:1px solid #ccc;border-radius:8px;margin:16px 0;padding:12px}
  label{display:block;margin:6px 0 2px}
  select, input[type=range]{width:100%;padding:8px;font-size:14px}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .btn{margin-top:12px;padding:10px 16px;font-size:15px;cursor:pointer}
  .result{border:2px solid #333;border-radius:8px;padding:12px;margin-top:16px}
  .muted{color:#555}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;background:#eee;margin-left:6px}
</style>
</head>
<body>
<h1>Анкета: статус и самооценка</h1>

<form method="post">
  <fieldset>
    <legend><strong>Образование</strong></legend>
    <label for="education">Наивысший завершённый уровень</label>
    <select id="education" name="education" required>
      <option value="" disabled selected>Выберите…</option>
      <?php foreach($education_options as $val=>$label): ?>
        <option value="<?= $val ?>" <?= $submitted && isset($_POST['education']) && $_POST['education']==$val?'selected':''; ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </fieldset>

  <fieldset>
    <legend><strong>Доход</strong></legend>
    <label for="income">Ваш среднемесячный доход (квантиль)</label>
    <select id="income" name="income" required>
      <option value="" disabled selected>Выберите…</option>
      <?php foreach($income_options as $val=>$label): ?>
        <option value="<?= $val ?>" <?= $submitted && isset($_POST['income']) && $_POST['income']==$val?'selected':''; ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </fieldset>

  <fieldset>
    <legend><strong>Профессия/занятие</strong></legend>
    <label for="job">Основное занятие</label>
    <select id="job" name="job" required>
      <option value="" disabled selected>Выберите…</option>
      <?php foreach($jobs as $key=>$j): ?>
        <option value="<?= h($key) ?>" <?= $submitted && isset($_POST['job']) && $_POST['job']===$key?'selected':''; ?>>
          <?= h($j['label']) ?> (оценка <?= $j['score'] ?>/10)
        </option>
      <?php endforeach; ?>
    </select>
    <p class="muted">При необходимости скорректируйте список и баллы в массиве <code>$jobs</code>.</p>
  </fieldset>

  <fieldset>
    <legend><strong>Субъективная самооценка</strong></legend>
    <label for="ladder">Лестница статуса: где вы? (1 низ — 10 верх)</label>
    <div class="row">
      <input type="range" id="ladder" name="ladder" min="1" max="10" step="1" value="<?= $submitted && isset($_POST['ladder'])? (int)$_POST['ladder'] : 5 ?>" oninput="document.getElementById('ladderOut').textContent=this.value;">
      <div><span class="pill">Выбор: <span id="ladderOut"><?= $submitted && isset($_POST['ladder'])? (int)$_POST['ladder'] : 5 ?></span>/10</span></div>
    </div>
  </fieldset>

  <button class="btn" type="submit">Посчитать</button>
</form>

<?php
if ($submitted) {
    $errors = [];
    $education = isset($_POST['education']) ? (int)$_POST['education'] : 0;   // 1..7
    $income    = isset($_POST['income'])    ? (int)$_POST['income']    : 0;   // 1..5
    $jobKey    = isset($_POST['job'])       ? (string)$_POST['job']    : '';
    $ladder    = isset($_POST['ladder'])    ? (int)$_POST['ladder']    : 0;   // 1..10

    if ($education<1 || $education>7) $errors[]='Некорректное образование.';
    if ($income<1 || $income>5)       $errors[]='Некорректный доход.';
    if (!isset($jobs[$jobKey]))       $errors[]='Некорректная профессия.';
    if ($ladder<1 || $ladder>10)      $errors[]='Некорректная самооценка.';

    if (!$errors) {
        // Нормировки к 1–10
        $edu10 = 1 + ($education - 1) * (9/6); // 1→1, 7→10
        $inc10 = 1 + ($income    - 1) * (9/4); // 1→1, 5→10
        $job10 = (float)$jobs[$jobKey]['score']; // уже 1–10

        $objective  = round(($edu10 + $inc10 + $job10) / 3, 1);
        $subjective = (float)$ladder;
        $diff       = round($subjective - $objective, 1);
        $absdiff    = abs($diff);

        if     ($absdiff <= 1.0) $level = 'низкая';
        elseif ($absdiff <= 2.0) $level = 'умеренная';
        else                     $level = 'высокая';

        $direction = $diff > 0 ? 'самооценка выше объективного уровня'
                    : ($diff < 0 ? 'самооценка ниже объективного уровня'
                                 : 'самооценка совпадает с объективным уровнем');

        echo '<div class="result">';
        echo '<div><strong>Результат</strong></div>';
        echo '<div>Объективный индекс: <strong>'.h($objective).'</strong> / 10</div>';
        echo '<div>Субъективная самооценка: <strong>'.h($subjective).'</strong> / 10</div>';
        echo '<div>Расхождение: <strong>'.($diff>0?'+':'').h($diff).'</strong> (' . h($direction) . ')</div>';
        echo '<div>Степень несогласованности: <strong>'.h($level).'</strong></div>';
        echo '<div class="muted" style="margin-top:8px">Весовые коэффициенты равные. При желании измените формулу усреднения или баллы профессий.</div>';
        echo '</div>';
    } else {
        echo '<div class="result"><strong>Ошибка:</strong> '.h(implode(' ', $errors)).'</div>';
    }
}
?>
</body>
</html>
