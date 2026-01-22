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

    // Вызов SQL функции logout_user
    $stmt = $db->prepare('SELECT logout_user(:p_token)::json AS data');
    $stmt->execute([
        ':p_token' => $token,
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] === 'success') {
                // Успешный выход
                echo json_encode([
                    'status' => 'ok',
                    'message' => $result['message'] ?? 'Logged out successfully'
                ]);
            } else {
                // Ошибка выхода
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'error'  => 'invalid_token',
                    'message' => $result['message'] ?? 'Invalid token'
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

