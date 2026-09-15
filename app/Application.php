<?php

declare(strict_types=1);

namespace GB;

use GB\Middleware\AuthMiddleware;
use GB\Middleware\CsrfMiddleware;
use GB\Middleware\ForceHttpsMiddleware;
use GB\Middleware\GuestMiddleware;
use GB\Middleware\MustChangePasswordMiddleware;
use GB\Middleware\RoleMiddleware;
use GB\Middleware\SecurityHeadersMiddleware;
use GB\Middleware\ThrottleMiddleware;
use GB\Models\BlockItemRepository;
use GB\Models\BlockRepository;
use GB\Models\CourseModuleRepository;
use GB\Models\CourseRepository;
use GB\Models\ContentAccessRepository;
use GB\Models\EnrollmentRepository;
use GB\Models\LessonRepository;
use GB\Models\LessonProgressRepository;
use GB\Models\MediaRepository;
use GB\Models\ModuleRepository;
use GB\Models\NewsRepository;
use GB\Models\PaymentRepository;
use GB\Models\PaymentEventRepository;
use GB\Models\SubscriberRepository;
use GB\Models\TemplateRepository;
use GB\Models\VideoRepository;
use GB\Models\PasswordResetRepository;
use GB\Models\UserRepository;
use GB\Services\AccountAdminService;
use GB\Services\AccountService;
use GB\Services\BlockPublisher;
use GB\Services\BlockRenderer;
use GB\Services\EnrollmentService;
use GB\Services\ImagePipeline;
use GB\Services\NavigationBuilder;
use GB\Services\OnboardingService;
use GB\Services\PageComposer;
use GB\Services\PaymentService;
use GB\Services\ProtectedTokenService;
use GB\Services\SessionRegistry;
use GB\Services\WatermarkedGuideService;
use GB\Services\Payments\ManualGateway;
use GB\Services\Payments\PaymentGatewayManager;
use GB\Services\ProtectedFileStorage;
use GB\Services\TrashService;
use GB\Services\ViewCache;
use GB\Support\Audit;
use GB\Support\AdminMenu;
use GB\Support\AdminBlockForm;
use GB\Support\Auth;
use GB\Support\Config;
use GB\Support\ConfigurationException;
use GB\Support\Container;
use GB\Support\Csrf;
use GB\Support\Env;
use GB\Support\ErrorResponder;
use GB\Support\Flash;
use GB\Support\HealthCheck;
use GB\Support\Mailer;
use GB\Support\PasswordPolicy;
use GB\Support\Request;
use GB\Support\Router;
use GB\Support\Session;
use GB\Support\Throttle;
use GB\Support\View;
use PDO;
use PDOException;

/**
 * Construye y configura el contenedor de servicios de la aplicación.
 */
final class Application
{
    private static ?Container $container = null;

