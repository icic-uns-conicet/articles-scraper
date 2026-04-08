# articles-scraper

openalex-team-publications/
│
├── openalex-team-publications.php   ← Cabecera del plugin + require de clases
│
├── includes/
│   └── class-helpers.php            ← Utilidades compartidas (formato, mapeo, DB)
│
├── core/
│   ├── class-openalex-api.php       ← Comunicación con api.openalex.org
│   └── class-teachpress-import.php  ← Dedup + inserción en teachPress
│
├── admin/
│   ├── class-admin-columns.php      ← Columnas, Quick Edit, filtro, row actions
│   ├── class-admin-sync.php         ← Handler admin-post (formulario sync)
│   └── class-publications-page.php  ← Submenú y vistas de administración
│
└── frontend/
    └── class-single-team.php        ← Inyección en single-team.php (tlp-team)