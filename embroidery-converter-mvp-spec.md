# ТЕХНИЧЕСКОЕ ЗАДАНИЕ: Image to Embroidery Converter MVP

## КРАТКОЕ ОПИСАНИЕ

Веб-приложение для автоматической конвертации фотографий в форматы вышивальных машин. 

**MVP функционал:** Пользователь загружает фото → автоматическая обработка → скачивание DST файла.

**Архитектурная особенность:** Система спроектирована для легкого расширения источников входных данных (URL, текст+AI, API) и форматов вывода (PES, JEF, XXX и др.).

---

## 1. ТЕХНИЧЕСКИЙ СТЕК

### Backend
- **PHP 8.2+** - веб-интерфейс, обработка загрузок, API endpoints
- **Python 3.11+** - обработка изображений, генерация вышивки
- **Redis 7** - очередь задач между PHP и Python

### Библиотеки (Python)
- **PyEmbroidery** - генерация форматов вышивки (DST, PES, JEF)
- **OpenCV (cv2)** - обработка изображений, детекция контуров
- **NumPy** - математические операции с массивами
- **scikit-learn** - K-means кластеризация для цветов
- **Pillow (PIL)** - дополнительная работа с изображениями
- **Predis (PHP)** - Redis клиент для PHP

### Frontend
- **Vanilla JavaScript** - логика интерфейса, AJAX
- **HTML5 + CSS3** - разметка и стили
- **Bootstrap 5** (опционально) - быстрая верстка UI

### Инфраструктура
- **Docker + Docker Compose** - контейнеризация
- **Nginx** - веб-сервер
- **PHP-FPM** - обработка PHP запросов

---

## 2. АРХИТЕКТУРА СИСТЕМЫ

### 2.1 Общая схема

```
┌─────────────────────────────────────────────────────────────┐
│                    NGINX (Port 8080)                        │
└────────────────────────┬────────────────────────────────────┘
                         │
         ┌───────────────┴────────────────┐
         │                                │
         ▼                                ▼
┌────────────────┐              ┌─────────────────┐
│  Static Files  │              │   PHP-FPM       │
│  (HTML/CSS/JS) │              │   Application   │
└────────────────┘              └────────┬────────┘
                                         │
                                         ▼
                                ┌────────────────┐
                                │ Redis Queue    │
                                │ (embroidery_   │
                                │  queue)        │
                                └────────┬───────┘
                                         │
                                         ▼
                                ┌────────────────┐
                                │ Python Worker  │
                                │ (Processor)    │
                                └────────┬───────┘
                                         │
                                         ▼
                                ┌────────────────┐
                                │ Storage        │
                                │ (Shared Volume)│
                                └────────────────┘
```

### 2.2 Поток данных (User Journey)

```
1. Пользователь открывает веб-интерфейс
   │
   ├──> Загружает фото (drag & drop или file picker)
   │
   ├──> Видит превью загруженного изображения
   │
   ├──> Нажимает "Convert to Embroidery"
   │
2. PHP обрабатывает загрузку
   │
   ├──> Валидация (формат, размер)
   │
   ├──> Сохранение в /storage/uploads/{uuid}.jpg
   │
   ├──> Создание задачи в Redis
   │
   └──> Возврат job_id клиенту
   │
3. JavaScript начинает polling статуса
   │
4. Python Worker забирает задачу из очереди
   │
   ├──> Обработка изображения (сегментация, контуры)
   │
   ├──> Генерация паттернов стежков
   │
   ├──> Экспорт в DST формат
   │
   └──> Обновление статуса в Redis
   │
5. Клиент получает статус "completed"
   │
   └──> Автоматическое скачивание DST файла
```

### 2.3 Расширяемая архитектура Input/Output

#### Паттерн Strategy для входных источников

```
┌──────────────────────────────────────────────────┐
│           InputSourceFactory (PHP)               │
│  - create(type, data): InputSourceInterface      │
│  - register(type, className): void               │
└─────────────────┬────────────────────────────────┘
                  │
      ┌───────────┴──────────┬─────────────┬──────────────┐
      │                      │             │              │
┌─────▼──────┐      ┌───────▼──────┐  ┌──▼───────┐  ┌───▼────────┐
│ FileUpload │      │ UrlSource    │  │ TextLLM  │  │ Future...  │
│ Source     │      │              │  │ Source   │  │            │
└─────┬──────┘      └───────┬──────┘  └──┬───────┘  └────────────┘
      │                     │             │
      └─────────────┬───────┴─────────────┘
                    │
          ┌─────────▼──────────┐
          │  validate(): bool  │
          │  process(): path   │
          │  getMetadata()     │
          │  getType(): string │
          └────────────────────┘
```

#### Паттерн Strategy для обработчиков (Python)

```
┌──────────────────────────────────────────────────┐
│        ProcessorFactory (Python)                 │
│  - create(job_data): BaseInputProcessor          │
│  - register(type, ProcessorClass): void          │
└─────────────────┬────────────────────────────────┘
                  │
      ┌───────────┴──────────┬─────────────┬──────────────┐
      │                      │             │              │
┌─────▼──────┐      ┌───────▼──────┐  ┌──▼───────┐  ┌───▼────────┐
│ Image      │      │ URL          │  │ LLM      │  │ Future...  │
│ Processor  │      │ Processor    │  │ Processor│  │            │
└─────┬──────┘      └───────┬──────┘  └──┬───────┘  └────────────┘
      │                     │             │
      └─────────────┬───────┴─────────────┘
                    │
          ┌─────────▼──────────┐
          │  validate(): bool  │
          │  process(): ndarray│
          │  cleanup(): void   │
          └────────────────────┘
```

