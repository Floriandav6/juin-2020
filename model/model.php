<?php

/*Fonction de vérification envoie formualire
*/
function isFormSubmitted():bool{
    return(isset($_GET['submit']));
}

/*Fonction de validation des données reçu pour formulaire*/
function dataValidation($page){
    $regexTitle = "#^[\w -éèêëàâîïôùûüÿæœç]{1,300}$#";
    $regexTexte = "#^[\w -éèêëàâîïôùûüÿæœç]{10,300}$#";
    switch ($page) {
        case 'rea':
            $instance = new Realisation(); 

            if(isset($_POST['title']) ){
                if(preg_match($regexTitle, $_POST['title'])){
                    $instance->title = transformData($_POST['title']);
                }
            }

            if(isset($_POST['link'])){
                if(preg_match($regexTexte, $_POST['link'])){
                    $instance->link = transformData($_POST['link']);
                } 
            }
            if(isset($_FILES['picture'])  ){
                $instance = isValidImage($instance,"rea/");
            }
            $instance->id_color=$_POST['id_color'];
               
            break;
        case 'log':
            
            if(isset($_POST['id']) && !empty($_POST['pass']) ){
                $manager = new AdminManager();
                $instance = $manager->getOne($_POST['id']);

                if($instance !== null){
                    if(password_verify($_POST['pass'],$instance[0]['password'])){
                        $manager->defineToken($instance[0]['user_name']);

                        if($instance[0]["superuser"] == true){
                           $_SESSION['superuser'] = true;
                        }

                        redirection($page);
                    }
                    else {
                        $instance = null;
                    }
                }
            } else {
                $instance = null;
            }

            break;
        case 'addUser':
            $instance = new User;
            $_SESSION['tabError'] = ["id" => null, "mdp" => null,"confirm" => null];

            //Identifiant
            if(isset($_POST['id'])){
                $manager = new AdminManager();

                $id = transformData($_POST['id']);
                $id = strtolower($id);

                $regex= "#^[a-z][a-z0-9]{3,20}$#";
                if(preg_match($regex, $id)){
                    $exist = $manager->getOne($id); 
                    if($exist !== null){
                        $_SESSION['tabError']["id"] ="exist";
                    } else{
                        $instance->name = $id;
                    }
                } else{
                    $_SESSION['tabError']['id'] = "regex";
                }      
            } else {
                $_SESSION['tabError']['id'] = "empty";
            }

            //Mot de passe
            if(isset($_POST['pass']) && !empty($_POST['pass'])){
                $pass = transformData($_POST['pass']);

                $regex = "#^\w{3,20}$#";
                if(!preg_match($regex, $pass)){
                    $_SESSION['tabError']['mdp'] = 1;
                    
                } else {
                    $instance->pwr = $pass;
                    if(isset($_POST['confirm']) && !empty($_POST['confirm'])){
                        $confirm = transformData($_POST['confirm']);

                        if(strcmp($pass, $confirm) !== 0){
                            $_SESSION['tabError']['confirm'] = 1;
                        }
                    } else {
                        $_SESSION['tabError']['confirm'] = 1;
                    }
                }
            } else {
                $_SESSION['tabError']['mdp'] = "empty";
            }
            
            break;

        default:
            $instance=[];
            break;
    }
    return $instance;
}

/*Modification des données sur les chaines de caractères
IN : Chaine de caractère à modifier
OUT : Chaine modifié */
function transformData($data){
    $data = trim($data); //Supprime les espaces (ou d'autres caractères) en début et fin de chaîne
    $data = stripslashes($data); //Supprime les antislashs d'une chaîne
    $data = htmlspecialchars($data); //Convertit les caractères spéciaux en entités HTML

    return $data;
}


/*Fonction de traitement des données pour réalisations
IN : - $page : la page concerné
     - $action : l'action à effectuer
     - $message : le message à afficher en cas de réussite
     - $manager : Objet d'une classe manager 

OUT : - $content : Le contenue à afficher dans la page index
 */
