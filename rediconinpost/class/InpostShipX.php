<?php

class InpostShipX
{
    const API_ENDPOINT = 'https://api-shipx-pl.easypack24.net/';

    public $API_ENDPOINT = 'https://api-shipx-pl.easypack24.net/';

    const API_ENDPOINT_SANDOX = 'https://sandbox-api-shipx-pl.easypack24.net/';

    const TRACKING_PACKAGE_URL = 'https://inpost.pl/sledzenie-przesylek?number=';

    private $api_url;

    private $api_key;

    private $debug;

    private $sandbox = false;

    public $insurance = array(
        '1' => array(
            'name' => 'Do 5 000 PLN',
            'price' => '1,50',
            'value' => '5000'
        ),
        '2' => array(
            'name' => 'Do 10 000 PLN',
            'price' => '2,50',
            'value' => '10000'
        ),
        '3' => array(
            'name' => 'Do 20 000 PLN',
            'price' => '3,00',
            'value' => '20000'
        ),
    );

    public $locker = array(
        'small' => array(
            'name' => 'Gabaryt A',
            'description' => '8 x 38 x 64 cm',
            'price' => '1,00',
            'short_name' => 'A',
            'value' => 'small',
            'length' => 80,
            'width' => 380,
            'height' => 640,
            'unit' => 'mm'
        ),
        'medium' => array(
            'name' => 'Gabaryt B',
            'description' => '19 x 38 x 64 cm',
            'price' => '2,00',
            'short_name' => 'B',
            'value' => 'medium',
            'length' => 190,
            'width' => 380,
            'height' => 640,
            'unit' => 'mm'
        ),
        'large' => array(
            'name' => 'Gabaryt C',
            'description' => '41 x 38 x 64 cm',
            'price' => '3,00',
            'short_name' => 'C',
            'value' => 'large',
            'length' => 410,
            'width' => 380,
            'height' => 640,
            'unit' => 'mm'
        ),
    );
    
    public static $label_type = [
        0=>'Pdf',
        1=>'Zpl',
        2=>'Epl'
    ];

    public static $page_format = [
        0=>'normal',
        1=>'A6'
    ];

    public function __construct($api, $sandbox = true, $debug = false)
    {
        $this->api_key = $api;
        $this->debug = $debug;
        $this->sandbox = $sandbox;
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
    }

    /**
     * Pobieranie informacji o ilości punktów paczkomatowych
     * @return object of points info
     */
    public function getNumberOfPoints()
    {
        $this->api_url = self:: API_ENDPOINT;
        $point = $this->request('points?page=1&per_page=5&type=parcel_locker_only', 'GET');
        return $point;
    }

    public function getNumberOfPopPoints()
    {
        $this->api_url = self:: API_ENDPOINT;
        $point = $this->request('points?page=1&per_page=5&type=pop', 'GET');
        return $point;
    }

    /**
     * Pobieranie listy lokalizacji
     * @param number $per_page
     * @return object
     */
    public function getPoints($per_page = 50, $type = 'parcel_locker_only')
    {
        $this->api_url = self:: API_ENDPOINT;
        $points = array();
        for ($i = 1; $i < 200; $i++) {
            $point = $this->request('points?page='.$i.'&per_page='.$per_page.'&type='.$type, 'GET');
            $point2 = $point['result'];
            $points = array_merge($points, $point2->items);
        }
        return $points;
    }

    /**
     * Pobieranie listy lokalizacji względem strony i ilości punktów
     * @param int $page
     * @param int $per_page
     * @param string $type
     * @return object
     */
    public function getPointsPerPage($page = 1, $per_page = 100, $type = 'parcel_locker_only')
    {
        $this->api_url = self:: API_ENDPOINT;
        $result = $this->request('points?page='.$page.'&per_page='.$per_page.'&type='.$type, 'GET');
        return $result['result']->items;
    }

    /**
     * Pobieranie informacji o punkcie paczkomatowym - rozpoznanie za pomocą nazwy punktu.
     * @param string $code
     * @return object of point info
     */
    public function getPointInfo($code)
    {
        $this->api_url = self:: API_ENDPOINT;
        $return = $this->request('points/'.$code, 'GET');
        return $return['result'];
    }

