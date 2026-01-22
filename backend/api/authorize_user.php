<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();

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

    $login    = trim($input['login'] ?? '');
    $password = trim($input['password'] ?? '');

    if ($login === '' || $password === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_parameters',
            'message' => 'Login and password are required'
        ]);
        exit;
    }

    // Вызов SQL функции authorize_user
    $stmt = $db->prepare('SELECT authorize_user(:p_login, :p_password)::json AS data');
    $stmt->execute([
        ':p_login'    => $login,
        ':p_password' => $password,
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] === 'success' && isset($result['token'])) {
                // Успешная авторизация - возвращаем формат, который ожидает фронтенд
                echo json_encode([
                    'status' => 'ok',
                    'token'  => $result['token']
                ]);
            } else {
                // Ошибка авторизации
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'error'  => 'invalid_credentials',
                    'message' => $result['message'] ?? 'Invalid credentials'
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
        'message' => $e->getMessage(),
    ]);
    exit;
}

