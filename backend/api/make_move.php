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
    $game_id      = isset($input['game_id']) ? (int)$input['game_id'] : 0;
    $card_id      = isset($input['card_id']) ? (int)$input['card_id'] : 0;
    $target_turtle = isset($input['target_turtle']) ? (int)$input['target_turtle'] : null;
    $return_hand  = isset($input['return_hand']) ? (bool)$input['return_hand'] : false;

    // Валидация
    if ($game_id <= 0 || $card_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_parameters',
            'message' => 'game_id and card_id are required'
        ]);
        exit;
    }

    // Вызов SQL функции make_move
    // Порядок параметров: p_game_id, p_token, p_card_id, p_target_turtle, p_return_hand
    // Если token пустой, передаем NULL
    $stmt = $db->prepare('
        SELECT make_move(
            :p_game_id,
            :p_token,
            :p_card_id,
            :p_target_turtle,
            :p_return_hand
        )::json AS data
    ');

    $stmt->execute([
        ':p_game_id'      => $game_id,
        ':p_token'        => $token !== '' ? $token : null,
        ':p_card_id'      => $card_id,
        ':p_target_turtle' => $target_turtle !== null ? $target_turtle : null,
        ':p_return_hand'  => $return_hand,
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] === 'ok') {
                // Успешный ход - возвращаем JSON как есть
                echo $row['data'];
            } else {
                // Ошибка хода
                $errorCode = $result['error'] ?? 'move_failed';
                $httpCode = 400;
                
                // Определяем HTTP код на основе типа ошибки
                if ($errorCode === 'invalid_token') {
                    $httpCode = 401;
                } else if ($errorCode === 'game_not_found') {
                    $httpCode = 404;
                } else if ($errorCode === 'game_already_finished') {
                    $httpCode = 410; // Gone
                } else if ($errorCode === 'game_not_started') {
                    $httpCode = 409; // Conflict
                } else if ($errorCode === 'not_your_turn') {
                    $httpCode = 409; // Conflict
                } else if ($errorCode === 'turn_time_expired') {
                    $httpCode = 408; // Request Timeout
                } else if ($errorCode === 'card_not_in_hand') {
                    $httpCode = 400;
                } else if ($errorCode === 'target_turtle_required') {
                    $httpCode = 400;
                } else if ($errorCode === 'cannot_move_back_from_start') {
                    $httpCode = 400;
                } else if ($errorCode === 'choose_last_turtle_required') {
                    $httpCode = 400;
                }
                
                http_response_code($httpCode);
                echo json_encode([
                    'status' => 'error',
                    'error'  => $errorCode,
                    'message' => $result['message'] ?? 'Failed to make move',
                    'candidates' => $result['candidates'] ?? null,
                    'color' => $result['color'] ?? null,
                    'action' => $result['action'] ?? null,
                    'card_color' => $result['card_color'] ?? null
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

