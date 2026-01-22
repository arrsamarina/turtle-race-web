// Массив путей к изображениям черепах
const turtleImages = [
  'images/turtles/blue_turtle.svg',
  'images/turtles/green_turtle.svg',
  'images/turtles/pink_turtle.svg',
  'images/turtles/purple_turtle.svg',
  'images/turtles/yellow_turtle.svg'
];

// Массив для хранения данных о черепахах
let turtles = [];
let animationId = null;

/**
 * Инициализация фоновых декоративных черепах
 * @param {string} containerId - ID контейнера для размещения черепах
 */
function initTurtlesBackground(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  // Останавливаем предыдущую анимацию, если она есть
  if (animationId) {
    cancelAnimationFrame(animationId);
  }

  // Очищаем контейнер и массив
  container.innerHTML = '';
  turtles = [];

  // Определяем размеры контейнера (для игровой панели используем её размеры, для фона - размеры экрана)
  const isGameUi = containerId === 'gameUiTurtles';
  const containerWidth = isGameUi ? container.offsetWidth : window.innerWidth;
  const containerHeight = isGameUi ? container.offsetHeight : window.innerHeight;
  
  // Количество черепах (меньше для игровой панели)
  const turtleCount = isGameUi 
    ? Math.min(15, Math.floor((containerWidth * containerHeight) / 15000) + 5)
    : Math.min(40, Math.floor((containerWidth * containerHeight) / 20000) + 12);

  // Создаем черепах
  for (let i = 0; i < turtleCount; i++) {
    const turtle = createTurtle(container, containerWidth, containerHeight);
    turtles.push(turtle);
  }

  // Запускаем анимацию
  animateTurtles(isGameUi ? container : null);
}

/**
 * Создает один декоративный элемент черепахи с данными для движения
 * @param {HTMLElement} container - Контейнер для размещения
 * @returns {Object} - Объект с данными черепахи
 */
function createTurtle(container, containerWidth, containerHeight) {
  const turtleElement = document.createElement('img');
  
  // Случайный выбор изображения черепахи
  const randomImage = turtleImages[Math.floor(Math.random() * turtleImages.length)];
  turtleElement.src = randomImage;
  turtleElement.className = 'turtle-decorative';
  turtleElement.alt = '';

  // Случайный размер (меньше для игровой панели)
  const isGameUi = containerWidth < window.innerWidth * 0.8;
  const size = isGameUi 
    ? 20 + Math.random() * 15  // Меньше для игровой панели
    : 45 + Math.random() * 30;  // Обычный размер для фона
  
  turtleElement.style.width = size + 'px';
  turtleElement.style.height = size + 'px';

  // Случайное начальное позиционирование в пределах контейнера
  const x = Math.random() * (containerWidth - size);
  const y = Math.random() * (containerHeight - size);

  // Случайная скорость (медленное движение)
  const speed = 0.3 + Math.random() * 0.4;
  const angle = Math.random() * Math.PI * 2;
  const vx = Math.cos(angle) * speed;
  const vy = Math.sin(angle) * speed;

  // Случайный начальный поворот
  const rotation = Math.random() * 360;

  // Добавляем элемент в контейнер
  container.appendChild(turtleElement);

  // Возвращаем объект с данными черепахи
  return {
    element: turtleElement,
    x: x,
    y: y,
    vx: vx,
    vy: vy,
    size: size,
    rotation: rotation,
    rotationSpeed: (Math.random() - 0.5) * 0.5, // Медленное вращение
    containerWidth: containerWidth,
    containerHeight: containerHeight
  };
}

/**
 * Обновляет позицию черепахи
 */
function updateTurtle(turtle) {
  // Обновляем позицию
  turtle.x += turtle.vx;
  turtle.y += turtle.vy;

  // Обновляем вращение
  turtle.rotation += turtle.rotationSpeed;

  // Проверка границ контейнера - отскок
  const maxX = (turtle.containerWidth || window.innerWidth) - turtle.size;
  const maxY = (turtle.containerHeight || window.innerHeight) - turtle.size;

  if (turtle.x <= 0 || turtle.x >= maxX) {
    turtle.vx = -turtle.vx;
    turtle.x = Math.max(0, Math.min(maxX, turtle.x));
  }

  if (turtle.y <= 0 || turtle.y >= maxY) {
    turtle.vy = -turtle.vy;
    turtle.y = Math.max(0, Math.min(maxY, turtle.y));
  }

  // Применяем изменения к элементу
  turtle.element.style.left = turtle.x + 'px';
  turtle.element.style.top = turtle.y + 'px';
  turtle.element.style.transform = `rotate(${turtle.rotation}deg)`;
}

