<?php

/**
 * Bootstrap for Unit Tests
 */

/**
 * Declaration for the PrestaShopBundle namespace
 */

namespace PrestaShopBundle\Translation {
    if (!interface_exists('TranslatorInterface')) {
        interface TranslatorInterface
        {
            public function trans($id, array $parameters = [], $domain = null, $locale = null);
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject {
    if (!class_exists('ShopConstraint')) {
        class ShopConstraint
        {
            private function __construct(private $shopId)
            {
            }

            public static function shop($shopId): self
            {
                return new self($shopId);
            }

            public function getShopId()
            {
                return $this->shopId;
            }
        }
    }
}

/**
 * Declaration for the Global namespace
 */

namespace {
    DG\BypassFinals::enable();
    // Loading autoloader inside global namespace
    $autoloader = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloader)) {
        require_once $autoloader;
    }

    // System constants
    if (!defined('_PS_VERSION_')) {
        define('_PS_VERSION_', '8.1.0');
    }

    if (!defined('_DB_PREFIX_')) {
        define('_DB_PREFIX_', 'ps_');
    }

    /*
     * Autoloader Stubbing mechanism
     */
    spl_autoload_register(function ($class): void {
        $stubs = [
            'PaymentModule' => [
                'is_abstract' => true,
                'extends' => 'Module',
                'methods' => '
                public $currencies = true;
                public $currencies_mode = "checkbox";
                public function install() { return true; }
                public function uninstall() { return true; }
            ',
            ],
            'Context' => [
                'methods' => '
                    public $customer; 
                    public $cart; 
                    public $shop; 
                    public $language; 
                    public $link; 
                    public static $instance; 
                    public static function getContext() { 
                        if (!self::$instance) self::$instance = new self(); 
                        return self::$instance; 
                    }
                    public function getTranslator() { return null; }
                ',
            ],
            'Db' => [
                'methods' => '
                    public static function getInstance() { return new self(); }
                    public function executeS($sql) { return []; }
                    public function execute($sql) { return true; }
                    public function escape($str) { return addslashes($str); }
                ',
            ],
            'Cache' => [
                'is_abstract' => true,
                'methods' => '
                    public static $instance;
                    public static function getInstance() { 
                        if (!self::$instance) self::$instance = new class extends Cache {}; 
                        return self::$instance; 
                    }
                    public function get($key) { return false; }
                    public function set($key, $value, $ttl = 0) { return true; }
                    public function exists($key) { return false; }
                    public function delete($key) { return true; }
                    public static function retrieve($key) { return null; }
                    public static function store($key, $value) { return true; }
            ',
            ],
            'Currency' => [
                'methods' => '
                    public $iso_code;
                    public static $mock;
                    
                    public function __construct($data = []) {
                        foreach ($data as $key => $val) { $this->$key = $val; }
                    }
            
                    public static function getCurrencies($object = false, $active = true, $groupByShop = false) {
                        if (self::$mock) {
                            return self::$mock->getCurrencies($object, $active, $groupByShop);
                        }
                        return [];
                    }
                    
                    public static function setMock($mock) {
                        self::$mock = $mock;
                    }
                ',
            ],
            'ObjectModel' => [
                'is_abstract' => true,
                'methods' => '
                    public $id; 
                    public function __construct($id = null) { $this->id = $id; }
                    public function add() { return true; }
                    public function update() { return true; }
                    public function delete() { return true; }
                ',
            ],
            'Module' => [
                'is_abstract' => true,
                'methods' => 'public $name; public $displayName;',
            ],
            'Customer' => [],
            'Cart' => [],
            'Shop' => [
                'methods' => 'public $id = 1;',
            ],
            'Language' => [],
            'Link' => [],
        ];

        if (isset($stubs[$class]) && !class_exists($class)) {
            $config = $stubs[$class];
            $abstract = isset($config['is_abstract']) ? 'abstract ' : '';
            $body = $config['methods'] ?? '';

            eval(sprintf('%sclass %s { %s }', $abstract, $class, $body));
        }
    });
}
