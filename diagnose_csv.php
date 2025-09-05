<?php
// Настройки для корректного отображения ошибок и кириллицы
header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>🕵️‍♂️ Диагностика CSV-файла</h1>";

$csvFile = 'Module 2 FINAL(1).csv';

if (!file_exists($csvFile)) {
    die("<p style='color:red;'><strong>ОШИБКА:</strong> Файл не найден: " . htmlspecialchars($csvFile) . "</p>");
}

echo "<p>Открываем файл: <strong>" . htmlspecialchars($csvFile) . "</strong></p>";
echo "<p>Используем разделитель: <strong>;</strong> (точка с запятой)</p>";
echo "<hr>";

if (($handle = fopen($csvFile, "r")) !== FALSE) {
    // Читаем заголовки
    $header = fgetcsv($handle, 0, ";");
    echo "<h2>Заголовки (первая строка):</h2>";
    echo "<p>Найдено колонок в заголовке: <strong>" . count($header) . "</strong></p>";
    echo "<pre>";
    print_r($header);
    echo "</pre>";
    echo "<p>Мы ожидаем найти текст объяснения в 11-й колонке (технический индекс 10).</p>";
    echo "<hr>";

    $rowNumber = 1;
    // Читаем и анализируем первые 5 строк с данными
    while (($data = fgetcsv($handle, 0, ";")) !== FALSE && $rowNumber <= 5) {
        echo "<h3>Анализ строки данных №" . $rowNumber . "</h3>";
        echo "<p>Найдено колонок в этой строке: <strong>" . count($data) . "</strong></p>";
        
        echo "<h4>Полное содержимое строки в виде массива:</h4>";
        echo "<pre>";
        var_dump($data); // var_dump даёт больше деталей, чем print_r
        echo "</pre>";

        echo "<h4>Проверка 11-го элемента (индекс 10):</h4>";
        if (isset($data[10])) {
            echo "<p>Элемент \$data[10] <strong>существует</strong>.</p>";
            $trimmedContent = trim($data[10]);
            
            echo "<p>Его содержимое: <code>\"" . htmlspecialchars($trimmedContent) . "\"</code></p>";

            if (empty($trimmedContent)) {
                echo "<p style='color:red;'><strong>Вывод:</strong> Элемент пустой. Именно поэтому данные не записываются.</p>";
            } else {
                echo "<p style='color:green;'><strong>Вывод:</strong> Элемент НЕ пустой. С этой строкой всё должно было сработать.</p>";
            }
        } else {
            echo "<p style='color:red;'><strong>Вывод:</strong> Элемент \$data[10] <strong>НЕ СУЩЕСТВУЕТ</strong>. Это значит, что в строке меньше 11 колонок, и проблема, скорее всего, в неправильном разделителе.</p>";
        }
        echo "<hr>";
        $rowNumber++;
    }
    fclose($handle);
} else {
    echo "<p style='color:red;'><strong>ОШИБКА:</strong> Не удалось открыть CSV-файл для чтения.</p>";
}
?>
