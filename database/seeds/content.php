<?php

declare(strict_types=1);

/**
 * Contenido inicial: los textos, enlaces e imágenes del sitio actual.
 *
 * Este archivo es la fuente de la carga inicial. Está escrito para poder
 * revisarlo y corregirlo sin conocimientos de programación: cada bloque lleva su
 * clave (la misma de `config/admin_labels.php`) y la lista de sus elementos.
 *
 * Convenciones:
 *   - 'file'    ruta de la imagen dentro de public/assets (se reprocesa por el
 *               pipeline para recortarla y aligerarla).
 *   - 'profile' destino en config/images.php: define la proporción del recorte.
 *   - 'media'   imagen del propio bloque (banner, fondo...).
 *   - En los textos, una línea que empieza por "- " se muestra como punto de
 *     lista; una línea en blanco separa párrafos.
 *
 * Los textos son los del sitio en producción, sin cambios.
 *
 * Lo que NO está aquí y por qué:
 *
 *   - 'stats' (franja de cifras): el sitio anterior tenía el archivo
 *     `modulos/stats.php`, pero ninguna página lo incluía y sus cuatro cifras
 *     estaban todas en cero. La franja se crea vacía y aparece en el panel para
 *     que se rellene cuando el despacho decida qué cifras publicar.
 *   - 'princing' (tabla de precios): igual, archivo suelto sin usar. Ni siquiera
 *     se ofrece en el panel.
 *   - Los enlaces del pie "Aviso Legal" y "Política de Privacidad" apuntaban a
 *     "#" porque su página no existe. Se añadirán cuando se redacten.
 */

