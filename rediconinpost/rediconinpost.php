<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once "class/InpostShipX.php";
require_once "class/InpostPdf.php";

class RediconInpost extends CarrierModule
{
    /* @var boolean error */
    protected $_errors = false;
    private $dir_module;
    protected $_join = '';
    protected $limit = 10;
    protected $columns = [
            ['column'=>'rip.id','header'=>'ID','type'=>'search'],
            ['column'=>'rip.error','header'=>'Error','type'=>'search'],
            ['column'=>'o.id_order','header'=>'ID Order','type'=>'search'],
            ['column'=>'o.date_add','header'=>'Data wydruku etykiety','type'=>'search'],
            ['column'=>'rip.event','header'=>'Status paczki','type'=>'search'],
            ['column'=>'rip.id_shipment','header'=>'ID Shipment','type'=>'search'],
            ['column'=>'rip.shipping_number','header'=>'Numer przesyłk','type'=>'search'],
            ['column'=>'c.firstname','header'=>'Odbiorca','type'=>'search'],
            ['column'=>'a.phone','header'=>'telefon','type'=>'search'],
            ['column'=>'','header'=>'Kraj','type'=>'none'],
            ['column'=>'a.postcode','header'=>'Kod pocztowy','type'=>'search'],
            ['column'=>'a.city','header'=>'Miejscowość','type'=>'search'],
            ['column'=>'o.id_address_delivery','header'=>'Adres','type'=>'search'],
    ];
    public static $key_access = '681549342531876019900138';

