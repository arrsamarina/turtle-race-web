<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();

    $input = $_POST;
    if (empty($input)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $token   = trim($input['token'] ?? '');
    $game_id = isset($input['game_id']) ? (int)$input['game_id'] : 0;

    if ($token === '' || $game_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_parameters',
            'message' => 'Token and game_id are required'
        ]);
        exit;
    }

    // Вызов SQL функции join_game_room
    $stmt = $db->prepare('SELECT join_game_room(:p_token, :p_game_id)::json AS data');
    $stmt->execute([
        ':p_token'   => $token,
        ':p_game_id' => $game_id,
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] === 'error') {
                // Ошибка присоединения
                $errorCode = $result['error'] ?? 'join_failed';
                $httpCode = 400;
                
                // Определяем HTTP код на основе типа ошибки
                if ($errorCode === 'invalid_token') {
                    $httpCode = 401;
                } else if ($errorCode === 'game_not_found') {
                    $httpCode = 404;
                } else if ($errorCode === 'game_already_finished') {
                    $httpCode = 410; // Gone
                } else if ($errorCode === 'game_already_started') {
                    $httpCode = 409; // Conflict
                } else if ($errorCode === 'room_is_full') {
                    $httpCode = 409; // Conflict
                }
                
                http_response_code($httpCode);
                echo json_encode([
                    'status' => 'error',
                    'error'  => $errorCode,
                    'message' => $result['message'] ?? 'Failed to join game room',
                    'id_game' => $result['id_game'] ?? null,
                    'players_now' => $result['players_now'] ?? null,
                    'players_max' => $result['players_max'] ?? null
                ]);
            } else {
                // Успешное присоединение или уже в комнате - возвращаем состояние игры
                // Это может быть результат get_game_state_json
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
        'message' => $e->getMessage(),
    ]);
    exit;
}

