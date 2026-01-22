<?php
require_once __DIR__ . '/../db.php';

// Всегда возвращаем JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();
    if (!$db) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error'  => 'db_connection_failed'
        ]);
        exit;
    }

    // Читаем JSON из body
    $input = [];
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    // Если JSON пустой, пробуем $_POST
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    $token = trim($input['token'] ?? '');

    if ($token === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_token',
            'message' => 'Token is required'
        ]);
        exit;
    }

    $stmt = $db->prepare('SELECT list_open_rooms(:p_token)::json AS data');
    $stmt->execute([
        ':p_token' => $token,
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        // Функция может вернуть либо массив комнат, либо объект с ошибкой
        if (is_array($result)) {
            // Проверяем, является ли это массивом комнат или объектом с ошибкой
            if (isset($result['status']) && $result['status'] === 'error') {
                // Ошибка авторизации (объект с ошибкой)
                $errorCode = $result['error'] ?? 'list_failed';
                $httpCode = 401; // invalid_token
                
                http_response_code($httpCode);
                echo json_encode([
                    'status' => 'error',
                    'error'  => $errorCode,
                    'message' => $result['message'] ?? 'Failed to list rooms'
                ]);
            } else {
                // Массив комнат - возвращаем как есть
                echo $row['data'];
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