#### Паттерн Strategy для форматов вывода

```
┌──────────────────────────────────────────────────┐
│        OutputFormatFactory (Python)              │
│  - create(format): OutputFormatInterface         │
│  - register(format, FormatClass): void           │
└─────────────────┬────────────────────────────────┘
                  │
      ┌───────────┴──────────┬─────────────┬──────────────┐
      │                      │             │              │
┌─────▼──────┐      ┌───────▼──────┐  ┌──▼───────┐  ┌───▼────────┐
│ DST        │      │ PES          │  │ JEF      │  │ XXX        │
│ Exporter   │      │ Exporter     │  │ Exporter │  │ Exporter   │
└─────┬──────┘      └───────┬──────┘  └──┬───────┘  └────────────┘
      │                     │             │
      └─────────────┬───────┴─────────────┘
                    │
          ┌─────────▼──────────┐
          │  export(pattern,   │
          │         path): void│
          └────────────────────┘
```

---

## 3. СТРУКТУРА ПРОЕКТА

```
embroidery-converter/
├── docker-compose.yml              # Оркестрация контейнеров
├── .env.example                    # Шаблон переменных окружения
├── .gitignore
├── README.md                       # Документация проекта
│
├── nginx/                          # Конфигурация Nginx
│   ├── Dockerfile
│   └── default.conf
│
├── php/                            # PHP приложение
│   ├── Dockerfile
│   ├── composer.json
│   ├── composer.lock
│   │
│   ├── public/                     # Публичная директория (document root)
│   │   ├── index.html             # Главная страница
│   │   │
│   │   ├── api/                   # API endpoints
│   │   │   ├── input/             # Расширяемые источники ввода
│   │   │   │   ├── base.php      # Базовый функционал
│   │   │   │   └── upload.php    # Загрузка файла (MVP)
│   │   │   ├── process.php        # Единая точка обработки
│   │   │   ├── status.php         # Проверка статуса задачи
│   │   │   └── download.php       # Скачивание результата
│   │   │
│   │   ├── assets/
│   │   │   ├── css/
│   │   │   │   └── style.css
│   │   │   └── js/
│   │   │       ├── app.js         # Главная логика
│   │   │       └── input-handlers/
│   │   │           └── file-upload.js
│   │   │
│   │   └── favicon.ico
│   │
│   ├── src/                        # Бизнес-логика PHP
│   │   ├── InputSource/           # Источники входных данных
│   │   │   ├── InputSourceInterface.php
│   │   │   ├── FileUploadSource.php
│   │   │   └── InputSourceFactory.php
│   │   │
│   │   └── Config/
│   │       └── redis.php
│   │
│   └── storage/                    # Файловое хранилище (монтируется как volume)
│       ├── uploads/                # Загруженные файлы
│       ├── processing/             # Файлы в обработке
│       └── results/                # Готовые DST файлы
│
└── python/                         # Python Worker
    ├── Dockerfile
    ├── requirements.txt
    │
    ├── worker.py                   # Главный worker (точка входа)
    ├── converter.py                # Orchestrator конвертации
    │
    ├── input_processors/           # Обработчики входных данных
    │   ├── __init__.py
    │   ├── base_processor.py       # Абстрактный базовый класс
    │   ├── image_processor.py      # Обработка изображений (MVP)
    │   └── processor_factory.py    # Фабрика процессоров
    │
    ├── output_formats/             # Экспортеры форматов вывода
    │   ├── __init__.py
    │   ├── base_exporter.py        # Абстрактный базовый класс
    │   ├── dst_exporter.py         # DST формат (MVP)
    │   └── format_factory.py       # Фабрика форматов
    │
    └── utils/                      # Утилиты обработки
        ├── __init__.py
        ├── image_processing.py     # Работа с изображениями
        ├── color_segmentation.py   # Сегментация по цветам
        ├── contour_detection.py    # Детекция контуров
        ├── stitch_generation.py    # Генерация стежков
        └── geometry.py             # Геометрические вычисления (PCA и др.)
```

---

## 4. ДЕТАЛЬНАЯ СПЕЦИФИКАЦИЯ КОМПОНЕНТОВ

### 4.1 PHP Backend

#### 4.1.1 InputSourceInterface (базовый интерфейс)

```php
<?php
namespace EmbroideryConverter\InputSource;

interface InputSourceInterface
{
    /**
     * Валидация входных данных
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validate(): array;
    
    /**
     * Обработка и сохранение входных данных
     * @return string Путь к файлу или метаданным
     */
    public function process(): string;
    
    /**
     * Метаданные о входных данных
     * @return array
     */
    public function getMetadata(): array;
    
    /**
     * Тип источника для маршрутизации
     * @return string 'file'|'url'|'text'|'custom'
     */
    public function getType(): string;
}
```

#### 4.1.2 FileUploadSource (MVP реализация)

**Файл:** `php/src/InputSource/FileUploadSource.php`

**Функционал:**
- Валидация загруженного файла (тип, размер)
- Сохранение в `/storage/uploads/{uuid}.{ext}`
- Возврат пути к файлу

**Валидация:**
- Типы файлов: JPG, PNG, GIF, WEBP
- Максимальный размер: 10MB
- Проверка на корректность изображения

#### 4.1.3 InputSourceFactory

**Файл:** `php/src/InputSource/InputSourceFactory.php`

**Функционал:**
- Создание соответствующего источника по типу
- Регистрация кастомных источников
- Управление зависимостями (storage path)

#### 4.1.4 API Endpoints