function treatmentAction($page, $action, $message, $manager){
    $instance = dataValidation($page);
    
    /*Gestion des erreurs*/
    foreach($instance as $key => $val){
        if($key !=='id' && $val == null && !($key=="image" && $action =="update" )){
            $_SESSION['tabsErrors'][$page][$key]=true;
        } else {
            $_SESSION['tabsErrors'][$page][$key]=false;
        }
    }

    if($instance->image == "error"){
        $_SESSION['tabsErrors'][$page]['image']=true;
        //$instance->image = transformData($_POST['nomimg']);
    }

    /*Si erreur */
    if(in_array(true, $_SESSION['tabsErrors'][$page])){
        $instance->id = intval($_GET['submit']);

        if($page == "rea")
            $content = reaForm($instance, $action,$manager->fetchColors());
        $_SESSION['tabsErrors'][$page] = resetTabError($page);

    } else {
        /*Si pas d'erreur*/
        if($action === "update"){
            
             $instance->id = $_GET['submit'];
             if($instance->image !== null && $action == "update"){
                $recup= $manager->getOne($instance->id);
                @unlink($_SESSION['pathUpload']."rea/".$recup->image);
             }
             $manager->update($instance);
             
             $content = '<div class="row justify-content-md-center mt-5">
                                <div class="col-8">
                                    <h1 class="alert alert-success">Recette modifiées avec succès !</h1>
                                    <a href="./index.php?action=list&page='.$page.'" class="btn btn-info" role="button">Retour</a>
                                </div>
                            </div>';
            
        } else{
            $manager->create($instance);
            $content = '
            <h1 class="alert alert-success">'.$message.'</h1> 
            <a href="./index.php?action=list&page='.$page.'" class="btn btn-info" role="button">Retour</a>';
        }
        
    }
    return $content;
}


/*Fonction de vérification de l'existance des données du reçu formulaire de contact
IN : $tab : Array des données du formulaire
OUT : $exist : Booléen */
function isExist($tab){
    $keys = ["name", "email", "message"];
    $exist = true;
    for ($i=0; $i < count($keys) ; $i++) { 
        if(!isset($tab[$keys[$i]])){
            $exist=false;
            break;
        }
    }

    return $exist;
}

/*Fonction de vérification des images
 */
function isValidImage($instance,$dos){
    $error = false;
   $newName = bin2hex(random_bytes(8));
   $legalExtentions = [".jpg", ".JPG", ".png", ".jpeg", "JPEG", ".gif"];
   $maxSize = "400000";
   $file = $_FILES['picture'];
   $actualName = $file['tmp_name'];
   $size = $file['size'];
   $extension = strrchr($file['name'],'.');

   //Vérification existence du fichier
   if(empty($actualName) || $size == 0){
       $error = true;   
   }
   
   //Vérifie si le fichier correspond aux critères
   if(!$error)
   {
        if(in_array($extension, $legalExtentions) && $size <= $maxSize){
            /*Vérification de l'existant du nom du fichier sur le serveur*/
            while(file_exists($_SESSION['pathUpload'].$dos.$newName.$extension)){
                $newName = bin2hex(random_bytes(8));
            }
            move_uploaded_file($actualName, $_SESSION['pathUpload'].$dos.$newName.$extension);
            $instance->image = $newName.$extension;
        } else {
            @unlink($_SESSION['pathUpload'].$dos.$newName.$extension);

            if(isset($_POST['nomimg']) && !empty($_POST['nomimg'])){
                $instance->image = "error";
            }
        }
   } else {
        if(isset($_POST['nomimg']) && !empty($_POST['nomimg'])){
            $instance->image = transformData($_POST['nomimg']);
        }
   }

   return $instance;
}

/*Fonction de redirection */
function redirection($page){
    header('Location: ./index.php?action=list&page='.$page);
}

/* Fonction de validation formulaire de contact
 */
function validContact($tab):array{

    $tabRegex = [
        "email"=>"#^.+@.+\.[a-zA-Z]{2,}$#",
        "name" =>"#^[a-zA-Z]{1,30}$#",
        "message"=>"#^.{1,100}$#"];

    foreach ($tab as $key => $elem) {
        if(!preg_match($tabRegex[$key], $elem)){
            $tab[$key]=null;
        }
    }
    return $tab;
}

/*Fonctionne afin réinitialiser les erreurs

 */
function resetTabError($page){
    switch ($page) {
        case 'rea':
            $tab =["title"=>null, "link"=>null, "image"=>null];
            break;

        case "log" :
            $tab = ["login"=>null, "pass"=>"null"];
            break;
    }

    if (!empty($tab)) {
        return $tab;
    }
}