    public static function boot(): Container
    {
        $container = new Container();

        $container->set(Container::class, $container);

        // La petición actual se resuelve una sola vez y se comparte con todo el
        // que la necesite (auditoría, limitación de intentos, vistas).
        $container->set(Request::class, Request::fromGlobals());

        $container->singleton(View::class, static fn (): View => new View(GB_APP_PATH . '/Views'));

        // La conexión se crea de forma diferida: sólo cuando algo la pide.
        $container->singleton(PDO::class, static fn (): PDO => self::connect());

        $container->singleton(Session::class, static fn (): Session => new Session(Config::array('app.session')));

        $container->singleton(Csrf::class, static fn (Container $c): Csrf => new Csrf($c->get(Session::class)));

        $container->singleton(Flash::class, static fn (Container $c): Flash => new Flash($c->get(Session::class)));

        $container->singleton(UserRepository::class, static fn (Container $c): UserRepository => new UserRepository($c->get(PDO::class)));

        $container->singleton(Auth::class, static fn (Container $c): Auth => new Auth(
            $c->get(Session::class),
            $c->get(UserRepository::class),
            $c->get(SessionRegistry::class),
            Config::array('app.security')
        ));

        $container->singleton(Audit::class, static fn (Container $c): Audit => new Audit(
            $c->get(PDO::class),
            $c->get(Auth::class),
            $c->get(Request::class)
        ));

        $container->singleton(Throttle::class, static fn (Container $c): Throttle => new Throttle(
            $c->get(PDO::class),
            Config::array('middleware.limits')
        ));

        // Correo. Cuando no hay servidor configurado, los mensajes se guardan en
        // storage/logs/mail para poder comprobarlos, y `configured()` lo avisa
        // para que el panel lo muestre como pendiente.
        $container->singleton(Mailer::class, static fn (): Mailer => new Mailer(
            Config::array('app.mail'),
            storage_path('logs/mail')
        ));

        $container->singleton(PasswordPolicy::class, static fn (): PasswordPolicy => new PasswordPolicy(
            (int) Config::get('app.security.password_min_length', 10)
        ));

        $container->singleton(PasswordResetRepository::class, static fn (Container $c): PasswordResetRepository => new PasswordResetRepository($c->get(PDO::class)));

        $container->singleton(CourseRepository::class, static fn (Container $c): CourseRepository => new CourseRepository($c->get(PDO::class)));

        $container->singleton(ContentAccessRepository::class, static fn (Container $c): ContentAccessRepository => new ContentAccessRepository($c->get(PDO::class)));

        $container->singleton(CourseModuleRepository::class, static fn (Container $c): CourseModuleRepository => new CourseModuleRepository($c->get(PDO::class)));

        $container->singleton(SettingRepository::class, static fn (Container $c): SettingRepository => new SettingRepository($c->get(PDO::class)));

        $container->singleton(LessonRepository::class, static fn (Container $c): LessonRepository => new LessonRepository($c->get(PDO::class)));
        $container->singleton(EnrollmentRepository::class, static fn (Container $c): EnrollmentRepository => new EnrollmentRepository($c->get(PDO::class)));

        $container->singleton(LessonProgressRepository::class, static fn (Container $c): LessonProgressRepository => new LessonProgressRepository($c->get(PDO::class)));

        $container->singleton(TemplateRepository::class, static fn (Container $c): TemplateRepository => new TemplateRepository($c->get(PDO::class)));

        $container->singleton(VideoRepository::class, static fn (Container $c): VideoRepository => new VideoRepository($c->get(PDO::class)));

        $container->singleton(NewsRepository::class, static fn (Container $c): NewsRepository => new NewsRepository($c->get(PDO::class)));

        $container->singleton(PaymentRepository::class, static fn (Container $c): PaymentRepository => new PaymentRepository($c->get(PDO::class)));

        $container->singleton(PaymentEventRepository::class, static fn (Container $c): PaymentEventRepository => new PaymentEventRepository($c->get(PDO::class)));

        $container->singleton(PaymentGatewayManager::class, static fn (): PaymentGatewayManager => new PaymentGatewayManager(
            ['manual' => new ManualGateway()],
            (string) Config::get('app.payments.gateway', 'manual')
        ));

        $container->singleton(PaymentService::class, static fn (Container $c): PaymentService => new PaymentService(
            $c->get(CourseRepository::class),
            $c->get(PaymentRepository::class),
            $c->get(PaymentGatewayManager::class),
            $c->get(PaymentEventRepository::class),
            $c->get(EnrollmentRepository::class),
            $c->get(Audit::class)
        ));

        $container->singleton(ProtectedTokenService::class, static fn (): ProtectedTokenService => new ProtectedTokenService());

        $container->singleton(WatermarkedGuideService::class, static fn (): WatermarkedGuideService => new WatermarkedGuideService());

        $container->singleton(SessionRegistry::class, static fn (): SessionRegistry => new SessionRegistry(
            GB_STORAGE_PATH . '/sessions/active',
            (int) Config::get('app.session.max_concurrent', 2)
        ));

        $container->singleton(SubscriberRepository::class, static fn (Container $c): SubscriberRepository => new SubscriberRepository($c->get(PDO::class)));

        $container->singleton(ProtectedFileStorage::class, static fn (): ProtectedFileStorage => new ProtectedFileStorage(GB_STORAGE_PATH . '/protected'));

        $container->singleton(AccountService::class, static fn (Container $c): AccountService => new AccountService(
            $c->get(UserRepository::class),
            $c->get(PasswordResetRepository::class),
            $c->get(Auth::class),
            $c->get(Audit::class),
            $c->get(Mailer::class),
            $c->get(PasswordPolicy::class),
            Config::array('app.security')
        ));

        $container->singleton(EnrollmentService::class, static fn (Container $c): EnrollmentService => new EnrollmentService(
            $c->get(CourseRepository::class),
            $c->get(EnrollmentRepository::class),
            $c->get(LessonProgressRepository::class),
            $c->get(Audit::class)
        ));

        $container->singleton(ErrorResponder::class, static fn (Container $c): ErrorResponder => new ErrorResponder($c->get(View::class)));

        self::registerMiddleware($container);

        $container->singleton(Router::class, static function (Container $c): Router {
            $router = new Router($c);

            foreach (Config::array('middleware.aliases') as $alias => $class) {
                if (is_string($alias) && is_string($class)) {
                    $router->alias($alias, $class);
                }
            }

            $router->global(array_values(array_filter(
                Config::array('middleware.global'),
                'is_string'
            )));

            return $router;
        });

        $container->singleton(HealthCheck::class, static fn (Container $c): HealthCheck => new HealthCheck(GB_BASE_PATH, $c));

        self::registerContentServices($container);

        self::$container = $container;

        return $container;
    }

    /**
     * Contenedor ya construido, para las funciones auxiliares de las vistas.
     */
    public static function container(): Container
    {
        if (self::$container === null) {
            throw new \RuntimeException('La aplicación todavía no se ha iniciado.');
        }

        return self::$container;
    }