    public function __construct()
    {
        $this->name = 'rediconinpost';
        $this->tab = 'redicon_inpost';
        $this->version = '1.0';
        $this->author = 'Patryk Pawlicki- redicon';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_,
        );
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Wysyłka - kurier inpost');
        $this->description = $this->l('...');
        $this->dir_module = 'module:'.$this->name;
        $this->template = $this->dir_module.'/view.tpl';
        $this->templateBackend = $this->dir_module.'/view/backend.tpl';
        $this->templateBefore = $this->dir_module.'/view/before.tpl';
        $this->pagination_file = $this->dir_module.'/view/_pagination.tpl';
    }

    private function getConfirmedUrl($action)
    {
        $ssl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://';
        $url = $ssl . $this->context->shop->domain . '/modules/rediconinpost/ajax.php?action='.$action;
        return $url;
    }

    public function saveConfirmShipment($data = [])
    {
        $result = false;
        if (isset($data['event']) && isset($data['payload']) && isset($data['payload']['shipment_id']) && isset($data['payload']['tracking_number'])) {
            $shipment_id = (int)$data['payload']['shipment_id'];
            $event = pSQL($data['event']);
            $shipping_number = pSQL($data['payload']['tracking_number']);

            $result =  Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'redicon_inpost_points` 
            SET `shipping_number`='.$shipping_number.',`event`="'.$event.'" WHERE `id_shipment` = '.$shipment_id);
        }
        return ['status'=>$result];
    }

    public function initProcess()
    {
        parent::initProcess();
    }

    private function postSave()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'REDICON') !== false) {
                    Configuration::updateValue($key, $value);
                }
            }
           
            Tools::redirectAdmin($_SERVER['HTTP_REFERER']);
        }
    }
    
    public function savePdfFile($pdf, $id)
    {
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'redicon_inpost_points` 
        SET `pdf`="'.$pdf.'",`date_print`=NOW() WHERE `id`= '.$id);
    }

    public function checkShipment()
    {
        $rows = Db::getInstance()->executeS('SELECT * FROM  `'._DB_PREFIX_.'redicon_inpost_points` 
            WHERE `event` LIKE "shipment_confirmed" AND `shipping_number` IS NOT  NULL AND (`pdf`="" OR `pdf` IS NULL)');

        if ($rows) {
            $shipX = new InpostShipX(Configuration::get('REDICON_INPOST_API_KEY'), Configuration::get('REDICON_INPOST_SANDBOX'), 0);
            foreach ($rows as $row) {
                if ($pdf = $shipX->getLabel(
                    $row['id_shipment'],
                    InpostShipX::$label_type[Configuration::get('REDICON_INPOST_TYPE_LABEL')],
                    InpostShipX::$page_format[Configuration::get('REDICON_INPOST_FORMAT_LABEL')]
                )
                ) {
                    $response = json_decode($pdf, true);
                    if (isset($response['error'])) {
                        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'redicon_inpost_points` 
                        SET `error`="'.pSql(json_encode($pdf)).'" WHERE `id` = '.(int) $row['id']);
                    } else {
                        $inpostPdf = new InpostPdf();
                        $pdf_name = $inpostPdf->savePdfToFile($row['id_shipment'].'.pdf', $pdf);

                        $this->savePdfFile($pdf_name, $row['id']);
                        if($row['shipping_number']){
                            $id_order_carrier = Db::getInstance()->getValue('
                                SELECT `id_order_carrier`
                                FROM `'._DB_PREFIX_.'order_carrier`
                                WHERE `id_order` = '.(int) $row['id_order']);
                            if ($id_order_carrier) {
                                $order_carrier = new OrderCarrier($id_order_carrier);
                                $order_carrier->tracking_number = $row['shipping_number'];
                                $order_carrier->update();
                            }
                        }
                        
                    }
                    
                    return $pdf_name;
                } else {
                    return file_get_contents('php://input');
                }
            }
        }
    }

    private function getShipmentIds()
    {
        if (isset($_GET['selected'])) {
            $shipX = new InpostShipX(Configuration::get('REDICON_INPOST_API_KEY'), Configuration::get('REDICON_INPOST_SANDBOX'), 0);

            if ($rows = $this->getOrdersId($_GET['selected'])) {
                foreach ($rows as $row) {
                    $temp = self::OrderToLabel((int)$row['id_order']);
    
                    $response = $shipX->shipment(Configuration::get('REDICON_INPOST_ORGANIZATION_ID'), $temp);
                    //  dd();
                    if (is_null($response['error'])) {
                        if (isset($response['result']->id)) {
                            Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'redicon_inpost_points` 
                            SET `id_shipment`='.(int)$response['result']->id.',event="dispatch_order",shipping_number="" WHERE `id` = '.(int) $row['id']);
                        }
                    } else {
                        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'redicon_inpost_points` 
                            SET `error`="'.pSql(json_encode($response)).'" WHERE `id` = '.(int) $row['id']);
                    }
                }
            }
            Tools::redirectAdmin($_SERVER['HTTP_REFERER']);
        }
    }

    public function getContent()
    {
        if (isset($_GET['id_shipment'])) {
            $id_shipment = $_GET['id_shipment'] . '.pdf';
            (new InpostPdf())->getFile($id_shipment);
        }

        $this->getShipmentIds();
        
        $this->postSave();
        // $this->addCarrier();
        // $this->registerHook('displaySelectedPaczkomat');
        // $this->registerHook('adminOrder');
        // $this->registerHook('displayBeforeCarrier');
        // $this->registerHook('displayCarrierExtraContent');
        // $this->registerHook('actionValidateOrder');
        // $this->registerHook('actionValidateOrder');
        // dump($this->registerHook('actionCarrierProcess'));
        // $this->registerHook('actionCarrierUpdate');
        // $this->addCarrier();
        // Configuration::updateValue('REDICON_INPOST_CARRIER_URL', 'https://inpost.pl/sledzenie-przesylek?number=@');
        // $shipX = new InpostShipX(Configuration::get('REDICON_INPOST_API_KEY'), Configuration::get('REDICON_INPOST_SANDBOX'), 0);
        // dd($shipX->getLabel(
        //     $row['id_shipment'],
        //     InpostShipX::$label_type[Configuration::get('REDICON_INPOST_TYPE_LABEL')],
        //      InpostShipX::$page_format[Configuration::get('REDICON_INPOST_FORMAT_LABEL')]
              
        // ));

        $fields = [
            [
                'label' => '<b>DANE NADAWCY</b>'
            ],
            [
                'label' => 'ID INPOST',
                'required' => 'required',
                'name' => 'REDICON_INPOST_ID',
                'value' => Configuration::get('REDICON_INPOST_ID')
            ],
            [
                'label' => 'Nazwa firmy',
                'required' => 'required',
                'name' => 'REDICON_INPOST_COMPANY',
                'value' => Configuration::get('REDICON_INPOST_COMPANY')
            ],
            [
                'label' => 'Imię nadawcy',
                'required' => 'required',
                'name' => 'REDICON_INPOST_FIRSTNAME',
                'value' => Configuration::get('REDICON_INPOST_FIRSTNAME')
            ],
            [
                'label' => 'Nazwisko nadawcy',
                'required' => 'required',
                'name' => 'REDICON_INPOST_LASTNAME',
                'value' => Configuration::get('REDICON_INPOST_LASTNAME')
            ],
            [
                'label' => 'Email nadawcy',
                'required' => 'required',
                'name' => 'REDICON_INPOST_EMAIL',
                'value' => Configuration::get('REDICON_INPOST_EMAIL')
            ],
            [
                'label' => 'telefon nadawcy',
                'required' => 'required',
                'name' => 'REDICON_INPOST_PHONE',
                'value' => Configuration::get('REDICON_INPOST_PHONE')
            ],
            [
                'label' => 'Adres',
                'required' => 'required',
                'name' => 'REDICON_INPOST_ADDRESS_STREET',
                'value' => Configuration::get('REDICON_INPOST_ADDRESS_STREET')
            ],
            [
                'label' => 'Nnr budynku/lokalu',
                'required' => 'required',
                'name' => 'REDICON_ADDRESS_NR',
                'value' => Configuration::get('REDICON_ADDRESS_NR')
            ],
            [
                'label' => 'Miasto',
                'required' => 'required',
                'name' => 'REDICON_INPOST_ADDRESS_CITY',
                'value' => Configuration::get('REDICON_INPOST_ADDRESS_CITY')
            ],
            [
                'label' => 'Kod pocztowy',
                'required' => 'required',
                'name' => 'REDICON_INPOST_ADDRESS_POSTCODE',
                'value' => Configuration::get('REDICON_INPOST_ADDRESS_POSTCODE')
            ],
            [
                'label'=> '<b>USTAWIENIA</b>'
            ],
            [
                'label' => 'Link śledzący',
                'required' => 'required',
                'name' => 'REDICON_INPOST_CARRIER_URL',
                'value' => Configuration::get('REDICON_INPOST_CARRIER_URL')
            ],
            [
                'label' => 'API KEY',
                'required' => 'required',
                'name' => 'REDICON_INPOST_API_KEY',
                'value' => Configuration::get('REDICON_INPOST_API_KEY')
            ],
            [
                'label' => 'ID ORGANIZACJI',
                'required' => 'required',
                'name' => 'REDICON_INPOST_ORGANIZATION_ID',
                'value' => Configuration::get('REDICON_INPOST_ORGANIZATION_ID')
            ],
            [
                'label' => 'Sandbox',
                'required' => 'required',
                'name' => 'REDICON_INPOST_SANDBOX',
                'value' => Configuration::get('REDICON_INPOST_SANDBOX'),
                'options' => [
                    0=>'Nie',
                    1=>'Tak'
                ]
            ],
            [
                'label' => 'Typ etykiety',
                'required' => 'required',
                'name' => 'REDICON_INPOST_TYPE_LABEL',
                'value' => Configuration::get('REDICON_INPOST_TYPE_LABEL'),
                'options' => InpostShipX::$label_type,
            ],
            [
                'label' => 'Format etykiety',
                'required' => 'required',
                'name' => 'REDICON_INPOST_FORMAT_LABEL',
                'value' => Configuration::get('REDICON_INPOST_FORMAT_LABEL'),
                'options' => InpostShipX::$page_format,
            ],
        
        ];

        $columns = array_map(function ($row) {
            $row['value'] = isset($_GET['f'][$row['column']]) ?$_GET['f'][$row['column']]:'';
            return $row;
        }, $this->columns);

        $list_total = $this->getData(1);

        $this->context->smarty->assign(array(
            'access_url' => $this->getConfirmedUrl('confirmShipment&auth='.self::$key_access),
            'check_url' => $this->getConfirmedUrl('checkShipment&auth='.self::$key_access),
            'fields' =>   $fields,
            'rows' => $this->getData(),
            'columns' => $columns,
            'url' => 'index.php?controller=AdminModules&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
            'order_link' =>'index.php?controller=AdminOrders&vieworder&token='.Tools::getAdminTokenLite('AdminOrders'),
            'order_by'=>isset($_GET['order_by'])?$_GET['order_by']:'',
            'order_way'=>isset($_GET['order_way'])?$_GET['order_way']:'DESC',
            'order_search'=>isset($_GET['order_search'])?$_GET['order_search']:'',
            'list_total'=>$list_total,
            'total_pages' => ceil($list_total / $this->limit),
            'limit_per_page' => $this->limit,
            'page' => isset($_GET['page'])?(int)$_GET['page']:1,
            'pagination_file' => $this->pagination_file
        ));

        return $this->fetch($this->templateBackend);
    }

    private function getOrdersId($ids='')
    {
        $rows = Db::getInstance()->executeS('SELECT o.id_order,rip.id FROM `'._DB_PREFIX_.'orders` o 
        LEFT JOIN `'._DB_PREFIX_.'redicon_inpost_points` rip ON rip.id_cart=o.id_cart
        WHERE rip.id IN ('.$ids.')');
        return $rows;
    }

    private function getData($all = false)
    {
        $i = isset($_GET['page'])?(int)($_GET['page']-1):0;

        $start = $i * $this->limit;

        $ids = Db::getInstance()->executeS('SELECT `id_carrier` FROM `'._DB_PREFIX_.'carrier` 
        WHERE `id_reference` = '.(int) Configuration::get('REDICON_INPOST_CARRIER_REFERENCE'));
        $_ids = '0';
        foreach ($ids as $id) {
            $_ids .= ','.$id['id_carrier'];
        }

        $order_by = 'ORDER BY rip.id DESC';
        if (isset($_GET['order_by']) && isset($_GET['order_way']) && in_array($_GET['order_way'], ['asc','desc'])) {
            $order_by = "ORDER BY rip.id DESC,{$_GET['order_by']} {$_GET['order_way']}";
        }

        $where =[];
        if (isset($_GET['f'])) {
            foreach ($_GET['f'] as $key => $f) {
                if ($key == 'c.firstname') {
                    $key = 'CONCAT(c.firstname," ",c.lastname)';
                }
                $where[] = "$key LIKE '$f'";
            }
        }
       
        $where = implode(' AND ', $where);
        
        if ($where!='') {
            $where .= " AND `id_carrier` IN (". $_ids .") ";
        } else {
            $where .= " `id_carrier` IN (". $_ids.") ";
        }

        $limit_where ='';

        if ($all == false) {
            $limit_where ='LIMIT ' . $start . ',' . $this->limit;
        }
        $select = 'rip.point_code,rip.date_print,rip.pdf,c.lastname,co.iso_code,a.address1,a.address2';
        foreach ($this->columns as $column) {
            if ($column['column']) {
                $select .= ', '.$column['column'];
            }
        }
   
        if ($all == true) {
            $select = 'COUNT(*) as total';
        }

        $sql = 'SELECT '.$select.' FROM `'._DB_PREFIX_.'orders` o 
        LEFT JOIN `'._DB_PREFIX_.'address` a ON a.id_address=o.id_address_delivery
        LEFT JOIN `'._DB_PREFIX_.'customer` c ON c.id_customer=o.id_customer
        LEFT JOIN `'._DB_PREFIX_.'country` co ON co.id_country=a.id_country
        LEFT JOIN `'._DB_PREFIX_.'redicon_inpost_points` rip ON rip.id_cart=o.id_cart
        WHERE  '.$where.' '.$order_by.' '.$limit_where;
       
        if ($all == true) {
            return Db::getInstance()->getValue($sql);
        }


        $rows = Db::getInstance()->executeS($sql);

        $orders = [];

        foreach ($rows as $row) {
            $orders[]= $this->orderFormat($row);
        }

        return $orders;
    }

    private function orderFormat($order)
    {
        $error = json_encode(json_decode($order['error']), JSON_PRETTY_PRINT);
        
        return [
            'id' => $order['id'],
            'error' => str_replace('"', "'", $error),
            'id_order' => $order['id_order'],
            'date_add' => $order['date_print'],
            'event' => $order['event'],
            'id_shipment' => [
                'show'=> !empty($order['pdf']),
                'id' => $order['id_shipment']
            ],
            'shipping_number' => $order['shipping_number'],
            'customer' => $order['firstname'] . ' ' . $order['lastname'],
            'phone' => $order['phone'],
            'country' => $order['iso_code'],
            'postcode' => $order['postcode'],
            'city' => $order['city'],
            'address' => $order['point_code'].'-'.$order['address1'].' '.$order['address2'],
        ];
    }

    private function checkDir($dir = null)
    {
        $path = $dir ? $this->download_path.$dir : $this->download_path;
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    private static function updateOrderId($order)
    {
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'redicon_inpost_points` 
        SET `id_order`='.(int) $order->id.' WHERE id_order=0 AND `id_cart` = '.(int) $order->id_cart);
    }

    private static function updateShipmentId($id_order, $shipment_id)
    {
        Db::getInstance()->execute('UPDATE `'._DB_PREFIX_.'redicon_inpost_points` 
        SET `id_order`='.(int) $order->id.' WHERE id_order=0 AND `id_cart` = '.(int) $order->id_cart);
    }

    private static function OrderToLabel($id)
    {
        $order = new Order($id);

        if ($order->id) {
            self::updateOrderId($order);
            $order_address = new Address($order->id_address_delivery);
            $customer = new Customer($order_address->id_customer);
            $country = new Country($order_address->id_country);

            $order_carrier = new OrderCarrier(self::getOrderCarrierId($order->id));

            $width = 0;
            $height = 0;
            $depth = 0;
            
            if ($products = $order->getProducts()) {
                foreach ($products as $product) {
                    if ($height < $product['height']) {
                        $height =  $product['height'];
                    }
                    if ($depth < $product['depth']) {
                        $depth =  $product['depth'];
                    }
                    $width += $product['width'];
                }
            }
            $total_size = ($height+$width+$depth)*10;

            if ($total_size < 1100) {
                $template ='small';
            } elseif ($total_size > 1100 && $total_size < 1210) {
                $template ='medium';
            } elseif ($total_size > 1210 && $total_size < 1430) {
                $template ='large';
            }
            // dd($total_size,$template);
            return [
                // "status"=> "offers_prepared",
                "custom_attributes" => [
                    // "sending_method" => "dispatch_order",
                    'target_point' => "BBI02A",//str_replace('Paczkomat-', '', $order_address->alias),
                ],
                "sender"=> [
                    'first_name'=>Configuration::get('REDICON_INPOST_FIRSTNAME'),
                    'last_name'=>Configuration::get('REDICON_INPOST_LASTNAME'),
                    'company_name'=>Configuration::get('REDICON_INPOST_COMPANY'),
                    'phone'=>Configuration::get('REDICON_INPOST_PHONE'),
                    'email'=>Configuration::get('REDICON_INPOST_EMAIL'),
                    'address'=>[
                        'street'=>Configuration::get('REDICON_INPOST_ADDRESS_STREET'),
                        'building_number'=>Configuration::get('REDICON_ADDRESS_NR'),
                        'city'=>Configuration::get('REDICON_INPOST_ADDRESS_CITY'),
                        'post_code'=>Configuration::get('REDICON_INPOST_ADDRESS_POSTCODE'),
                    ]
                ],
                'reference'=>$order->reference,
                'receiver' => [
                    "name"=> $order_address->alias,
                    "company_name"=> $order_address->company,
                    "first_name"=> $order_address->lastname,
                    "last_name"=> $order_address->firstname,
                    "email"=> $customer->email,
                    "phone"=> $order_address->phone,
                    "address"=> [
                        "street"=> $order_address->address1,
                        "building_number"=> $order_address->address1,
                        "city"=> $order_address->address1,
                        "post_code"=> $order_address->postcode,
                        "country_code"=> $country->iso_code
                    ]
                    ],
                'parcels'=>[
                    [
                        "dimensions"=>[
                            "length" => $depth*10,
                            "width" => $width*10,
                            "height" => $height*10,
                            "unit"=> "mm"
                        ],
                        "tracking_number"=>null,
                        "template" => $template,
                        "weight" => [
                            "amount"=> $order->getTotalWeight(),
                                "unit"=> "kg"
                        ],
                        "is_non_standard"=> false
                    ]
                ],
                "insurance" => [
                    "amount"=> $order->getTotalPaid(),
                    "currency"=> "PLN"
                ],
                "service"=> "inpost_locker_standard",

            ];
        }
        
           


        return false;
    }

    private static function getOrderCarrierId($id_order)
    {
        $id_order_carrier = Db::getInstance()->getValue('SELECT `id_order_carrier`
                FROM `'._DB_PREFIX_.'order_carrier`
                WHERE `id_order` = '.(int) $id_order);

        return $id_order_carrier;
    }

    public function hookAdminOrder($params)
    {
        $id_order = (int) $params['id_order'];

        $order_info = Db::getInstance()->executeS("SELECT * FROM `"._DB_PREFIX_."redicon_inpost_points` WHERE id_order = $id_order");
        $order_info = isset($order_info[0])?$order_info[0]:false;

        if ($order_info) {
            $order_info['error'] = json_encode(json_decode($order_info['error']), JSON_PRETTY_PRINT);
        }

        $this->context->smarty->assign([
            'url' => 'index.php?controller=AdminModules&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules').'&&id_shipment=',
            'order_info' => $order_info,
            'access_url' => $this->getConfirmedUrl('confirmShipment&auth='.self::$key_access),
            'check_url' => $this->getConfirmedUrl('checkShipment&auth='.self::$key_access),
        ]);

        return $this->fetch($this->template);
    }

    public function hookDisplaySelectedPaczkomat($params=[])
    {
        $cart = Context::getContext()->cart;

        $selected = '';

        if ($point = $this->getSelectedPoint($cart->id)) {
            $point = $point[0];
            $selected = sprintf("<br/>Wybrany paczkomat: %s- %s , %s", $point['point_code'], $point['point_address1'], $point['point_address2']);
        }

        return $selected;
    }

    public function hookDisplayCarrierExtraContent($params=[])
    {
        $cart = Context::getContext()->cart;

        $selected = '';
        $click = 'kliknij aby wybrac paczkomat';

        if ($point = $this->getSelectedPoint($cart->id)) {
            $point = $point[0];
            $selected = sprintf("Wybrany paczkomat: %s- %s , %s", $point['point_code'], $point['point_address1'], $point['point_address2']);
            $click = 'kliknij aby zmienić paczkomat';
        }
        // $this->setNewAddress($cart);
       
        return '<div class="col-12 mb-3">
        <span id="selected-point">'.$selected.'</span>
        <button type="button" onclick="$(\'.shipping-radio input:checked\').click()" class="btn btn-primary text-white mt-2">'.$click.'</button>
        </div>';
    }

    public function setNewAddress($cart)
    {
        $id_order_carrier = Configuration::get('REDICON_INPOST_CARRIER_ID');

        $carrier = new Carrier($id_order_carrier);

        $point = $this->getSelectedPoint($cart->id);
       
        if (isset($point[0])) {
            $address = $this->addNewAddress($cart, $point[0]);
            
            if (null == $address) {
                return;
            }

            $cart->id_address_delivery = $address->id;
            $cart->save();
        }
    }

    private function addCarrier()
    {
        $paczkomat = [
            'name' => 'Redicon - inpost',
            'url' => 'https://inpost.pl/sledzenie-przesylek?number=@',
            'id_tax_rules_group' => 0,
            'active' => true, 'deleted' => 0,
            'shipping_handling' => false,
            'range_behavior' => 0,
            'delay' => [
                 Language::getIsoById(Configuration::get('PS_LANG_DEFAULT')) => 'Wybierz paczkomat'
            ],
            'id_zone' => 1,
            'is_module' => true,
            'shipping_external' => true,
            'external_module_name' => $this->name,
            'need_range' => true
        ];

        

        if ($id_carrier = self::installExternalCarrier($paczkomat)) {
            Configuration::updateValue('REDICON_INPOST_CARRIER_ID', (int) $id_carrier);
            Configuration::updateValue('REDICON_INPOST_CARRIER_REFERENCE', (int) $id_carrier);
            return true;
        }

        return false;
    }

    private function addNewAddress($cart, $point)
    {
        $idCountry = Country::getByIso('PL');
        if (!$idCountry) {
            return null;
        }

        $currentAddress = new Address($cart->id_address_delivery);

        $phone = !empty($currentAddress->phone_mobile) ? $currentAddress->phone_mobile : $currentAddress->phone;

        $customer = new Customer($cart->id_customer);

        list($postcode, $city) = explode(' ', $point['point_address2'], 2);

        $address = new Address();
        $address->address1 = $point['point_address1'];
        $address->city = $city;
        $address->postcode = $postcode;
        $address->id_country = $idCountry;
        $address->firstname = $customer->firstname;
        $address->lastname = $customer->lastname;
        $address->id_state = 0;
        $address->id_customer = (int) $customer->id;
        $address->id_manufacturer = 0;
        $address->id_supplier = 0;
        $address->id_warehouse = 0;
        $address->alias = 'Paczkomat-' . $point['point_code'];
        $address->company = (string) $customer->company;
        $address->address2 = '';
        $address->other = '';
        $address->phone = $phone;
        $address->vat_number = '';
        $address->dni = '';
        $address->deleted = 1;

        
        if (!$address->save()) {
            return null;
        }

        return $address;
    }

    public function hookActionCarrierProcess($params=[])
    {
    }

    public function hookActionCarrierUpdate($params=[])
    {
        // file_put_contents('params.txt', json_encode($params));
        $id_carrier = $params['carrier']->id;
        $id_reference = $params['carrier']->id_reference;

        if ($id_reference === Configuration::get('REDICON_INPOST_CARRIER_REFERENCE')) {
            Configuration::updateValue('REDICON_INPOST_CARRIER_ID', (int) $id_carrier);
        }
    }

    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];

        $id_order_carrier = Configuration::get('REDICON_INPOST_CARRIER_ID');

        $carrier = new Carrier($order->id_carrier);
       
        $currentAddress = new Address($order->id_address_delivery);

        $point = $this->getSelectedPoint($order->id_cart);

        if (isset($point[0])) {
            $address = $this->addNewAddress($params['cart'], $point[0]);
        }

        if (null == $address) {
            return;
        }

        $order->id_address_delivery = $address->id;
        $order->save();
    }

    public function hookDisplayBeforeCarrier()
    {
        $ssl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://';
        $this->context->smarty->assign(array(
            'cart'=> Context::getContext()->cart->id,
            'inpostID' => Configuration::get('REDICON_INPOST_CARRIER_ID'),
            'ajaxUrl' => $ssl . $this->context->shop->domain . '/modules/rediconinpost/ajax.php',
        ));
        return $this->fetch($this->templateBefore);
    }

    private static function checkPoint($p)
    {
        return isset($p['name'])
        &&isset($p['location'])
        &&count($p['location'])==2
        &&isset($p['address'])
        &&count($p['address'])==2
        &&isset($p['type']);
    }

    public function addInpostPoint($id_cart, $p)
    {
        if (self::checkPoint($p)) {
            $location = (array)$p['location'];
            $address = (array)$p['address'];
            $type = implode(',', (array)$p['type']);
            $cod = 0;

            Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'redicon_inpost_points` WHERE id_cart="'.(int)$id_cart.'"');

            return  Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'redicon_inpost_points`
            (`id_cart`,`point_code`, `point_address1`, `point_address2`,
             `point_lat`, `point_lng`, `point_desc`, `point_payment`, `point_cod`, `point_type`)
            VALUES("'.$id_cart.'", "' . $p['name'] . '", "' . $address['line1']
            . '", "' . $address['line2'] . '", "' . $location['latitude'] . '", "'
            . $location['longitude'] . '", "", "", "'.(int)$cod.'",  "'.$type.'")');
        }

        return false;
    }

    private function getSelectedPoint($id_cart)
    {
        return Db::getInstance()->executeS('SELECT * FROM  `'._DB_PREFIX_.'redicon_inpost_points` WHERE `id_cart` LIKE ' . $id_cart .' ORDER BY `id` DESC LIMIT 1');
    }

    public function getOrderShippingCost($cart, $shipping_cost)
    {
        return $this->getOrderShippingCostExternal($shipping_cost);
    }

    public function getOrderShippingCostExternal($shipping_cost)
    {
        return $shipping_cost;
    }

    public function install()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `ps_redicon_inpost_points` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `id_cart` int(11) NOT NULL,
            `id_order` int(11) DEFAULT 0 ,
            `id_customer` int(11) DEFAULT 0 ,
            `id_guest` int(11) DEFAULT 0 ,
            `point_code` varchar(255) DEFAULT NULL,
            `point_address1` varchar(255) DEFAULT NULL,
            `point_address2` varchar(255) DEFAULT NULL,
            `point_lat` varchar(255) DEFAULT NULL,
            `point_lng` varchar(255) DEFAULT NULL,
            `point_desc` varchar(255) DEFAULT NULL,
            `point_payment` tinyint(1) DEFAULT NULL,
            `point_cod` int(11) DEFAULT NULL,
            `point_type` varchar(100) DEFAULT NULL,
            `pdf` longtext DEFAULT NULL,
            `id_shipment` int(11) DEFAULT NULL,
            `event` varchar(100) DEFAULT NULL,
            `shipping_number` varchar(64) DEFAULT NULL,
            `date_print` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

        if (
            !parent::install()
            or !$this->addCarrier()
            or !$this->registerHook('displayBeforeCarrier')
            or !$this->registerHook('displayCarrierExtraContent')
            or !$this->registerHook('displayBeforeCarrier')
            or !$this->registerHook('displayCarrierExtraContent')
            or !$this->registerHook('actionValidateOrder')
            or !$this->registerHook('actionValidateOrder')
            or !$this->registerHook('actionCarrierUpdate')
            or !$this->registerHook('displaySelectedPaczkomat')
            or !Db::getInstance()->execute($sql)
        ) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        $sql = 'DROP TABLE IF EXISTS ps_redicon_inpost_points';
        if (!parent::uninstall()
        or !Db::getInstance()->execute($sql)
        ) {
            return false;
        }

        return true;
    }

    public static function installExternalCarrier($config)
    {
        $carrier = new Carrier();
        $carrier->name = $config['name'];
        $carrier->url = $config['url'];
        $carrier->id_tax_rules_group = $config['id_tax_rules_group'];
        $carrier->id_zone = $config['id_zone'];
        $carrier->active = $config['active'];
        $carrier->deleted = $config['deleted'];
        $carrier->delay = $config['delay'];
        $carrier->shipping_handling = $config['shipping_handling'];
        $carrier->range_behavior = $config['range_behavior'];
        $carrier->is_module = $config['is_module'];
        $carrier->shipping_external = $config['shipping_external'];
        $carrier->external_module_name = $config['external_module_name'];
        $carrier->need_range = $config['need_range'];

        $languages = Language::getLanguages(true);
        foreach ($languages as $language) {
            if ($language['iso_code'] == Language::getIsoById(Configuration::get('PS_LANG_DEFAULT'))) {
                $carrier->delay[(int)$language['id_lang']] = $config['delay'][$language['iso_code']];
            }
        }

        if ($carrier->add()) {
            $groups = Group::getGroups(true);
            foreach ($groups as $group) {
                Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'carrier_group SET id_carrier='. (int)$carrier->id. ', id_group=' . (int)$group['id_group']);
            }
                
            $rangePrice = new RangePrice();
            $rangePrice->id_carrier = $carrier->id;
            $rangePrice->delimiter1 = '0';
            $rangePrice->delimiter2 = '10000';
            $rangePrice->add();

            $rangeWeight = new RangeWeight();
            $rangeWeight->id_carrier = $carrier->id;
            $rangeWeight->delimiter1 = '0';
            $rangeWeight->delimiter2 = '10000';
            $rangeWeight->add();

            $zones = Zone::getZones(true);
            foreach ($zones as $zone) {
                Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'carrier_zone SET id_carrier='. (int)$carrier->id. ', id_zone=' . (int)$zone['id_zone']);
                Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'delivery SET id_carrier='. (int)$carrier->id. ', id_range_price=' . (int)$rangePrice->id. ', id_range_weight=NULL, id_zone=' . (int)$zone['id_zone']. ', price=15');
                Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'delivery SET id_carrier='. (int)$carrier->id. ', id_range_price=NULL, id_range_weight=' . (int)$rangeWeight->id . ', id_zone=' . (int)$zone['id_zone']. ', price=15');
            }

            copy(dirname(__FILE__) . '/logoinpost.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');

            // Return ID Carrier
            return (int)($carrier->id);
        }

        return false;
    }
}
