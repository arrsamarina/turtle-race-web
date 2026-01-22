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

    $token   = trim($input['token'] ?? '');
    $game_id = isset($input['game_id']) ? (int)$input['game_id'] : 0;

    if ($token === '' || $game_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'error'  => 'missing_parameters',
            'message' => 'Token or game_id is missing'
        ]);
        exit;
    }

    $stmt = $db->prepare('SELECT get_game_state_json(:p_token, :p_game_id)::json AS data');
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
                // Успешное получение состояния игры - возвращаем JSON как есть
                echo $row['data'];
            } else {
                // Ошибка получения состояния
                $errorCode = $result['error'] ?? 'state_failed';
                $httpCode = 400;
                
                // Определяем HTTP код на основе типа ошибки
                if ($errorCode === 'invalid_token') {
                    $httpCode = 401;
                } else if ($errorCode === 'game_not_found') {
                    $httpCode = 404;
                } else if ($errorCode === 'player_not_in_game') {
                    $httpCode = 404;
                } else if ($errorCode === 'game_finished') {
                    // Игра завершена - это не ошибка, а состояние игры
                    // Возвращаем как есть, чтобы фронтенд мог обработать результаты
                    echo $row['data'];
                    return;
                }
                
                http_response_code($httpCode);
                echo json_encode([
                    'status' => 'error',
                    'error'  => $errorCode,
                    'message' => $result['message'] ?? 'Failed to get game state',
                    'id_game' => $result['id_game'] ?? null,
                    'login' => $result['login'] ?? null
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

