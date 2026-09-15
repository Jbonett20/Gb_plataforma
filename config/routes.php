<?php

declare(strict_types=1);

/**
 * Rutas de la aplicación.
 *
 * Los middleware se declaran con los alias de config/middleware.php y se
 * aplican en el orden indicado. La tarea 3.x termina de conectarlos; hasta
 * entonces las rutas no los referencian para que el sitio arranque.
 *
 *   $router->get('/cursos/{slug}', 'CourseController@show', ['auth'], 'courses.show');
 */

use GB\Support\Router;

return static function (Router $router): void {
    // --- Sitio público ------------------------------------------------------
    $router->get('/', 'PublicController@home', [], 'home');
    $router->get('/cursos', 'CourseController@index', [], 'courses.index');
    $router->get('/cursos/{slug}', 'CourseController@show', [], 'courses.show');
    $router->get('/cursos/{slug}/comprar', 'PaymentController@start', ['auth', 'role:Student'], 'payments.start');
    $router->get('/pago/resultado', 'PaymentController@result', [], 'payments.result');

    // Notificaciones de la pasarela. Es pública porque la llama el proveedor,
    // no una persona: su seguridad no depende de una sesión sino de que la
    // firma pueda verificarse. Sin firma válida no se concede ningún acceso.
    $router->post('/webhooks/{provider}', 'PaymentWebhookController@handle', [], 'payments.webhook');
    $router->get('/contenido/lecciones/{lesson}', 'ProtectedContentController@lesson', ['auth', 'role:Student'], 'protected.lesson');
    $router->get('/aula/lecciones/{lesson}', 'ProtectedContentController@player', ['auth', 'role:Student'], 'protected.player');
    $router->get('/plantillas', 'TemplateController@index', [], 'templates.index');
    $router->get('/plantillas/{slug}', 'TemplateController@show', [], 'templates.show');
    $router->get('/plantillas/{slug}/descargar', 'TemplateController@download', [], 'templates.download');
    $router->get('/videos', 'VideoController@index', [], 'videos.index');
    $router->post('/videos/{slug}/reproducir', 'VideoController@recordView', ['csrf'], 'videos.play');
    $router->get('/videos/{slug}', 'VideoController@show', [], 'videos.show');
    $router->get('/noticias', 'NewsController@index', [], 'news.index');
    $router->get('/noticias/{slug}', 'NewsController@show', [], 'news.show');

    // Formulario de contacto: exige token de seguridad y limita los envíos
    // repetidos desde un mismo origen. Se cuenta por dirección IP: incluir un
    // campo del propio formulario repartiría los intentos entre varios
    // contadores y debilitaría el límite.
    // Quien escriba la dirección a mano —o llegue desde un enlace antiguo— va al
    // formulario de la portada, en vez de encontrarse una página de error.
    $router->get('/contacto', 'ContactController@show', [], 'contact.show');
    $router->post('/contacto', 'ContactController@send', ['csrf', 'throttle:contact'], 'contact.send');
    $router->get('/suscripcion', 'SubscriberController@show', [], 'newsletter.show');
    $router->post('/suscripcion', 'SubscriberController@store', ['csrf', 'throttle:newsletter'], 'newsletter.store');

    // --- Carga de imágenes (panel) ------------------------------------------
    // Sólo para SuperAdmin y siempre con token de seguridad: toda imagen pasa
    // por el pipeline que la recorta a la proporción de su destino.
    $adminMedia = ['auth', 'role:SuperAdmin', 'csrf'];

    // Acceso exclusivo de administración. Es una puerta separada de la del
    // área de estudiantes: valida contra las mismas cuentas, pero sólo deja
    // entrar al rol de superadministración.
    $router->get('/admin/ingresar', 'Admin\\AuthController@showLogin', ['guest'], 'admin.login');
    $router->post('/admin/ingresar', 'Admin\\AuthController@login',
        ['guest', 'csrf', 'throttle:login,email'], 'admin.login.attempt');
    // Cambio de la contraseña inicial, antes de poder usar el panel.
    $router->get('/admin/clave', 'Admin\AuthController@showPassword', ['auth', 'role:SuperAdmin'], 'admin.password');
    $router->post('/admin/clave', 'Admin\AuthController@updatePassword', ['auth', 'role:SuperAdmin', 'csrf'], 'admin.password.update');
    // Entrada del panel. Es el destino al que llega quien administra tras
    // iniciar sesión, así que tiene que existir antes que ninguna otra pantalla.
    $router->get('/admin', 'Admin\\DashboardController@index', ['auth', 'role:SuperAdmin'], 'admin.dashboard');

    // Cierre del recorrido guiado del primer ingreso (tarea 11.5). Sólo deja
    // constancia de que la guía ya se vio: no cambia nada más.
    $router->post('/admin/recorrido', 'Admin\\OnboardingController@finish', ['auth', 'role:SuperAdmin', 'csrf'], 'admin.onboarding.finish');

    // Secciones del sitio editable: guardar prepara el borrador y publicar lo
    // hace visible. La vista previa muestra el borrador sin publicarlo.
    $router->get('/admin/bloques', 'Admin\\BlockController@index', ['auth', 'role:SuperAdmin'], 'admin.blocks');
    $router->get('/admin/bloques/{id}', 'Admin\\BlockController@edit', ['auth', 'role:SuperAdmin'], 'admin.blocks.edit');
    $router->get('/admin/bloques/{id}/previa', 'Admin\\BlockController@preview', ['auth', 'role:SuperAdmin'], 'admin.blocks.preview');
    $router->post('/admin/bloques/{id}', 'Admin\\BlockController@save', [...$adminMedia], 'admin.blocks.save');
    $router->post('/admin/bloques/{id}/publicar', 'Admin\\BlockController@publish', [...$adminMedia], 'admin.blocks.publish');
    $router->post('/admin/bloques/{id}/deshacer', 'Admin\\BlockController@undo', [...$adminMedia], 'admin.blocks.undo');

    $router->post('/admin/imagenes', 'MediaController@upload', $adminMedia, 'admin.media.upload');
    $router->post('/admin/imagenes/{id}/reemplazar', 'MediaController@replace', $adminMedia, 'admin.media.replace');
    $router->delete('/admin/imagenes/{id}', 'MediaController@destroy', $adminMedia, 'admin.media.destroy');

    $router->get('/admin/cursos', 'Admin\\CourseController@index', ['auth', 'role:SuperAdmin'], 'admin.courses');
    $router->get('/admin/cursos/nuevo', 'Admin\\CourseController@create', ['auth', 'role:SuperAdmin'], 'admin.courses.create');
    $router->post('/admin/cursos', 'Admin\\CourseController@store', [...$adminMedia], 'admin.courses.store');
    $router->get('/admin/cursos/{id}/editar', 'Admin\\CourseController@edit', ['auth', 'role:SuperAdmin'], 'admin.courses.edit');
    $router->put('/admin/cursos/{id}', 'Admin\\CourseController@update', [...$adminMedia], 'admin.courses.update');
    $router->delete('/admin/cursos/{id}', 'Admin\\CourseController@destroy', [...$adminMedia], 'admin.courses.destroy');
    $router->get('/admin/cursos/{id}/temario', 'Admin\\SyllabusController@show', ['auth', 'role:SuperAdmin'], 'admin.courses.syllabus');
    $router->post('/admin/cursos/{id}/modulos', 'Admin\\SyllabusController@storeModule', [...$adminMedia], 'admin.courses.modules.store');
    $router->post('/admin/cursos/{id}/modulos/{module}/lecciones', 'Admin\\SyllabusController@storeLesson', [...$adminMedia], 'admin.courses.lessons.store');
    $router->put('/admin/cursos/{id}/modulos/{module}', 'Admin\\SyllabusController@updateModule', [...$adminMedia], 'admin.courses.modules.update');
    $router->delete('/admin/cursos/{id}/modulos/{module}', 'Admin\\SyllabusController@destroyModule', [...$adminMedia], 'admin.courses.modules.destroy');
    $router->put('/admin/cursos/{id}/modulos/{module}/lecciones/{lesson}', 'Admin\\SyllabusController@updateLesson', [...$adminMedia], 'admin.courses.lessons.update');
    $router->delete('/admin/cursos/{id}/modulos/{module}/lecciones/{lesson}', 'Admin\\SyllabusController@destroyLesson', [...$adminMedia], 'admin.courses.lessons.destroy');
    $router->get('/admin/plantillas', 'Admin\\TemplateController@index', ['auth', 'role:SuperAdmin'], 'admin.templates');
    $router->get('/admin/plantillas/nueva', 'Admin\\TemplateController@create', ['auth', 'role:SuperAdmin'], 'admin.templates.create');
    $router->post('/admin/plantillas', 'Admin\\TemplateController@store', [...$adminMedia], 'admin.templates.store');
    $router->get('/admin/plantillas/{id}/editar', 'Admin\\TemplateController@edit', ['auth', 'role:SuperAdmin'], 'admin.templates.edit');
    $router->put('/admin/plantillas/{id}', 'Admin\\TemplateController@update', [...$adminMedia], 'admin.templates.update');
    $router->delete('/admin/plantillas/{id}', 'Admin\\TemplateController@destroy', [...$adminMedia], 'admin.templates.destroy');
    $router->get('/admin/videos', 'Admin\\VideoController@index', ['auth', 'role:SuperAdmin'], 'admin.videos');
    $router->get('/admin/videos/nuevo', 'Admin\\VideoController@create', ['auth', 'role:SuperAdmin'], 'admin.videos.create');
    $router->post('/admin/videos', 'Admin\\VideoController@store', [...$adminMedia], 'admin.videos.store');
    $router->get('/admin/videos/{id}/editar', 'Admin\\VideoController@edit', ['auth', 'role:SuperAdmin'], 'admin.videos.edit');
    $router->put('/admin/videos/{id}', 'Admin\\VideoController@update', [...$adminMedia], 'admin.videos.update');
    $router->delete('/admin/videos/{id}', 'Admin\\VideoController@destroy', [...$adminMedia], 'admin.videos.destroy');
    $router->get('/admin/noticias', 'Admin\\NewsController@index', ['auth', 'role:SuperAdmin'], 'admin.news');
    $router->get('/admin/noticias/nueva', 'Admin\\NewsController@create', ['auth', 'role:SuperAdmin'], 'admin.news.create');
    $router->post('/admin/noticias', 'Admin\\NewsController@store', [...$adminMedia], 'admin.news.store');
    $router->get('/admin/noticias/{id}/editar', 'Admin\\NewsController@edit', ['auth', 'role:SuperAdmin'], 'admin.news.edit');
    $router->put('/admin/noticias/{id}', 'Admin\\NewsController@update', [...$adminMedia], 'admin.news.update');
    $router->delete('/admin/noticias/{id}', 'Admin\\NewsController@destroy', [...$adminMedia], 'admin.news.destroy');
    $router->get('/admin/suscriptores', 'Admin\\SubscriberController@index', ['auth', 'role:SuperAdmin'], 'admin.subscribers');
    $router->get('/admin/suscriptores/exportar', 'Admin\\SubscriberController@export', ['auth', 'role:SuperAdmin'], 'admin.subscribers.export');
    $router->get('/admin/pagos', 'PaymentController@adminStatus', ['auth', 'role:SuperAdmin'], 'admin.payments');
    $router->get('/admin/ventas', 'Admin\\SalesController@index', ['auth', 'role:SuperAdmin'], 'admin.sales');

    // Cuentas de acceso. El servicio impide quedarse sin administradores.
    $router->get('/admin/usuarios', 'Admin\\UserController@index', ['auth', 'role:SuperAdmin'], 'admin.users');
    $router->post('/admin/usuarios', 'Admin\\UserController@create', [...$adminMedia], 'admin.users.create');
    $router->post('/admin/usuarios/{id}/rol', 'Admin\\UserController@changeRole', [...$adminMedia], 'admin.users.role');
    $router->post('/admin/usuarios/{id}/estado', 'Admin\\UserController@changeStatus', [...$adminMedia], 'admin.users.status');
    $router->get('/admin/accesos-contenido', 'Admin\\ContentAccessController@index', ['auth', 'role:SuperAdmin'], 'admin.content-access');
    $router->get('/admin/modulos', 'Admin\\ModuleController@index', ['auth', 'role:SuperAdmin'], 'admin.modules');
    $router->post('/admin/modulos/{id}', 'Admin\\ModuleController@update', [...$adminMedia], 'admin.modules.update');
    // Borrado definitivo en dos pasos: primero la advertencia, después la confirmación.
    $router->get('/admin/modulos/{id}/borrar', 'Admin\\ModuleController@confirmDelete', ['auth', 'role:SuperAdmin'], 'admin.modules.delete');
    $router->post('/admin/modulos/{id}/borrar', 'Admin\\ModuleController@deletePermanently', [...$adminMedia], 'admin.modules.delete.confirm');

    // Estudiantes: ficha con sus cursos y compras, y acceso concedido o
    // retirado a mano. Cada cambio queda en la auditoría con su autor.
    $router->get('/admin/estudiantes', 'Admin\\StudentController@index', ['auth', 'role:SuperAdmin'], 'admin.students');
    $router->get('/admin/estudiantes/{id}', 'Admin\\StudentController@show', ['auth', 'role:SuperAdmin'], 'admin.students.show');
    $router->post('/admin/estudiantes/{id}/accesos', 'Admin\\StudentController@grant', [...$adminMedia], 'admin.students.grant');
    $router->post('/admin/estudiantes/{id}/accesos/{enrollment}/revocar', 'Admin\\StudentController@revoke', [...$adminMedia], 'admin.students.revoke');
    $router->post('/admin/estudiantes/{id}/compras/{payment}/reembolsar', 'Admin\\StudentController@refund', [...$adminMedia], 'admin.students.refund');
    $router->post('/admin/estudiantes/{id}/compras/{payment}/conciliar', 'Admin\\StudentController@reconcile', [...$adminMedia], 'admin.students.reconcile');

    // --- Cuentas de estudiante ----------------------------------------------
    // `guest` evita que quien ya entró vuelva a las pantallas de ingreso.
    // `throttle:login,email` cuenta por cuenta y por origen, no sólo por origen:
    // así un ataque contra una cuenta concreta no bloquea a todo el que sale a
    // internet desde el mismo sitio.
    $router->get('/ingresar', 'AuthController@showLogin', ['guest'], 'login');
    $router->post('/ingresar', 'AuthController@login', ['guest', 'csrf', 'throttle:login,email'], 'login.attempt');

    $router->get('/registro', 'AuthController@showRegister', ['guest'], 'register');
    $router->post('/registro', 'AuthController@register', ['guest', 'csrf', 'throttle:register,email'], 'register.store');

    $router->post('/salir', 'AuthController@logout', ['auth', 'csrf'], 'logout');

    // Recuperación de contraseña. El límite se aplica también a la petición del
    // enlace, para no inundar de correos a una misma persona.
    $router->get('/recuperar-contrasena', 'PasswordResetController@showRequest', ['guest'], 'password.request');
    $router->post('/recuperar-contrasena', 'PasswordResetController@sendLink',
        ['guest', 'csrf', 'throttle:password_reset,email'], 'password.email');

    $router->get('/restablecer-contrasena/{token}', 'PasswordResetController@showReset', ['guest'], 'password.reset');
    $router->post('/restablecer-contrasena/{token}', 'PasswordResetController@reset',
        ['guest', 'csrf', 'throttle:password_reset'], 'password.update');

    // --- Área personal del estudiante ---------------------------------------
    $student = ['auth', 'role:Student'];

    $router->get('/mi-cuenta', 'AccountController@index', $student, 'account.dashboard');
    $router->get('/mi-cuenta/perfil', 'AccountController@profile', $student, 'account.profile');
    $router->post('/mi-cuenta/perfil', 'AccountController@updateProfile', [...$student, 'csrf'], 'account.profile.update');
    $router->post('/mi-cuenta/contrasena', 'AccountController@changePassword', [...$student, 'csrf'], 'account.password');

    // Inscripción a cursos gratuitos. Los de pago no se conceden desde aquí:
    // esperan a la confirmación de la pasarela (tarea 9.7).
    $router->post('/cursos/{slug}/inscribirse', 'EnrollmentController@store', [...$student, 'csrf'], 'courses.enroll');

    // Marcado de lecciones completadas. Responde en JSON: se hace sin recargar.
    $router->post('/cursos/{course}/lecciones/{lesson}/completar', 'EnrollmentController@completeLesson',
        [...$student, 'csrf'], 'courses.lesson.complete');

    // --- Asistente de instalación -------------------------------------------
    // Sólo responde cuando APP_DEBUG está activo (ver SetupController).
    $router->get('/instalacion', 'SetupController@index', [], 'setup');
};