##### api/process.php (единая точка входа)

**Функционал:**
```php
1. Определить тип входных данных (input_type)
2. Создать соответствующий InputSource через Factory
3. Валидировать данные
4. Обработать и сохранить
5. Создать задачу для Redis
6. Вернуть job_id клиенту
```

**Формат задачи в Redis:**
```json
{
  "job_id": "uuid-v4-string",
  "input_type": "file",
  "input_path": "/storage/uploads/uuid.jpg",
  "output_path": "/storage/results/uuid.dst",
  "output_format": "dst",
  "metadata": {
    "original_name": "photo.jpg",
    "size": 2048000,
    "mime_type": "image/jpeg"
  },
  "status": "pending",
  "created_at": 1701234567,
  "params": {
    "colors": 6,
    "size_mm": 100,
    "density": "medium",
    "stitch_type": "auto"
  }
}
```

**Response:**
```json
{
  "success": true,
  "job_id": "uuid-v4-string",
  "input_type": "file"
}
```

##### api/status.php

**Функционал:**
- Получить статус задачи из Redis по job_id
- Вернуть текущее состояние

**Response:**
```json
{
  "job_id": "uuid",
  "status": "pending|processing|completed|failed",
  "progress": 0-100,
  "created_at": 1701234567,
  "completed_at": 1701234600,
  "error": "Error message if failed"
}
```

##### api/download.php

**Функционал:**
- Проверить существование результата
- Отдать файл для скачивания с правильными headers
- Опционально: удалить файл после скачивания

**Headers:**
```
Content-Type: application/octet-stream
Content-Disposition: attachment; filename="embroidery_{timestamp}.dst"
Content-Length: {file_size}
```

### 4.2 Python Worker

#### 4.2.1 Базовые классы

##### BaseInputProcessor (абстрактный класс)

**Файл:** `python/input_processors/base_processor.py`

```python
from abc import ABC, abstractmethod
from pathlib import Path
from typing import Dict, Any
import numpy as np

class BaseInputProcessor(ABC):
    """Базовый класс для всех обработчиков входных данных"""
    
    def __init__(self, job_data: Dict[str, Any]):
        self.job_data = job_data
        self.input_path = Path(job_data['input_path'])
        self.metadata = job_data.get('metadata', {})
    
    @abstractmethod
    def process(self) -> np.ndarray:
        """
        Обработка входных данных
        Returns:
            np.ndarray: Изображение в формате BGR (OpenCV)
        """
        pass
    
    def validate(self) -> bool:
        """Дополнительная валидация на стороне Python"""
        return self.input_path.exists()
    
    def cleanup(self):
        """Очистка временных файлов"""
        pass
```

##### BaseOutputExporter (абстрактный класс)

**Файл:** `python/output_formats/base_exporter.py`

```python
from abc import ABC, abstractmethod
from typing import Any

class BaseOutputExporter(ABC):
    """Базовый класс для экспорта в форматы вышивки"""
    
    @abstractmethod
    def export(self, pattern: Any, output_path: str) -> None:
        """
        Экспорт паттерна вышивки в файл
        
        Args:
            pattern: Внутреннее представление вышивки
            output_path: Путь для сохранения файла
        """
        pass
    
    @abstractmethod
    def get_extension(self) -> str:
        """Расширение файла (без точки)"""
        pass
    
    def validate_pattern(self, pattern: Any) -> bool:
        """Валидация паттерна перед экспортом"""
        return True
```

#### 4.2.2 MVP Реализации

##### ImageProcessor

**Файл:** `python/input_processors/image_processor.py`

**Функционал:**
1. Загрузка изображения через OpenCV
2. Resize до разумного размера (макс 800px по большей стороне)
3. Возврат изображения в формате BGR

##### DSTExporter

**Файл:** `python/output_formats/dst_exporter.py`

**Функционал:**
1. Конвертация внутреннего представления в PyEmbroidery pattern
2. Экспорт в DST формат
3. Валидация результата

#### 4.2.3 Converter (оркестратор)

**Файл:** `python/converter.py`

**Класс:** `EmbroideryConverter`

**Методы:**
```python
class EmbroideryConverter:
    def convert(
        self,
        image: np.ndarray,
        output_path: str,
        params: dict
    ) -> None:
        """
        Полный цикл конвертации изображения в вышивку
        
        Args:
            image: Входное изображение (BGR)
            output_path: Путь для сохранения результата
            params: Параметры конвертации
        """
        # 1. Цветовая сегментация
        color_regions = self._segment_colors(image, params['colors'])
        
        # 2. Генерация контуров для каждого региона
        contours_data = self._extract_contours(color_regions)
        
        # 3. Генерация стежков
        embroidery_pattern = self._generate_stitches(
            contours_data,
            params
        )
        
        # 4. Экспорт в нужный формат
        output_format = params.get('output_format', 'dst')
        exporter = FormatFactory.create(output_format)
        exporter.export(embroidery_pattern, output_path)
```

**Внутренние методы:**
- `_segment_colors()` - K-means кластеризация
- `_extract_contours()` - детекция и упрощение контуров
- `_generate_stitches()` - создание паттернов стежков
- `_calculate_stitch_angle()` - определение направления (PCA)
- `_create_fill_pattern()` - генерация заливки

#### 4.2.4 Worker (главный процесс)

**Файл:** `python/worker.py`

