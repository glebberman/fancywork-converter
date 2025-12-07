# Контекст проекта для Claude

## Основная информация

**Название проекта:** Fancywork Converter (Image to Embroidery Converter MVP)

**Описание:** Веб-приложение для автоматической конвертации фотографий в форматы вышивальных машин.

## Репозиторий и трекинг задач

### Git
- **Удаленный репозиторий:** `git@github.com:glebberman/fancywork-converter.git`
- **Статус:** Создан, но не инициализирован локально
- **Текущая директория:** `/Users/glebberman/Pets/fancy-converter`

### YouTrack
- **URL проекта:** https://glebberman.youtrack.cloud/projects/FAN
- **Ключ проекта:** `FAN`
- **API Token:** `perm-YWRtaW4=.NDktMg==.SLaa21RggHWyV8Np3iK1hiLrkcOb9P`

## Техническая спецификация

Полная спецификация находится в файле `embroidery-converter-mvp-spec.md`.

### Краткая архитектура

**Стек:**
- Backend: PHP 8.2+ (веб-интерфейс, API) + Python 3.11+ (обработка изображений)
- Очередь: Redis 7
- Библиотеки: PyEmbroidery, OpenCV, scikit-learn, NumPy, Pillow
- Frontend: Vanilla JavaScript, HTML5, CSS3
- Инфраструктура: Docker + Docker Compose, Nginx, PHP-FPM

**Основной поток:**
1. Пользователь загружает фото через веб-интерфейс
2. PHP обрабатывает загрузку, создает задачу в Redis
3. Python Worker забирает задачу из очереди
4. Обработка: сегментация цветов → детекция контуров → генерация стежков → экспорт в DST
5. Пользователь скачивает готовый файл

### Ключевые паттерны

**Расширяемая архитектура:**
- Strategy Pattern для источников ввода (файл, URL, текст+LLM)
- Strategy Pattern для форматов вывода (DST, PES, JEF, XXX)
- Factory Pattern для создания процессоров

### Структура проекта

```
embroidery-converter/
├── docker-compose.yml
├── nginx/                  # Веб-сервер
├── php/                    # PHP приложение
│   ├── public/            # Document root
│   │   ├── index.html
│   │   ├── api/           # API endpoints
│   │   └── assets/        # CSS/JS
│   ├── src/               # Бизнес-логика
│   │   └── InputSource/   # Расширяемые источники
│   └── storage/           # Файловое хранилище
└── python/                # Python Worker
    ├── worker.py
    ├── converter.py
    ├── input_processors/
    ├── output_formats/
    └── utils/
```

## MVP Критерии готовности

### Must Have ✅
- Загрузка JPG/PNG через drag & drop
- Превью загруженного изображения
- Автоматическая цветовая сегментация
- Генерация DST файла
- Скачивание результата
- Базовая обработка ошибок
- Docker Compose one-command запуск
- README с инструкциями
- Расширяемая архитектура

### Should Have ⚠️
- Настройка параметров (цвета, размер)
- Индикатор прогресса
- Базовый responsive дизайн

## Roadmap

### v1.1
- Загрузка по URL
- Генерация из текста (Stable Diffusion)
- API для интеграции

### v1.2
- Дополнительные форматы (PES, JEF, XXX)

### v2.0
- Авторизация, история, облачное хранилище

## Полезные команды

### Docker
```bash
# Запуск
docker-compose up --build

# Просмотр логов
docker-compose logs -f python-worker

# Остановка
docker-compose down
```

### Git
```bash
# Инициализация и первый коммит
git init
git remote add origin git@github.com:glebberman/fancywork-converter.git
git add .
git commit -m "Initial commit"
git push -u origin main
```

## Примечания

- Максимальный размер файла: 10MB
- Поддерживаемые форматы: JPG, PNG, GIF, WEBP
- Timeout обработки: 60 секунд
- Целевые машины: Bernina и другие DST-совместимые

## Полная документация

См. `embroidery-converter-mvp-spec.md` для детальной технической спецификации.
