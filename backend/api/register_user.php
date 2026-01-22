<?php
require_once __DIR__ . '/../db.php';

// Всегда возвращаем JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Подключение к базе
    $db = get_db();
    if (!$db) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error'  => 'db_connection_failed'
        ]);
        exit;
    }

    // Получаем JSON или обычный POST
    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $login    = trim($input['login'] ?? '');
    $password = trim($input['password'] ?? '');

    if ($login === '' || $password === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_parameters'
        ]);
        exit;
    }

    // Вызов SQL функции register_user
    $stmt = $db->prepare('SELECT register_user(:p_login, :p_password)::json AS data');
    $stmt->execute([
        ':p_login'    => $login,
        ':p_password' => $password
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] === 'success' && isset($result['token'])) {
                // Успешная регистрация и авторизация - возвращаем формат, который ожидает фронтенд
                echo json_encode([
                    'status' => 'ok',
                    'token'  => $result['token']
                ]);
            } else if ($result['status'] === 'error') {
                // Ошибка регистрации
                $errorMessage = $result['message'] ?? 'Registration failed';
                $errorCode = 'registration_failed';
                
                // Определяем код ошибки на основе сообщения
                if (strpos($errorMessage, 'Nickname taken') !== false) {
                    $errorCode = 'user_already_exists';
                    http_response_code(409); // Conflict
                } else if (strpos($errorMessage, 'Short nickname') !== false) {
                    $errorCode = 'short_nickname';
                    http_response_code(400);
                } else if (strpos($errorMessage, 'Short password') !== false) {
                    $errorCode = 'short_password';
                    http_response_code(400);
                } else {
                    http_response_code(400);
                }
                
                echo json_encode([
                    'status' => 'error',
                    'error'  => $errorCode,
                    'message' => $errorMessage
                ]);
            } else {
                // Неожиданный формат ответа
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'error'  => 'unexpected_response_format',
                    'message' => 'Unexpected response format from database'
                ]);
            }
        } else {
            // Неожиданный формат ответа
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'error'  => 'unexpected_response_format',
                'message' => 'Unexpected response format from database'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error'  => 'empty_response',
            'message' => 'No data returned from database'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'error'   => 'database_error',
        'message' => $e->getMessage(),
        'code'    => $e->getCode()
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'error'   => 'php_exception',
        'message' => $e->getMessage()
    ]);
    exit;
}

