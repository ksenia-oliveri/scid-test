<?php
class DatabaseSync {
    private $testDb;  // Соединение с тестовой базой данных 
    private $mainDb;  // Соединение с главной базой данных 

    // Конструктор класса. Принимает параметры подключения к двум базам данных
    public function __construct($testDbConfig, $mainDbConfig) {
        // Подключение к тестовой БД 
        $this->testDb = new mysqli($testDbConfig['host'], $testDbConfig['user_name'], $testDbConfig['password'], $testDbConfig['database_name']);
        if ($this->testDb->connect_errno) {
            echo "Ошибка подключения к БД: " . $this->testDb->connect_error;
            exit();
        }

        // Подключение к основной БД 
        $this->mainDb = new mysqli($mainDbConfig['host'], $mainDbConfig['user_name'], $mainDbConfig['password'], $mainDbConfig['database_name']);
        if ($this->mainDb->connect_errno) {
            echo "Ошибка подключения к БД: " . $this->mainDb->connect_error;
            exit();
        }
    }

    // Метод для синхронизации таблиц
    public function syncTable($tableName, $uniqueKey) {
        // Получение всех строк из тестовой таблицы 
        $sql = "SELECT * FROM $tableName";
        $result1 = $this->testDb->query($sql);

        // Проверка наличия ошибок при выполнении запроса
        if (!$result1) {
            echo "Ошибка выполнения запроса к первой БД: " . $this->testDb->error;
            exit();
        }

        // Обработка каждой строки из образца
        while ($row = $result1->fetch_assoc()) {
            $this->syncRow($tableName, $row, $uniqueKey);
        }
    }

    // Метод для синхронизации отдельной строки
    private function syncRow($tableName, $rowData, $uniqueKey) {
        // Проверка существования строки во второй базе
        $uniqueValue = $rowData[$uniqueKey];
        $sql = "SELECT * FROM $tableName WHERE $uniqueKey = '$uniqueValue'";
        $result2 = $this->mainDb->query($sql);

        if ($result2->num_rows > 0) {
            // Если запись существует, обновляем её
            $updateFields = [];
            while ($existingRow = $result2->fetch_assoc()) {
                foreach ($rowData as $column => $value) {
                    if ($existingRow[$column] != $value) {
                        $updateFields[] = "$column = '" . $this->mainDb->real_escape_string($value) . "'";
                    }
                }
            }

            // Выполнение запроса на обновление, если есть поля для обновления
            if (count($updateFields) > 0) {
                $updateQuery = "UPDATE $tableName SET " . implode(', ', $updateFields) . " WHERE $uniqueKey = '$uniqueValue'";
                $this->mainDb->query($updateQuery);
            }
        } else {
            // Если записи нет, вставляем новую строку
            $columns = implode(", ", array_keys($rowData));
            $values = implode("', '", array_map([$this->mainDb, 'real_escape_string'], array_values($rowData)));
            $insertQuery = "INSERT INTO $tableName ($columns) VALUES ('$values')";
            $this->mainDb->query($insertQuery);
        }
    }

    // Закрываем соединения с базами данных
    public function closeConnections() {
        $this->testDb->close();
        $this->mainDb->close();
    }
}

// Пример использования
$testDbConfig = [
    'host' => 'localhost',
    'user_name' => 'user1',
    'password' => 'password1',
    'database_name' => 'test_db'
];

$mainDbConfig = [
    'host' => 'localhost',
    'user_name' => 'user2',
    'password' => 'password2',
    'database_name' => 'main_db'
];

$sync = new DatabaseSync($testDbConfig, $mainDbConfig);
$sync->syncTable('table_name', 'id'); // Укажите имя таблицы и уникальный ключ (например, 'id')
$sync->closeConnections();
