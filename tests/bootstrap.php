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

namespace PrestaShop\PrestaShop\Core\Domain\Order\ValueObject {
    if (!class_exists('OrderId')) {
        class OrderId
        {
            public function __construct(private readonly int $value)
            {
            }

            public function getValue(): int
            {
                return $this->value;
            }
        }
    }
}

namespace Doctrine\ORM {
    if (!class_exists('EntityRepository')) {
        class EntityRepository
        {
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Shop {
    if (!interface_exists('ShopContextInterface')) {
        interface ShopContextInterface
        {
            public function getShopName();

            public function getContextShopIds(): array;
        }
    }
}

namespace PrestaShop\PrestaShop\Core\Domain\Order\Exception {
    if (!class_exists('OrderNotFoundException')) {
        class OrderNotFoundException extends \Exception
        {
        }
    }
}

namespace PrestaShop\PrestaShop\Adapter\Order\Repository {
    use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;

    if (!class_exists('OrderRepository')) {
        class OrderRepository
        {
            public function get(OrderId $orderId): \Order
            {
                return new \Order($orderId->getValue());
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
            'Configuration' => [
                'methods' => '
                    public static $values = [];
                    public static function get($key) { return self::$values[$key] ?? false; }
                    public static function updateValue($key, $value) { self::$values[$key] = $value; return true; }
                    public static function deleteByName($key) { unset(self::$values[$key]); return true; }
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
            'Order' => [
                'methods' => '
                    public $id;
                    public $id_shop = 1;
                    public $current_state = 0;
                    public function __construct($id = null) { $this->id = $id; }
                    public function getCurrentState() { return $this->current_state; }
                    public function setCurrentState($stateId) { $this->current_state = $stateId; }
                ',
            ],
            'OrderHistory' => [
                'methods' => '
                    public $id_order;
                    public $id_order_state;
                    public function changeIdOrderState($stateId, $order) { $this->id_order_state = $stateId; }
                    public function add() { return true; }
                ',
            ],
            'Module' => [
                'is_abstract' => true,
                'methods' => 'public $name; public $displayName;',
            ],
            'Customer' => [],
            'Cart' => [],
            'Shop' => [
                'methods' => '
                    public $id = 1;
                    public function __construct($id = null) { $this->id = $id ?? 1; }
                ',
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
