# OpenAlex Team Publications

Plugin de WordPress que integra el Custom Post Type `team` (de [TLP Team](https://wordpress.org/plugins/tlp-team/)) con la API de [OpenAlex](https://openalex.org/) para importar y gestionar automáticamente las publicaciones académicas de los miembros de un equipo de investigación, almacenándolas en [teachPress](https://wordpress.org/plugins/teachpress/).

## 📋 Tabla de Contenidos

- [Características](#-características)
- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Uso](#-uso)
- [Arquitectura](#-arquitectura)
- [API de OpenAlex](#-api-de-openalex)
- [Sincronización](#-sincronización)
- [Frontend](#-frontend)
- [Herramientas de Migración](#-herramientas-de-migración)
- [Solución de Problemas](#-solución-de-problemas)
- [Licencia](#-licencia)

## ✨ Características

### 🔍 Importación Inteligente
- **Deduplicación automática** por OpenAlex Work ID y DOI
- **Mapeo completo de metadatos**: título, autores, DOI, journal, volumen, número, páginas, abstract, año
- **Reconstrucción de abstracts** desde el índice invertido de OpenAlex
- **Generación automática de claves BibTeX**

### 👥 Gestión de Autores
- **Relaciones autor-publicación** individuales en teachPress
- **Enlaces inteligentes**: Los miembros del equipo aparecen enlazados a sus perfiles en las listas de autores
- **Deduplicación de autores** reutilizando entradas existentes en teachPress
- **Soporte para múltiples autores** con formato estándar (Apellido, Iniciales)

### ⚡ Procesamiento en Background
- **Cola de trabajos asíncrona** usando Action Scheduler
- **Sincronización sin bloquear** la interfaz de administración
- **Estado de sincronización** en tiempo real por miembro
- **Prevención de trabajos duplicados**

### 🎨 Frontend Integrado
- **Inyección automática** de publicaciones en páginas `single-team.php`
- **Agrupación por año** con diseño responsive
- **Estilos personalizables** con CSS inline
- **Enlaces a DOI** y URLs de publicaciones
- **Tipos de publicación** con badges de colores (artículo, libro, conferencia, tesis, etc.)

### 🛠️ Administración
- **Configuración centralizada** de API key y email
- **Sincronización automática** con intervalos configurables (manual, cada hora, diario, semanal)
- **Columnas personalizadas** en el listado de miembros del equipo
- **Quick Edit** para OpenAlex ID
- **Filtros por estado de sincronización**
- **Ocultar/mostrar publicaciones** individuales
- **Herramienta de migración** de IDs de autores

### 🚀 Optimización
- **Sistema de caché** con transients de WordPress (12 horas)
- **Consultas optimizadas** con índices apropiados
- **Rate limiting** respetuoso con la API de OpenAlex
- **Paginación automática** para autores con muchas publicaciones

## 📦 Requisitos

- **WordPress** 5.0 o superior
- **PHP** 7.4 o superior
- **Plugin teachPress** activo (para gestión de publicaciones)
- **Plugin TLP Team** activo (para el Custom Post Type `team`)
- **Action Scheduler** (incluido en `vendor/action-scheduler/`)

## 🔧 Instalación

### 1. Clonar o Descargar el Repositorio

```bash
cd wp-content/plugins/
git clone https://github.com/icic-uns-conicet/articles-scraper.git openalex-team-publications