    private static function registerMiddleware(Container $container): void
    {
        $container->singleton(SecurityHeadersMiddleware::class, static fn (): SecurityHeadersMiddleware => new SecurityHeadersMiddleware(Config::array('app.security')));

        $container->singleton(ForceHttpsMiddleware::class, static fn (): ForceHttpsMiddleware => new ForceHttpsMiddleware(Config::string('app.env', 'production')));

        $container->singleton(AuthMiddleware::class, static fn (Container $c): AuthMiddleware => new AuthMiddleware(
            $c->get(Auth::class),
            $c->get(ErrorResponder::class),
            Config::string('middleware.paths.login', 'ingresar')
        ));

        $container->singleton(GuestMiddleware::class, static fn (Container $c): GuestMiddleware => new GuestMiddleware(
            $c->get(Auth::class),
            Config::string('middleware.paths.admin', 'admin'),
            Config::string('middleware.paths.student', 'mi-cuenta')
        ));

        $container->singleton(RoleMiddleware::class, static fn (Container $c): RoleMiddleware => new RoleMiddleware(
            $c->get(Auth::class),
            $c->get(ErrorResponder::class),
            $c->get(Audit::class)
        ));

        $container->singleton(CsrfMiddleware::class, static fn (Container $c): CsrfMiddleware => new CsrfMiddleware(
            $c->get(Csrf::class),
            $c->get(ErrorResponder::class)
        ));

        $container->singleton(ThrottleMiddleware::class, static fn (Container $c): ThrottleMiddleware => new ThrottleMiddleware(
            $c->get(Throttle::class),
            $c->get(ErrorResponder::class)
        ));

        $container->singleton(MustChangePasswordMiddleware::class, static fn (Container $c): MustChangePasswordMiddleware => new MustChangePasswordMiddleware(
            $c->get(Auth::class)
        ));
    }

    /**
     * Servicios de contenido: bloques, módulos, renderizado y publicación.
     *
     * Van con fábrica explícita porque reciben configuración (etiquetas del
     * panel, tiempo de caché). Construirlos por reflexión perdería esos valores.
     */
    private static function registerContentServices(Container $container): void
    {
        $container->singleton(ViewCache::class, static fn (): ViewCache => new ViewCache(
            GB_STORAGE_PATH . '/cache',
            (int) (Config::get('app.cache_ttl') ?? 600),
            (bool) (Config::get('app.cache_enabled') ?? true)
        ));

        // El pipeline es el único punto por el que entra una imagen al sistema.
        $container->singleton(ImagePipeline::class, static fn (Container $c): ImagePipeline => new ImagePipeline(
            $c->get(MediaRepository::class),
            Config::array('images'),
            GB_STORAGE_PATH . '/uploads/originals',
            GB_PUBLIC_PATH . '/uploads'
        ));

        $container->singleton(BlockRenderer::class, static fn (Container $c): BlockRenderer => new BlockRenderer(
            $c->get(View::class),
            Config::array('admin_labels.blocks')
        ));

        $container->singleton(AdminMenu::class, static fn (): AdminMenu => new AdminMenu(
            Config::array('admin_labels.menu_groups'),
            Config::array('admin_labels.sections')
        ));

        $container->singleton(AdminBlockForm::class, static fn (): AdminBlockForm => new AdminBlockForm());

        $container->singleton(AccountAdminService::class, static fn (Container $c): AccountAdminService => new AccountAdminService(
            $c->get(UserRepository::class),
            $c->get(Audit::class),
            $c->get(PasswordPolicy::class)
        ));

        $container->singleton(OnboardingService::class, static fn (Container $c): OnboardingService => new OnboardingService(
            $c->get(UserRepository::class),
            $c->get(AdminMenu::class),
            Config::array('admin_labels.tour')
        ));

        $container->singleton(ModuleAccessService::class, static fn (Container $c): ModuleAccessService => new ModuleAccessService(
            $c->get(PDO::class)
        ));

        $container->singleton(NavigationBuilder::class, static fn (Container $c): NavigationBuilder => new NavigationBuilder(
            $c->get(BlockRepository::class),
            $c->get(ModuleRepository::class),
            $c->get(BlockRenderer::class)
        ));

        $container->singleton(BlockPublisher::class, static fn (Container $c): BlockPublisher => new BlockPublisher(
            $c->get(BlockRepository::class),
            $c->get(BlockItemRepository::class),
            $c->get(Audit::class),
            $c->get(ViewCache::class)
        ));

        $container->singleton(TrashService::class, static fn (Container $c): TrashService => new TrashService(
            $c->get(Session::class)
        ));

        $container->singleton(PageComposer::class, static fn (Container $c): PageComposer => new PageComposer(
            $c->get(BlockRepository::class),
            $c->get(ModuleRepository::class),
            $c->get(BlockRenderer::class),
            $c->get(NavigationBuilder::class),
            $c->get(ViewCache::class),
            $c->get(Auth::class),
            $c->get(Csrf::class)
        ));
    }

    private static function connect(): PDO
    {
        $host = Env::required('DB_HOST');
        $database = Env::required('DB_DATABASE');
        $username = Env::required('DB_USERNAME');
        $password = (string) Env::get('DB_PASSWORD', '');
        $port = (int) Env::get('DB_PORT', '3306');
        $charset = (string) Env::get('DB_CHARSET', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $exception) {
            throw new \GB\Support\ConfigurationException(
                'No se pudo conectar a la base de datos. Revisa las variables DB_* del archivo .env. '
                . 'Detalle: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }

        return $pdo;
    }
}
