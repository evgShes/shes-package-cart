<?php
/**
 * Created by PhpStorm.
 * User: shes
 * Date: 29.06.2017
 * Time: 16:35
 */

namespace ShesShoppingCart\Src;
use ShesShoppingCart\Model\Carts;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;


class Cart
{
    protected $storage = 'mysql'; // mysql, redis
    protected $data = [];
    protected $cart_id = 'default';
    protected $totalQty = 0;
    protected $totalPrice = 0;

    public function __construct()
    {
        $this->cartInit();
    }

    /**
     *  Принимает ассоциативный массив обязательны ключи ['id'=>1, 'qty' => 0, 'price'=>100.4,
     * 'item'=>array/object,]
     * @param $items
     * @param null $id_cart
     * @return mixed
     */
    public static function add($items, $id_cart = null)
    {
        $inst = new static();
        if (!empty($id_cart)) $inst->cart_id = $id_cart;
        $storedItem = $inst->data['items'];

        if ($inst->checkMultiArr($items)) {
            foreach ($items as $item) {
                if ($item['qty'] <= 0) $item['qty']++;
                $id = $item['id'];
                if (array_key_exists($id, $storedItem)) {
                    $qty = $storedItem[$id]['qty'] + 1;
                    $storedItem[$id] = $item;
                    $storedItem[$id]['qty'] = $qty;
                } else {
                    $storedItem[$id] = $item;
                }
                $inst->data['total_qty']++;
                $inst->data['total_price'] += $item['price'];
            }
        } else {
            if ($items['qty'] <= 0) $items['qty']++;
            $storedItem[$items['id']] = $items;
        }
        $inst->data['items'] = $storedItem;
        $inst->cartSave();
        return $inst->get();
    }

    /**
     * Возврат корзины с вычиткой процента
     * @param $percent
     * @return mixed
     */
    public static function getWithPct($percent = 0)
    {
        $inst = new static();
        $cart = $inst->get();
        $total_price = $cart['total_price'];
        $result = $total_price - ($total_price * $percent / 100);
        if ($result >= 0) {
            $cart['total_price'] = $result;
        } else {
            $cart['total_price'] = 0;
        }
        return $cart;
    }

    /**
     * Получение корзины  с скидкой
     * @param int $summ
     * @return mixed
     */
    public static function getWithDic($summ = 0)
    {
        $inst = new static();
        $cart = $inst->get();
        $total_price = $cart['total_price'];
        $result = $total_price - $summ;
        if ($result >= 0) {
            $cart['total_price'] = $result;
        } else {
            $cart['total_price'] = 0;
        }
        return $cart;
    }

    /**
     * @return mixed
     */
    public static function get()
    {
        $inst = new static();
        return $inst->data;
    }

    /**
     * удаляет товар, передается id- продукта, количество(по умолчанию 1)
     * @param $id_prod
     * @param int $num
     * @return mixed
     */
    public static function reduce($id_prod, $num = 1)
    {
        $inst = new static();
        $cart = $inst->data;

        if (array_key_exists($id_prod, $cart['items'])) {
            $prod = &$cart['items'][$id_prod];
            if ($prod['qty'] <= $num) {
                $cart['total_price'] -= $prod['price'] * $prod['qty'];
                $cart['total_qty'] -= $prod['qty'];
                unset($cart['items'][$id_prod]);
            } else {
                $cart['total_price'] -= $prod['price'] * $num;
                $cart['total_qty'] -= $num;
                $prod['qty'] -= $num;

            }
            $inst->data = $cart;
            $inst->cartSave();
        }
        return $inst->get();
    }

    /**
     * добавляет товар, передается id- продукта, количество(по умолчанию 1)
     * @param $id_prod
     * @param int $num
     * @return mixed
     */
    public static function increase($id_prod, $num = 1)
    {
        $inst = new static();
        $cart = $inst->data;

        if (array_key_exists($id_prod, $cart['items'])) {
            $prod = &$cart['items'][$id_prod];
            $cart['total_price'] += $prod['price'] * $num;
            $cart['total_qty'] += $num;
            $prod['qty'] += $num;
            $inst->data = $cart;
            $inst->cartSave();
        }
        return $inst->get();
    }

    /**
     * Удалет полностью продукты по id
     * @param $id_prod
     * @return mixed
     */
    public static function remove($id_prod)
    {
        $inst = new static();
        $cart = $inst->data;

        if (array_key_exists($id_prod, $cart['items'])) {
            $prod = &$cart['items'][$id_prod];
            $cart['total_price'] -= $prod['price'] * $prod['qty'];
            $cart['total_qty'] -= $prod['qty'];
            unset($cart['items'][$id_prod]);
            $inst->data = $cart;

            $inst->cartSave();
        }
        return $inst->get();
    }

    /**
     * Очистка корзины
     */
    public static function delete()
    {
        $inst = new static();
        $inst->setDefaultCart();
        $inst->cartSave();
    }

    public function cartSave()
    {
        $cart_id = $this->cart_id;
        $data = json_encode($this->data);
        $storage = $this->storage;
        $status = false;
        switch ($storage) {
            case 'mysql':
//                $cart = Carts::where('cart_id', $cart_id)->first();
//                if ($cart) {
                    $cart = Carts::firstOrNew(['cart_id' => $cart_id]);
                    $cart->items = $data;
                    $cart->save();
                    $status = true;
//                }
                break;

            case 'redis':
                Redis::set($cart_id, $data);
                $status = true;
                break;
        }

//        dd($status);
        if ($status) {
            Session::put(['cart_id' => $cart_id, 'cart_data' => $this->data]);
            return true;
        }
    }

    public function cartInit()
    {
        $this->data['total_qty'] = $this->totalQty;
        $this->data['total_price'] = $this->totalPrice;
        $this->data['items'] = [];
        $this->cart_id .= '_' . Session::getId();
        $storage = $this->storage;
        if (Session::has('cart_id')) {
            $cart_id = Session::get('cart_id');
            switch ($storage) {
                case 'mysql':
                    $cart = Carts::where('cart_id', $cart_id)->first();
                    if ($cart) $rec = json_decode($cart->items, true);
                    break;
                case 'redis':
                    $cart = Redis::get($cart_id);
                    if ($cart) $rec = json_decode($cart, true);
                    break;
            }

            if (isset($rec)) {
                if (!empty($rec['items'])) {
                    $this->data['total_qty'] = $rec['total_qty'];
                    $this->data['total_price'] = $rec['total_price'];
                    $this->data['items'] = $rec['items'];
                }
                $this->cart_id = $cart_id;
            }

        }
        if (empty($this->data['items'])) Session::forget(['cart_id', 'cart_data']);
    }


    public function checkMultiArr($arr)
    {
        return ((count($arr, COUNT_RECURSIVE) - count($arr)) > 0) ? true : false;
    }


    public function setDefaultCart()
    {
        $this->data = [];
        $this->totalPrice = 0;
        $this->totalQty = 0;
    }


}