    /**
     * Pobranie informacji o najbliższych paczomatach
     * @param int $postcode - kod pocztowy bez kresek np. 00999
     * @param int $nearcount - ilość wyników
     * @return object - list of items
     */
    public function getPointNearAddress($postcode, $nearcount)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $point = $this->request('points?relative_post_code='.$postcode.'&page=1&per_page='.(int)$nearcount.
            '&type=parcel_locker_only&sort_by=distance_to_relative_point', 'GET');
        return $point['result']->items;
    }

    /**
     * Pobieranie informacji o organizacji
     * @param int $organization_id - organization ID
     * @return object of organization info
     *
     */
    public function getOrganizationsInfo($organization_id)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('organizations/'.(int)$organization_id, 'GET');
    }

    /**
     * Pobieranie listy sposobów nadania
     * @param string $service - wybrany typ serwisu
     * @return object
     */
    public function getSendingMethods($service = null)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('sending_methods'.($service === null ? '' : '?service='.$service), 'GET');
    }

    /**
     * Pobieranie listy dostępnych punktów odbioru przesyłki przez kuriera
     * @param int $organization_id - numer organizacji
     * @return object
     */
    public function searchDispatchPoints2($organization_id)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('organizations/'.(int)$organization_id.'/dispatch_points', 'GET');
    }

    /**
     * Wysłanie parametrów potrzebnych do utworzenia przesyłki inpost w trybie uproszczonym
     * @param int $organization_id
     * @param array $receiver
     * @param array $sender
     * @param array $parcels
     * @param array $custom_attrib
     * @param array $cod
     * @param array $insurance
     * @param array $additional_services
     * @param array $reference
     * @param string $service
     * @param bool $only_choice_of_offer
     * @param bool $weekPack
     * @return object of result
     */
    public function shipment($organization_id, $data, $service = 'inpost_locker_standard')
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $posts = json_encode($data);

        return $this->request('organizations/'.(int)$organization_id.'/shipments', 'POST', $posts);
    }

    public function shipment2($organization_id, $receiver, $sender, $parcels, $custom_attrib, $cod, $insurance, $additional_services, $reference, $service = 'inpost_locker_standard', $only_choice_of_offer = false, $weekPack = false)
    {
        $services = array('inpost_courier_standard', 'inpost_locker_allegro', 'inpost_courier_allegro');
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $posts = '{
        "receiver": {
            "first_name":"'.$receiver['first_name'].'",
            "last_name":"'.$receiver['last_name'].'",
            "email":"'.$receiver['email'].'",
            "phone":"'.$receiver['phone'].'",
            "address": {
                "street":"'.$receiver['address']['street'].'",
                "building_number":"'.$receiver['address']['building_number'].'",
                "city":"'.$receiver['address']['city'].'",
                "post_code":"'.$receiver['address']['post_code'].'",
                "country_code":"PL"
            }
        },
        "sender":
                {"first_name":"'.$sender['first_name'].'",
                "last_name":"'.$sender['last_name'].'",
                "company_name":"'.$sender['company_name'].'",
                "phone":"'.$sender['phone'].'",
                "email":"'.$sender['email'].'",
                "address":
                               {"street":"'.$sender['address']['street'].'",
                               "building_number":"'.$sender['address']['building_number'].'"
                               ,"city":"'.$sender['address']['city'].'",
                               "post_code":"'.$sender['address']['post_code'].'",
                               "country_code":"PL"}},';
                              
        if (in_array($service, $services)) {
            
            if ($parcels[0]['info'] != 'other') {
                $packInfo = $this->locker[$parcels[0]['info']];
                $posts .= '"parcels": {
                                "dimensions": {
                                    "length": "'.$packInfo['length'].'",
                                    "width": "'.$packInfo['width'].'",
                                    "height": "'.$packInfo['height'].'",
                                    "unit": "mm"
                                },
                                 "weight": {
                                    "amount": "'.$parcels[0]['weight'].'",
                                    "unit": "kg"
                                },
                                "tracking_number":null,
                                "is_non_standard":false
                            },
                ';
            } else {
                $parcel = $parcels[0];
                $posts .= '"parcels": {
                                "dimensions": {
                                    "length": "' . ($parcel['length']*10) . '",
                                    "width": "' . ($parcel['width']*10) . '",
                                    "height": "' . ($parcel['height']*10) . '",
                                    "unit": "mm"
                                },
                                 "weight": {
                                    "amount": "' . $parcel['weight'] . '",
                                    "unit": "kg"
                                },
                                "tracking_number":null,
                                "is_non_standard":false
                            },
                ';
            }
        } else {
            $parcel = $parcels[0];
            $posts .= '"parcels":
                [{"id":"'.$parcel['id'].'",
                "template":"'.$parcel['template'].'",
                "dimensions": {
                    "length": "' . ($parcel['length']*10) . '",
                    "width": "' . ($parcel['width']*10) . '",
                    "height": "' . ($parcel['height']*10) . '",
                    "unit": "mm"
                },
                "weight" :{
                    "amount": "'.$parcel['weight'].'",
                    "unit": "kg"
                },
                "tracking_number":null,
                "is_non_standard":false}],';
        }
       
        $posts .= '"custom_attributes":{';
        //         "sending_method":"'.$custom_attrib['sending_method'].'"';
        // if ($service == 'inpost_locker_standard' || $service == 'inpost_locker_pass_thru') {
        //     $posts .= ',"target_point":"'.$custom_attrib['target_point'].'"';
        //     if ($custom_attrib['sending_method'] == 'parcel_locker' || $custom_attrib['sending_method'] == 'pop') { // || $custom_attrib['sending_method'] == 'dispatch_order') {
        //         $posts .= ',"dropoff_point":"'.$custom_attrib['dropoff_point'].'", "service":"'.$service.'"';
        //     } else {
        //         $posts .= ',"service":"'.$service.'"';
        //     }
        // } elseif ($service == 'inpost_courier_standard') {
        //     if ($custom_attrib['sending_method'] == 'pop') {
        //         $posts .= ',"dropoff_point":"'.$custom_attrib['dropoff_point'].'", "service":"'.$service.'"';
        //     } else {
        //         $posts .= '';
        //     }
        // } elseif ($service == 'inpost_locker_allegro' || $service == 'inpost_courier_allegro') {
        //     if ($service == 'inpost_locker_allegro') {
        //         $posts .= ',"dropoff_point":"'.$custom_attrib['dropoff_point'].'"';
        //     }
        //     $posts .= ',"target_point":"'.$custom_attrib['target_point'].'"';
        //     $posts .= ',"allegro_transaction_id":"'.$custom_attrib['allegro_transaction_id'].'","allegro_user_id":"'.$custom_attrib['allegro_user_id'].'"';
        // }
        // if (!empty($additional_services)) {
        //     $posts .= '';
        // }
        // if ($only_choice_of_offer) {
        //     $posts .= '';
        // }
        // $posts .= '},';
        // if ($weekPack) {
        //     $posts .= '"end_of_week_collection": true,';
        // }
        // $posts .= '"service" : "'.$service.'",
        // "reference": "'.$reference.'"';
        // ($insurance)?($posts .= ',"insurance":{"amount":"'.$insurance['amount'].'", "currency": "PLN"}'):'';
        // (!empty($cod))?($posts .= ',"cod":{"amount":"'.$cod['amount'].'", "currency": "PLN"}'):'';
        $posts .= '}}';


        return $this->request('organizations/'.(int)$organization_id.'/shipments', 'POST', $posts);
    }

    /**
     * @param $organization_id
     * @param $receiver
     * @param null $sender
     * @param $parcels
     * @param $custom_attrib
     * @param $cod
     * @param $insurance
     * @param $additional_services
     * @param $reference
     * @param string $service
     * @param bool $only_choice_of_offer
     * @return NULL[]|unknown[]
     */
    public function shipmentCalculate($organization_id, $receiver, $sender = null, $parcels, $custom_attrib, $cod, $insurance, $additional_services, $reference, $service = 'inpost_locker_standard', $only_choice_of_offer = false, $weekPack = false)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $posts = '{"shipments": [
        {
            "id": "SHIPMENT1",
                "receiver": {
                    "first_name":"'.$receiver['first_name'].'",
                    "last_name":"'.$receiver['last_name'].'",
                    "email":"'.$receiver['email'].'",
                    "phone":"'.$receiver['phone'].'",
                    "address": {
                        "street":"'.$receiver['address']['street'].'",
                        "building_number":"'.$receiver['address']['building_number'].'",
                        "city":"'.$receiver['address']['city'].'",
                        "post_code":"'.$receiver['address']['post_code'].'",
                        "country_code":"PL"
                    }
                },
                "sender":
                        {"first_name":"'.$sender['first_name'].'",
                        "last_name":"'.$sender['last_name'].'",
                        "company_name":"'.$sender['company_name'].'",
                        "phone":"'.$sender['phone'].'",
                        "email":"'.$sender['email'].'",
                        "address":
                                    {"street":"'.$sender['address']['street'].'",
                                    "building_number":"'.$sender['address']['building_number'].'"
                                    ,"city":"'.$sender['address']['city'].'",
                                    "post_code":"'.$sender['address']['post_code'].'",
                                    "country_code":"PL"}},';
                if ($service == 'inpost_courier_standard' || $service == 'inpost_locker_allegro' || $service == 'inpost_courier_allegro') {
                    if ($parcels[0]['info'] != 'other') {
                        $packInfo = $this->locker[$parcels[0]['info']];
                        $posts .= '"parcels": {
                                    "dimensions": {
                                        "length": "' . $packInfo['length'] . '",
                                        "width": "' . $packInfo['width'] . '",
                                        "height": "' . $packInfo['height'] . '",
                                        "unit": "mm"
                                    },
                                    "weight": {
                                        "amount": "' . $parcels[0]['weight'] . '",
                                        "unit": "kg"
                                    },
                                    "tracking_number":null,
                                    "is_non_standard":false
                                },
                    ';
                    } else {
                        $parcel = $parcels[0];
                        $posts .= '"parcels": {
                                    "dimensions": {
                                        "length": "' . $parcel['length'] . '",
                                        "width": "' . $parcel['width'] . '",
                                        "height": "' . $parcel['height'] . '",
                                        "unit": "cm"
                                    },
                                    "weight": {
                                        "amount": "' . $parcel['weight'] . '",
                                        "unit": "kg"
                                    },
                                    "tracking_number":null,
                                    "is_non_standard":false
                                },
                    ';
                    }
                } else {
                    $posts .= '"parcels":
                        [{"id":"'.$parcels[0]['id'].'",
                        "template":"'.$parcels[0]['template'].'",
                        "weight" :{
                            "amount": "'.$parcels[0]['weight'].'",
                            "unit": "kg"
                        },
                        "tracking_number":null,
                        "is_non_standard":false}],';
                }
                $posts .= '"custom_attributes":{
                        "sending_method":"'.$custom_attrib['sending_method'].'"';
                if ($service == 'inpost_locker_standard' || $service == 'inpost_locker_pass_thru') {
                    $posts .= ',"target_point":"'.$custom_attrib['target_point'].'"';
                    if ($custom_attrib['sending_method'] == 'parcel_locker' || $custom_attrib['sending_method'] == 'pop') { // || $custom_attrib['sending_method'] == 'dispatch_order') {
                        $posts .= ',"dropoff_point":"'.$custom_attrib['dropoff_point'].'", "service":"'.$service.'"';
                    } else {
                        $posts .= ',"service":"'.$service.'"';
                    }
                } elseif ($service == 'inpost_courier_standard') {
                    $posts .= '';
                } elseif ($service == 'inpost_locker_allegro' || $service == 'inpost_courier_allegro') {
                    if ($service == 'inpost_locker_allegro') {
                        $posts .= ',"dropoff_point":"'.$custom_attrib['dropoff_point'].'"';
                    }
                    $posts .= ',"target_point":"'.$custom_attrib['target_point'].'"';
                    $posts .= ',"allegro_transaction_id":"'.$custom_attrib['allegro_transaction_id'].'","allegro_user_id":"'.$custom_attrib['allegro_user_id'].'"';
                }
                if (!empty($additional_services)) {
                    $posts .= '';
                }
                if ($only_choice_of_offer) {
                    $posts .= '';
                }
                $posts .= '},';
                if ($weekPack) {
                    $posts .= '"end_of_week_collection": true,';
                }
                $posts .= '"service" : "'.$service.'",
                "reference": "'.$reference.'"';
                ($insurance)?($posts .= ',"insurance":{"amount":"'.$insurance['amount'].'", "currency": "PLN"}'):'';
                (!empty($cod))?($posts .= ',"cod":{"amount":"'.$cod['amount'].'", "currency": "PLN"}'):'';
                $posts .= '}]}';

        return $this->request('organizations/'.(int)$organization_id.'/shipments/calculate', 'POST', $posts);
    }

    /**
     * Usunięcie przesyłki z systemu (tylko dla statusów created oraz offers_prepared)
     * @param $id_shipments
     * @return NULL[]|unknown[]
     */
    public function deleteShipments($id_shipments)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('shipments/'.(int)$id_shipments, 'DELETE');
    }

    /**
     * Pobranie informacji o przesyłce
     * @param int $id_shipments
     * @param int $organization_id
     * @return object of pack info
     */
    public function getShipments($id_shipments)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('shipments/'.(int)$id_shipments, 'GET');
    }

    /**
     * Pobranie etykiety wg. formatu i typu
     * @param int $shipment_id
     * @param string $format
     * @param string $type
     * @return file
     */
    public function getLabel($shipment_id, $format, $type)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $frm = '';
        if ($format != 'PDF') {
            $frm = '&format='.Tools::strtolower($format);
        }
        return $this->request('shipments/'.(int)$shipment_id.'/label?type='.$type.$frm, 'GET', null, true);
    }

    /**
     * Pobranie etykiet
     * @param int $organization_id
     * @param array $shipment_ids
     * @param string $type
     * @param string $format
     * @return object of file
     */
    public function getMultiLabels($organization_id, $shipment_ids, $type, $format)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $posts = '{
            "format": "'.$format.'",
            "type" : "'.$type.'",
            "shipment_ids" : ['.implode(',', $shipment_ids).']
        }';

        return $this->request('organizations/'.$organization_id.'/shipments/labels', 'POST', $posts, true);
    }

    /**
     * Pobranie informacji o przesyłce
     * @param int $tracking_number
     * @return object of package info
     */
    public function getTracking($tracking_number)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('tracking/'.$tracking_number, 'GET');
    }

    /**
     * Pobranie informacji o statusach Inpost
     * @return object of list
     */
    public function getStatuses()
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('statuses?lang=pl_PL', 'GET');
    }

    /**
     * Wygenerowanie danych do opcji "zamów kuriera"
     * @param array $package_ids
     * @param int $dispatch_point
     * @param int $organization_id
     * @return object of info
     */
    public function setDispatchOrders($package_ids, $dispatch_point, $organization_id)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $ids = implode(',', $package_ids);
        if ($dispatch_point == 0) {
            $posts = '{
                "shipments": ["'.$ids.'"],
                "address": {
                    "street": "'.Configuration::get('INPOSTSHIP_SENDER_STREET').'",
                    "building_number": "'.Configuration::get('INPOSTSHIP_SENDER_BUILDING_NR').'",
                    "city": "'.Configuration::get('INPOSTSHIP_SENDER_CITY').'",
                    "post_code": "'.Configuration::get('INPOSTSHIP_SENDER_POSTCODE').'",
                    "country_code": "PL"
                }
            }';
        } else {
            $posts = '{
                "shipments": ["'.$ids.'"],
                "dispatch_point_id" : "'.(int)$dispatch_point.'"
            }';
        }
        return $this->request('organizations/'.$organization_id.'/dispatch_orders', 'POST', $posts);
    }

    /**
     * Wygenerowanie zlecenia odbioru dla przesyłek
     * @param int $organization_id
     * @param array $shipment_ids
     * @param string $type
     * @param string $format
     * @return object of info
     */
    public function getMultiDispatchOrder($organization_id, $shipment_ids, $type, $format)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $shipments = '';
        if (!empty($shipment_ids)) {
            foreach ($shipment_ids as $s) {
                $shipments .= "&shipment_ids[]=".$s;
            }
        }
        // nic nie robi
        if ($type) {
            $shipments .= '';
        }
        return $this->request('organizations/'.$organization_id.'/dispatch_orders/printouts?format='.$format.
            $shipments, 'GET', null, true);
    }

    /**
     * Zlecenie odbioru przesyłki przez kuriera
     * @param int $package_id
     * @param int $dispatch_point
     * @param int $organization_id
     * @return object of info
     */
    public function setDispatchOrder($package_id, $dispatch_point, $organization_id)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        if ($dispatch_point == 0) {
            $posts = '{
                "shipments": ["'.(int)$package_id.'"],
                "address": {
                    "street": "'.Configuration::get('INPOSTSHIP_SENDER_STREET').'",
                    "building_number": "'.Configuration::get('INPOSTSHIP_SENDER_BUILDING_NR').'",
                    "city": "'.Configuration::get('INPOSTSHIP_SENDER_CITY').'",
                    "post_code": "'.Configuration::get('INPOSTSHIP_SENDER_POSTCODE').'",
                    "country_code": "PL"
                }
            }';
        } else {
            $posts = '{
                "shipments": ["'.(int)$package_id.'"],
                "dispatch_point_id" : "'.(int)$dispatch_point.'"
            }';
        }
        return $this->request('organizations/'.$organization_id.'/dispatch_orders', 'POST', $posts);
    }

    /**
     * Pobranie szczegółowych informacji nt. zlecenia odbioru przez kuriera
     * @param $dispatch_id int - number of dispatch ID
     * @return object of info
     */
    public function getDispatchInfo($dispatch_id)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('dispatch_orders/'.(int)$dispatch_id, 'GET', null);
    }

    /**
     * Usunięcie zlecenia odbioru przez kuriera
     * TIP: działa tylko na statusach new oraz sent
     * @param $dispatch_id int - number of dispatch ID
     * @return object of info
     */
    public function deleteDispatch($dispatch_id)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('dispatch_orders/'.(int)$dispatch_id, 'DELETE', null);
    }

    /**
     * Drukowanie zlecenia odbioru przez kuriera
     * format: PDF
     * @param $dispatch_id int - number of dispatch ID
     * @return object of info
     */
    public function printDispatchPdf($dispatch_id)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        return $this->request('dispatch_orders/'.(int)$dispatch_id.'/printout?format=Pdf', 'GET', null, true);
    }

    public function calculateDispatches($organization_id, $dispatch_point_id)
    {
        $this->sandbox ? $this->api_url = self::API_ENDPOINT_SANDOX : $this->api_url = self:: API_ENDPOINT;
        $posts = '{
            "dispatch_point_id" : '.(int)$dispatch_point_id.',
            "shipments" : []
        }';
        return $this->request('organizations/'.(int)$organization_id.'/dispatch_orders/calculate', 'POST', $posts);
    }

    /**
     *
     * @param unknown $action
     * @param unknown $method
     * @param unknown $posts
     * @return NULL[]|unknown[]
     */
    public function request($action, $method, $posts = null, $download = null)
    {
        $url = $this->api_url.'v1/'.$action;
        if ($method == 'POST') {
            $post = $posts;//json_encode($posts, JSON_UNESCAPED_SLASHES);
        } else {
            $post = '';
        }
        $authorization = "Authorization: Bearer ".$this->api_key;

        $server = '91.216.25.111';
        $prev_server = $server;
        $resolveparam = [ $prev_server ? '-api-shipx-pl.easypack24.net:443:'.$prev_server : '', 'api-shipx-pl.easypack24.net:443:'.$server ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RESOLVE, $resolveparam);
        if ($this->debug) {
            $fp = fopen(dirname(__FILE__).'/../log/errorlog'.date('YmdHis').'.txt', 'w');
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $fp);
        }
        $result = curl_exec($ch);
        if ($this->debug) {
            echo "<pre>";
            print_r($posts);
            echo "</pre>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        }
//         print_r($result);exit();
        curl_close($ch);
        if ($download) {
            return $result;
        }
        $result2 = json_decode($result);
        $request = array();
        if (isset($result2->error)) {
            $request['error'] = $result2->message;
        } else {
            $request['error'] = null;
        }
        $request['result'] = $result2;
        return $request;
    }
}
