<!DOCTYPE html>
<html>
<head>
  <title>Навигатор по корпусу L</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" >
  <link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>

<h2>Навигатор по корпусу L <sup>beta</sup></h2>

<?

// Функция считаывает из файла блоки чисел до появления пустой строки
// f -- файл, cnt -- количество блоков, size -- размер блока
function read_blocks($f, &$cnt, &$size)
{
  $cnt = 0;
  while (true) {
    $s = fgets($f);
    if (trim($s) == "")
      break;
    $nums = explode(" ", $s);
    $size[$cnt] = count($nums);
    for ($i = 0; $i < count($nums); ++$i) {
      $block[$cnt][$i] = (int)$nums[$i];
    }
    ++$cnt;
  }
  return $block;
}

if (isset($_GET["from"])) {
  $from = $_GET["from"];
  $to = $_GET["to"];
  echo "<hr/>\n";
  echo "<i>"."Маршрут от ".$from." до ".$to."</i>\n";

  /*
  Исходные данные в файле data.txt:
  - блоки аудиторий (каждая аудитория принадлежит одному блоку,
    блок -- это аудитории, между которыми можно пройти, не открывая дверей)
  - пустая строка
  - пары находящихся рядом аудиторий из смежных блоков
  - пустая строка
  - списки аудиторий возле каждой лестницы
  - пустая строка
  - списки аудиторий возле каждого лифта

  Структура данных:
  block[i][j] -- j-я аудитория в i-м блоке
  blocks_cnt -- количество блоков
  block_size[i] -- количество аудиторий в i-м блоке
  Аналогично с stair и lift
  pairs_cnt -- количество пар аудиторий из смежных блоков
  pair_a[i], pair_b[i] -- номера аудиторий из смежных блоков
  */

  $f = fopen("data.txt", "r");

  // Читаем из файла блоки аудиторий
  $block = read_blocks($f, $blocks_cnt, $block_size);

  // Читаем из файла пары аудиторий из смежных блоков
  $pairs_cnt = 0;
  while (true) {
    $s = fgets($f);
    if (trim($s) == "")
      break;
    $nums = explode(" ", $s);
    $pair_a[$pairs_cnt] = (int)$nums[0];
    $pair_b[$pairs_cnt] = (int)$nums[1];
    ++$pairs_cnt;
    $pair_a[$pairs_cnt] = (int)$nums[1];
    $pair_b[$pairs_cnt] = (int)$nums[0];
    ++$pairs_cnt;
  }

  // Читаем из файла аудитории вдоль лестниц
  $stair = read_blocks($f, $stairs_cnt, $stair_size);

  // Читаем из файла аудитории вдоль лифтов
  $lift = read_blocks($f, $lifts_cnt, $lift_size);

  fclose($f);

  /*
    Структура данных:
    N -- число вершин в графе (вершина в графе -- это аудитория)
    room[i] -- номер аудитории, соответствующий i-й вершине графа
    e_cnt[i] -- количество рёбер, идущих из i-й вершины (i = 0, ..., N-1)
    e[i][j] -- рёбра графа (список рёбер, идущих из i-й вершины)
    e_type[i][j] -- тип ребра (1 -- аудитории в одном блоке, 2 -- между блоками,
      3 -- по лестнице, 4 -- на лифте)
    room_index[room] -- индекс аудитории room в графе
  */

  // Формируем список вершин графа, исходя из блоков аудиторий
  // (предполагаем, что в списках каждая аудитория встречается только один раз)
  $N = 0;
  for ($i = 0; $i < $blocks_cnt; ++$i) {
    for ($j = 0; $j < $block_size[$i]; ++$j) {
      $room[$N] = $block[$i][$j];
      $room_index[$room[$N]] = $N;
      ++$N;
    }
  }

  // Инициализация списков рёбер
  for ($i = 0; $i < $N; ++$i)
    $e_cnt[$i] = 0;

  // Добавляем рёбра между вершинами одного блока
  for ($i = 0; $i < $blocks_cnt; ++$i) {
    for ($j = 0; $j < $block_size[$i]; ++$j) {
      $room_j = $room_index[$block[$i][$j]];
      for ($k = 0; $k < $block_size[$i]; ++$k) {
        if ($k != $j) {
          $room_k = $room_index[$block[$i][$k]];
          $e[$room_k][$e_cnt[$room_k]] = $room_j;
          $e_type[$room_k][$e_cnt[$room_k]] = 1;
          ++$e_cnt[$room_k];
        }
      }
    }
  }

  // Добавляем рёбра между крайними вершинами смежных блоков
  for ($i = 0; $i < $pairs_cnt; ++$i) {
    $index_a = $room_index[$pair_a[$i]];
    $index_b = $room_index[$pair_b[$i]];
    $e[$index_a][$e_cnt[$index_a]] = $index_b;
    $e_type[$index_a][$e_cnt[$index_a]] = 2;
    ++$e_cnt[$index_a];
  }

  // Добавляем рёбра между вершинами возле лестниц
  // (аналогично добавлению рёбер между вершинами одного блока)
  for ($i = 0; $i < $stairs_cnt; ++$i) {
    for ($j = 0; $j < $stair_size[$i]; ++$j) {
      $room_j = $room_index[$stair[$i][$j]];
      for ($k = 0; $k < $block_size[$i]; ++$k) {
        if ($k != $j) {
          $room_k = $room_index[$stair[$i][$k]];
          $e[$room_j][$e_cnt[$room_j]] = $room_k;
          $e_type[$room_j][$e_cnt[$room_j]] = 3;
          ++$e_cnt[$room_j];
        }
      }
    }
  }

  // Добавляем рёбра между вершинами возле лифтов
  // (аналогично добавлению рёбер между вершинами одного блока)
  for ($i = 0; $i < $lifts_cnt; ++$i) {
    for ($j = 0; $j < $lift_size[$i]; ++$j) {
      $room_j = $room_index[$lift[$i][$j]];
      for ($k = 0; $k < $block_size[$i]; ++$k) {
        if ($k != $j) {
          $room_k = $room_index[$lift[$i][$k]];
          $e[$room_j][$e_cnt[$room_j]] = $room_k;
          $e_type[$room_j][$e_cnt[$room_j]] = 4;
          ++$e_cnt[$room_j];
        }
      }
    }
  }

  // Алгоритм Дейкстры поиска кратчайшего пути в графе
  $INF = 1000000;
  $first = $room_index[$from];
  for ($i = 0; $i < $N; ++$i) {
    $d[$i] = $INF;
    $found[$i] = false;
  }
  $d[$first] = 0;
  for ($i = 0; $i < $N; ++$i) {
    $min_d = $INF + 1;
    $j_min = -1;
    for ($j = 0; $j < $N; ++$j) {
      if (!$found[$j] && $d[$j] < $min_d) {
        $min_d = $d[$j];
        $j_min = $j;
      }
    }
    $found[$j_min] = true;
    for ($k = 0; $k < $e_cnt[$j_min]; ++$k) {
      $v = $e[$j_min][$k];
      if ($d[$v] > $d[$j_min] + 1) {
        $d[$v] = $d[$j_min] + 1;
        $p[$v] = $j_min;
        $p_e[$v] = $k;
      }
    }
  }

  $last = $room_index[$to];
  if ($d[$last] == $INF) {
    echo "<p>"."Маршрут не найден."."</p>";
  }
  else {
    $path_cnt = 1;
    $path[0] = $last;
    $v = $last;
    while ($v != $first) {
      $path[$path_cnt] = $p[$v];
      $path_e[$path_cnt] = $p_e[$v];
      ++$path_cnt;
      $v = $p[$v];
    }
    echo "<ol type=\"a\">\n";
    for ($i = $path_cnt - 1; $i > 0; --$i) {
      $v = $path[$i];
      $edge_index = $path_e[$i];
      $edge_type = $e_type[$v][$edge_index];
      $new_room = $room[$e[$v][$edge_index]];
      echo "<li>";
      if ($edge_type == 1) {
        echo "Пройдите к аудитории ".$new_room." в том же блоке.";
      }
      else if ($edge_type == 2) {
        echo "Пройдите к аудитории ".$new_room.", находящейся рядом в соседнем блоке.";
      }
      else if ($edge_type == 3) {
        echo "Пройдите к аудитории ".$new_room." по лестнице.";
      }
      else if ($edge_type == 4) {
        echo "Пройдите к аудитории ".$new_room." на лифте.";
      }
      echo "</li>\n";
    }
    echo "</ol>\n";
  }
  echo "<hr/>\n";
}

?>

<form name="frm" action="index.php" method="GET">
<center>
Откуда: <input type="text" size="8" name="from" />
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
Куда: <input type="text" size="8" name="to" />
<br/><br/>
<input type="submit" value="Маршрут" />
</center>
</form>

<small>
<p>
<i>Инструкция</i><br/>
Введите в каждое поле ввода одно из:
</p>
<ul>
<li>номер аудитории (без буквы L)
<li>1 – вход/выход, ближайший к трассе</li>
<li>2 – вход/выход с противоположной стороны корпуса</li>
</ul>
</small>

<p><a href="/feedback" target="_blank">Отзывы и предложения</a></p>
<p><a href="http://github.com/grenkin/fefu-lab" target="_blank">Разработка</p>

</body>
</html>
