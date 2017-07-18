<?php

namespace Application;

use Zend\Permissions\Acl\Acl;
use Zend\ServiceManager\Factory\InvokableFactory;

use Autowp\ExternalLoginService\Factory as ExternalLoginServiceFactory;

use Zend_Db_Adapter_Abstract;

$host = getenv('AUTOWP_HOST');
$hostCookie = ($host == 'localhost' ? '' : '.' . $host);

$mailTypes = [
    'in-memory' => [
        'type' => 'in-memory'
    ],
    'smtp' => [
        'type'    => 'smtp',
        'options' => [
            'host'              => getenv('AUTOWP_MAIL_SMTP_HOST'),
            'connection_class'  => 'login',
            'connection_config' => [
                'username' => getenv('AUTOWP_MAIL_SMTP_USERNAME'),
                'password' => getenv('AUTOWP_MAIL_SMTP_PASSWORD'),
                'ssl'      => 'tls'
            ],
        ],
    ]
];

$mailType = getenv('AUTOWP_MAIL_TYPE');
if (! $mailType) {
    throw new \Exception("Mail type not provided");
}
if (! isset($mailTypes[$mailType])) {
    throw new \Exception("Mail type `$mailType` not found");
}
$mailTransport = $mailTypes[$mailType];

return [
    'controllers' => [
        'factories' => [
            Controller\AboutController::class           => Controller\Frontend\Service\AboutControllerFactory::class,
            Controller\AccountController::class         => Controller\Frontend\Service\AccountControllerFactory::class,
            Controller\ArticlesController::class        => Controller\Frontend\Service\ArticlesControllerFactory::class,
            Controller\BrandsController::class          => Controller\Frontend\Service\BrandsControllerFactory::class,
            Controller\CarsController::class            => Controller\Frontend\Service\CarsControllerFactory::class,
            Controller\CatalogueController::class       => Controller\Frontend\Service\CatalogueControllerFactory::class,
            Controller\CategoryController::class        => Controller\Frontend\Service\CategoryControllerFactory::class,
            Controller\ChartController::class           => Controller\Frontend\Service\ChartControllerFactory::class,
            Controller\CommentsController::class        => Controller\Frontend\Service\CommentsControllerFactory::class,
            Controller\DonateController::class          => Controller\Frontend\Service\DonateControllerFactory::class,
            Controller\FactoriesController::class       => Controller\Frontend\Service\FactoriesControllerFactory::class,
            Controller\FeedbackController::class        => Controller\Frontend\Service\FeedbackControllerFactory::class,
            Controller\ForumsController::class          => Controller\Frontend\Service\ForumsControllerFactory::class,
            Controller\IndexController::class           => Controller\Frontend\Service\IndexControllerFactory::class,
            Controller\InboxController::class           => InvokableFactory::class,
            Controller\InfoController::class            => Controller\Frontend\Service\InfoControllerFactory::class,
            Controller\LoginController::class           => Controller\Frontend\Service\LoginControllerFactory::class,
            Controller\MapController::class             => Controller\Frontend\Service\MapControllerFactory::class,
            Controller\MostsController::class           => Controller\Frontend\Service\MostsControllerFactory::class,
            Controller\MuseumsController::class         => Controller\Frontend\Service\MuseumsControllerFactory::class,
            Controller\NewController::class             => Controller\Frontend\Service\NewControllerFactory::class,
            Controller\PerspectiveController::class     => InvokableFactory::class,
            Controller\PictureController::class         => InvokableFactory::class,
            Controller\PictureFileController::class     => Controller\Frontend\Service\PictureFileControllerFactory::class,
            Controller\PulseController::class           => Controller\Frontend\Service\PulseControllerFactory::class,
            Controller\RegistrationController::class    => Controller\Frontend\Service\RegistrationControllerFactory::class,
            Controller\RestorePasswordController::class => Controller\Frontend\Service\RestorePasswordControllerFactory::class,
            Controller\DocController::class             => InvokableFactory::class,
            Controller\TelegramController::class        => Controller\Frontend\Service\TelegramControllerFactory::class,
            Controller\TwinsController::class           => Controller\Frontend\Service\TwinsControllerFactory::class,
            Controller\UsersController::class           => Controller\Frontend\Service\UsersControllerFactory::class,
            Controller\UploadController::class          => Controller\Frontend\Service\UploadControllerFactory::class,
            Controller\VotingController::class          => Controller\Frontend\Service\VotingControllerFactory::class,
            Controller\Frontend\YandexController::class => Controller\Frontend\Service\YandexControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'invokables' => [
            'forbiddenAction'     => Controller\Plugin\ForbiddenAction::class,
            'inputFilterResponse' => Controller\Api\Plugin\InputFilterResponse::class,
            'inputResponse'       => Controller\Api\Plugin\InputResponse::class
        ],
        'factories' => [
            'car'         => Controller\Plugin\Service\CarFactory::class,
            'catalogue'   => Controller\Plugin\Service\CatalogueFactory::class,
            'fileSize'    => Controller\Plugin\Service\FileSizeFactory::class,
            'language'    => Controller\Plugin\Service\LanguageFactory::class,
            'log'         => Controller\Plugin\Service\LogFactory::class,
            'oauth2'      => Factory\OAuth2PluginFactory::class,
            'pic'         => Controller\Plugin\Service\PicFactory::class,
            'pictureVote' => Controller\Plugin\Service\PictureVoteFactory::class,
            'sidebar'     => Controller\Plugin\Service\SidebarFactory::class,
            'translate'   => Controller\Plugin\Service\TranslateFactory::class,
        ]
    ],
    'translator' => [
        'locale' => 'ru',
        'fallbackLocale' => 'en',
        'translation_file_patterns' => [
            [
                'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => __DIR__ . '/../language',
                'pattern'  => '%s.php'
            ],
            [
                'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => \Zend\I18n\Translator\Resources::getBasePath(),
                'pattern'  => \Zend\I18n\Translator\Resources::getPatternForValidator()
            ],
            [
                'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => \Zend\I18n\Translator\Resources::getBasePath(),
                'pattern'  => \Zend\I18n\Translator\Resources::getPatternForCaptcha()
            ],
            [
                'type'     => \Zend\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => \Autowp\ZFComponents\Resources::getBasePath(),
                'pattern'  => \Autowp\ZFComponents\Resources::getPatternForViewHelpers()
            ]
        ],
    ],

    'service_manager' => [
        'factories' => [
            Acl::class                           => Permissions\AclFactory::class,
            Comments::class                      => Service\CommentsFactory::class,
            Db\TableManager::class               => Db\TableManagerFactory::class,
            DuplicateFinder::class               => Service\DuplicateFinderFactory::class,
            ExternalLoginServiceFactory::class   => Service\ExternalLoginServiceFactory::class,
            FileSize::class                      => InvokableFactory::class,
            HostManager::class                   => Service\HostManagerFactory::class,
            HostnameCheckRouteListener::class    => HostnameCheckRouteListenerFactory::class,
            Language::class                      => Service\LanguageFactory::class,
            LanguagePicker::class                => Service\LanguagePickerFactory::class,
            MainMenu::class                      => Service\MainMenuFactory::class,
            Model\BrandNav::class                => Model\Service\BrandNavFactory::class,
            Model\CarOfDay::class                => Model\Service\CarOfDayFactory::class,
            Model\Catalogue::class               => Model\Service\CatalogueFactory::class,
            Model\Categories::class              => Model\Service\CategoriesFactory::class,
            Model\Contact::class                 => Model\ContactFactory::class,
            Model\Item::class                    => Model\ItemFactory::class,
            Model\ItemAlias::class               => Model\ItemAliasFactory::class,
            Model\ItemParent::class              => Model\ItemParentFactory::class,
            Model\DbTable\Picture::class         => Model\Service\DbTablePictureFactory::class,
            Model\Log::class                     => Model\Service\LogFactory::class,
            Model\Perspective::class             => Model\PerspectiveFactory::class,
            Model\PictureItem::class             => InvokableFactory::class,
            Model\PictureView::class             => Model\PictureViewFactory::class,
            Model\PictureVote::class             => Model\Service\PictureVoteFactory::class,
            Model\UserPicture::class             => Model\Service\UserPictureFactory::class,
            Model\UserAccount::class             => Model\UserAccountFactory::class,
            Model\UserItemSubscribe::class       => Model\UserItemSubscribeFactory::class,
            Model\VehicleType::class             => InvokableFactory::class,
            PictureNameFormatter::class          => Service\PictureNameFormatterFactory::class,
            Service\SpecificationsService::class => Service\SpecificationsServiceFactory::class,
            Service\TelegramService::class       => Service\TelegramServiceFactory::class,
            Service\UsersService::class          => Service\UsersServiceFactory::class,
            ItemNameFormatter::class             => Service\ItemNameFormatterFactory::class,
            'translator'                         => \Zend\Mvc\I18n\TranslatorFactory::class,
            Provider\UserId\OAuth2UserIdProvider::class => Provider\UserId\OAuth2UserIdProviderFactory::class,
        ],
        'aliases' => [
            'ZF\OAuth2\Provider\UserId' => Provider\UserId\OAuth2UserIdProvider::class
        ],
        'abstract_factories' => [
            'Zend\Cache\Service\StorageCacheAbstractServiceFactory',
            //'Zend\Form\FormAbstractServiceFactory',
        ]
    ],

    'telegram' => [
        'accessToken' => getenv('AUTOWP_TELEGRAM_ACCESS_TOKEN'),
        'token'       => getenv('AUTOWP_TELEGRAM_TOKEN'),
        'webhook'     => 'https://www.autowp.ru/telegram/webhook/token/' . getenv('AUTOWP_TELEGRAM_TOKEN')
    ],

    'twitter' => [
        'username' => getenv('AUTOWP_TWITTER_USERNAME'),
        'oauthOptions' => [
            'consumerKey'    => getenv('AUTOWP_TWITTER_OAUTH_KEY'),
            'consumerSecret' => getenv('AUTOWP_TWITTER_OAUTH_SECRET')
        ],
        'token' => [
            'oauth_token'        => getenv('AUTOWP_TWITTER_TOKEN_OAUTH'),
            'oauth_token_secret' => getenv('AUTOWP_TWITTER_TOKEN_OAUTH_SECRET')
        ]
    ],

    'facebook' => [
        'app_id' => getenv('AUTOWP_FACEBOOK_APP_ID'),
        'app_secret' => getenv('AUTOWP_FACEBOOK_APP_SECRET'),
        'page_access_token' => getenv('AUTOWP_FACEBOOK_PAGE_ACCESS_TOKEN'),
    ],

    'hosts' => [
        'ru' => [
            'hostname' => getenv('AUTOWP_HOST_RU'),
            'timezone' => 'Europe/Moscow',
            'name'     => 'Русский',
            'flag'     => 'flag-icon flag-icon-ru',
            'cookie'   => getenv('AUTOWP_HOST_COOKIE_RU'),
            'aliases'  => [
                'ru.autowp.ru'
            ]
        ],
        'en' => [
            'hostname' => 'en.' . $host,
            'timezone' => 'Europe/London',
            'name'     => 'English (beta)',
            'flag'     => 'flag-icon flag-icon-gb',
            'cookie'   => $hostCookie,
            'aliases'  => [
                'en.autowp.ru',
                $host,
                'www' . $host
            ]
        ],
        'fr' => [
            'hostname' => 'fr.' . $host,
            'timezone' => 'Europe/Paris',
            'name'     => 'Français (beta)',
            'flag'     => 'flag-icon flag-icon-fr',
            'cookie'   => $hostCookie,
            'aliases'  => []
        ],
        'zh' => [
            'hostname' => 'zh.' . $host,
            'timezone' => 'Asia/Shanghai',
            'name'     => '中文 (beta)',
            'flag'     => 'flag-icon flag-icon-cn',
            'cookie'   => $hostCookie,
            'aliases'  => []
        ]
    ],

    'hostname_whitelist' => ['www.autowp.ru', 'ru.autowp.ru', 'en.autowp.ru',
        'i.' . $host, 'en.' . $host, 'fr.' . $host,
        'zh.' . $host, 'www.' . $host, $host],

    'pictures_hostname' => 'i.wheelsage.org',

    'content_languages' => ['ru', 'en', 'it', 'fr', 'zh', 'de', 'es', 'pt'],

    /*'acl' => [
        'cache'         => 'long',
        'cacheLifetime' => 3600
    ],*/

    'textstorage' => [
        'textTableName'     => 'textstorage_text',
        'revisionTableName' => 'textstorage_revision',
        'dbAdapter'         => Zend_Db_Adapter_Abstract::class
    ],

    'feedback' => [
        'from'     => 'no-reply@autowp.ru',
        'fromname' => 'robot autowp.ru',
        'to'       => 'autowp@gmail.com',
        'subject'  => 'AutoWP Feedback'
    ],

    'validators' => [
        'factories' => [
            Validator\Brand\NameNotExists::class => InvokableFactory::class,
            Validator\ItemParent\CatnameNotExists::class => Validator\ItemParent\CatnameNotExistsFactory::class,
            Validator\User\EmailExists::class    => InvokableFactory::class,
            Validator\User\EmailNotExists::class => InvokableFactory::class,
            Validator\User\Login::class          => InvokableFactory::class,
        ],
    ],

    'externalloginservice' => [
        'vk' => [
            'clientId'     => getenv('AUTOWP_ELS_VK_CLIENTID'),
            'clientSecret' => getenv('AUTOWP_ELS_VK_SECRET'),
        ],
        'google-plus' => [
            'clientId'     => getenv('AUTOWP_ELS_GOOGLEPLUS_CLIENTID'),
            'clientSecret' => getenv('AUTOWP_ELS_GOOGLEPLUS_SECRET'),
        ],
        'twitter' => [
            'consumerKey'    => getenv('AUTOWP_ELS_TWITTER_CLIENTID'),
            'consumerSecret' => getenv('AUTOWP_ELS_TWITTER_SECRET'),
        ],
        'facebook' => [
            'clientId'     => getenv('AUTOWP_ELS_FACEBOOK_CLIENTID'),
            'clientSecret' => getenv('AUTOWP_ELS_FACEBOOK_SECRET'),
            'scope'        => ['public_profile', 'user_friends']
        ],
        'github' => [
            'clientId'     => getenv('AUTOWP_ELS_GITHUB_CLIENTID'),
            'clientSecret' => getenv('AUTOWP_ELS_GITHUB_SECRET'),
        ],
        'linkedin' => [
            'clientId'     => getenv('AUTOWP_ELS_LINKEDIN_CLIENTID'),
            'clientSecret' => getenv('AUTOWP_ELS_LINKEDIN_SECRET'),
        ]
    ],

    'gulp-rev' => [
        'manifest' => __DIR__ . '/../../../public_html/dist/manifest.json',
        'prefix'   => '/dist/'
    ],

    'mosts_min_vehicles_count' => (int)getenv('AUTOWP_MOSTS_MIN_VEHICLES_COUNT'),

    'yandex' => [
        'secret' => getenv('AUTOWP_YANDEX_SECRET'),
        'price'  => (int)getenv('AUTOWP_YANDEX_PRICE')
    ],

    'vk' => [
        'token'    => getenv('AUTOWP_VK_TOKEN'),
        'owner_id' => getenv('AUTOWP_VK_OWNER_ID')
    ],

    'input_filters' => [
        'abstract_factories' => [
            \Zend\InputFilter\InputFilterAbstractServiceFactory::class
        ]
    ],

    'users' => [
        'salt'      => getenv('AUTOWP_USERS_SALT'),
        'emailSalt' => getenv('AUTOWP_EMAIL_SALT')
    ],

    'mail' => [
        'transport' => $mailTransport
    ],
];
