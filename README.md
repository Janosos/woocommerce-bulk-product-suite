# Nakama Product Suite

**Herramienta SPA (Single Page Application) personalizada para la gestión masiva de productos en WooCommerce.**

Este proyecto es una solución a medida desarrollada para optimizar el flujo de trabajo de un e-commerce de ropa (Nakama Bordados). Reemplaza la interfaz nativa de WooCommerce —lenta para cargas masivas— por una interfaz ágil inyectada directamente en el admin de WordPress.

## Características Principales

* **Importación Inteligente CSV:**
    * Parseo de archivos en el navegador (Client-side) usando `PapaParse`.
    * Mapeo "Fuzzy" de columnas: Detecta automáticamente encabezados como "Precio", "Price" o "Precio Normal" ignorando mayúsculas o tildes.
    * Validación de SKUs en tiempo real contra la base de datos para evitar duplicados.
* **Constructor Manual de Productos (Hybrid Mode):**
    * Generador de variantes basado en **Producto Cartesiano**: Crea combinaciones de *Estilo x Color x Talla* automáticamente.
    * Conexión con la API de WooCommerce para obtener taxonomías reales (Categorías, Etiquetas y Atributos globales).
* **Smart Image Assignment:**
    * Asignación de imágenes por "Grupo de Estilo". Al subir una foto para "Negro", se replica automáticamente a todas las tallas (S, M, L, XL) de ese color.
* **UX/UI Optimizada:**
    * Interfaz modal no intrusiva con barra de progreso.
    * Botones de "Plantilla Rápida" para descripciones recurrentes.
    * Manejo de errores y feedback visual de estado.

## Stack Tecnológico

* **Frontend:** jQuery, HTML5, CSS3 (Diseño responsivo y z-index management), PapaParse.js.
* **Backend:** PHP (WordPress Hooks & WooCommerce CRUD Methods).
* **Entorno:** Inyectado vía WPCode (Snippets) para fácil despliegue y mantenimiento sin dependencias de plugins pesados.

## Changelog & Evolución

El desarrollo siguió un ciclo iterativo basado en feedback real del cliente:

### v1.0.0 - Release Candidate (Producción)
* **Feat:** Implementación final de la Suite con modo Híbrido (CSV + Manual).
* **Fix:** Lógica de precios estricta. El producto padre variable muestra rango calculado, no permite edición manual.
* **Fix:** Mapeo de columnas CSV corregido para ignorar "Precio Rebajado" y tomar "Precio Normal".

### v0.9.0 - Constructor Manual
* **Feat:** Agregado generador manual de productos.
* **Logic:** Implementación de atributos fijos (Estilo, Color, Talla) para estandarización.
* **Fix:** Eliminación de generación de SKU para variaciones (WooCommerce las maneja internamente vinculadas al padre).

### v0.8.0 - Smart Features
* **Feat:** Sistema de "Smart Images". Agrupación visual de variantes para carga de fotos masiva.
* **UI:** Botón de "Plantilla Mágica" para descripciones y corrección de codificación de caracteres (UTF-8/ISO).
* **Feat:** Soporte para datos de envío (Peso y Dimensiones).

### v0.5.0 - Core Import
* **Core:** Lectura de CSV y creación de productos simples y variables vía AJAX.
* **Feat:** Validación de SKUs existentes para prevenir duplicidad.
* **Fix:** Solución a conflictos de Z-Index con la librería multimedia de WordPress (`wp.media`).

---
*Desarrollado por Ezequiel López(Janosos) - 2026*