**Главный цикл:**
```python
def main():
    redis_client = redis.Redis(
        host=os.getenv('REDIS_HOST', 'redis'),
        port=6379,
        decode_responses=True
    )
    
    converter = EmbroideryConverter()
    logger.info("Worker started, waiting for jobs...")
    
    while True:
        try:
            # Blocking pop из очереди
            result = redis_client.brpop('embroidery_queue', timeout=1)
            
            if not result:
                continue
            
            _, job_json = result
            job = json.loads(job_json)
            job_id = job['job_id']
            
            logger.info(f"Processing job {job_id}")
            
            # Обновляем статус
            job['status'] = 'processing'
            redis_client.setex(f"job:{job_id}", 3600, json.dumps(job))
            
            try:
                # 1. Создаем процессор входных данных
                processor = ProcessorFactory.create(job)
                
                # 2. Обрабатываем входные данные
                image = processor.process()
                
                # 3. Конвертируем в вышивку
                converter.convert(
                    image,
                    job['output_path'],
                    job['params']
                )
                
                # 4. Очистка
                processor.cleanup()
                
                # 5. Успешное завершение
                job['status'] = 'completed'
                job['completed_at'] = int(time.time())
                redis_client.setex(f"job:{job_id}", 3600, json.dumps(job))
                
                logger.info(f"Job {job_id} completed")
                
            except Exception as e:
                logger.error(f"Job {job_id} failed: {e}", exc_info=True)
                job['status'] = 'failed'
                job['error'] = str(e)
                redis_client.setex(f"job:{job_id}", 3600, json.dumps(job))
        
        except Exception as e:
            logger.error(f"Worker error: {e}", exc_info=True)
            time.sleep(1)
```

### 4.3 Frontend

#### 4.3.1 HTML Structure (index.html)

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Embroidery Converter</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Image to Embroidery Converter</h1>
            <p>Convert your photos into embroidery machine formats</p>
        </header>
        
        <main>
            <!-- Upload Area -->
            <div id="upload-area" class="upload-zone">
                <input type="file" id="file-input" accept="image/*" hidden>
                <div class="upload-placeholder">
                    <svg class="upload-icon"><!-- Icon --></svg>
                    <p>Drag & drop your image here or click to browse</p>
                    <p class="hint">Supported: JPG, PNG, GIF, WEBP (max 10MB)</p>
                </div>
            </div>
            
            <!-- Preview Area -->
            <div id="preview-area" class="preview" style="display: none;">
                <img id="preview-image" alt="Preview">
                <button id="remove-image" class="btn-secondary">Remove</button>
            </div>
            
            <!-- Settings (Optional for MVP, но структура готова) -->
            <div id="settings" class="settings" style="display: none;">
                <h3>Conversion Settings</h3>
                <div class="setting-group">
                    <label>Number of Colors:
                        <input type="number" id="param-colors" value="6" min="2" max="12">
                    </label>
                </div>
                <div class="setting-group">
                    <label>Size (mm):
                        <input type="number" id="param-size" value="100" min="10" max="500">
                    </label>
                </div>
            </div>
            
            <!-- Convert Button -->
            <button id="convert-btn" class="btn-primary" disabled>
                Convert to Embroidery
            </button>
            
            <!-- Progress -->
            <div id="progress-area" class="progress-container" style="display: none;">
                <div class="progress-bar">
                    <div id="progress-fill" class="progress-fill"></div>
                </div>
                <p id="progress-status">Processing your image...</p>
            </div>
            
            <!-- Download Area -->
            <div id="download-area" class="download-container" style="display: none;">
                <div class="success-message">
                    <svg class="success-icon"><!-- Checkmark icon --></svg>
                    <h3>Conversion Complete!</h3>
                    <p>Your embroidery file is ready</p>
                </div>
                <a id="download-link" class="btn-primary" download>
                    Download DST File
                </a>
                <button id="convert-another" class="btn-secondary">
                    Convert Another Image
                </button>
            </div>
        </main>
        
        <footer>
            <p>Supports Bernina and other DST-compatible machines</p>
        </footer>
    </div>
    
    <script src="/assets/js/app.js"></script>
</body>
</html>
```

#### 4.3.2 JavaScript Logic (app.js)

**Основные функции:**

```javascript
// Состояние приложения
const AppState = {
    selectedFile: null,
    jobId: null,
    pollingInterval: null
};

// Инициализация
function init() {
    setupDragAndDrop();
    setupFileInput();
    setupConvertButton();
}

// Drag & Drop
function setupDragAndDrop() {
    const uploadArea = document.getElementById('upload-area');
    
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });
    
    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('drag-over');
    });
    
    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelect(files[0]);
        }
    });
    
    uploadArea.addEventListener('click', () => {
        document.getElementById('file-input').click();
    });
}

// Обработка выбора файла
function handleFileSelect(file) {
    // Валидация
    if (!validateFile(file)) {
        return;
    }
    
    AppState.selectedFile = file;
    
    // Показываем превью
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('preview-image').src = e.target.result;
        document.getElementById('upload-area').style.display = 'none';
        document.getElementById('preview-area').style.display = 'block';
        document.getElementById('settings').style.display = 'block';
        document.getElementById('convert-btn').disabled = false;
    };
    reader.readAsDataURL(file);
}

// Валидация файла
function validateFile(file) {
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const maxSize = 10 * 1024 * 1024; // 10MB
    
    if (!validTypes.includes(file.type)) {
        alert('Invalid file type. Please upload JPG, PNG, GIF, or WEBP.');
        return false;
    }
    
    if (file.size > maxSize) {
        alert('File is too large. Maximum size is 10MB.');
        return false;
    }
    
    return true;
}