return [

    // =========================================================================
    // Ajustes del sitio (tabla settings)
    // =========================================================================
    'settings' => [
        'site_name' => 'González & Ballesteros',
        'site_email' => 'gerencia@gonzalezballesteros.com',
        'site_phone' => '3188517817 - 3017543649',
        'site_address' => 'Bogotá y Barranquilla',
        'site_coverage' => 'Atención virtual o presencial en toda Colombia',
        'whatsapp' => '573188517817',
        'instagram' => 'https://www.instagram.com/gonzalezyballesteros_abogados/',
        'linkedin' => 'https://www.linkedin.com/company/gonzalez-ballesteros',
        'currency' => 'COP',
        'currency_symbol' => '$',
    ],

    // =========================================================================
    // Módulos de contenido.
    //
    // Se crean INACTIVOS a propósito: sus páginas (catálogo de cursos, sección
    // de noticias...) todavía no existen, y un enlace a una página que no existe
    // sería un enlace roto. Se activarán al implementar cada sección.
    // =========================================================================
    'modules' => [
        'courses' => ['status' => 'active'],
        'templates' => ['status' => 'inactive'],
        'videos' => ['status' => 'inactive'],
        'news' => ['status' => 'inactive'],
    ],

    // =========================================================================
    // Bloques del sitio público
    // =========================================================================
    'blocks' => [

        // --- Inicio: carrusel de tres diapositivas --------------------------
        'hero' => [
            'items' => [
                [
                    'title' => '¿Quiénes somos?',
                    'body' => 'Somos una firma de abogados especializada en derecho laboral y civil, dedicada a la '
                        . 'asesoría y consultoría integral. Nuestra experiencia está respaldada por años de trabajo '
                        . 'con empresas y firmas de abogados, lo que nos permite ofrecer soluciones jurídicas '
                        . 'efectivas y personalizadas.',
                    'link_label' => 'EMPEZAR',
                    'link_url' => '#services',
                    'media' => [
                        'file' => 'img/hero-carousel/hero1.jpg',
                        'profile' => 'hero_slide',
                        'alt' => 'Equipo de la firma en una reunión de asesoría legal',
                    ],
                ],
                [
                    'title' => 'Soluciones legales con propósito',
                    'body' => 'Abordamos cada caso con visión integral. Te acompañamos en los procesos de relaciones '
                        . 'laborales, proceso pensional con decisiones claves para la vejez, manejo civil y comercial '
                        . 'con un enfoque estratégico. Nuestro objetivo es prevenir, negociar y construir confianza.',
                    'link_label' => 'EMPEZAR',
                    'link_url' => '#services',
                    'media' => [
                        'file' => 'img/hero-carousel/hero2.jpg',
                        'profile' => 'hero_slide',
                        'alt' => 'Asesoría legal a empresas',
                    ],
                ],
                [
                    'title' => 'Tu tranquilidad legal, nuestro compromiso',
                    'body' => 'Estamos preparados para orientarte en decisiones claves de tu empresa. Diseñamos '
                        . 'estrategias jurídicas que se adaptan a tus necesidades.',
                    'link_label' => 'EMPEZAR',
                    'link_url' => '#services',
                    'media' => [
                        'file' => 'img/hero-carousel/hero3.jpg',
                        'profile' => 'hero_slide',
                        'alt' => 'Acompañamiento jurídico estratégico',
                    ],
                ],
            ],
        ],

        // --- Visión ---------------------------------------------------------
        'about_vision' => [
            'eyebrow' => 'VISIÓN',
            'heading' => 'Ser líderes en el ámbito legal por innovación y adaptabilidad',
            'items' => [
                [
                    'title' => 'Propósito de Liderazgo Legal',
                    'body' => 'Aspiramos a ser reconocidos como líderes en el campo legal en 2030, fusionando '
                        . 'experiencia con innovación.',
                    'icon' => 'bi bi-lightbulb-fill',
                ],
                [
                    'title' => 'Adaptación al Cambio',
                    'body' => 'Nos comprometemos a adaptarnos a los cambios en el entorno legal, asegurando la '
                        . 'relevancia y efectividad de nuestros servicios.',
                    'icon' => 'bi bi-arrow-repeat',
                ],
                [
                    'title' => 'Objetivos Estratégicos',
                    'body' => 'Nuestro objetivo es el éxito de nuestros clientes, trabajando incansablemente para '
                        . 'defender sus intereses y necesidades.',
                    'icon' => 'bi bi-bullseye',
                ],
            ],
        ],

        // --- Servicios: áreas de práctica -----------------------------------
        'services' => [
            'eyebrow' => 'Servicios',
            'heading' => 'Nuestras áreas',
            'settings' => ['columns' => 4],
            'items' => [
                [
                    'title' => 'DERECHO LABORAL INDIVIDUAL Y COLECTIVO',
                    'body' => 'Ofrecemos asesoría integral a empresas, incluyendo la elaboración de documentos '
                        . 'jurídicos laborales, desarrollo de políticas de gestión de personal, planificación de '
                        . 'retiros y manejo de conflictos laborales, para optimizar el ambiente laboral y alcanzar '
                        . 'las metas organizacionales.',
                    'icon' => 'bi bi-check-circle-fill',
                ],
                [
                    'title' => 'SEGURIDAD SOCIAL',
                    'body' => 'Acompañamos a los afiliados al Sistema de Pensiones, analizando el impacto de la '
                        . 'reforma pensional en su futuro y el de su familia, ayudándoles a tomar decisiones '
                        . 'oportunas en materia pensional.',
                    'icon' => 'bi bi-check-circle-fill',
                ],
                [
                    'title' => 'DERECHO PROCESAL LABORAL Y TUTELAS',
                    'body' => 'Brindamos defensa en procesos judiciales y tutelas relacionados con conflictos '
                        . 'laborales, así como un manejo estratégico de las contingencias laborales en la empresa, '
                        . 'enfocándonos en la prevención y el control de riesgos jurídicos.',
                    'icon' => 'bi bi-check-circle-fill',
                ],
                [
                    'title' => 'DERECHO CIVIL, FAMILIA Y COMERCIAL',
                    'body' => "Ofrecemos acompañamiento en contratación comercial con un enfoque empresarial, "
                        . "asegurando el cumplimiento normativo corporativo, la representación legal, la elaboración "
                        . "de actas de comité y juntas directivas, así como la implementación de buenas prácticas de "
                        . "gobierno corporativo.\n\nAdicionalmente, te acompañamos en los trámites familiares de "
                        . "sucesiones y divorcios.",
                    'icon' => 'bi bi-check-circle-fill',
                ],
            ],
        ],

        // --- Recuperación de cartera -----------------------------------------
        // Segunda franja de tarjetas, la que en el sitio anterior estaba en
        // `modulos/services.php` y no llevaba identificador ni enlace de menú.
        'debt_recovery' => [
            'heading' => 'Recuperación de Cartera Efectiva',
            'settings' => ['columns' => 4],
            'items' => [
                [
                    'title' => 'Gestión de Deudas y Obligaciones',
                    'body' => 'Nos encargamos de gestionar la recuperación de deudas mediante negociaciones '
                        . 'amistosas y acciones legales cuando sea necesario.',
                    'icon' => 'bi bi-activity',
                ],
                [
                    'title' => 'Transparencia en el Proceso de Recuperación',
                    'body' => 'Mantenemos a nuestros clientes informados en cada etapa del proceso de '
                        . 'recuperación, actuando con total transparencia y profesionalismo.',
                    'icon' => 'bi bi-broadcast',
                ],
                [
                    'title' => 'Recuperación Efectiva de Obligaciones',
                    'body' => 'Nuestro objetivo es asegurar que nuestros clientes logren la recuperación '
                        . 'efectiva de las obligaciones a su favor, actuando con eficiencia.',
                    'icon' => 'bi bi-easel',
                ],
                [
                    'title' => 'Asesoría Constante en Recuperación',
                    'body' => 'Estamos aquí para ayudar a nuestros clientes a enfrentar desafíos legales, '
                        . 'ofreciéndoles la asesoría que necesitan en cada momento.',
                    'icon' => 'bi bi-bounding-box-circles',
                ],
            ],
        ],

        // --- Clientes -------------------------------------------------------
        'clients' => [
            'heading' => 'Algunos de nuestros clientes',
            'items' => [
                [
                    'title' => 'PERCOSFAR LTDA',
                    'subtitle' => 'Perfumería Cosméticos y Farmacéuticos Limitada',
                    'data' => ['stars' => 5],
                ],
                [
                    'title' => 'INFARVET S.A.S',
                    'subtitle' => 'Inversiones Farmacéuticas y Veterinarias S.A.S',
                    'data' => ['stars' => 5],
                ],
                [
                    'title' => 'MBH S.A.S',
                    'subtitle' => 'Motores Bombas y Herramientas MG S.A.S',
                    'data' => ['stars' => 5],
                ],
            ],
        ],

        // --- Franja de invitación a la acción -------------------------------
        'cta' => [
            'heading' => '¿Necesitas orientación legal?',
            'intro' => 'En González & Ballesteros estamos preparados para ayudarte a tomar decisiones seguras, '
                . 'prevenir conflictos y proteger tus intereses. Agenda una cita con nosotros y recibe una asesoría '
                . 'personalizada.',
            'cta_label' => 'Agendar cita',
            'cta_url' => '#contact',
            'media' => [
                'file' => 'img/cta-bg.jpg',
                'profile' => 'cta_banner',
                'alt' => '',
            ],
        ],

        // --- Casos destacados -----------------------------------------------
        'cases' => [
            'eyebrow' => 'CASOS DESTACADOS',
            'intro' => 'Conoce algunos de los casos donde hemos hecho la diferencia.',
            'settings' => ['columns' => 3, 'media_on_top' => true],
            'items' => [
                [
                    'title' => 'Negociación de retiro laboral',
                    'body' => 'Diseñamos una estrategia legal para una terminación de contrato amistosa, cuidando los '
                        . 'derechos del trabajador y la reputación de la empresa.',
                    'icon' => 'bi bi-check-circle-fill',
                    'media' => [
                        'file' => 'img/portfolio/retiro.jpg',
                        'profile' => 'news_cover',
                        'alt' => 'Negociación de retiro laboral',
                    ],
                ],
                [
                    'title' => 'Asesoría en pensión',
                    'body' => 'Acompañamos a nuestros clientes en la revisión de sus semanas cotizadas y derechos '
                        . 'pensionales, brindando claridad y estrategias personalizadas.',
                    'icon' => 'bi bi-check-circle-fill',
                    'media' => [
                        'file' => 'img/portfolio/pensional.jpg',
                        'profile' => 'news_cover',
                        'alt' => 'Asesoría en pensión',
                    ],
                ],
                [
                    'title' => 'Manejo de conflictos laborales',
                    'body' => 'Implementamos soluciones legales efectivas en conflictos entre empleadores y '
                        . 'trabajadores, priorizando el diálogo y la prevención del litigio.',
                    'icon' => 'bi bi-check-circle-fill',
                    'media' => [
                        'file' => 'img/portfolio/contrato.jpg',
                        'profile' => 'news_cover',
                        'alt' => 'Manejo de conflictos laborales',
                    ],
                ],
            ],
        ],

        // --- Equipo ---------------------------------------------------------
        'team' => [
            'eyebrow' => 'Equipo',
            'heading' => 'Abogadas laboralistas y civilistas con más de 20 años de experiencia en el manejo '
                . 'estratégico de relaciones laborales y comerciales, representación legal, litigios laborales y '
                . 'civiles, negociación de conflictos y gestión sindical.',
            'items' => [
                [
                    'title' => 'Claudia Marcela Gonzalez',
                    'subtitle' => 'Socia Fundadora',
                    'link_url' => 'https://www.linkedin.com/in/claudia-marcela-gonzalez-martinez-558549121',
                    'data' => ['instagram' => 'https://www.instagram.com/gonzalezyballesteros_abogados_/'],
                    'media' => [
                        'file' => 'img/team/team-3.png',
                        'profile' => 'team_photo',
                        'alt' => 'Claudia Marcela Gonzalez, socia fundadora',
                    ],
                ],
                [
                    'title' => 'Ana Paola Ballesteros',
                    'subtitle' => 'Socia Fundadora',
                    'link_url' => 'https://www.linkedin.com/in/ana-paola-ballesteros-42b32a68/',
                    'data' => ['instagram' => 'https://www.instagram.com/gonzalezyballesteros_abogados_/'],
                    'media' => [
                        'file' => 'img/team/team-4.png',
                        'profile' => 'team_photo',
                        'alt' => 'Ana Paola Ballesteros, socia fundadora',
                    ],
                ],
            ],
        ],

        // --- Preguntas frecuentes -------------------------------------------
        'faq' => [
            'heading' => 'Preguntas frecuentes',
            'eyebrow' => 'Asesoría clara, respuestas concretas',
            'items' => [
                [
                    'title' => '¿Cómo saber si estoy cotizando correctamente para mi pensión?',
                    'body' => 'Analizamos tu historial laboral y de aportes para verificar si estás cumpliendo con '
                        . 'los requisitos del sistema pensional colombiano. También te orientamos sobre qué régimen '
                        . 'te conviene más, cómo optimizar tus cotizaciones y qué hacer en caso de inconsistencias '
                        . 'en tu historia laboral.',
                ],
                [
                    'title' => '¿Cuáles son las obligaciones laborales que tiene una empresa en Colombia?',
                    'body' => "- Tener Reglamento Interno de Trabajo\n"
                        . "- Tener Reglamento de Higiene y Seguridad Industrial\n"
                        . "- Tener Autorización para Laborar Horas Extras\n"
                        . "- Contar con un vigía de seguridad y salud en el trabajo\n"
                        . "- Conformar el Comité de Convivencia Laboral\n"
                        . "- Entre otros.",
                ],
                [
                    'title' => '¿Cómo evitar sanciones del Ministerio de Trabajo?',
                    'body' => 'Cumpliendo con las obligaciones laborales, seguridad social, pago oportuno de '
                        . 'salarios y prestaciones sociales, prevención y salud en el trabajo, entre otros.',
                ],
                [
                    'title' => '¿Qué medidas preventivas debo implementar para evitar demandas laborales?',
                    'body' => "- Documentación integral y contratos claros\n"
                        . "- Cumplimiento riguroso de pagos y prestaciones\n"
                        . "- Implementación y actualización del SG-SST\n"
                        . "- Reglamento interno y políticas claras\n"
                        . "- Formación y comunicación continua\n"
                        . "- Control y supervisión de la jornada laboral\n"
                        . "- Auditorías internas periódicas\n"
                        . "- Asesoría legal preventiva",
                ],
            ],
        ],

        // --- Contacto -------------------------------------------------------
        'contact_info' => [
            'heading' => 'Contacto',
            'eyebrow' => '¿Necesitas ayuda?',
            'intro' => 'Agenda tu cita',
            'items' => [
                [
                    'title' => 'DIRECCIÓN',
                    'body' => "Bogotá y Barranquilla\nAtención en toda Colombia",
                    'icon' => 'bi bi-geo-alt',
                ],
                [
                    'title' => 'Llámanos',
                    'body' => '3188517817 - 3017543649',
                    'icon' => 'bi bi-telephone',
                ],
                [
                    'title' => 'Envíenos un correo electrónico',
                    'body' => 'gerencia@gonzalezballesteros.com',
                    'icon' => 'bi bi-envelope',
                ],
            ],
        ],

        // --- Pie de página --------------------------------------------------
        //
        // El sitio actual tenía aquí dos enlaces sin destino (Aviso Legal y
        // Política de Privacidad). No se cargan porque no existe la página: es
        // preferible no mostrar un enlace que no lleva a ninguna parte.
        'footer' => [
            'items' => [
                ['title' => 'Inicio', 'subtitle' => 'Enlaces Útiles', 'link_url' => '/'],
                ['title' => 'Sobre Nosotros', 'subtitle' => 'Enlaces Útiles', 'link_url' => '#about'],
                ['title' => 'Servicios', 'subtitle' => 'Enlaces Útiles', 'link_url' => '#services'],
                ['title' => 'Contacto', 'subtitle' => 'Enlaces Útiles', 'link_url' => '#contact'],

                ['title' => 'Derecho Laboral', 'subtitle' => 'Especialidades', 'link_url' => '#services'],
                ['title' => 'Derecho Civil', 'subtitle' => 'Especialidades', 'link_url' => '#services'],
                ['title' => 'Seguridad Social', 'subtitle' => 'Especialidades', 'link_url' => '#services'],
                ['title' => 'Consultoría Legal', 'subtitle' => 'Especialidades', 'link_url' => '#services'],
                ['title' => 'Litigios Estratégicos', 'subtitle' => 'Especialidades', 'link_url' => '#services'],
            ],
        ],
    ],
];
