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

    // Вызов SQL функции leave_lobby
    $stmt = $db->prepare('SELECT leave_lobby(:p_token, :p_game_id)::json AS data');
    $stmt->execute([
        ':p_token'   => $token,
        ':p_game_id' => $game_id,
    ]);

    $row = $stmt->fetch();
    if ($row && isset($row['data'])) {
        // Парсим JSON ответ от SQL функции
        $result = json_decode($row['data'], true);
        
        if ($result && isset($result['status'])) {
            if ($result['status'] === 'ok') {
                // Успешный выход из лобби - возвращаем JSON как есть
                echo $row['data'];
            } else {
                // Ошибка выхода из лобби
                $errorCode = $result['error'] ?? 'leave_failed';
                $httpCode = 400;
                
                // Определяем HTTP код на основе типа ошибки
                if ($errorCode === 'invalid_token') {
                    $httpCode = 401;
                } else if ($errorCode === 'game_not_found') {
                    $httpCode = 404;
                } else if ($errorCode === 'player_not_in_game') {
                    $httpCode = 404;
                } else if ($errorCode === 'game_already_started_use_leave_active_game') {
                    $httpCode = 409; // Conflict
                }
                
                http_response_code($httpCode);
                echo json_encode([
                    'status' => 'error',
                    'error'  => $errorCode,
                    'message' => $result['message'] ?? 'Failed to leave lobby',
                    'id_game' => $result['id_game'] ?? null
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

