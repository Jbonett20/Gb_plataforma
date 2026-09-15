<?php

declare(strict_types=1);

/**
 * Perfiles de imagen.
 *
 * Cada lugar donde se muestra una imagen tiene aquí su perfil: la proporción a
 * la que se recortará y los anchos que se generan. El SuperAdmin no necesita
 * saber nada de medidas: sube la foto que tenga y el sistema la adapta.
 *
 *   ratio    proporción de destino [ancho, alto]. `null` = se respeta la
 *            proporción original (sólo se reduce si es más grande).
 *   width    ancho del derivado principal, el que se usa como `public_path`.
 *   sizes    anchos de las variantes que se generan (de menor a mayor).
 *   max_mb   peso máximo aceptado para ese destino.
 *   min_*    dimensiones mínimas reales del archivo original. Por debajo, la
 *            imagen se vería borrosa y se avisa antes de aceptarla.
 */

return [

    'profiles' => [

        'hero_slide' => [
            'label' => 'Diapositiva del inicio',
            'help' => 'El banner grande de la portada. Se recorta en formato panorámico.',
            'ratio' => [16, 9],
            'width' => 1920,
            'sizes' => [640, 1280, 1920],
            'max_mb' => 8,
            'min_width' => 1280,
            'min_height' => 720,
        ],

        'course_cover' => [
            'label' => 'Portada de curso',
            'help' => 'La imagen que representa el curso en el catálogo.',
            'ratio' => [16, 9],
            'width' => 1280,
            'sizes' => [480, 960, 1280],
            'max_mb' => 8,
            'min_width' => 800,
            'min_height' => 450,
        ],

        'news_cover' => [
            'label' => 'Imagen de noticia',
            'help' => 'La imagen que acompaña a una noticia o a un caso destacado.',
            'ratio' => [3, 2],
            'width' => 1200,
            'sizes' => [480, 800, 1200],
            'max_mb' => 8,
            'min_width' => 600,
            'min_height' => 400,
        ],

        'team_photo' => [
            'label' => 'Fotografía de integrante',
            'help' => 'El retrato de una persona del equipo. Se recorta en formato vertical.',
            'ratio' => [3, 4],
            'width' => 800,
            'sizes' => [400, 600, 800],
            'max_mb' => 6,
            'min_width' => 500,
            'min_height' => 667,
        ],

        'template_thumb' => [
            'label' => 'Miniatura de plantilla',
            'help' => 'La vista previa de una plantilla descargable.',
            'ratio' => [4, 3],
            'width' => 1000,
            'sizes' => [400, 700, 1000],
            'max_mb' => 6,
            'min_width' => 600,
            'min_height' => 450,
        ],

        'cta_banner' => [
            'label' => 'Imagen de la franja de invitación',
            'help' => 'El fondo de la franja con el botón. Es muy panorámica.',
            'ratio' => [21, 9],
            'width' => 2100,
            'sizes' => [960, 1440, 2100],
            'max_mb' => 10,
            'min_width' => 1400,
            'min_height' => 600,
        ],

        'site_logo' => [
            'label' => 'Logo del sitio',
            'help' => 'El logo que aparece en la cabecera. Conserva su proporción original.',
            'ratio' => null,
            'width' => 400,
            'sizes' => [200, 400],
            'max_mb' => 2,
            'min_width' => 100,
            'min_height' => 40,
        ],
    ],

    // Formatos aceptados. El tipo real se comprueba leyendo el archivo, no por
    // su extensión: renombrar un archivo no engaña al sistema.
    'allowed_mime' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ],

    // Calidad de salida por formato.
    'quality' => [
        'webp' => 82,
        'jpeg' => 85,
        'png' => 6,
    ],

    // Fracciones aceptadas para el punto focal.
    'focal' => ['min' => 0.0, 'max' => 1.0, 'default' => 0.5],
];
