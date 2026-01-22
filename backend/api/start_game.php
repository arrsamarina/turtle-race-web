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

    // Проверка токена для авторизации (но не передается в SQL функцию)
    if ($token === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_token',
            'message' => 'Token is required'
        ]);
        exit;
    }

    if ($game_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_parameters',
            'message' => 'game_id is required'
        ]);
        exit;
    }

    // Вызов SQL функции start_game (без токена, только game_id)
    $stmt = $db->prepare('SELECT start_game(:p_game_id)::json AS data');
    $stmt->execute([
        ':p_game_id' => $game_id,
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] === 'started') {
                // Успешный старт игры - возвращаем JSON как есть
                echo $row['data'];
            } else {
                // Ошибка старта игры
                $errorCode = $result['error'] ?? 'start_failed';
                $httpCode = 400;
                
                // Определяем HTTP код на основе типа ошибки
                if ($errorCode === 'game_not_found') {
                    $httpCode = 404;
                } else if ($errorCode === 'game_already_finished') {
                    $httpCode = 410; // Gone
                } else if ($errorCode === 'game_already_started') {
                    $httpCode = 409; // Conflict
                } else if ($errorCode === 'room_not_full') {
                    $httpCode = 409; // Conflict
                } else if ($errorCode === 'not_enough_cards_in_deck') {
                    $httpCode = 500; // Server error
                } else if ($errorCode === 'no_turtles_for_game') {
                    $httpCode = 500; // Server error
                }
                
                http_response_code($httpCode);
                echo json_encode([
                    'status' => 'error',
                    'error'  => $errorCode,
                    'message' => $result['message'] ?? 'Failed to start game',
                    'id_game' => $result['id_game'] ?? null,
                    'current_players' => $result['current_players'] ?? null,
                    'required' => $result['required'] ?? null,
                    'needed' => $result['needed'] ?? null,
                    'have' => $result['have'] ?? null
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