/**
 * Проверяет столкновение двух черепах
 * @param {Object} turtle1 - Первая черепаха
 * @param {Object} turtle2 - Вторая черепаха
 * @returns {boolean} - true если есть столкновение
 */
function checkCollision(turtle1, turtle2) {
  const dx = turtle1.x - turtle2.x;
  const dy = turtle1.y - turtle2.y;
  const distance = Math.sqrt(dx * dx + dy * dy);
  const minDistance = (turtle1.size + turtle2.size) / 2;

  return distance < minDistance;
}

/**
 * Обрабатывает столкновение двух черепах
 * @param {Object} turtle1 - Первая черепаха
 * @param {Object} turtle2 - Вторая черепаха
 */
function handleCollision(turtle1, turtle2) {
  // Вычисляем нормаль столкновения
  const dx = turtle2.x - turtle1.x;
  const dy = turtle2.y - turtle1.y;
  const distance = Math.sqrt(dx * dx + dy * dy);

  if (distance === 0) return;

  const nx = dx / distance;
  const ny = dy / distance;

  // Меняем направление движения обеих черепах
  // Простое отражение от нормали
  const dot1 = turtle1.vx * nx + turtle1.vy * ny;
  const dot2 = turtle2.vx * nx + turtle2.vy * ny;

  turtle1.vx -= 2 * dot1 * nx;
  turtle1.vy -= 2 * dot1 * ny;
  turtle2.vx -= 2 * dot2 * nx;
  turtle2.vy -= 2 * dot2 * ny;

  // Добавляем небольшое случайное отклонение для более естественного движения
  turtle1.vx += (Math.random() - 0.5) * 0.2;
  turtle1.vy += (Math.random() - 0.5) * 0.2;
  turtle2.vx += (Math.random() - 0.5) * 0.2;
  turtle2.vy += (Math.random() - 0.5) * 0.2;

  // Ограничиваем максимальную скорость
  const maxSpeed = 0.8;
  const speed1 = Math.sqrt(turtle1.vx * turtle1.vx + turtle1.vy * turtle1.vy);
  const speed2 = Math.sqrt(turtle2.vx * turtle2.vx + turtle2.vy * turtle2.vy);

  if (speed1 > maxSpeed) {
    turtle1.vx = (turtle1.vx / speed1) * maxSpeed;
    turtle1.vy = (turtle1.vy / speed1) * maxSpeed;
  }

  if (speed2 > maxSpeed) {
    turtle2.vx = (turtle2.vx / speed2) * maxSpeed;
    turtle2.vy = (turtle2.vy / speed2) * maxSpeed;
  }

  // Разделяем черепах, чтобы они не застревали
  const overlap = (turtle1.size + turtle2.size) / 2 - distance;
  if (overlap > 0) {
    turtle1.x -= nx * overlap * 0.5;
    turtle1.y -= ny * overlap * 0.5;
    turtle2.x += nx * overlap * 0.5;
    turtle2.y += ny * overlap * 0.5;
  }
}

/**
 * Основной цикл анимации
 */
function animateTurtles(container) {
  // Обновляем все черепахи
  for (let i = 0; i < turtles.length; i++) {
    updateTurtle(turtles[i]);
  }

  // Проверяем столкновения между всеми парами черепах
  for (let i = 0; i < turtles.length; i++) {
    for (let j = i + 1; j < turtles.length; j++) {
      if (checkCollision(turtles[i], turtles[j])) {
        handleCollision(turtles[i], turtles[j]);
      }
    }
  }

  // Продолжаем анимацию
  animationId = requestAnimationFrame(() => animateTurtles(container));
}

// Обновление позиций черепах при изменении размера окна
let resizeTimeout;
window.addEventListener('resize', function() {
  clearTimeout(resizeTimeout);
  resizeTimeout = setTimeout(function() {
    const background = document.getElementById('turtlesBackground');
    if (background) {
      initTurtlesBackground('turtlesBackground');
    }
  }, 250);
});

// Остановка анимации при уходе со страницы
window.addEventListener('beforeunload', function() {
  if (animationId) {
    cancelAnimationFrame(animationId);
  }
});
