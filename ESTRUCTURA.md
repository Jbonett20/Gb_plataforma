# Estructura de carpetas de gbplataforma

El proyecto nuevo se organiza así:

```
gbplataforma/
├── public/                 ← document root (único directorio expuesto)
│   ├── index.php           ← front controller
│   ├── .htaccess
│   ├── assets/             ← copia de proyectoold/assets
│   └── uploads/            ← derivados públicos generados por el pipeline de imágenes
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Views/              ← layouts, parciales, bloques, panel
│   ├── Middleware/
│   ├── Services/
│   └── Support/
├── config/                 ← app.php, routes.php, images.php, admin_labels.php
├── database/
│   ├── migrations/
│   └── seeds/
└── storage/                ← FUERA del webroot
    ├── uploads/originals/  ← originales de las imágenes subidas
    ├── protected/          ← material de cursos de pago
    ├── cache/
    ├── logs/
    └── sessions/
```

`storage/` no debe ser accesible por URL. El asistente de instalación lo verifica.
