// ============================================
// Работа с localStorage (токен и логин)
// ============================================

function saveToken(token) {
  localStorage.setItem('token', token);
}

function getToken() {
  const token = localStorage.getItem('token') || '';
  
  // Проверяем, что токен не является JSON объектом (ошибкой)
  if (token && (token.startsWith('{') || token.startsWith('['))) {
    console.warn('[getToken] Обнаружен неверный токен (JSON объект), очищаем localStorage');
    removeToken();
    removeLogin();
    return '';
  }
  
  return token;
}

function removeToken() {
  localStorage.removeItem('token');
}

function saveLogin(login) {
  localStorage.setItem('login', login);
}

function getLogin() {
  return localStorage.getItem('login') || null;
}

function removeLogin() {
  localStorage.removeItem('login');
}

function checkAuth() {
  const token = getToken();
  // Проверяем, что токен существует и не является JSON объектом
  return !!token && !token.startsWith('{') && !token.startsWith('[');
}

async function apiLogout() {
  const token = getToken();
  if (!token) {
    // Если токена нет, просто очищаем локальное хранилище
    removeToken();
    removeLogin();
    return { status: 'ok' };
  }

  try {
    const result = await apiRequest('logout_user.php', { token });
    // Независимо от результата, очищаем локальное хранилище
    removeToken();
    removeLogin();
    return result;
  } catch (error) {
    // Даже при ошибке очищаем локальное хранилище
    removeToken();
    removeLogin();
    return { status: 'error', error: error.message || 'logout_failed' };
  }
}

function logout() {
  // Синхронная версия для обратной совместимости
  // Вызывает API асинхронно, но не ждет результата
  apiLogout();
}

// ============================================
// Базовые функции для работы с API
// ============================================

const API_BASE_URL = 'backend/api';

async function apiRequest(endpoint, data) {
  console.log(`[apiRequest] ${endpoint} запрос:`, data);
  console.log(`[apiRequest] ${endpoint} JSON body:`, JSON.stringify(data));

  const response = await fetch(`${API_BASE_URL}/${endpoint}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });

  const text = await response.text();
  console.log(`[apiRequest] ${endpoint} response (${response.status}):`, text);

  let json;
  try {
    json = JSON.parse(text);
  } catch (e) {
    console.error(`[apiRequest] ${endpoint} - Invalid JSON response:`, text);
    throw new Error(`Invalid JSON response: ${e.message}`);
  }

  // НОВЫЙ ФОРМАТ ОШИБОК: сервер возвращает { status: "error", error: "..." }
  if (!response.ok || json.status === 'error') {
    const errorCode = json.error || json.message || 'unknown_error';
    console.error(`[apiRequest] ${endpoint} - Error:`, errorCode, json);
    
    // Для ошибки 'game_finished' возвращаем весь объект, так как он содержит результаты игры
    if (errorCode === 'game_finished') {
      return json; // Возвращаем весь объект с results, winner_turtle и т.д.
    }
    
    // Для других ошибок возвращаем объект с ошибкой
    // Это позволяет клиенту обработать ошибку более гибко
    return {
      status: 'error',
      error: errorCode,
      message: json.message || errorCode
    };
  }

  return json;
}

function handleApiError(error) {
  console.error('API Error:', error);

  if (error.message === 'invalid_credentials') {
    alert('Неверный логин или пароль');
  } else if (error.message === 'user_already_exists') {
    alert('Пользователь с таким логином уже существует');
  } else if (error.message === 'short_nickname') {
    alert('Логин должен содержать минимум 3 символа');
  } else if (error.message === 'short_password') {
    alert('Пароль должен содержать минимум 3 символа');
  } else if (error.message === 'missing_token' || error.message === 'invalid_or_expired_token') {
    // Очищаем неверный токен
    removeToken();
    removeLogin();
    alert('Требуется авторизация');
    window.location.href = 'login.html';
  } else {
    alert(`Ошибка: ${error.message || 'Неизвестная ошибка'}`);
  }
}

// ============================================
// API функции для регистрации и входа
// ============================================

async function apiRegister(login, password) {
  const result = await apiRequest('register_user.php', { login, password });

  // Сохраняем токен только если это успешный ответ и токен - это строка
  if (result.status === 'ok' && result.token && typeof result.token === 'string' && result.token.trim() !== '') {
    // Дополнительная проверка: токен не должен быть JSON объектом
    if (!result.token.startsWith('{') && !result.token.startsWith('[')) {
      saveToken(result.token);
      saveLogin(login);
    } else {
      console.error('[apiRegister] Получен неверный формат токена:', result.token);
      throw new Error('invalid_token_format');
    }
  }

  return result;
}

async function apiLogin(login, password) {
  const result = await apiRequest('authorize_user.php', { login, password });

  // Сохраняем токен только если это успешный ответ и токен - это строка
  if (result.status === 'ok' && result.token && typeof result.token === 'string' && result.token.trim() !== '') {
    // Дополнительная проверка: токен не должен быть JSON объектом
    if (!result.token.startsWith('{') && !result.token.startsWith('[')) {
      saveToken(result.token);
      saveLogin(login);
    } else {
      console.error('[apiLogin] Получен неверный формат токена:', result.token);
      throw new Error('invalid_token_format');
    }
  }

  return result;
}

// ============================================
// API функции для комнат
// ============================================

async function apiListRooms() {
  const token = getToken();
  if (!token) throw new Error('missing_token');
  return apiRequest('list_open_rooms.php', { token });
}

async function apiCreateRoom({ token, players_count, turn_duration }) {
  console.log('[apiCreateRoom] Параметры:', {
    token: token ? 'present' : 'missing',
    players_count,
    turn_duration
  });

  return apiRequest('create_game_room.php', {
    token,
    players_count,
    turn_duration,
  });
}

async function apiJoinRoom(gameId) {
  const token = getToken();
  if (!token) throw new Error('missing_token');

  return apiRequest('join_game_room.php', {
    token,
    game_id: gameId
  });
}

async function apiLeaveLobby(gameId) {
  const token = getToken();
  if (!token) throw new Error('missing_token');

  return apiRequest('leave_lobby.php', {
    token,
    game_id: gameId
  });
}

async function apiStartGame(gameId) {
  const token = getToken();
  if (!token) throw new Error('missing_token');

  return apiRequest('start_game.php', {
    token,
    game_id: gameId
  });
}

async function apiLeaveGame(gameId) {
  const token = getToken();
  if (!token) throw new Error('missing_token');

  return apiRequest('leave_game.php', {
    token,
    game_id: gameId
  });
}

// ============================================
// API функции для игры
// ============================================

async function apiGetGameState(gameId) {
  const token = getToken();
  if (!token) throw new Error('missing_token');

  return apiRequest('get_state.php', {
    token,
    game_id: gameId
  });
}

async function apiMakeMove(gameId, cardId, targetTurtleId = null, returnHand = true) {
  const token = getToken();
  if (!token) throw new Error('missing_token');

  const data = { 
    token, 
    game_id: gameId, 
    card_id: cardId,
    return_hand: returnHand
  };
  // Используем target_turtle (сервер ожидает это поле, не target_turtle_id)
  if (targetTurtleId !== null && targetTurtleId !== undefined) {
    data.target_turtle = targetTurtleId;
    console.log('[api] Отправляем target_turtle:', targetTurtleId);
  } else {
    console.log('[api] target_turtle не передан (null/undefined)');
  }

  return apiRequest('make_move.php', data);
}