// Конвертация
async function convertImage() {
    const formData = new FormData();
    formData.append('input_type', 'file');
    formData.append('image', AppState.selectedFile);
    formData.append('colors', document.getElementById('param-colors').value);
    formData.append('size_mm', document.getElementById('param-size').value);
    formData.append('output_format', 'dst');
    
    try {
        // Показываем прогресс
        document.getElementById('convert-btn').style.display = 'none';
        document.getElementById('progress-area').style.display = 'block';
        
        // Отправляем на сервер
        const response = await fetch('/api/process.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            AppState.jobId = data.job_id;
            startPolling();
        } else {
            throw new Error(data.error || 'Conversion failed');
        }
    } catch (error) {
        alert('Error: ' + error.message);
        resetUI();
    }
}

// Polling статуса
function startPolling() {
    AppState.pollingInterval = setInterval(async () => {
        try {
            const response = await fetch(`/api/status.php?job_id=${AppState.jobId}`);
            const data = await response.json();
            
            // Обновляем UI
            updateProgress(data.status, data.progress);
            
            if (data.status === 'completed') {
                clearInterval(AppState.pollingInterval);
                showDownload();
            } else if (data.status === 'failed') {
                clearInterval(AppState.pollingInterval);
                alert('Conversion failed: ' + data.error);
                resetUI();
            }
        } catch (error) {
            console.error('Polling error:', error);
        }
    }, 1000); // Каждую секунду
}

// Обновление прогресса
function updateProgress(status, progress) {
    const statusText = {
        'pending': 'Waiting in queue...',
        'processing': 'Converting your image...',
        'completed': 'Done!',
        'failed': 'Failed'
    };
    
    document.getElementById('progress-status').textContent = statusText[status] || status;
    
    if (progress !== undefined) {
        document.getElementById('progress-fill').style.width = progress + '%';
    }
}

// Показать кнопку скачивания
function showDownload() {
    document.getElementById('progress-area').style.display = 'none';
    document.getElementById('download-area').style.display = 'block';
    
    const downloadLink = document.getElementById('download-link');
    downloadLink.href = `/api/download.php?job_id=${AppState.jobId}`;
    downloadLink.download = `embroidery_${Date.now()}.dst`;
}

// Сброс UI
function resetUI() {
    AppState.selectedFile = null;
    AppState.jobId = null;
    
    document.getElementById('upload-area').style.display = 'block';
    document.getElementById('preview-area').style.display = 'none';
    document.getElementById('settings').style.display = 'none';
    document.getElementById('convert-btn').style.display = 'block';
    document.getElementById('convert-btn').disabled = true;
    document.getElementById('progress-area').style.display = 'none';
    document.getElementById('download-area').style.display = 'none';
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', init);
```

---

## 5. АЛГОРИТМЫ ОБРАБОТКИ (Python Utils)

### 5.1 Цветовая сегментация (color_segmentation.py)

```python
import cv2
import numpy as np
from sklearn.cluster import KMeans

def segment_by_colors(image: np.ndarray, n_colors: int = 6) -> list:
    """
    Сегментация изображения по цветам через K-means
    
    Args:
        image: Входное изображение (BGR)
        n_colors: Количество цветов для выделения
    
    Returns:
        list: Список словарей с данными о каждом цветовом регионе
              [{'color': (B, G, R), 'mask': np.ndarray}, ...]
    """
    # Reshape для K-means
    pixels = image.reshape(-1, 3).astype(np.float32)
    
    # K-means кластеризация
    kmeans = KMeans(n_clusters=n_colors, random_state=42, n_init=10)
    kmeans.fit(pixels)
    
    labels = kmeans.labels_.reshape(image.shape[:2])
    colors = kmeans.cluster_centers_.astype(np.uint8)
    
    # Создаем маски для каждого цветового региона
    regions = []
    for i in range(n_colors):
        mask = (labels == i).astype(np.uint8) * 255
        
        # Морфологические операции для очистки
        kernel = np.ones((3, 3), np.uint8)
        mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, kernel)
        mask = cv2.morphologyEx(mask, cv2.MORPH_OPEN, kernel)
        
        # Пропускаем слишком маленькие области
        if np.sum(mask) < 100:
            continue
        
        regions.append({
            'color': tuple(colors[i].tolist()),
            'mask': mask
        })
    
    return regions
```

### 5.2 Детекция контуров (contour_detection.py)

```python
import cv2
import numpy as np

def extract_contours(mask: np.ndarray, simplify: bool = True) -> list:
    """
    Извлечение контуров из маски
    
    Args:
        mask: Бинарная маска
        simplify: Упрощать ли контуры
    
    Returns:
        list: Список контуров (np.ndarray)
    """
    contours, _ = cv2.findContours(
        mask,
        cv2.RETR_EXTERNAL,
        cv2.CHAIN_APPROX_SIMPLE
    )
    
    if not simplify:
        return contours
    
    # Упрощение контуров
    simplified = []
    for contour in contours:
        # Пропускаем слишком маленькие
        if cv2.contourArea(contour) < 50:
            continue
        
        # Douglas-Peucker упрощение
        epsilon = 0.01 * cv2.arcLength(contour, True)
        approx = cv2.approxPolyDP(contour, epsilon, True)
        
        simplified.append(approx)
    
    return simplified
```

### 5.3 Геометрические вычисления (geometry.py)

```python
import numpy as np

