<?php

declare(strict_types=1);

/**
 * Etiquetas del panel de administración.
 *
 * Este archivo es la frontera entre el modelo de datos y la persona que usa el
 * panel. Aquí NO se habla de tablas ni de columnas: se habla de "Diapositiva",
 * "Imagen de fondo" o "Texto del botón".
 *
 * De esta declaración salen:
 *   - los formularios del panel (tarea 11.3);
 *   - los valores iniciales de los bloques (tarea 6.x);
 *   - la ayuda contextual de cada pantalla (tarea 11.4).
 *
 * Tipos de campo disponibles:
 *   text | textarea | rich | media | number | url | email | phone | icon | boolean
 *
 * Tipos de bloque (el parcial de vista que lo dibuja):
 *   hero | text_image | card_grid | team | testimonial | cta | faq | contact_form | list | stats
 */

return [

    // =========================================================================
    // Grupos del menú del panel: lo que la persona quiere hacer, no una lista
    // de tablas.
    // =========================================================================
    'menu_groups' => [
        'web' => 'Lo que se ve en la web',
        'content' => 'Cursos y contenidos',
        'people' => 'Personas',
        'sales' => 'Ventas',
        'settings' => 'Ajustes',
    ],

    // =========================================================================
    // Recorrido guiado del primer ingreso (tarea 11.5).
    //
    // Son las tareas básicas en el orden en que conviene hacerlas la primera
    // vez. Se muestra una sola vez, en el Resumen, y se puede cerrar sin hacer
    // nada: no bloquea el trabajo ni obliga a seguir un orden.
    // =========================================================================
    'tour' => [
        'title' => 'Primeros pasos en el panel',
        'intro' => 'Estas son las tareas que conviene dejar listas para que el sitio quede completo. '
            . 'Puedes hacerlas en el orden que quieras y volver aquí cuando termines.',
        'steps' => [
            [
                'title' => 'Mira el resumen',
                'text' => 'Aquí ves estudiantes, cursos publicados, ventas del mes y mensajes recibidos.',
                'path' => 'admin',
                'icon' => 'bi-speedometer2',
            ],
            [
                'title' => 'Revisa lo que se ve en la portada',
                'text' => 'Cada bloque es una sección de la página de inicio: título, imagen y botones.',
                'path' => 'admin/bloques',
                'icon' => 'bi-layout-text-window-reverse',
            ],
            [
                'title' => 'Publica tus cursos',
                'text' => 'Sin publicar, un curso no aparece en el sitio ni se puede comprar.',
                'path' => 'admin/cursos',
                'icon' => 'bi-journal-bookmark',
            ],
            [
                'title' => 'Prepara las plantillas y los videos',
                'text' => 'Son los documentos y grabaciones que recibe quien compra o agenda una cita.',
                'path' => 'admin/plantillas',
                'icon' => 'bi-file-earmark-text',
            ],
            [
                'title' => 'Revisa las ventas y los accesos',
                'text' => 'Aquí se ve cada pago y a qué curso dio acceso.',
                'path' => 'admin/ventas',
                'icon' => 'bi-cash-coin',
            ],
            [
                'title' => 'Invita a quien te ayude',
                'text' => 'Crea cuentas para tu equipo y decide quién puede entrar al panel.',
                'path' => 'admin/usuarios',
                'icon' => 'bi-person-badge',
            ],
        ],
    ],

    // =========================================================================
    // Pantallas del panel. `help` se muestra en la propia pantalla y explica qué
    // controla y qué cambia en el sitio público. Sólo se listan pantallas que
    // existen: un enlace a algo sin construir sería un enlace roto.
    // =========================================================================
    'sections' => [
        [
            'group' => 'web',
            'label' => 'Secciones del sitio',
            'path' => 'admin/bloques',
            'icon' => 'bi-layout-text-window-reverse',
            'help' => 'Los textos e imágenes de las secciones de la web. Guardar deja el cambio preparado: '
                . 'hasta que pulses publicar, quien visita el sitio sigue viendo lo anterior.',
        ],
        [
            'group' => 'content',
            'label' => 'Cursos',
            'path' => 'admin/cursos',
            'icon' => 'bi-journal-bookmark',
            'help' => 'Aquí creas los cursos y su temario. Un curso se ve en el sitio público '
                . 'únicamente cuando está publicado; mientras tanto queda como borrador y nadie lo ve.',
        ],
        [
            'group' => 'content',
            'label' => 'Plantillas',
            'path' => 'admin/plantillas',
            'icon' => 'bi-file-earmark-arrow-down',
            'help' => 'Plantillas descargables. Los archivos se guardan fuera del sitio web: '
                . 'sólo se entregan desde esta plataforma, nunca por un enlace directo.',
        ],
        [
            'group' => 'content',
            'label' => 'Videos',
            'path' => 'admin/videos',
            'icon' => 'bi-play-btn',
            'help' => 'Biblioteca de videos. Al ocultar uno, deja de aparecer en el sitio y '
                . 'deja de poder reproducirse, pero no se borra su información.',
        ],
        [
            'group' => 'content',
            'label' => 'Noticias',
            'path' => 'admin/noticias',
            'icon' => 'bi-newspaper',
            'help' => 'Artículos y novedades. Una noticia programada se publica sola cuando '
                . 'llega la fecha indicada; mientras esté en borrador no es visible.',
        ],
        [
            'group' => 'people',
            'label' => 'Estudiantes',
            'path' => 'admin/estudiantes',
            'icon' => 'bi-people',
            'help' => 'Cada persona con su cuenta, sus cursos y sus compras. Desde aquí puedes '
                . 'conceder o retirar acceso a un curso a mano, y queda registrado quién lo hizo y cuándo.',
        ],
        [
            'group' => 'people',
            'label' => 'Usuarios y roles',
            'path' => 'admin/usuarios',
            'icon' => 'bi-person-badge',
            'help' => 'Aquí creas las cuentas de tu equipo y decides quién entra al panel (administración) '
                . 'y quién al área de estudiantes. Al crear una cuenta el sistema genera una contraseña '
                . 'temporal que verás una sola vez: entrégasela a la persona y el sistema le pedirá '
                . 'cambiarla al entrar. Nadie puede quitarse su propio acceso, de modo que el panel '
                . 'nunca se queda sin administradores.',
        ],
        [
            'group' => 'people',
            'label' => 'Suscriptores',
            'path' => 'admin/suscriptores',
            'icon' => 'bi-envelope-at',
            'help' => 'Personas suscritas a las novedades. Se pueden exportar para usar la lista '
                . 'en el envío de correos.',
        ],
        [
            'group' => 'people',
            'label' => 'Accesos a contenido',
            'path' => 'admin/accesos-contenido',
            'icon' => 'bi-shield-check',
            'help' => 'Registro de quién intentó abrir material protegido y con qué resultado. '
                . 'Sirve para investigar enlaces vencidos o accesos sin permiso.',
        ],
        [
            'group' => 'settings',
            'label' => 'Módulos del sitio',
            'path' => 'admin/modulos',
            'icon' => 'bi-toggles',
            'help' => 'Aquí decides qué secciones aparecen en la web (cursos, plantillas, videos, noticias). '
                . 'Al ocultar una sección desaparece del menú y de la portada; su contenido se conserva y '
                . 'puedes volver a mostrarla cuando quieras.',
        ],
        [
            'group' => 'sales',
            'label' => 'Ventas',
            'path' => 'admin/ventas',
            'icon' => 'bi-graph-up-arrow',
            'help' => 'Movimiento de ventas del período que elijas. Muestra cada orden con su estado: '
                . 'una orden pendiente todavía no da acceso al curso.',
        ],
        [
            'group' => 'sales',
            'label' => 'Pagos',
            'path' => 'admin/pagos',
            'icon' => 'bi-credit-card',
            'help' => 'Estado de la pasarela de pagos. Si aparece como pendiente, el sitio no '
                . 'cobra ni concede acceso a cursos de pago.',
        ],
    ],

    // =========================================================================
    // Bloques del sitio público. Cada clave coincide con `blocks.key_name`.
    // =========================================================================
    'blocks' => [

        'hero' => [
            'label' => 'Bloque de inicio',
            'help' => 'Es lo primero que ve quien entra al sitio. Cada diapositiva tiene su título, '
                . 'su texto, su imagen de fondo y su botón.',
            'type' => 'hero',
            'group' => 'web',
            'sort_order' => 10,
            'anchor' => '#hero',
            'in_menu' => true,
            'menu_label' => 'Inicio',
            'item_singular' => 'Diapositiva',
            'item_fields' => [
                'title' => ['label' => 'Título', 'type' => 'text', 'required' => true],
                'body' => ['label' => 'Texto', 'type' => 'textarea'],
                'media' => ['label' => 'Imagen de fondo', 'type' => 'media', 'profile' => 'hero_slide',
                    'help' => 'Se recorta sola al tamaño del banner, así que no necesita medidas exactas.'],
                'link_label' => ['label' => 'Texto del botón', 'type' => 'text'],
                'link_url' => ['label' => 'Enlace del botón', 'type' => 'text',
                    'help' => 'Por ejemplo #services para bajar a Servicios, o /cursos.'],
            ],
        ],

        'about_vision' => [
            'label' => 'Sección de visión',
            'help' => 'El rótulo pequeño, el título grande y los tres recuadros con su propósito.',
            'type' => 'text_image',
            'group' => 'web',
            'sort_order' => 20,
            'anchor' => '#about',
            'in_menu' => true,
            'menu_label' => 'Acerca de',
            'eyebrow' => ['label' => 'Rótulo pequeño', 'type' => 'text'],
            'heading' => ['label' => 'Título grande', 'type' => 'text'],
            'item_singular' => 'Recuadro',
            'item_fields' => [
                'title' => ['label' => 'Título del recuadro', 'type' => 'text', 'required' => true],
                'body' => ['label' => 'Texto', 'type' => 'textarea'],
                'icon' => ['label' => 'Ícono', 'type' => 'icon',
                    'help' => 'Se elige de la lista; no hay que escribir ningún código.'],
            ],
        ],

        'services' => [
            'label' => 'Sección de servicios',
            'help' => 'Las áreas de práctica que se muestran como tarjetas.',
            'type' => 'card_grid',
            'group' => 'web',
            'sort_order' => 30,
            'anchor' => '#services',
            'in_menu' => true,
            'menu_label' => 'Servicios',
            'eyebrow' => ['label' => 'Rótulo pequeño', 'type' => 'text'],
            'heading' => ['label' => 'Título grande', 'type' => 'text'],
            'settings' => [
                'columns' => ['label' => 'Tarjetas por fila en pantallas grandes', 'type' => 'number', 'default' => 4],
            ],
            'item_singular' => 'Servicio',
            'item_fields' => [
                'title' => ['label' => 'Nombre del servicio', 'type' => 'text', 'required' => true],
                'body' => ['label' => 'Descripción', 'type' => 'textarea'],
                'icon' => ['label' => 'Ícono', 'type' => 'icon'],
            ],
        ],

        'debt_recovery' => [
            'label' => 'Sección de recuperación de cartera',
            'help' => 'La segunda franja de tarjetas, dedicada al cobro de deudas. '
                . 'No aparece en el menú: se llega a ella bajando por la página.',
            'type' => 'card_grid',
            'group' => 'web',
            'sort_order' => 35,
            'anchor' => '#recuperacion',
            'heading' => ['label' => 'Título de la sección', 'type' => 'text'],
            'settings' => [
                'columns' => ['label' => 'Tarjetas por fila en pantallas grandes', 'type' => 'number', 'default' => 4],
            ],
            'item_singular' => 'Servicio',
            'item_fields' => [
                'title' => ['label' => 'Nombre del servicio', 'type' => 'text', 'required' => true],
                'body' => ['label' => 'Descripción', 'type' => 'textarea'],
                'icon' => ['label' => 'Ícono', 'type' => 'icon'],
            ],
        ],

        'clients' => [
            'label' => 'Sección de clientes',
            'help' => 'Empresas o personas que han confiado en la firma.',
            'type' => 'testimonial',
            'group' => 'web',
            'sort_order' => 40,
            'anchor' => '#testimonials',
            'in_menu' => true,
            'menu_label' => 'Nuestros clientes',
            'heading' => ['label' => 'Título de la sección', 'type' => 'text'],
            'item_singular' => 'Cliente',
            'item_fields' => [
                'title' => ['label' => 'Nombre', 'type' => 'text', 'required' => true],
                'subtitle' => ['label' => 'Descripción corta', 'type' => 'text'],
                'data' => ['label' => 'Calificación (estrellas)', 'type' => 'number', 'default' => 5],
            ],
        ],

        'cta' => [
            'label' => 'Franja de invitación a la acción',
            'help' => 'La banda con imagen de fondo y un botón. Sirve para invitar a agendar una cita '
                . 'o a registrarse.',
            'type' => 'cta',
            'group' => 'web',
            'sort_order' => 50,
            'heading' => ['label' => 'Título', 'type' => 'text'],
            'intro' => ['label' => 'Texto', 'type' => 'textarea'],
            'media' => ['label' => 'Imagen de fondo', 'type' => 'media', 'profile' => 'cta_banner'],
            'cta_label' => ['label' => 'Texto del botón', 'type' => 'text'],
            'cta_url' => ['label' => 'Enlace del botón', 'type' => 'text'],
        ],

        'cases' => [
            'label' => 'Sección de casos destacados',
            'help' => 'Ejemplos de casos resueltos, con imagen y descripción.',
            'type' => 'card_grid',
            'group' => 'web',
            'sort_order' => 60,
            'anchor' => '#casos',
            'in_menu' => true,
            'menu_label' => 'Portafolio',
            'eyebrow' => ['label' => 'Rótulo pequeño', 'type' => 'text'],
            'heading' => ['label' => 'Título grande', 'type' => 'text'],
            'intro' => ['label' => 'Texto de introducción', 'type' => 'textarea'],
            'settings' => [
                'columns' => ['label' => 'Tarjetas por fila en pantallas grandes', 'type' => 'number', 'default' => 3],
                'media_on_top' => ['label' => 'Mostrar la imagen encima del texto', 'type' => 'boolean', 'default' => true],
            ],
            'item_singular' => 'Caso',
            'item_fields' => [
                'title' => ['label' => 'Título del caso', 'type' => 'text', 'required' => true],
                'body' => ['label' => 'Descripción', 'type' => 'textarea'],
                'media' => ['label' => 'Imagen', 'type' => 'media', 'profile' => 'news_cover'],
            ],
        ],

        'team' => [
            'label' => 'Sección de equipo',
            'help' => 'Las personas de la firma, con su foto, su cargo y sus redes.',
            'type' => 'team',
            'group' => 'web',
            'sort_order' => 70,
            'anchor' => '#team',
            'in_menu' => true,
            'menu_label' => 'Equipo',
            'eyebrow' => ['label' => 'Rótulo pequeño', 'type' => 'text'],
            'heading' => ['label' => 'Texto de presentación', 'type' => 'textarea'],
            'item_singular' => 'Integrante',
            'item_fields' => [
                'title' => ['label' => 'Nombre', 'type' => 'text', 'required' => true],
                'subtitle' => ['label' => 'Cargo', 'type' => 'text'],
                'media' => ['label' => 'Fotografía', 'type' => 'media', 'profile' => 'team_photo'],
                'link_url' => ['label' => 'LinkedIn', 'type' => 'url'],
                'data' => ['label' => 'Instagram', 'type' => 'url'],
            ],
        ],

        'faq' => [
            'label' => 'Sección de preguntas frecuentes',
            'help' => 'Las preguntas que más hacen los clientes y sus respuestas.',
            'type' => 'faq',
            'group' => 'web',
            'sort_order' => 80,
            'anchor' => '#faq',
            'in_menu' => false,
            'menu_label' => 'Preguntas frecuentes',
            'eyebrow' => ['label' => 'Rótulo pequeño', 'type' => 'text'],
            'heading' => ['label' => 'Título de la sección', 'type' => 'text'],
            'intro' => ['label' => 'Texto de introducción', 'type' => 'text'],
            'item_singular' => 'Pregunta',
            'item_fields' => [
                'title' => ['label' => 'Pregunta', 'type' => 'text', 'required' => true],
                'body' => ['label' => 'Respuesta', 'type' => 'textarea'],
            ],
        ],

        'contact_info' => [
            'label' => 'Sección de contacto',
            'help' => 'Los datos de contacto y el formulario para agendar una cita.',
            'type' => 'contact_form',
            'group' => 'web',
            'sort_order' => 90,
            'anchor' => '#contact',
            'in_menu' => true,
            'menu_label' => 'Contacto',
            'eyebrow' => ['label' => 'Rótulo pequeño', 'type' => 'text'],
            'heading' => ['label' => 'Título', 'type' => 'text'],
            'intro' => ['label' => 'Texto de invitación', 'type' => 'text'],
            'item_singular' => 'Dato de contacto',
            'item_fields' => [
                'title' => ['label' => 'Título del dato', 'type' => 'text', 'required' => true],
                'body' => ['label' => 'Valor', 'type' => 'textarea',
                    'help' => 'Por ejemplo la dirección o los teléfonos.'],
                'icon' => ['label' => 'Ícono', 'type' => 'icon'],
            ],
        ],

        'footer' => [
            'label' => 'Pie de página',
            'help' => 'Los enlaces que aparecen al final de todas las páginas del sitio. '
                . 'La columna de cada enlace se elige en el campo "Columna".',
            'type' => 'footer',
            'group' => 'web',
            'sort_order' => 100,
            'heading' => ['label' => 'Título de la columna', 'type' => 'text'],
            // Los datos de contacto del pie se toman de los Ajustes del sitio
            // para no tener que repetirlos en dos sitios.
            'item_singular' => 'Enlace',
            'item_fields' => [
                'title' => ['label' => 'Texto del enlace', 'type' => 'text', 'required' => true],
                'subtitle' => ['label' => 'Columna', 'type' => 'text',
                    'help' => 'Por ejemplo "Enlaces Útiles" o "Especialidades".'],
                'link_url' => ['label' => 'A dónde lleva', 'type' => 'text',
                    'help' => 'Escribe una dirección interna como /cursos, o completa https://...'],
            ],
        ],

        'stats' => [
            'label' => 'Franja de cifras',
            'help' => 'Números que se animan al aparecer, por ejemplo años de experiencia o casos atendidos.',
            'type' => 'stats',
            'group' => 'web',
            'sort_order' => 110,
            'item_singular' => 'Cifra',
            'item_fields' => [
                'title' => ['label' => 'Texto de la cifra', 'type' => 'text', 'required' => true],
                'data' => ['label' => 'Valor numérico', 'type' => 'number', 'default' => 0],
                'icon' => ['label' => 'Ícono', 'type' => 'icon'],
            ],
        ],
    ],

    // =========================================================================
    // Módulos de contenido. `status` decide si se ven en el sitio.
    // =========================================================================
    'modules' => [
        'courses' => [
            'label' => 'Cursos',
            'menu_label' => 'Cursos',
            'description' => 'Los cursos gratuitos y de pago, con sus lecciones.',
            'sort_order' => 10,
            'show_in_menu' => true,
            'show_on_home' => true,
        ],
        'templates' => [
            'label' => 'Plantillas',
            'menu_label' => 'Plantillas',
            'description' => 'Plantillas descargables para quien visita el sitio.',
            'sort_order' => 20,
            'show_in_menu' => true,
            'show_on_home' => true,
        ],
        'videos' => [
            'label' => 'Videos',
            'menu_label' => 'Videos',
            'description' => 'La biblioteca de videos con reproductor dentro del sitio.',
            'sort_order' => 30,
            'show_in_menu' => true,
            'show_on_home' => true,
        ],
        'news' => [
            'label' => 'Noticias',
            'menu_label' => 'Noticias',
            'description' => 'Las novedades y artículos que se publican en el sitio.',
            'sort_order' => 40,
            'show_in_menu' => true,
            'show_on_home' => true,
        ],
    ],

    // =========================================================================
    // Significado de cada estado, tal como se le explica a quien administra.
    // =========================================================================
    'statuses' => [
        'active' => [
            'label' => 'Visible',
            'help' => 'Se ve en el sitio web.',
        ],
        'inactive' => [
            'label' => 'Oculto al público',
            'help' => 'No se ve en el sitio, pero su contenido se conserva aquí y se puede volver a mostrar.',
        ],
        'hidden' => [
            'label' => 'Sólo por enlace directo',
            'help' => 'No aparece en las listas ni en el menú, pero quien tenga el enlace puede abrirlo.',
        ],
        'deleted' => [
            'label' => 'Eliminado',
            'help' => 'Está en la papelera. Se puede recuperar durante 30 días; después se borra definitivamente.',
        ],
    ],

    // =========================================================================
    // Íconos disponibles. Se muestran con su nombre en español para que nadie
    // tenga que escribir una clase de Bootstrap Icons.
    // =========================================================================
    'icons' => [
        'bi bi-lightbulb-fill' => 'Bombilla (idea)',
        'bi bi-arrow-repeat' => 'Flechas circulares (cambio)',
        'bi bi-bullseye' => 'Diana (objetivo)',
        'bi bi-check-circle-fill' => 'Visto bueno',
        'bi bi-activity' => 'Actividad',
        'bi bi-broadcast' => 'Difusión',
        'bi bi-easel' => 'Atril',
        'bi bi-bounding-box-circles' => 'Círculos',
        'bi bi-geo-alt' => 'Ubicación',
        'bi bi-telephone' => 'Teléfono',
        'bi bi-envelope' => 'Correo',
        'bi bi-people' => 'Personas',
        'bi bi-journal-richtext' => 'Documento',
        'bi bi-headset' => 'Soporte',
        'bi bi-emoji-smile' => 'Satisfacción',
        'bi bi-briefcase' => 'Maletín',
        'bi bi-shield-check' => 'Escudo (seguridad)',
        'bi bi-bank' => 'Institución',
        'bi bi-file-earmark-text' => 'Contrato',
        'bi bi-graph-up-arrow' => 'Crecimiento',
    ],
];
