<?php

namespace Store\Test;

use Mockery as m;
use Store\Model\Cache;
use Store\Model\Category;
use Store\Model\Config;
use Store\Model\Discount;
use Store\Model\Email;
use Store\Model\Member;
use Store\Model\Order;
use Store\Model\OrderAdjustment;
use Store\Model\OrderItem;
use Store\Model\OrderShippingMethod;
use Store\Model\PaymentMethod;
use Store\Model\Product;
use Store\Model\ProductModifier;
use Store\Model\ProductOption;
use Store\Model\Sale;
use Store\Model\ShippingMethod;
use Store\Model\ShippingRule;
use Store\Model\State;
use Store\Model\Status;
use Store\Model\Stock;
use Store\Model\StockOption;
use Store\Model\Tax;
use Store\Model\Transaction;

/**
 * Test object factory
 */
class Factory
{
    public static function build($model, array $attributes = array())
    {
        $instance = new static;
        $method = 'build'.ucfirst($model);
        if (is_callable(array($instance, $method))) {
            $model = $instance->$method();
            // bypass mass assignment protection
            foreach ($attributes as $key => $value) {
                $model->setAttribute($key, $value);
            }

            return $model;
        } else {
            throw new \Exception("Don't know how to build '$model'.");
        }
    }

    public static function create($model, array $attributes = array())
    {
        $model = static::build($model, $attributes);
        $model->save();

        return $model;
    }

    public static function buildMock($model, array $attributes = array())
    {
        return m::mock(static::build($model, $attributes));
    }

    public function buildCache()
    {
        $cache = new Cache;
        $cache->key = md5(uniqid());
        $cache->value = json_encode(sha1(uniqid()));
        $cache->expiry_date = time() + 3600;

        return $cache;
    }

    public function buildCategory()
    {
        $model = new Category;

        return $model;
    }

    public function buildConfig()
    {
        $model = new Config;
        $model->site_id = TEST_SITE_ID;
        $model->preference = 'foo';
        $model->value = 'bar';

        return $model;
    }

    public function buildDiscount()
    {
        $model = new Discount;
        $model->site_id = TEST_SITE_ID;

        return $model;
    }

    public function buildEmail()
    {
        $email = new Email;

        return $email;
    }

    public function buildMember()
    {
        $model = new Member;
        $model->username = 'some_user';
        $model->screen_name = 'Some Screen Name';
        $model->email = 'example@adrianmacneil.com';
        $model->password = md5(uniqid(mt_rand(), true));
        $model->unique_id = sha1(uniqid(mt_rand(), true));
        $model->language = 'english';
        $model->timezone = 'Asia/Bangkok';

        return $model;
    }

    public function buildOrder()
    {
        $order = new Order;
        $order->site_id = TEST_SITE_ID;
        $order->order_date = time();
        $order->ip_address = '127.0.0.1';
        $order->billing_first_name = 'Bob';
        $order->billing_last_name = 'Bobson';
        $order->billing_address1 = '1 Somewhere St';
        $order->billing_address2 = 'Suburbia';
        $order->billing_city = 'San Francisco';
        $order->billing_postcode = '12345';
        $order->billing_state = 'CA';
        $order->billing_country = 'US';
        $order->billing_phone = '123 456 789';
        $order->shipping_first_name = 'Sally';
        $order->shipping_last_name = 'Salson';
        $order->shipping_address1 = '2 Another St';
        $order->shipping_address2 = 'Suburnville';
        $order->shipping_city = 'Sydney';
        $order->shipping_postcode = '2000';
        $order->shipping_state = 'NSW';
        $order->shipping_country = 'AU';
        $order->shipping_phone = '11 222 333 4444';
        $order->order_email = 'adrian@example.com';

        $items = array();
        $items[] = static::buildOrderItem();
        $items[] = static::buildOrderItem();
        $order->setRelation('items', $items);

        $order->updateItemTotals();

        return $order;
    }

    public function buildOrderAdjustment()
    {
        $model = new OrderAdjustment;
        $model->site_id = TEST_SITE_ID;
        $model->name = 'Ch ch ch changes';
        $model->type = 'tax';
        $model->amount = '5.00';
        $model->included = 0;

        return $model;
    }

    public function buildOrderItem()
    {
        $item = new OrderItem;
        $item->title = 'Test Product';
        $item->length = rand(1, 5);
        $item->width = rand(1, 5);
        $item->height = rand(1, 5);
        $item->weight = rand(1, 5);
        $item->price = store_round_currency(rand(1000, 9000) / 100);
        $item->item_qty = rand(1, 5);
        $item->recalculate();

        return $item;
    }

    public function buildOrderShippingMethod()
    {
        $model = new OrderShippingMethod;
        $model->id = rand(1, 5);
        $model->name = 'Test Shipping Method';
        $model->amount = store_round_currency(rand(1000, 9000) / 100);

        return $model;
    }

    public function buildPaymentMethod()
    {
        $method = new PaymentMethod;
        $method->site_id = TEST_SITE_ID;
        $method->class = 'Dummy';
        $method->title = 'Dummy Gateway';
        $method->enabled = 1;

        return $method;
    }

    public function buildProduct()
    {
        $model = new Product;
        $model->entry_id = $this->randomId();
        $model->length = rand(1, 5);
        $model->width = rand(1, 5);
        $model->height = rand(1, 5);
        $model->weight = rand(1, 5);

        return $model;
    }

    public function buildProductModifier()
    {
        $model = new ProductModifier;
        $model->mod_name = 'Color';

        return $model;
    }

    public function buildProductOption()
    {
        $model = new ProductOption;
        $model->opt_name = 'Blue';

        return $model;
    }

    public function buildSale()
    {
        $model = new Sale;
        $model->site_id = TEST_SITE_ID;
        $model->name = 'Test Sale';
        $model->enabled = 1;

        return $model;
    }

    public function buildShippingMethod()
    {
        $model = new ShippingMethod;
        $model->site_id = TEST_SITE_ID;
        $model->name = 'Test Shipping Method';
        $model->enabled = 1;

        return $model;
    }

    public function buildShippingRule()
    {
        $rule = new ShippingRule;
        $rule->enabled = true;

        return $rule;
    }

    public function buildState()
    {
        $model = new State;
        $model->name = 'The Shire';
        $model->code = 'SH';

        return $model;
    }

    public function buildStatus()
    {
        $status = new Status;
        $status->site_id = TEST_SITE_ID;

        return $status;
    }

    public function buildStock()
    {
        $model = new Stock();
        $model->entry_id = $this->randomId();

        return $model;
    }

    public function buildStockOption()
    {
        $model = new StockOption();
        $model->entry_id = $this->randomId();

        return $model;
    }

    public function buildTax()
    {
        $model = new Tax;
        $model->site_id = TEST_SITE_ID;
        $model->name = 'Sugar Tax';
        $model->rate = 0.4;
        $model->enabled = 1;

        return $model;
    }

    public function buildTransaction()
    {
        $transaction = new Transaction;
        $transaction->site_id = TEST_SITE_ID;
        $transaction->date = time();
        $transaction->payment_method = 'Dummy';
        $transaction->type = Transaction::PURCHASE;
        $transaction->amount = '20.01';
        $transaction->status = Transaction::SUCCESS;
        $transaction->reference = 'some-reference';
        $transaction->message = 'go home';

        return $transaction;
    }

    protected function randomId()
    {
        return rand(100, 999);
    }
}
