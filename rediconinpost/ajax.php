<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");

require_once __DIR__.'/../../config/config.inc.php';
require_once __DIR__.'/rediconinpost.php';

$rediconinpost = new RediconInpost();
$action = Tools::getValue('action');
$return = '';
switch ($action) {
    case 'addPoint':
        $return = $rediconinpost->addInpostPoint((int)Tools::getValue('c'),Tools::getValue('paczkomat'));
        break;
    case 'confirmShipment':
        if(Tools::getValue('auth') === RediconInpost::$key_access){
            try{
                
                $inpostPostData = file_get_contents('php://input');
                file_put_contents('post.txt', "\n".$inpostPostData, FILE_APPEND);
                $return = $rediconinpost->saveConfirmShipment(json_decode($inpostPostData,true));
                if($return){
                    $inpostPostData = $rediconinpost->checkShipment();
                }
                
            }catch(Exception $e){
                file_put_contents('error.txt', "\n".$e->getMessage(), FILE_APPEND);
            }

        }else{
            $return = ['error' => 'Unauthorized access'];
        }
        break;
    case 'checkShipment':
        if(Tools::getValue('auth') === RediconInpost::$key_access){
            file_put_contents('confrim.txt', "\n".json_encode(Tools::getAllValues()), FILE_APPEND);
            $return = $rediconinpost->checkShipment();
        }else{
            $return = ['error' => 'Unauthorized access'];
        }
        break;
}

echo json_encode($return);
exit();