def calculate_principal_angle(contour: np.ndarray) -> float:
    """
    Вычисление главного направления контура через PCA
    
    Args:
        contour: Контур (Nx1x2 или Nx2 array)
    
    Returns:
        float: Угол в градусах (0-180)
    """
    # Reshape контура
    points = contour.reshape(-1, 2).astype(np.float32)
    
    # Центрирование
    mean = np.mean(points, axis=0)
    centered = points - mean
    
    # Ковариационная матрица
    cov_matrix = np.cov(centered.T)
    
    # Собственные векторы и значения
    eigenvalues, eigenvectors = np.linalg.eig(cov_matrix)
    
    # Главный компонент (наибольшее собственное значение)
    principal_idx = np.argmax(eigenvalues)
    principal_vector = eigenvectors[:, principal_idx]
    
    # Угол главного направления
    angle = np.arctan2(principal_vector[1], principal_vector[0])
    angle_degrees = np.degrees(angle)
    
    # Нормализация к 0-180
    if angle_degrees < 0:
        angle_degrees += 180
    
    return angle_degrees

def calculate_bounding_box(contour: np.ndarray) -> dict:
    """
    Вычисление ограничивающего прямоугольника
    
    Returns:
        dict: {'x', 'y', 'width', 'height', 'area'}
    """
    x, y, w, h = cv2.boundingRect(contour)
    return {
        'x': x,
        'y': y,
        'width': w,
        'height': h,
        'area': w * h
    }
```

### 5.4 Генерация стежков (stitch_generation.py)

```python
import numpy as np
from typing import List, Tuple

def generate_fill_pattern(
    contour: np.ndarray,
    angle: float,
    density: str = 'medium'
) -> List[Tuple[int, int]]:
    """
    Генерация паттерна заливки для контура
    
    Args:
        contour: Контур области
        angle: Угол направления стежков (градусы)
        density: Плотность ('low', 'medium', 'high')
    
    Returns:
        list: Список координат стежков [(x, y), ...]
    """
    # Параметры плотности
    spacing = {
        'low': 1.0,
        'medium': 0.5,
        'high': 0.3
    }.get(density, 0.5)
    
    # Получаем ограничивающий прямоугольник
    x, y, w, h = cv2.boundingRect(contour)
    
    # Создаем маску контура
    mask = np.zeros((h + 2, w + 2), dtype=np.uint8)
    shifted_contour = contour.copy()
    shifted_contour[:, :, 0] -= x
    shifted_contour[:, :, 1] -= y
    cv2.drawContours(mask, [shifted_contour], -1, 255, -1)
    
    # Генерируем линии заполнения
    stitches = []
    angle_rad = np.radians(angle)
    
    # Направление перпендикулярное углу стежков
    perpendicular = angle + 90
    perp_rad = np.radians(perpendicular)
    
    # Генерируем параллельные линии
    num_lines = int(max(w, h) / spacing)
    
    for i in range(num_lines):
        # Стартовая точка линии
        offset = i * spacing
        start_x = offset * np.cos(perp_rad)
        start_y = offset * np.sin(perp_rad)
        
        # Конечная точка (очень длинная линия)
        line_length = max(w, h) * 2
        end_x = start_x + line_length * np.cos(angle_rad)
        end_y = start_y + line_length * np.sin(angle_rad)
        
        # Найти пересечения с контуром
        intersections = find_line_contour_intersections(
            (start_x, start_y),
            (end_x, end_y),
            mask
        )
        
        # Добавляем стежки (попарно - вход/выход)
        for j in range(0, len(intersections), 2):
            if j + 1 < len(intersections):
                p1 = intersections[j]
                p2 = intersections[j + 1]
                stitches.append((int(p1[0] + x), int(p1[1] + y)))
                stitches.append((int(p2[0] + x), int(p2[1] + y)))
    
    return stitches

def generate_contour_stitches(contour: np.ndarray) -> List[Tuple[int, int]]:
    """
    Генерация контурных стежков (обводка)
    
    Args:
        contour: Контур для обводки
    
    Returns:
        list: Список координат стежков
    """
    points = contour.reshape(-1, 2)
    stitches = [(int(x), int(y)) for x, y in points]
    
    # Замыкаем контур
    if len(stitches) > 0:
        stitches.append(stitches[0])
    
    return stitches
```

---

## 6. DOCKER КОНФИГУРАЦИЯ

### 6.1 docker-compose.yml

```yaml
version: '3.8'

services:
  nginx:
    build: ./nginx
    container_name: embroidery_nginx
    ports:
      - "8080:80"
    volumes:
      - ./php/public:/var/www/html:ro
      - shared-storage:/var/www/storage
    depends_on:
      - php
    networks:
      - embroidery-network

  php:
    build: ./php
    container_name: embroidery_php
    volumes:
      - ./php:/var/www
      - shared-storage:/var/www/storage
    environment:
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - STORAGE_PATH=/var/www/storage
    depends_on:
      - redis
    networks:
      - embroidery-network

  python-worker:
    build: ./python
    container_name: embroidery_worker
    volumes:
      - ./python:/app
      - shared-storage:/storage
    environment:
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - PYTHONUNBUFFERED=1
    depends_on:
      - redis
    networks:
      - embroidery-network
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    container_name: embroidery_redis
    ports:
      - "6379:6379"
    networks:
      - embroidery-network
    volumes:
      - redis-data:/data

networks:
  embroidery-network:
    driver: bridge

volumes:
  shared-storage:
    driver: local
  redis-data:
    driver: local
```

### 6.2 nginx/Dockerfile

```dockerfile
FROM nginx:alpine

COPY default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
```

### 6.3 nginx/default.conf

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html;
    index index.html index.php;

    client_max_body_size 10M;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 6.4 php/Dockerfile

```dockerfile
FROM php:8.2-fpm-alpine

# Установка расширений
RUN docker-php-ext-install pdo pdo_mysql

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www

# Копируем зависимости
COPY composer.json composer.lock ./

