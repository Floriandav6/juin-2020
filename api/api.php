<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, origin");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

require_once('../model/Realisation.php');
require_once('../model/manager/RealisationManager.php');
require_once('../model/ContactMsg.php');
require_once('../model/manager/ContactMsgManager.php');
require_once('../view/view.php');
require_once('../model/model.php');
date_default_timezone_set('Europe/Amsterdam');

if(isset($_GET['entity'])){
    switch ($_GET['entity']) {      case 'realisation':
        $manager = new RealisationManager;
        $data = $manager->getAll();
        $data = json_encode($data);
        echo $data;



        break;
        case 'contactMessage' :
            if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                exit;
            }
            $manager = new ContactMsgManager();
            $recupJson = json_decode(file_get_contents('php://input'), true);

            if(isExist($recupJson)){
                $tab =$recupJson;

                if(!in_array(null, $tab)){
                    $contactMsg = new ContactMsg;
                    $contactMsg->name = $tab['name'];
                    $contactMsg->email = $tab['email'];
                    $contactMsg->message = $tab['message'];

                    $manager->create($contactMsg);

                } 
            }
        
            break;


    }
}
