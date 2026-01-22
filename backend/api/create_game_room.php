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

    $token        = trim($input['token'] ?? '');
    $playersCount = isset($input['players_count']) ? (int)$input['players_count'] : 0;
    $turnDuration = trim($input['turn_duration'] ?? '');

    // Валидация
    if ($token === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_token',
            'message' => 'Token is required'
        ]);
        exit;
    }

    // Базовая валидация на клиенте (полная валидация в SQL)
    if ($playersCount <= 0 || $turnDuration === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_parameters',
            'message' => 'players_count and turn_duration are required'
        ]);
        exit;
    }

    // Вызов SQL функции create_game_room
    $stmt = $db->prepare('
        SELECT create_game_room(
            :p_token,
            :p_players_count,
            :p_turn_duration
        )::json AS data
    ');

    $stmt->execute([
        ':p_token'         => $token,
        ':p_players_count' => $playersCount,
        ':p_turn_duration' => $turnDuration,
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] === 'ok') {
                // Успешное создание комнаты - возвращаем JSON как есть
                echo $row['data'];
                exit;
            } else {
                // Ошибка создания комнаты
                $errorCode = $result['error'] ?? 'creation_failed';
                $httpCode = 400;
                
                if ($errorCode === 'invalid_token') {
                    $httpCode = 401;
                } else if ($errorCode === 'invalid_players_count' || $errorCode === 'invalid_turn_duration') {
                    $httpCode = 400;
                }
                
                http_response_code($httpCode);
                echo json_encode([
                    'status' => 'error',
                    'error'  => $errorCode,
                    'message' => $result['message'] ?? 'Failed to create game room',
                    'allowed' => $result['allowed'] ?? null
                ]);
                exit;
            }
        } else {
            // Неожиданный формат ответа
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'error'  => 'unexpected_response_format',
                'message' => 'Unexpected response format from database'
            ]);
            exit;
        }
    } else {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'error'  => 'empty_response',
            'message' => 'No data returned from database'
        ]);
        exit;
    }

} catch (PDOException $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'error'   => 'database_error',
        'message' => $e->getMessage(),
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