# Устанавливаем зависимости
RUN composer install --no-dev --optimize-autoloader

# Копируем код приложения
COPY . .

# Права доступа
RUN chown -R www-data:www-data /var/www/storage

EXPOSE 9000

CMD ["php-fpm"]
```

### 6.5 php/composer.json

```json
{
    "name": "embroidery-converter/php-app",
    "description": "PHP backend for embroidery converter",
    "require": {
        "php": "^8.2",
        "predis/predis": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "EmbroideryConverter\\": "src/"
        }
    }
}
```

### 6.6 python/Dockerfile

```dockerfile
FROM python:3.11-slim

# Установка системных зависимостей для OpenCV
RUN apt-get update && apt-get install -y \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgomp1 \
    libgl1-mesa-glx \
    && rm -rf /var/lib/apt/lists/*

# Рабочая директория
WORKDIR /app

# Копируем requirements
COPY requirements.txt .

# Устанавливаем Python зависимости
RUN pip install --no-cache-dir -r requirements.txt

# Копируем код
COPY . .

# Запуск worker
CMD ["python", "worker.py"]
```

### 6.7 python/requirements.txt

```
redis==5.0.1
numpy==1.24.3
opencv-python-headless==4.8.1.78
scikit-learn==1.3.2
Pillow==10.1.0
pyembroidery==1.4.37
```

### 6.8 .env.example

```bash
# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379

# Storage Configuration
STORAGE_PATH=/var/www/storage

# Python Worker Configuration
PYTHONUNBUFFERED=1

# Future: LLM API Keys (для расширения)
# LLM_API_KEY=
# REPLICATE_API_TOKEN=
```

---

## 7. РАСШИРЕНИЕ СИСТЕМЫ (Roadmap)

### 7.1 Добавление нового источника ввода (пример: URL)

**Шаг 1:** Создать PHP класс
```php
// php/src/InputSource/UrlSource.php
class UrlSource implements InputSourceInterface {
    // Реализация интерфейса
}
```

**Шаг 2:** Создать Python процессор
```python
# python/input_processors/url_processor.py
class UrlProcessor(BaseInputProcessor):
    def process(self):
        # Скачать изображение по URL
        # Вернуть np.ndarray
```

**Шаг 3:** Зарегистрировать в фабриках
```php
// InputSourceFactory автоматически подхватит по типу 'url'
```
```python
# ProcessorFactory автоматически подхватит по input_type
```

**Шаг 4:** Добавить UI
```javascript
// Новый обработчик в app.js
class UrlInputHandler extends InputHandler { ... }
```

### 7.2 Добавление нового формата вывода (пример: PES)

**Шаг 1:** Создать экспортер
```python
# python/output_formats/pes_exporter.py
from pyembroidery import write_pes

class PESExporter(BaseOutputExporter):
    def export(self, pattern, output_path):
        write_pes(pattern, output_path)
    
    def get_extension(self):
        return 'pes'
```

**Шаг 2:** Зарегистрировать в фабрике
```python
# output_formats/format_factory.py
FormatFactory.register('pes', PESExporter)
```

**Шаг 3:** Добавить опцию в UI
```html
<select id="output-format">
    <option value="dst">DST (Bernina)</option>
    <option value="pes">PES (Brother)</option>
</select>
```

---

## 8. ТЕСТИРОВАНИЕ

### 8.1 Ручное тестирование MVP

**Тест-кейсы:**

1. **Простое изображение (2-3 цвета)**
   - Загрузить логотип или иконку
   - Проверить, что DST генерируется
   - Открыть в эмуляторе вышивки (или реальной машине)

2. **Фото с градиентами**
   - Загрузить фото с плавными переходами
   - Проверить сегментацию цветов

3. **Большое изображение (близко к 10MB)**
   - Проверить, что обрабатывается без ошибок
   - Проверить время обработки

4. **Невалидные данные**
   - Попробовать загрузить PDF, TXT
   - Попробовать файл >10MB
   - Проверить, что ошибки обрабатываются корректно

### 8.2 Автоматизированные тесты (future)

```python
# python/tests/test_converter.py
import pytest
from converter import EmbroideryConverter

def test_color_segmentation():
    # Тест K-means сегментации
    pass

def test_contour_extraction():
    # Тест детекции контуров
    pass

def test_dst_export():
    # Тест экспорта в DST
    pass
```

---

## 9. КРИТЕРИИ ГОТОВНОСТИ MVP

### Must Have (обязательно для релиза)

- ✅ Загрузка JPG/PNG изображения через drag & drop
- ✅ Превью загруженного изображения
- ✅ Автоматическая обработка с цветовой сегментацией
- ✅ Генерация DST файла
- ✅ Скачивание результата
- ✅ Базовая обработка ошибок (валидация, таймауты)
- ✅ Docker Compose запуск одной командой
- ✅ README с инструкциями по запуску
- ✅ Расширяемая архитектура (Factory patterns)

### Should Have (желательно)

- ⚠️ Настройка параметров (количество цветов, размер)
- ⚠️ Индикатор прогресса обработки
- ⚠️ История конвертаций (локально, без БД)
- ⚠️ Базовый responsive дизайн

### Could Have (можно отложить)

- ❌ Множественная загрузка файлов
- ❌ Предпросмотр вышивки до скачивания
- ❌ Сравнение исходного изображения и результата
- ❌ Экспорт в другие форматы (PES, JEF)

---

## 10. ДОКУМЕНТАЦИЯ

### 10.1 README.md (структура)

```markdown
# Embroidery Converter

Convert images to embroidery machine formats automatically.

## Features
- Upload photos (JPG, PNG, GIF, WEBP)
- Automatic color segmentation
- Generate DST files for Bernina machines
- Extensible architecture for new input/output formats

## Requirements
- Docker
- Docker Compose

## Quick Start

git clone <repository>
cd embroidery-converter
docker-compose up --build

# Open http://localhost:8080

## Usage
1. Drag & drop your image
2. Click "Convert to Embroidery"
3. Download your DST file

## Architecture
[Diagram or explanation]

## Extending
### Adding a new input source
[Instructions]

### Adding a new output format
[Instructions]

## Troubleshooting
[Common issues]

## License
MIT
```

### 10.2 Inline документация

**Каждый класс и функция должны иметь:**
- Docstring с описанием
- Типы параметров (PHP: @param, Python: type hints)
- Возвращаемые значения
- Примеры использования (где применимо)

---

## 11. ПРОИЗВОДИТЕЛЬНОСТЬ И ОПТИМИЗАЦИЯ

### 11.1 Ожидаемое время обработки

- Простое изображение (300x300): ~2-5 сек
- Среднее изображение (800x600): ~5-10 сек
- Большое изображение (1920x1080, ресайзнутое): ~10-15 сек

### 11.2 Оптимизации (для будущего)

- Кэширование результатов (по хешу изображения)
- Масштабирование Python workers (несколько контейнеров)
- Использование GPU для K-means (cuML)
- CDN для статических файлов

---

## 12. БЕЗОПАСНОСТЬ

### 12.1 Валидация входных данных

**PHP:**
- Проверка MIME типа файла
- Проверка размера
- Проверка содержимого (не только расширения)
- Генерация уникальных имен файлов (UUID)

**Python:**
- Повторная проверка типа файла
- Защита от path traversal
- Ограничение времени обработки (timeout)

### 12.2 Ограничения ресурсов

- Максимальный размер файла: 10MB
- Timeout обработки: 60 секунд
- Ограничение памяти для Python: 2GB (docker)
- Автоматическая очистка старых файлов (cronjob)

---

## 13. МОНИТОРИНГ И ЛОГИРОВАНИЕ

### 13.1 Логи

**PHP:**
```php
error_log("Job {$job_id} created: {$metadata['original_name']}");
```

**Python:**
```python
logger.info(f"Processing job {job_id}, input type: {input_type}")
logger.error(f"Job {job_id} failed: {str(e)}", exc_info=True)
```

**Уровни логов:**
- INFO: Обычные операции (создание задачи, завершение)
- WARNING: Необычные ситуации (большой файл, долгая обработка)
- ERROR: Ошибки обработки

### 13.2 Метрики (future)

- Количество обработанных файлов
- Среднее время обработки
- Процент ошибок
- Использование ресурсов (CPU, RAM)

---

## 14. ДЕПЛОЙ И ОКРУЖЕНИЯ

### 14.1 Локальная разработка

```bash
# Запуск всех сервисов
docker-compose up

# Пересборка после изменений
docker-compose up --build

# Просмотр логов
docker-compose logs -f python-worker

# Остановка
docker-compose down
```

### 14.2 Production (рекомендации)

- Использовать `.env` для конфигурации
- Настроить reverse proxy (Nginx на хосте)
- Включить HTTPS (Let's Encrypt)
- Настроить регулярную очистку файлов
- Мониторинг (Prometheus + Grafana)
- Backups (если добавится БД)

---

## 15. ROADMAP (Будущие версии)

### v1.1 - Расширенные источники
- Загрузка по URL
- Генерация из текста (Stable Diffusion)
- API для интеграции

### v1.2 - Дополнительные форматы
- PES (Brother)
- JEF (Janome)
- XXX (Singer)

### v1.3 - Улучшенная обработка
- ML-модель для определения типа стежков
- Предпросмотр результата
- Ручное редактирование параметров

### v2.0 - Полноценная платформа
- Авторизация пользователей
- История конвертаций
- Облачное хранилище
- API для разработчиков
- Платные подписки

---

## ПРИЛОЖЕНИЯ

### A. Полезные ресурсы

**Форматы вышивки:**
- DST specification: [edutechwiki.unige.ch/en/Embroidery_format_DST](https://edutechwiki.unige.ch/en/Embroidery_format_DST)
- PyEmbroidery docs: [github.com/EmbroidePy/pyembroidery](https://github.com/EmbroidePy/pyembroidery)

**Образовательные материалы:**
- Ink/Stitch tutorials: [inkstitch.org/docs](https://inkstitch.org/docs)
- Embroidery digitizing basics: различные YouTube каналы

### B. Альтернативы (для сравнения)

**Коммерческие:**
- Wilcom
- Pulse
- Hatch

**Open Source:**
- Ink/Stitch (расширение для Inkscape)
- Embroidermodder

---

## ЗАКЛЮЧЕНИЕ

Данное ТЗ описывает минимально жизнеспособный продукт (MVP) с четкой архитектурой, позволяющей легко расширять функционал. 

**Ключевые преимущества архитектуры:**
1. **Модульность** - каждый компонент независим
2. **Расширяемость** - новые источники/форматы добавляются легко
3. **Масштабируемость** - можно добавить больше workers
4. **Тестируемость** - четкое разделение ответственности

**Следующие шаги:**
1. Настроить окружение разработки
2. Реализовать базовые классы (интерфейсы, фабрики)
3. Имплементировать MVP функционал
4. Протестировать на реальных изображениях
5. Протестировать DST файл на машине Bernina
6. Итеративно улучшать качество результата

Удачи в разработке! 🧵✨
